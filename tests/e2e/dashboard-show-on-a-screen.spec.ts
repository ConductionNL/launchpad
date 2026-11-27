/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Show one dashboard on a screen from its Sharing tab (dashboard-kiosk-mode
 * REQ-KIOSK-006, REQ-KIOSK-007, REQ-KIOSK-008; change
 * dashboard-show-on-a-screen).
 *
 * WHO DOES WHAT
 * =============
 * - pieter owns "Kantine" and may share it; he makes, copies and revokes the
 *   screen link through his Sharing tab.
 * - ella reads "Kantine" through a share and may not share it.
 * - The admin plays Noor: the switch and the screen link rows on the Kiosk tab.
 * - The screen is a logged-out browser context, proven logged out.
 *
 * The admin settings this file changes (the switch) are read first and put
 * back in afterAll.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-kiosk-mode::the-owner-puts-a-dashboard-on-the-canteen-screen
 *   @e2e dashboard-kiosk-mode::a-second-create-returns-the-same-link
 *   @e2e dashboard-kiosk-mode::the-owner-revokes-the-screen-link
 *   @e2e dashboard-kiosk-mode::a-reader-of-a-shared-dashboard-cannot-put-it-on-a-screen
 *   @e2e dashboard-kiosk-mode::the-administrator-switches-screen-links-off
 *   @e2e dashboard-kiosk-mode::not-set-means-on
 *   @e2e dashboard-kiosk-mode::noor-finds-the-canteen-screen
 *   @e2e dashboard-kiosk-mode::deleting-the-dashboard-revokes-its-screen-link
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/specs/dashboard-kiosk-mode/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, request, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { openDashboardSettings } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const RUN = Date.now()
const LOGGED_OUT = { cookies: [], origins: [] }

type Dashboard = { id: number; uuid: string; slug: string; name: string }

let admin: APIRequestContext
let pieter: Principal | null = null
let ella: Principal | null = null
let kantine: Dashboard | null = null
let priorSwitch = true

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/** The screen link endpoint of a dashboard. */
function linkUrl(uuid: string): string {
	return `${API}/dashboards/${uuid}/screen-link`
}

/** Switch screen links on or off as the administrator. */
async function setSwitch(on: boolean): Promise<void> {
	const res = await admin.put(`${API}/admin/sharing-policy`, { data: { kioskScreenLinksAllowed: on } })
	expect(res.status(), await res.text()).toBe(200)
}

/** The kiosk route as a logged-out player reads it. */
async function playAnonymously(token: string): Promise<number> {
	const anon = await request.newContext({ baseURL: BASE_URL, storageState: LOGGED_OUT })
	try {
		const res = await anon.get(`${APP}/kiosk/${token}`, { headers: { Accept: 'application/json' } })
		return res.status()
	} finally {
		await anon.dispose()
	}
}

/** Log in as someone and open the Sharing tab of a dashboard. */
async function openSharingTab(browser: Browser, who: Principal, dashboard: Dashboard): Promise<{ page: Page; close: () => Promise<void> }> {
	const { context, page } = await loginAs(browser, who.username, who.password)
	await page.goto(`${APP}/${dashboard.slug}`)
	await openDashboardSettings(page)
	await page.locator('[data-test="config-tab-sharing"]').click()
	await expect(page.locator('[data-test="config-panel-sharing"]')).toBeVisible({ timeout: 10_000 })
	return { page, close: () => context.close() }
}

test.beforeAll(async () => {
	admin = await adminContext()
	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status()).toBe(200)
	const body = await settings.json()
	priorSwitch = (body.data ?? body).kioskScreenLinksAllowed !== false
	await allowPersonalDashboards(admin, true)

	pieter = await createPrincipal(admin, 'e2e-screen-pieter')
	ella = await createPrincipal(admin, 'e2e-screen-ella')
	await assertActingAs(pieter.api, pieter.username)
	await assertActingAs(ella.api, ella.username)

	const name = `E2E Kantine ${RUN}`
	const created = await pieter.api.post(`${API}/dashboard`, { data: { name, slug: `e2e-kantine-${RUN}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	kantine = { id: Number(dash.id), uuid: String(dash.uuid), slug: String(dash.slug), name }
	await shareWith(pieter.api, kantine.id, ella.username, 'view_only')
})

test.afterAll(async () => {
	await admin.put(`${API}/admin/sharing-policy`, { data: { kioskScreenLinksAllowed: priorSwitch } })
	if (kantine !== null && pieter !== null) {
		await pieter.api.delete(linkUrl(kantine.uuid))
		await pieter.api.delete(`${API}/dashboard/${kantine.id}`)
	}
	await removePrincipal(admin, pieter)
	await removePrincipal(admin, ella)
	await admin.dispose()
})

// @e2e dashboard-kiosk-mode::not-set-means-on
// @e2e dashboard-kiosk-mode::the-owner-puts-a-dashboard-on-the-canteen-screen
// @e2e dashboard-kiosk-mode::the-owner-revokes-the-screen-link
test('the owner makes a screen link on the Sharing tab, a logged-out screen plays it, and Revoke stops it', async ({ browser }) => {
	await setSwitch(true)
	const tab = await openSharingTab(browser, pieter!, kantine!)
	try {
		const block = tab.page.locator('[data-test="sharing-screen-link"]')
		await expect(block).toBeVisible()
		await block.locator('[data-test="sharing-screen-link-create"]').click()
		const url = block.locator('[data-test="sharing-screen-link-url"]')
		await expect(url).toHaveValue(/\/apps\/launchpad\/kiosk\/[A-Za-z0-9]+$/)
		await expect(block.locator('[data-test="sharing-screen-link-copy"]')).toBeVisible()
		const link = await url.inputValue()
		const token = link.split('/kiosk/')[1]

		// The screen: logged out, full screen, no position line for one dashboard.
		const screen = await browser.newContext({ baseURL: BASE_URL, storageState: LOGGED_OUT })
		try {
			const me = await screen.request.get('/ocs/v2.php/cloud/user?format=json', { headers: { 'OCS-APIRequest': 'true' } })
			expect(me.status(), 'CONTROL: the screen must be logged out').toBe(401)
			const player = await screen.newPage()
			await player.goto(`${APP}/kiosk/${token}`)
			await expect(player.getByTestId('kiosk-dashboard')).toBeVisible({ timeout: 30_000 })
			await expect(player.getByTestId('kiosk-dashboard').locator('h1')).toHaveText(kantine!.name)
			await expect(player.locator('#header')).toHaveCount(0)
			await expect(player.getByTestId('kiosk-position')).toHaveCount(0)
		} finally {
			await screen.close()
		}

		await block.locator('[data-test="sharing-screen-link-revoke"]').click()
		await tab.page.getByRole('dialog').getByRole('button', { name: 'Revoke', exact: true }).click()
		await expect(block.locator('[data-test="sharing-screen-link-create"]')).toBeVisible()
		expect(await playAnonymously(token)).toBe(404)
	} finally {
		await tab.close()
	}
})

// @e2e dashboard-kiosk-mode::a-second-create-returns-the-same-link
test('a second create returns the same link', async () => {
	const first = await pieter!.api.post(linkUrl(kantine!.uuid))
	expect(first.status(), await first.text()).toBe(201)
	const second = await pieter!.api.post(linkUrl(kantine!.uuid))
	expect(second.status(), await second.text()).toBe(200)
	expect((await second.json()).token).toBe((await first.json()).token)
	const read = await pieter!.api.get(linkUrl(kantine!.uuid))
	expect((await read.json()).token).toBe((await first.json()).token)
})

// @e2e dashboard-kiosk-mode::a-reader-of-a-shared-dashboard-cannot-put-it-on-a-screen
test('a reader of a shared dashboard is refused with 403', async () => {
	const refused = await ella!.api.post(linkUrl(kantine!.uuid))
	expect(refused.status(), await refused.text()).toBe(403)
	const revoke = await ella!.api.delete(linkUrl(kantine!.uuid))
	expect(revoke.status()).toBe(403)
	const missing = await pieter!.api.post(linkUrl('00000000-0000-0000-0000-000000000000'))
	expect(missing.status(), 'a dashboard that does not exist answers like a refused one').toBe(403)
})

// @e2e dashboard-kiosk-mode::the-administrator-switches-screen-links-off
test('with the switch off no new link is made, and an existing one keeps playing with Revoke on the tab', async ({ browser }) => {
	const existing = await (await pieter!.api.get(linkUrl(kantine!.uuid))).json()
	await setSwitch(false)
	try {
		const settings = await (await admin.get(`${API}/admin/settings`)).json()
		expect((settings.data ?? settings).kioskScreenLinksAllowed).toBe(false)
		expect(await playAnonymously(existing.token), 'an existing screen link keeps playing').toBe(200)

		const tab = await openSharingTab(browser, pieter!, kantine!)
		try {
			const block = tab.page.locator('[data-test="sharing-screen-link"]')
			await expect(block.locator('[data-test="sharing-screen-link-revoke"]')).toBeVisible()
			await expect(block.locator('[data-test="sharing-screen-link-off"]')).toBeVisible()
			await expect(block.locator('[data-test="sharing-screen-link-create"]')).toHaveCount(0)
		} finally {
			await tab.close()
		}

		const revoked = await pieter!.api.delete(linkUrl(kantine!.uuid))
		expect(revoked.status()).toBe(204)
		const refused = await pieter!.api.post(linkUrl(kantine!.uuid))
		expect(refused.status(), await refused.text()).toBe(403)
	} finally {
		await setSwitch(true)
	}
})

// @e2e dashboard-kiosk-mode::noor-finds-the-canteen-screen
test('the administrator sees the screen link on the Kiosk tab without Edit, and the API marks it', async ({ browser }) => {
	const made = await pieter!.api.post(linkUrl(kantine!.uuid))
	expect(made.status()).toBeLessThan(300)
	const token = (await made.json()).token

	const rows = await (await admin.get(`${API}/kiosk/playlists`)).json()
	const row = rows.find((p: { token: string }) => p.token === token)
	expect(row?.screenDashboardUuid).toBe(kantine!.uuid)
	const update = await admin.put(`${API}/kiosk/playlists/${row.id}`, {
		data: { name: 'Rotation', entries: [{ dashboardUuid: kantine!.uuid }], refreshSeconds: 300 },
	})
	expect(update.status(), 'a screen link does not grow into a rotation').toBe(400)

	const page = await browser.newPage({ baseURL: BASE_URL })
	try {
		await page.goto(`${APP}/admin/kiosk`)
		const screenRow = page.locator('[data-testid="kiosk-screen-link-row"]').filter({ hasText: pieter!.username })
		await expect(screenRow).toBeVisible({ timeout: 30_000 })
		await expect(screenRow.locator('[data-testid="kiosk-screen-link-label"]')).toBeVisible()
		await expect(screenRow.locator('[data-testid="kiosk-edit"]')).toHaveCount(0)
		await expect(page.locator('[data-testid="kiosk-screen-links-allowed"]')).toBeVisible()
	} finally {
		await page.close()
	}
})

// @e2e dashboard-kiosk-mode::deleting-the-dashboard-revokes-its-screen-link
test('deleting the dashboard revokes its screen link', async () => {
	const name = `E2E Kantine delete ${RUN}`
	const created = await pieter!.api.post(`${API}/dashboard`, { data: { name, slug: `e2e-kantine-del-${RUN}` } })
	expect(created.status()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	const made = await pieter!.api.post(linkUrl(String(dash.uuid)))
	expect(made.status()).toBe(201)
	const token = (await made.json()).token
	expect(await playAnonymously(token)).toBe(200)

	const deleted = await pieter!.api.delete(`${API}/dashboard/${dash.id}`)
	expect(deleted.status()).toBeLessThan(300)
	expect(await playAnonymously(token)).toBe(404)
})
