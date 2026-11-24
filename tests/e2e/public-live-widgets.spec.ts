/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Live widgets on a kiosk screen (public-live-widgets, REQ-PSHR-011): the
 * canteen playlist shows its news and its live data card to a visitor who
 * is not logged in, read through the playlist token. A news item for a
 * group never reaches that visitor, and a placement on another dashboard
 * cannot be read through the token.
 *
 * Written from the change's scenario; it runs in the nightly against a
 * live instance.
 *
 * @e2e openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import { adminContext, API } from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const PLAYLISTS = `${API}/kiosk/playlists`
const STAMP = Date.now()
/** Every announcement needs a "Show until" (REQ-ANN-001). */
const inTwoDays = new Date(Date.now() + 2 * 24 * 3600 * 1000).toISOString()

/** A logged-out browser: no cookies, no local storage. */
const LOGGED_OUT = { cookies: [], origins: [] }

let admin: APIRequestContext
const dashboards: number[] = []
const announcements: string[] = []
let playlistId = 0
let token = ''
let newsPlacement = 0
let livePlacement = 0
let otherPlacement = 0

/**
 * Create a dashboard and answer its id.
 *
 * @param name The dashboard name.
 * @return The id.
 */
async function createDashboard(name: string): Promise<{ id: number; uuid: string }> {
	const created = await admin.post(`${API}/dashboard`, {
		data: { name, slug: name.toLowerCase().replace(/[^a-z0-9]+/g, '-') },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	dashboards.push(Number(dash.id))
	return { id: Number(dash.id), uuid: String(dash.uuid) }
}

/**
 * Place a widget and answer the placement id.
 *
 * @param dashboardId The dashboard.
 * @param data The placement.
 * @return The placement id.
 */
async function place(dashboardId: number, data: Record<string, unknown>): Promise<number> {
	const res = await admin.post(`${API}/dashboard/${dashboardId}/widgets`, { data })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const placement = await res.json()
	return Number(placement.id ?? placement.data?.id ?? placement.placement?.id)
}

/**
 * Publish a news item.
 *
 * @param data The announcement.
 */
async function publish(data: Record<string, unknown>): Promise<void> {
	const created = await admin.post(`${API}/announcements`, { data })
	expect(created.status(), await created.text()).toBe(201)
	const uuid = (await created.json()).uuid as string
	announcements.push(uuid)
	const published = await admin.post(`${API}/announcements/${uuid}/publish`)
	expect(published.status(), await published.text()).toBeLessThan(300)
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()

	const canteen = await createDashboard(`E2E Kantine ${STAMP}`)
	newsPlacement = await place(canteen.id, {
		widgetId: 'announcements',
		gridX: 0,
		gridY: 0,
		gridWidth: 6,
		gridHeight: 4,
		content: { category: '', limit: 10 },
	})
	livePlacement = await place(canteen.id, {
		widgetId: 'livetile',
		gridX: 6,
		gridY: 0,
		gridWidth: 3,
		gridHeight: 2,
		content: { label: 'Waiting time at the desk', sourceMode: 'url', url: '', valueExpr: '', refresh: 300 },
	})
	const other = await createDashboard(`E2E Elders ${STAMP}`)
	otherPlacement = await place(other.id, {
		widgetId: 'announcements',
		gridX: 0,
		gridY: 0,
		gridWidth: 6,
		gridHeight: 4,
		content: {},
	})

	await publish({ kind: 'news', title: `Kantine dicht vrijdag ${STAMP}`, body: 'Verbouwing.', expiresAt: inTwoDays })
	await publish({ kind: 'news', title: `Alleen voor beheer ${STAMP}`, body: 'Intern.', targetGroups: ['admin'], expiresAt: inTwoDays })

	const res = await admin.post(PLAYLISTS, {
		data: { name: `E2E Kantine ${STAMP}`, entries: [{ dashboardUuid: canteen.uuid, dwellSeconds: 60 }], refreshSeconds: 300 },
	})
	expect(res.status(), await res.text()).toBe(201)
	const playlist = await res.json()
	playlistId = Number(playlist.id)
	token = String(playlist.token)
})

test.afterAll(async () => {
	if (playlistId !== 0) {
		await admin.delete(`${PLAYLISTS}/${playlistId}`)
	}
	for (const uuid of announcements) {
		await admin.delete(`${API}/announcements/${uuid}`)
	}
	for (const id of dashboards.reverse()) {
		await admin.delete(`${API}/dashboard/${id}`)
	}
	await admin.dispose()
})

// @e2e dashboard-public-share::the-canteen-screen
test('the kiosk playlist shows the news and the live data card without a login', async ({ browser }) => {
	const context = await browser.newContext({ storageState: LOGGED_OUT })
	const page = await context.newPage()
	try {
		await page.goto(`${APP}/kiosk/${token}`)
		const news = page.locator('[data-test="public-news-widget"]')
		await expect(news).toContainText(`Kantine dicht vrijdag ${STAMP}`)
		await expect(news).not.toContainText(`Alleen voor beheer ${STAMP}`)
		await expect(page.locator('.live-tile-widget')).toBeVisible()
		await expect(page.locator('.public-share-view__widget-restricted')).toHaveCount(0)
	} finally {
		await context.close()
	}
})

test('the token reads only placements on the playlist dashboards', async () => {
	const anonymous = await request.newContext({ baseURL: BASE_URL, storageState: LOGGED_OUT })
	try {
		const news = await anonymous.get(`${APP}/kiosk/${token}/placements/${newsPlacement}`)
		expect(news.status(), await news.text()).toBe(200)
		const body = await news.json()
		expect(body.widget).toBe('announcements')
		const titles = body.data.announcements.map((item: { title: string }) => item.title)
		expect(titles).toContain(`Kantine dicht vrijdag ${STAMP}`)
		expect(titles).not.toContain(`Alleen voor beheer ${STAMP}`)
		expect(JSON.stringify(body)).not.toContain('author')

		const other = await anonymous.get(`${APP}/kiosk/${token}/placements/${otherPlacement}`)
		expect(other.status()).toBe(404)

		const live = await anonymous.get(`${APP}/kiosk/${token}/placements/${livePlacement}`)
		expect([200, 404, 502]).toContain(live.status())
	} finally {
		await anonymous.dispose()
	}
})
