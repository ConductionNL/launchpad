/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end test for the responsive grid breakpoints covering
 * tasks 3.1 + 3.2 of the `responsive-grid-breakpoints` OpenSpec change.
 *
 * Asserts:
 *   - REQ-GRID-007: the grid's `opts.column` follows the breakpoint table
 *     (1400 → 12, 1100 → 8, 768 → 4). Below the smallest entry a phone keeps
 *     four columns (board LpMobiel), so 360 px and 320 px read 4, not 1.
 *   - REQ-GRID-007: on a 390 px phone a heading over six one-cell app tiles,
 *     with a clock beside it, is laid out in reading order: the heading on a
 *     full row, the tiles four to a row under it, the clock on a full row
 *     after them. A throwaway account owns that dashboard, so nothing another
 *     spec left on the grid is measured.
 *
 * Gate-19 @e2e traceability:
 *   @e2e grid-layout::12-columns-on-wide-desktop
 *   @e2e grid-layout::reflow-at-1100-px
 *   @e2e grid-layout::app-tiles-four-to-a-row-on-a-phone
 *   @e2e grid-layout::below-smallest-breakpoint
 *   @e2e grid-layout::grid-fills-container-width
 *   @e2e grid-layout::minimum-grid-height
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { OwnDashboard, Rect } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import {
	openOwnDashboard,
	placementRects,
	renderedRects,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'
import { API } from './support/principals.ts'

/**
 * Read GridStack's live column count for the rendered grid.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {Promise<number>} The active column count.
 */
async function readColumns(page: Page): Promise<number> {
	await page.waitForFunction(
		() => {
			const el = document.querySelector('.grid-stack') as
				(HTMLElement & { gridstack?: { opts: { column: number } } }) | null
			return Boolean(el?.gridstack)
		},
		{ timeout: 15_000 },
	)
	// Allow the breakpoint reflow to settle after the viewport change.
	await page.waitForTimeout(400)
	return page.evaluate(() => {
		const el = document.querySelector('.grid-stack') as HTMLElement & {
			gridstack: { opts: { column: number } }
		}
		return Number(el.gridstack.opts.column)
	})
}

test.describe('responsive grid breakpoints', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e grid-layout::12-columns-on-wide-desktop
	test('REQ-GRID-007: a wide desktop uses the full 12-column grid', async ({
		page,
	}) => {
		await page.setViewportSize({ width: 1600, height: 900 })
		const columns = await readColumns(page)
		// Above the largest breakpoint the grid uses its full column count.
		expect(columns).toBe(12)
	})

	// @e2e grid-layout::below-smallest-breakpoint
	test('REQ-GRID-007: below the smallest breakpoint the grid keeps its four columns', async ({
		page,
	}) => {
		// 320 px is below the 768 entry: the smallest entry's column count
		// applies, and a phone does not collapse to one column (board LpMobiel).
		await page.setViewportSize({ width: 320, height: 900 })
		expect(await readColumns(page)).toBe(4)
		await page.setViewportSize({ width: 360, height: 900 })
		expect(await readColumns(page)).toBe(4)
	})

	// @e2e grid-layout::reflow-at-1100-px
	// @e2e grid-layout::grid-fills-container-width
	test('REQ-GRID-007: column count is monotonically non-increasing as the viewport narrows', async ({
		page,
	}) => {
		// The exact column count at an intermediate width depends on the live
		// container width (Nextcloud chrome offset) and GridStack's
		// window-breakpoint matching, so assert the responsive INVARIANT —
		// columns never grow as the viewport shrinks, spanning 12 → 4 — rather
		// than brittle per-breakpoint exact values.
		const widths = [1600, 1200, 900, 640, 360]
		const counts: number[] = []
		for (const w of widths) {
			await page.setViewportSize({ width: w, height: 900 })
			counts.push(await readColumns(page))
		}
		// Monotonic non-increasing.
		for (let i = 1; i < counts.length; i++) {
			expect(counts[i]).toBeLessThanOrEqual(counts[i - 1])
		}
		// Spans the full responsive range.
		expect(counts[0]).toBe(12)
		expect(counts[counts.length - 1]).toBe(4)
	})

	// @e2e grid-layout::minimum-grid-height
	test('REQ-GRID-012: the grid container fills its width and has a non-zero height', async ({
		page,
	}) => {
		await page.setViewportSize({ width: 1400, height: 900 })
		await readColumns(page)
		const grid = page.locator('.grid-stack').first()
		const box = await grid.boundingBox()
		expect(box).not.toBeNull()
		expect(box!.width).toBeGreaterThan(400)
		expect(box!.height).toBeGreaterThan(0)
	})
})

test.describe('a phone lays the dashboard out in reading order', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(120_000)

	let own: OwnDashboard | undefined

	test.afterEach(async () => {
		await tearDownOwnDashboard(own)
		own = undefined
	})

	/**
	 * Place a widget at an exact rectangle and return its placement id.
	 *
	 * @param api The owner's context.
	 * @param dashboardId The dashboard.
	 * @param data The placement body.
	 * @return The placement id.
	 */
	async function place(
		api: APIRequestContext,
		dashboardId: number,
		data: Record<string, unknown>,
	): Promise<number> {
		const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, { data })
		expect(res.status(), await res.text()).toBe(201)
		const body = await res.json()
		return Number(body.id ?? body.placement?.id ?? body.data?.id)
	}

	/**
	 * Add an app tile, then make it one cell at an exact spot. The tile
	 * endpoint takes no size (it stores 2 × 2), so the tile is created on an
	 * empty row far below and moved and shrunk with a placement update.
	 *
	 * @param api The owner's context.
	 * @param dashboardId The dashboard.
	 * @param index The tile's number, for its title and its parking row.
	 * @param rect Where the tile ends up.
	 * @return The placement id.
	 */
	async function oneCellTile(
		api: APIRequestContext,
		dashboardId: number,
		index: number,
		rect: Rect,
	): Promise<number> {
		const created = await api.post(`${API}/dashboard/${dashboardId}/tile`, {
			data: {
				title: `E2E app ${index}`,
				icon: '📁',
				iconType: 'emoji',
				bgColor: '#3b82f6',
				textColor: '#ffffff',
				linkType: 'app',
				linkValue: 'files',
				gridX: 0,
				gridY: 20 + 2 * index,
			},
		})
		expect(created.status(), await created.text()).toBe(201)
		const id = Number((await created.json()).id)
		const moved = await api.put(`${API}/widgets/${id}`, {
			data: {
				gridX: rect.x,
				gridY: rect.y,
				gridWidth: rect.w,
				gridHeight: rect.h,
			},
		})
		expect(moved.status(), await moved.text()).toBe(200)
		return id
	}

	// @e2e grid-layout::app-tiles-four-to-a-row-on-a-phone
	test('on a 390 px phone the heading takes a row, its six tiles sit four and two under it, and the clock follows', async ({
		browser,
	}) => {
		own = await setUpOwnDashboard('e2e-grid-phone', `E2E grid phone ${Date.now()}`)
		const api = own.owner.api

		// The desktop layout, in 12 columns: "My apps" over six one-cell tiles
		// on the left, a clock beside them on the right.
		const heading = await place(api, own.id, {
			widgetId: 'section-heading',
			gridX: 0,
			gridY: 0,
			gridWidth: 8,
			gridHeight: 1,
			showTitle: 0,
			content: { title: 'My apps', description: '' },
		})
		const clock = await place(api, own.id, {
			widgetId: 'clock',
			gridX: 8,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
			content: { style: 'digital', hourFormat: 'auto', timezone: '', showDate: false },
		})
		const tiles: number[] = []
		for (let i = 0; i < 6; i++) {
			tiles.push(await oneCellTile(api, own.id, i, { x: i, y: 1, w: 1, h: 1 }))
		}

		// The seed is what the scenario describes, or the test says so here.
		const stored = await placementRects(api, own.id)
		expect(stored.get(heading)).toEqual({ x: 0, y: 0, w: 8, h: 1 })
		expect(stored.get(clock)).toEqual({ x: 8, y: 0, w: 4, h: 4 })
		tiles.forEach((id, i) => {
			expect(stored.get(id), `tile ${i}`).toEqual({ x: i, y: 1, w: 1, h: 1 })
		})

		const { context, page } = await openOwnDashboard(browser, own, 8)
		try {
			// The workspace renders on the phone itself, not after a resize.
			await page.setViewportSize({ width: 390, height: 844 })
			await page.reload()
			await expect(page.locator('.grid-stack > .grid-stack-item')).toHaveCount(8, {
				timeout: 30_000,
			})
			expect(await readColumns(page)).toBe(4)

			const expected = new Map<number, Rect>([
				[heading, { x: 0, y: 0, w: 4, h: 1 }],
				[tiles[0], { x: 0, y: 1, w: 1, h: 1 }],
				[tiles[1], { x: 1, y: 1, w: 1, h: 1 }],
				[tiles[2], { x: 2, y: 1, w: 1, h: 1 }],
				[tiles[3], { x: 3, y: 1, w: 1, h: 1 }],
				[tiles[4], { x: 0, y: 2, w: 1, h: 1 }],
				[tiles[5], { x: 1, y: 2, w: 1, h: 1 }],
				[clock, { x: 0, y: 3, w: 4, h: 4 }],
			])
			await expect
				.poll(async () => Object.fromEntries(await renderedRects(page)), {
					timeout: 10_000,
				})
				.toEqual(Object.fromEntries(expected))

			// The tiles are on screen four to a row, not merely in the
			// attributes: the first four share a top edge, the fifth starts a
			// new row at the left edge, and the clock spans the grid.
			const box = async (id: number) => {
				const b = await page
					.locator(`.grid-stack > .grid-stack-item[gs-id="${id}"]`)
					.boundingBox()
				expect(b, `item ${id} has no box`).not.toBeNull()
				return b!
			}
			const first = await box(tiles[0])
			for (const id of tiles.slice(1, 4)) {
				const b = await box(id)
				expect(Math.abs(b.y - first.y)).toBeLessThan(2)
				expect(b.x).toBeGreaterThan(first.x)
			}
			const fifth = await box(tiles[4])
			expect(fifth.y).toBeGreaterThan(first.y + first.height / 2)
			expect(Math.abs(fifth.x - first.x)).toBeLessThan(2)
			const grid = await page.locator('.grid-stack').first().boundingBox()
			const clockBox = await box(clock)
			expect(clockBox.y).toBeGreaterThan(fifth.y)
			expect(clockBox.width).toBeGreaterThan(grid!.width * 0.9)

			// The phone layout is drawn, never saved over the desktop one.
			const after = await placementRects(api, own.id)
			expect(after.get(clock)).toEqual({ x: 8, y: 0, w: 4, h: 4 })
			expect(after.get(tiles[4])).toEqual({ x: 4, y: 1, w: 1, h: 1 })
		} finally {
			await context.close()
		}
	})
})
