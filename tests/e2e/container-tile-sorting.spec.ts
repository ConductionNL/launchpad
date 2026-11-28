/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A container sorts its tiles when viewed (launcher-tile-sorting).
 *
 * The container comes from the bookmark import, the one path in LaunchPad
 * that writes a container with children, so its link children are exactly
 * what a real user gets. Usage is counted in the viewer's browser only, so
 * the counts live in this test's page storage.
 *
 * The alphabetical, counts-on-the-device, forget-my-usage and random-order
 * tests were added for e2e-coverage-programme task 4.23. They read the order
 * from where the tiles are drawn (top to bottom, then left to right), not
 * from the DOM order, because the inner grid places each tile by its own
 * inline position and a DOM order alone does not prove what Pieter sees.
 *
 * @e2e openspec/specs/container-widget/spec.md
 */

import type {
	APIRequestContext,
	BrowserContext,
	Page,
	Request,
} from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const STAMP = `${Date.now()}`
const STORED_ORDER = ['Zaaksysteem', 'Agenda', 'Mail']
const ALPHABETICAL = ['Agenda', 'Mail', 'Zaaksysteem']
const API = '/index.php/apps/launchpad/api'
/** The browser-storage key the per-tile counts live under (src/utils/tileSort.js). */
const TILE_USE_KEY = 'launchpad.tileUse'

/*
 * The admin over basic auth, with an explicitly empty jar so the project's
 * stored browser session is not sent along.
 */
async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: `Basic ${Buffer.from(`${ADMIN.user}:${ADMIN.pass}`).toString('base64')}`,
		},
	})
}

async function shownOrder(page: Page): Promise<string[]> {
	const container = page
		.locator('.container-widget')
		.filter({ hasText: `Applicaties ${STAMP}` })
	await expect(container).toBeVisible()
	const labels = await container
		.locator('.container-widget__child')
		.allInnerTexts()
	return labels.map((label) => label.trim()).filter((label) => label !== '')
}

/**
 * The tile labels in the order they are drawn: by row (top), then by
 * column (left). Positions are rounded so sub-pixel jitter cannot swap two
 * tiles that sit on one row.
 *
 * @param page The workspace page.
 * @return the labels in reading order.
 */
async function drawnOrder(page: Page): Promise<string[]> {
	const container = page
		.locator('.container-widget')
		.filter({ hasText: `Applicaties ${STAMP}` })
	await expect(container).toBeVisible()
	const tiles = container.locator('.container-widget__child')
	await expect(tiles).toHaveCount(STORED_ORDER.length)
	const drawn: Array<{ label: string; top: number; left: number }> = []
	for (const tile of await tiles.all()) {
		const box = await tile.boundingBox()
		expect(box, 'a container tile has no box on the page').not.toBeNull()
		drawn.push({
			label: (await tile.innerText()).trim(),
			top: Math.round(box!.y),
			left: Math.round(box!.x),
		})
	}
	drawn.sort((a, b) => a.top - b.top || a.left - b.left)
	return drawn.map((entry) => entry.label)
}

/**
 * Click a tile of the container `times` times.
 *
 * @param page The workspace page.
 * @param label The tile's label.
 * @param times How often to click it.
 */
async function openTile(page: Page, label: string, times: number): Promise<void> {
	const container = page
		.locator('.container-widget')
		.filter({ hasText: `Applicaties ${STAMP}` })
	for (let i = 0; i < times; i++) {
		await container.getByText(label, { exact: true }).click()
	}
}

/**
 * Close every tab a link tile opens, so the external example addresses
 * never hold the run up.
 *
 * @param context The browser context of the test.
 */
function closePopups(context: BrowserContext): void {
	context.on('page', (popup) => {
		popup.close().catch(() => undefined)
	})
}

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
let containerId = 0
let containerContent: Record<string, unknown> = {}

/**
 * The key a child's usage is counted under in the browser: the key the
 * server stamps on each child (REQ-WDG-050, ContainerChildKeys), as
 * tileSort.childUseKey() reads it, else the renderer's `<container>-<index>`.
 *
 * @param index The child's index in the stored order.
 * @return The child's usage key.
 */
function childKey(index: number): string {
	const child = ((containerContent.placements ?? []) as { id?: unknown, uuid?: unknown }[])[index] ?? {}
	if (child.id !== undefined && child.id !== null) {
		return String(child.id)
	}
	return typeof child.uuid === 'string' && child.uuid !== '' ? child.uuid : `${containerId}-${index}`
}

async function sortBy(mode: string): Promise<void> {
	const updated = await api.put(`${API}/widgets/${containerId}`, {
		data: { content: { ...containerContent, sortBy: mode } },
	})
	expect(updated.status(), await updated.text()).toBeLessThan(300)
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E tile sorting ${STAMP}`)
	const imported = await api.post(
		`${API}/dashboard/${dashboard.id}/tiles/import`,
		{
			data: {
				folders: [
					{
						name: `Applicaties ${STAMP}`,
						bookmarks: STORED_ORDER.map((title) => ({
							title,
							url: `https://example.invalid/${title.toLowerCase()}`,
						})),
					},
				],
				bookmarks: [],
			},
		},
	)
	expect(imported.status(), await imported.text()).toBe(201)
	const container = (await imported.json()).placements[0]
	containerId = Number(container.id)
	containerContent = container.content
	expect(containerContent.placements).toHaveLength(STORED_ORDER.length)
	await sortBy('most-used')
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

// @e2e container-widget::a-daily-app-moves-to-the-front
test('most used: a tile opened three times is first after a reload', async ({
	page,
	context,
}) => {
	context.on('page', (popup) => {
		popup.close().catch(() => undefined)
	})
	await page.goto('/index.php/apps/launchpad/')
	expect(await shownOrder(page)).toEqual(['Agenda', 'Mail', 'Zaaksysteem'])

	const container = page
		.locator('.container-widget')
		.filter({ hasText: `Applicaties ${STAMP}` })
	for (let i = 0; i < 3; i++) {
		await container.getByText('Zaaksysteem', { exact: true }).click()
	}

	await page.reload()
	expect((await shownOrder(page))[0]).toBe('Zaaksysteem')
})

// @e2e container-widget::back-to-the-authors-layout
test('by hand: the stored order comes back', async ({ page }) => {
	await sortBy('manual')

	await page.goto('/index.php/apps/launchpad/')
	expect(await shownOrder(page)).toEqual(STORED_ORDER)
})

// @e2e container-widget::alphabetical-order
test('alphabetically: the tiles are drawn A to Z, left to right', async ({
	page,
}) => {
	await sortBy('alphabetical')

	await page.goto('/index.php/apps/launchpad/')
	expect(await drawnOrder(page)).toEqual(ALPHABETICAL)

	// Sorting is a view: the stored order on the server is untouched.
	const stored = await api.get(`${API}/dashboard/${dashboard!.id}`)
	expect(stored.status(), await stored.text()).toBe(200)
	const placement = ((await stored.json()).placements ?? []).find(
		(entry: { id: number }) => Number(entry.id) === containerId,
	)
	// The bookmark import stores each child as a tile node (#2073), whose
	// name is `content.title`; a legacy link child carries `content.label`.
	const labels = (placement?.content?.placements ?? []).map(
		(child: { content?: { title?: string; label?: string } }) =>
			child.content?.title ?? child.content?.label,
	)
	expect(labels).toEqual(STORED_ORDER)
})

// @e2e container-widget::counts-stay-on-the-device
test('most used: the counts stay in the browser, a click sends nothing but the tile click', async ({
	page,
	context,
}) => {
	await sortBy('most-used')
	closePopups(context)
	// Opening the dashboard records one page view (REQ-ANLT-002), sent after
	// the page has loaded. It is not caused by a click, so it is waited for
	// before listening; with analytics off it never comes.
	const viewed = page
		.waitForRequest((req) => req.url().includes('/view-event'), { timeout: 15_000 })
		.catch(() => null)
	await page.goto('/index.php/apps/launchpad/')
	await drawnOrder(page)
	await viewed

	const origin = new URL(BASE_URL).origin
	const sent: Request[] = []
	const listen = (req: Request): void => {
		if (new URL(req.url()).origin === origin) {
			sent.push(req)
		}
	}
	context.on('request', listen)
	await openTile(page, 'Zaaksysteem', 2)
	await openTile(page, 'Mail', 1)
	// Give a deferred send the chance to happen before we judge it.
	await page.waitForTimeout(2_000)
	context.off('request', listen)

	// The clicks were recorded, on this device.
	const use = await page.evaluate(
		(key) => JSON.parse(window.localStorage.getItem(key) ?? '{}'),
		TILE_USE_KEY,
	)
	const counts = Object.fromEntries(
		Object.entries(use as Record<string, { count: number }>).map(
			([key, value]) => [key, value.count],
		),
	)
	expect(counts).toMatchObject({
		[childKey(0)]: 2,
		[childKey(2)]: 1,
	})

	// No request carries them, in its address or its body.
	const stored = JSON.stringify(use)
	for (const req of sent) {
		const carried = `${req.url()} ${req.postData() ?? ''}`
		expect(
			carried,
			`${req.method()} ${req.url()} carries the tile counts`,
		).not.toContain(TILE_USE_KEY)
		expect(
			carried,
			`${req.method()} ${req.url()} carries the tile counts`,
		).not.toContain(stored)
		expect(
			carried,
			`${req.method()} ${req.url()} carries the tile counts`,
		).not.toContain('"lastUsedAt"')
	}

	// The only LaunchPad call a click may cause is the anonymous tile click.
	const appCalls = sent
		.filter((req) =>
			new URL(req.url()).pathname.includes('/apps/launchpad/api/'),
		)
		.map((req) => `${req.method()} ${new URL(req.url()).pathname}`)
	for (const call of appCalls) {
		expect(call).toMatch(/^POST \S*\/apps\/launchpad\/api\/tile-click\/[^/]+$/)
	}
	expect(appCalls.length).toBeLessThanOrEqual(3)
})

// @e2e container-widget::forget-my-usage
test('forget my usage: alphabetical again until a tile is opened', async ({
	page,
	context,
}) => {
	await sortBy('most-used')
	closePopups(context)
	await page.goto('/index.php/apps/launchpad/')
	await openTile(page, 'Zaaksysteem', 3)
	await page.reload()
	expect((await drawnOrder(page))[0]).toBe('Zaaksysteem')

	const container = page
		.locator('.container-widget')
		.filter({ hasText: `Applicaties ${STAMP}` })
	await container.getByRole('button', { name: 'Forget my usage' }).click()
	await expect.poll(() => drawnOrder(page)).toEqual(ALPHABETICAL)
	const left = await page.evaluate(
		(key) => JSON.parse(window.localStorage.getItem(key) ?? '{}'),
		TILE_USE_KEY,
	)
	for (let index = 0; index < STORED_ORDER.length; index++) {
		expect(left, 'a forgotten tile kept its count').not.toHaveProperty(
			childKey(index),
		)
	}

	// Still alphabetical on the next visit: the counts are gone, not hidden.
	await page.reload()
	expect(await drawnOrder(page)).toEqual(ALPHABETICAL)

	// Until he clicks one again: that tile leads after the next load.
	await openTile(page, 'Mail', 1)
	await page.reload()
	expect(await drawnOrder(page)).toEqual(['Mail', 'Agenda', 'Zaaksysteem'])
})

// @e2e container-widget::no-jumping-tiles
test('at random: the order holds while a widget elsewhere refreshes', async ({
	page,
}) => {
	await sortBy('random')
	// A widget elsewhere on the dashboard that refreshes what it shows: an
	// analog clock redraws its second hand every second.
	const clock = await api.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
		data: {
			widgetId: 'clock',
			gridX: 8,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
			content: {
				style: 'analog',
				hourFormat: 'auto',
				timezone: '',
				showDate: false,
			},
		},
	})
	expect(clock.status(), await clock.text()).toBe(201)
	const clockBody = await clock.json()
	const clockId = Number(
		clockBody.id ?? clockBody.placement?.id ?? clockBody.data?.id,
	)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const first = await drawnOrder(page)
		expect([...first].sort()).toEqual([...STORED_ORDER].sort())

		const hand = page.locator('.clock-widget__hand--second').first()
		await expect(hand).toBeVisible()
		const handAt = async (): Promise<string> =>
			`${await hand.getAttribute('x2')},${await hand.getAttribute('y2')}`
		const before = await handAt()
		await expect.poll(handAt, { timeout: 5_000 }).not.toBe(before)

		// Several refreshes later, and after the page reflows, nothing moved.
		const later = await handAt()
		await expect.poll(handAt, { timeout: 5_000 }).not.toBe(later)
		expect(await drawnOrder(page)).toEqual(first)
		const size = page.viewportSize()
		if (size) {
			await page.setViewportSize({
				width: size.width - 40,
				height: size.height,
			})
			await page.setViewportSize(size)
		}
		expect(await drawnOrder(page)).toEqual(first)
	} finally {
		if (clockId) {
			await api.delete(`${API}/widgets/${clockId}`)
		}
	}
})
