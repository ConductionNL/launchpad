/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Communities widget with Talk (engagement-communities): open Talk
 * conversations are listed, a reader joins one from the widget, and a
 * conversation the organisation pinned comes first.
 *
 * Needs Talk (spreed) enabled; skips with that reason when it is not. The
 * admin creates two listable conversations and a dashboard holding the widget,
 * shared with a throwaway reader; all of it is removed afterwards.
 *
 * @e2e openspec/specs/communities-widget/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const API = '/index.php/apps/launchpad/api'
const TALK = '/ocs/v2.php/apps/spreed/api/v4'
const STAMP = `${Date.now()}`
const OPEN_ROOM = `Duurzaamheid ${STAMP}`
const PINNED_ROOM = `Vraag het de ICT ${STAMP}`

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
			Accept: 'application/json',
			Authorization: `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`,
		},
	})
}

let admin: APIRequestContext
let member: { username: string, password: string } | null = null
let dashboardId = 0
let pinnedBefore: string[] = []
const rooms: Record<string, string> = {}

async function createListedRoom(name: string): Promise<string> {
	const created = await admin.post(`${TALK}/room`, { data: { roomType: 3, roomName: name } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const token = (await created.json()).ocs.data.token as string
	const listable = await admin.put(`${TALK}/room/${token}/listable`, { data: { scope: 1 } })
	expect(listable.status(), await listable.text()).toBeLessThan(300)
	return token
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await apiAs(ADMIN.user, ADMIN.pass)
	const talk = await admin.get(`${TALK}/listed-room`)
	test.skip(talk.status() === 404, 'Talk (spreed) is not enabled on this instance')

	rooms.open = await createListedRoom(OPEN_ROOM)
	rooms.pinned = await createListedRoom(PINNED_ROOM)

	const settings = await admin.get(`${API}/admin/settings`)
	const current = await settings.json()
	pinnedBefore = (current.data ?? current).pinnedCommunities ?? []
	const pin = await admin.put(`${API}/admin/settings`, {
		data: { pinnedCommunities: [rooms.pinned], allowUserDash: true },
	})
	expect(pin.status(), await pin.text()).toBeLessThan(300)

	member = await provisionThrowawayUser('e2e-community')
	const created = await admin.post(`${API}/dashboard`, { data: { name: `Gemeenschap ${STAMP}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	dashboardId = Number(dash.id)
	const widget = await admin.post(`${API}/dashboard/${dash.id}/widgets`, {
		data: { widgetId: 'communities', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 6, content: {} },
	})
	expect(widget.status(), await widget.text()).toBeLessThan(300)
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
	await admin.put(`${API}/admin/settings`, { data: { pinnedCommunities: pinnedBefore } })
	for (const token of Object.values(rooms)) {
		await admin.delete(`${TALK}/room/${token}`)
	}
	if (dashboardId !== 0) {
		await admin.delete(`${API}/dashboard/${dashboardId}`)
	}
	if (member !== null) {
		await deprovisionUser(member.username)
	}
	await admin.dispose()
})

// @e2e communities-widget::pieter-finds-a-community
// @e2e communities-widget::pinned-first
// @e2e communities-widget::join-and-open
test('the reader sees the pinned community first and joins an open one', async ({ browser }) => {
	const { context, page } = await loginAs(browser, member!.username, member!.password)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const list = page.locator('[data-testid="communities-list"]')
		await expect(list).toBeVisible({ timeout: 30_000 })

		const first = list.locator('[data-testid^="community-"]').first()
		await expect(first).toHaveAttribute('data-testid', `community-${rooms.pinned}`)
		await expect(first.locator('[data-testid="community-pinned"]')).toBeVisible()

		const open = list.locator(`[data-testid="community-${rooms.open}"]`)
		await expect(open).toContainText(OPEN_ROOM)
		await open.locator('[data-testid="community-join"]').click()
		await expect(open.locator('[data-testid="community-open"]')).toBeVisible({ timeout: 15_000 })
	} finally {
		await context.close()
	}

	const reader = await apiAs(member!.username, member!.password)
	try {
		const room = await reader.get(`${TALK}/room/${rooms.open}`)
		expect(room.status(), await room.text()).toBe(200)
		// 3 is a user added to the conversation, 5 a user who joined it themselves.
		expect([3, 5]).toContain((await room.json()).ocs.data.participantType)
	} finally {
		await reader.dispose()
	}
})
