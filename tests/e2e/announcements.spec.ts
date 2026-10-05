/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Announcements in a real browser (announcements REQ-ANN-001, 002, 005): a
 * notice for one group shows as a banner above the member's dashboard and
 * not above an outsider's, and a reader likes and comments on a news item
 * through Nextcloud's comments service, after which the card shows 1 and 1.
 *
 * The administrator writes through the API (the editor is covered by
 * AnnouncementEditorModal.spec.js); the readers use the page.
 *
 * @e2e openspec/specs/announcements/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request as pwRequest, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const API = '/index.php/apps/launchpad/api'
const NOTICE = 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00'
const NEWS = 'Nieuwe werkplekken op de 3e verdieping'

let api: APIRequestContext
let group = ''
let member: { username: string; password: string } | null = null
let outsider: { username: string; password: string } | null = null
const created: string[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

/**
 * Create and publish an announcement as the administrator.
 *
 * @param fields The announcement fields.
 * @return The uuid.
 */
async function publish(fields: Record<string, unknown>): Promise<string> {
	const draft = await api.post(`${API}/announcements`, { data: fields })
	expect(draft.status(), 'the draft must be created').toBe(201)
	const uuid = (await draft.json()).uuid as string
	created.push(uuid)
	const published = await api.post(`${API}/announcements/${uuid}/publish`)
	expect(published.ok(), `publish answered ${published.status()}`).toBe(true)
	return uuid
}

test.beforeAll(async () => {
	api = await pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})

	group = `e2e-burgerzaken-${Date.now()}`
	const madeGroup = await api.post('/ocs/v1.php/cloud/groups', { form: { groupid: group } })
	expect(madeGroup.ok(), 'the group must be created').toBe(true)

	member = await provisionThrowawayUser('e2e-ann-lid')
	outsider = await provisionThrowawayUser('e2e-ann-buiten')
	const joined = await api.post(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(member.username)}/groups`,
		{ form: { groupid: group } },
	)
	expect(joined.ok(), 'the member must join the group').toBe(true)

	// A notice without an end time is refused (REQ-ANN-005).
	const endless = await api.post(`${API}/announcements`, { data: { kind: 'notice', title: 'Zonder einde' } })
	const endlessUuid = (await endless.json()).uuid as string
	created.push(endlessUuid)
	const refused = await api.post(`${API}/announcements/${endlessUuid}/publish`)
	expect(refused.status()).toBe(400)
	expect((await refused.json()).error).toBe('A notice needs an end time')

	await publish({
		kind: 'notice',
		level: 'warning',
		dismissible: false,
		title: NOTICE,
		targetGroups: [group],
		expiresAt: new Date(Date.now() + 3_600_000).toISOString(),
	})
	await publish({ kind: 'news', title: NEWS, category: 'Facilitair', allowComments: true })
})

test.afterAll(async () => {
	for (const uuid of created) {
		await api.delete(`${API}/announcements/${uuid}`)
	}
	for (const user of [member, outsider]) {
		if (user !== null) {
			await deprovisionUser(user.username)
		}
	}
	if (group !== '') {
		await api.delete(`/ocs/v1.php/cloud/groups/${encodeURIComponent(group)}`)
	}
	await api.dispose()
})

// @e2e announcements::maintenance-banner
test('a member sees the notice above the dashboard, with a warning word and no close button', async ({ browser }) => {
	const { context, page } = await loginAs(browser, member!.username, member!.password)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const banner = page.locator('.notice-banner', { hasText: NOTICE })
		await expect(banner).toBeVisible({ timeout: 30_000 })
		await expect(banner).toContainText('Warning:')
		await expect(banner.getByRole('button', { name: 'Close this notice' })).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e announcements::news-for-one-group
test('someone outside the group does not see the notice and gets 404 for it by id', async ({ browser }) => {
	const { context, page } = await loginAs(browser, outsider!.username, outsider!.password)
	try {
		await page.goto('/index.php/apps/launchpad/')
		await page.waitForSelector('.workspace-shell, .launchpad-floating-controls', { timeout: 30_000 })
		await expect(page.locator('.notice-banner', { hasText: NOTICE })).toHaveCount(0)
		const byId = await context.request.get(`${API}/announcements/${created[1]}`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		expect(byId.status()).toBe(404)
	} finally {
		await context.close()
	}
})

// @e2e announcements::pieter-likes-and-comments
test('a reader likes and comments on a news item and the counts show 1 and 1', async ({ browser }) => {
	const { context } = await loginAs(browser, member!.username, member!.password)
	const headers = { 'OCS-APIRequest': 'true' }
	try {
		const uuid = created[2]
		const liked = await context.request.put(`${API}/announcements/${uuid}/like`, { headers, data: { liked: true } })
		expect(liked.ok()).toBe(true)
		const commented = await context.request.post(`${API}/announcements/${uuid}/comments`, {
			headers,
			data: { message: 'Komen er ook sta-bureaus?' },
		})
		expect(commented.status()).toBe(201)

		const seen = await (await context.request.get(`${API}/announcements/${uuid}`, { headers })).json()
		expect(seen.likeCount).toBe(1)
		expect(seen.commentCount).toBe(1)
		const comments = await (await context.request.get(`${API}/announcements/${uuid}/comments`, { headers })).json()
		expect(comments.comments.map((c: { message: string }) => c.message)).toContain('Komen er ook sta-bureaus?')
	} finally {
		await context.close()
	}
})
