/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Where the kiosk playlist is (kiosk-position-indicator, REQ-KIOSKUI-003):
 * a two-dashboard playlist shows "1 van 2" (or "1 of 2"), then "2 van 2"
 * once the first dwell time is over; a playlist of one dashboard shows no
 * position line. Language-neutral: the visitor's language decides the word.
 *
 * Written from the change's scenarios; it runs in the nightly against a
 * live instance.
 *
 * @e2e openspec/changes/archive/2026-11-24-kiosk-position-indicator/specs/dashboard-kiosk-mode/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { adminContext, API } from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const PLAYLISTS = `${API}/kiosk/playlists`
const STAMP = Date.now()
/** The shortest dwell time a playlist entry takes, in seconds (KioskService::DWELL_MIN). */
const DWELL = 10

/** A logged-out browser: no cookies, no local storage. */
const LOGGED_OUT = { cookies: [], origins: [] }

let admin: APIRequestContext
const dashboards: number[] = []
const playlists: number[] = []

/**
 * Create a dashboard and answer its uuid.
 *
 * @param label The dashboard label.
 * @return The uuid.
 */
async function createDashboard(label: string): Promise<string> {
	const name = `E2E Positie ${label} ${STAMP}`
	const created = await admin.post(`${API}/dashboard`, {
		data: { name, slug: name.toLowerCase().replace(/[^a-z0-9]+/g, '-') },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	dashboards.push(Number(dash.id))
	return String(dash.uuid)
}

/**
 * Create a playlist and answer its token.
 *
 * @param name The playlist name.
 * @param uuids The dashboards, in order.
 * @return The token.
 */
async function createPlaylist(name: string, uuids: string[]): Promise<string> {
	const res = await admin.post(PLAYLISTS, {
		data: {
			name: `${name} ${STAMP}`,
			entries: uuids.map((dashboardUuid) => ({ dashboardUuid, dwellSeconds: DWELL })),
			refreshSeconds: 300,
		},
	})
	expect(res.status(), await res.text()).toBe(201)
	const playlist = await res.json()
	playlists.push(Number(playlist.id))
	return String(playlist.token)
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

let twoToken = ''
let oneToken = ''

test.beforeAll(async () => {
	admin = await adminContext()
	const first = await createDashboard('Eerste')
	const second = await createDashboard('Tweede')
	twoToken = await createPlaylist('E2E Positie twee', [first, second])
	oneToken = await createPlaylist('E2E Positie een', [first])
})

test.afterAll(async () => {
	for (const id of playlists) {
		await admin.delete(`${PLAYLISTS}/${id}`)
	}
	for (const id of dashboards.reverse()) {
		await admin.delete(`${API}/dashboard/${id}`)
	}
	await admin.dispose()
})

// @e2e dashboard-kiosk-mode::the-canteen-screen
test('a two-dashboard playlist shows 1 van 2 and then 2 van 2', async ({ browser }) => {
	const context = await browser.newContext({ storageState: LOGGED_OUT })
	const page = await context.newPage()
	try {
		await page.goto(`${APP}/kiosk/${twoToken}`)
		const position = page.getByTestId('kiosk-position')
		await expect(position).toHaveText(/^\s*1 (van|of) 2 · /, { timeout: 30_000 })
		await expect(position).toHaveAttribute('aria-hidden', 'true')
		await expect(position).toHaveText(/^\s*2 (van|of) 2 · /, { timeout: (DWELL + 10) * 1000 })
		await expect(page.getByTestId('kiosk-announcement')).toContainText('Tweede')
	} finally {
		await context.close()
	}
})

// @e2e dashboard-kiosk-mode::one-dashboard
//
// An absent line alone proves nothing: `positionText` is also empty while no
// entry is current, so a playlist that never loaded would pass. The test
// therefore (1) shows on the same build that the line renders for two
// dashboards, so the selector is live; (2) requires the band to name the one
// dashboard, so its entry IS current; and (3) waits out a dwell time, so a
// line that appears on the first rotation tick is caught too.
test('a playlist of one dashboard shows no position line', async ({ browser }) => {
	const context = await browser.newContext({ storageState: LOGGED_OUT })
	const page = await context.newPage()
	try {
		await page.goto(`${APP}/kiosk/${twoToken}`)
		await expect(page.getByTestId('kiosk-position')).toHaveText(/^\s*1 (van|of) 2 · /, { timeout: 30_000 })

		await page.goto(`${APP}/kiosk/${oneToken}`)
		const title = page.getByTestId('kiosk-dashboard').getByRole('heading', { level: 1 })
		await expect(title).toHaveText(`E2E Positie Eerste ${STAMP}`, { timeout: 30_000 })
		await expect(page.getByTestId('kiosk-position')).toHaveCount(0)

		await page.waitForTimeout((DWELL + 2) * 1000)
		await expect(title).toHaveText(`E2E Positie Eerste ${STAMP}`)
		await expect(page.getByTestId('kiosk-position')).toHaveCount(0)
	} finally {
		await context.close()
	}
})
