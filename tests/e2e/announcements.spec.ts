/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Announcements as a reader sees them (engagement-announcements): a published
 * notice is a banner above the reader's dashboard, and a news item can be
 * liked and commented on through Nextcloud's comments.
 *
 * The admin writes; a throwaway member reads, on a dashboard the admin
 * shares with them that holds the announcements widget.
 *
 * @e2e openspec/specs/announcements/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { publishForRecipients } from './support/principals.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const API = '/index.php/apps/launchpad/api'
const STAMP = `${Date.now()}`

/*
 * A basic-auth API context with an explicitly empty jar, so the project's
 * stored admin session is never sent along with another user's credentials.
 */
async function apiAs(user: string, pass: string): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`,
		},
	})
}

let admin: APIRequestContext
let member: { username: string; password: string } | null = null
let dashboardId = 0
const announcements: string[] = []
const tomorrow = () => new Date(Date.now() + 24 * 3600 * 1000).toISOString()

async function publishAnnouncement(data: Record<string, unknown>): Promise<string> {
	const created = await admin.post(`${API}/announcements`, { data })
	expect(created.status(), await created.text()).toBe(201)
	const uuid = (await created.json()).uuid as string
	announcements.push(uuid)
	const published = await admin.post(`${API}/announcements/${uuid}/publish`)
	expect(published.status(), await published.text()).toBeLessThan(300)
	return uuid
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await apiAs(ADMIN.user, ADMIN.pass)
	const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)

	member = await provisionThrowawayUser('e2e-reader')

	// The news widget sits on a dashboard the admin shares with the reader,
	// the way an intranet page reaches its readers.
	const created = await admin.post(`${API}/dashboard`, { data: { name: `Nieuws ${STAMP}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	dashboardId = Number(dash.id)
	const widget = await admin.post(`${API}/dashboard/${dash.id}/widgets`, {
		data: { widgetId: 'announcements', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 6, content: {} },
	})
	expect(widget.status(), await widget.text()).toBeLessThan(300)
	await publishForRecipients(admin, Number(dash.id))
	const share = await admin.post(`${API}/dashboard/${dash.id}/shares`, {
		data: { shareType: 'user', shareWith: member.username, permissionLevel: 'view_only' },
	})
	expect(share.status(), await share.text()).toBeLessThan(300)

	const reader = await apiAs(member.username, member.password)
	try {
		// A new account's first request also sets up its home folder, which takes a while.
		const active = await reader.post(`${API}/dashboards/active`, { data: { uuid: dash.uuid }, timeout: 60_000 })
		expect(active.status(), await active.text()).toBeLessThan(300)
	} finally {
		await reader.dispose()
	}
})

test.afterAll(async () => {
	for (const uuid of announcements) {
		await admin.delete(`${API}/announcements/${uuid}`)
	}
	if (dashboardId !== 0) {
		await admin.delete(`${API}/dashboard/${dashboardId}`)
	}
	if (member !== null) {
		await deprovisionUser(member.username)
	}
	await admin.dispose()
})

// @e2e announcements::notice-without-an-end-time
test('a notice without an end time is not published', async () => {
	// Since announcement-show-until (REQ-ANN-001) the server already refuses
	// to create an announcement without "Show until", so it never reaches
	// publish.
	const created = await admin.post(`${API}/announcements`, {
		data: { kind: 'notice', level: 'warning', title: `Zonder einde ${STAMP}`, dismissible: true },
	})
	expect(created.status(), await created.text()).toBe(400)
	expect(await created.text()).toMatch(/Show until|expiresAt|end time/)
})

// @e2e announcements::maintenance-banner
test('a published notice is a banner above the reader\'s dashboard, and a dismissible one can be closed', async ({ browser }) => {
	const fixed = `Onderhoud zaaksysteem ${STAMP}`
	const closable = `Nieuwe koffieautomaat ${STAMP}`
	await publishAnnouncement({ kind: 'notice', level: 'warning', title: fixed, dismissible: false, expiresAt: tomorrow() })
	await publishAnnouncement({ kind: 'notice', level: 'info', title: closable, dismissible: true, expiresAt: tomorrow() })

	const { context, page } = await loginAs(browser, member!.username, member!.password)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const region = page.locator('[data-test="notice-region"]')
		const warning = region.locator('.notice-banner').filter({ hasText: fixed })
		await expect(warning).toBeVisible({ timeout: 30_000 })
		await expect(warning.locator('.notice-banner__level')).not.toHaveText('')
		await expect(warning.locator('[data-test="notice-dismiss"]')).toHaveCount(0)

		const info = region.locator('.notice-banner').filter({ hasText: closable })
		await info.locator('[data-test="notice-dismiss"]').click()
		await expect(info).toHaveCount(0)

		await page.reload()
		await expect(region.locator('.notice-banner').filter({ hasText: fixed })).toBeVisible({ timeout: 30_000 })
		await expect(region.locator('.notice-banner').filter({ hasText: closable })).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e announcements::pieter-likes-and-comments
test('a reader likes and comments on a news item, and the card counts both', async ({ browser }) => {
	const title = `Nieuwe werkplekken ${STAMP}`
	const comment = `Komen er ook sta-bureaus? ${STAMP}`
	await publishAnnouncement({ kind: 'news', title, body: 'Op de 3e verdieping.', allowComments: true })

	const { context, page } = await loginAs(browser, member!.username, member!.password)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const card = page.locator('[data-test="announcement-card"]').filter({ hasText: title })
		await expect(card).toBeVisible({ timeout: 30_000 })

		await card.locator('[data-test="announcement-like"]').click()
		await expect(card.locator('[data-test="announcement-like"]')).toHaveText(/1/)

		await card.locator('[data-test="announcement-comments"]').click()
		await card.getByLabel('Write a comment').fill(comment)
		await card.getByRole('button', { name: 'Send' }).click()
		await expect(card.locator('[data-test="announcement-comment-list"]')).toContainText(comment)

		await page.reload()
		const reloaded = page.locator('[data-test="announcement-card"]').filter({ hasText: title })
		await expect(reloaded.locator('[data-test="announcement-like"]')).toHaveText(/1/, { timeout: 30_000 })
		await expect(reloaded.locator('[data-test="announcement-comments"]')).toHaveText(/1/)
	} finally {
		await context.close()
	}

	const comments = await admin.get(`${API}/announcements/${announcements.at(-1)}/comments`)
	expect(comments.status(), await comments.text()).toBe(200)
	expect(JSON.stringify(await comments.json())).toContain(comment)
})
