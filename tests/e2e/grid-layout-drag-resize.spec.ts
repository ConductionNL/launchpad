/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Dragging and resizing widgets on the GridStack grid, and how the result is
 * saved (grid-layout REQ-GRID-002, REQ-GRID-003, REQ-GRID-005).
 *
 * Every test gets a fresh throwaway account whose only dashboard holds text
 * widgets at exact coordinates, seeded through the API. The test then drags
 * with the mouse the way a person does (`page.mouse` down, move in steps, up),
 * reads the rendered `gs-*` attributes, waits for the layout save
 * (`PUT /api/dashboard/{id}`) and re-reads the stored placements.
 *
 * The viewport is 1600 px wide, so the grid is on its 12-column breakpoint and
 * one column is a twelfth of the grid's width.
 *
 * Gate-19 @e2e traceability:
 *   @e2e grid-layout::drag-a-widget-to-a-new-position
 *   @e2e grid-layout::drag-is-disabled-in-view-mode
 *   @e2e grid-layout::drag-respects-grid-boundaries
 *   @e2e grid-layout::drag-pushes-other-widgets
 *   @e2e grid-layout::drag-emits-position-update
 *   @e2e grid-layout::resize-a-widget-horizontally
 *   @e2e grid-layout::resize-a-widget-vertically
 *   @e2e grid-layout::resize-constrained-by-minimum-size
 *   @e2e grid-layout::resize-constrained-by-grid-columns
 *   @e2e grid-layout::resize-handles-not-visible-in-view-mode
 *   @e2e grid-layout::save-after-grid-change
 *   @e2e grid-layout::multiple-rapid-changes
 *   @e2e grid-layout::concurrent-edits-from-multiple-tabs
 *   @e2e grid-layout::change-event-maps-grid-items-to-placements
 *
 * @spec openspec/specs/grid-layout/spec.md
 */

import type { Browser, Locator, Page } from '@playwright/test'
import type { OwnDashboard, Rect } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	anyOverlap,
	nextLayoutSave,
	openOwnDashboard,
	placementRects,
	renderedRects,
	seedTextWidget,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'

const CELL_HEIGHT = 60

let own: OwnDashboard | undefined

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.afterEach(async () => {
	await tearDownOwnDashboard(own)
	own = undefined
})

/**
 * A fresh account with text widgets at the given rectangles.
 *
 * @param rects The rectangles, in order.
 * @return The placement ids, in the same order.
 */
async function seed(rects: Rect[]): Promise<number[]> {
	own = await setUpOwnDashboard('e2e-grid-drag', `E2E grid drag ${Date.now()}`)
	const ids: number[] = []
	for (const [index, rect] of rects.entries()) {
		ids.push(
			await seedTextWidget(own.owner.api, own.id, rect, `Widget ${index + 1}`),
		)
	}
	return ids
}

/**
 * The grid item of a placement.
 *
 * @param page The page.
 * @param id The placement id.
 * @return The item.
 */
function item(page: Page, id: number): Locator {
	return page.locator(`.grid-stack > .grid-stack-item[gs-id="${id}"]`)
}

/**
 * The width of one grid column in pixels.
 *
 * @param page The page.
 * @return The width.
 */
async function columnWidth(page: Page): Promise<number> {
	const box = await page.locator('.grid-stack').first().boundingBox()
	expect(box).not.toBeNull()
	return box!.width / 12
}

/**
 * Drag a widget by its body over a number of columns and rows.
 *
 * @param page The page.
 * @param id The placement id.
 * @param columns Columns to the right (negative: left).
 * @param rows Rows down (negative: up).
 */
async function dragBy(
	page: Page,
	id: number,
	columns: number,
	rows: number,
): Promise<void> {
	const colW = await columnWidth(page)
	const box = await item(page, id)
		.locator('.grid-stack-item-content')
		.boundingBox()
	expect(box).not.toBeNull()
	// Low in the body: on a narrow widget the header's Actions button sits
	// at the middle of the card, and GridStack starts no drag on a button.
	const startX = box!.x + box!.width / 2
	const startY = box!.y + box!.height * 0.8
	await page.mouse.move(startX, startY)
	await page.mouse.down()
	await page.mouse.move(startX + 6, startY + 6, { steps: 3 })
	await page.mouse.move(startX + columns * colW, startY + rows * CELL_HEIGHT, {
		steps: 20,
	})
	await page.mouse.up()
}

/**
 * Drag a widget's bottom-right resize handle over a number of columns and rows.
 * GridStack draws only that one handle (`resizable.handles: 'se'`), so it is
 * how a person changes the width as well as the height.
 *
 * @param page The page.
 * @param id The placement id.
 * @param columns Columns wider (negative: narrower).
 * @param rows Rows taller (negative: shorter).
 */
async function resizeBy(
	page: Page,
	id: number,
	columns: number,
	rows: number,
): Promise<void> {
	const colW = await columnWidth(page)
	const target = item(page, id)
	await target.hover()
	const handle = target.locator('.ui-resizable-se')
	await expect(handle).toBeVisible()
	const box = await handle.boundingBox()
	const startX = box!.x + box!.width / 2
	const startY = box!.y + box!.height / 2
	await page.mouse.move(startX, startY)
	await page.mouse.down()
	await page.mouse.move(startX + 4, startY + 4, { steps: 3 })
	await page.mouse.move(startX + columns * colW, startY + rows * CELL_HEIGHT, {
		steps: 20,
	})
	await page.mouse.up()
}

/**
 * Open the dashboard in edit mode.
 *
 * @param browser The browser.
 * @param count How many widgets it holds.
 * @return The context and page.
 */
async function openEditing(browser: Browser, count: number) {
	const session = await openOwnDashboard(browser, own!, count)
	await enterEditMode(session.page)
	return session
}

// @e2e grid-layout::drag-a-widget-to-a-new-position
test('a dragged widget snaps to its new cell and the widget in the way is pushed down', async ({
	browser,
}) => {
	// notes holds the drop target. GridStack pushes a widget aside only once
	// the dragged one covers more than half of it, and swaps two widgets of
	// one size, so notes is taller than weather and covered past half only at
	// the drop target: an equal-size notes a row lower was covered by exactly
	// half there, kept its place and blocked the drop at 3,1.
	const [weather, notes] = await seed([
		{ x: 0, y: 0, w: 4, h: 2 },
		{ x: 4, y: 2, w: 4, h: 3 },
	])
	const { context, page } = await openEditing(browser, 2)
	try {
		const saved = nextLayoutSave(page, own!.id)
		await dragBy(page, weather, 4, 2)
		expect((await saved).status()).toBe(200)

		const rendered = await renderedRects(page)
		expect(rendered.get(weather)).toEqual({ x: 4, y: 2, w: 4, h: 2 })
		// notes sat on the drop target: pushed down below it.
		expect(rendered.get(notes)!.y).toBeGreaterThanOrEqual(4)
		expect(anyOverlap([...rendered.values()])).toBe(false)

		const stored = await placementRects(own!.owner.api, own!.id)
		expect(stored.get(weather)).toEqual({ x: 4, y: 2, w: 4, h: 2 })
		expect(anyOverlap([...stored.values()])).toBe(false)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::drag-is-disabled-in-view-mode
test('in view mode a widget cannot be dragged and shows no drag affordance', async ({
	browser,
}) => {
	const [only] = await seed([{ x: 0, y: 0, w: 4, h: 2 }])
	const { context, page } = await openOwnDashboard(browser, own!, 1)
	try {
		await expect(page.locator('.launchpad-edit-mode')).toHaveCount(0)
		const content = item(page, only).locator('.grid-stack-item-content')
		await content.hover()
		const cursor = await content.evaluate((el) => getComputedStyle(el).cursor)
		expect(['move', 'grab', 'grabbing']).not.toContain(cursor)
		await expect(item(page, only)).not.toHaveClass(/ui-draggable-armed/)
		await expect(
			item(page, only).locator('.ui-resizable-handle:visible'),
		).toHaveCount(0)

		let saves = 0
		page.on('request', (req) => {
			if (
				req.method() === 'PUT'
				&& req.url().includes(`/api/dashboard/${own!.id}`)
			) {
				saves++
			}
		})
		await dragBy(page, only, 4, 2)
		await page.waitForTimeout(1_000)

		expect((await renderedRects(page)).get(only)).toEqual({
			x: 0,
			y: 0,
			w: 4,
			h: 2,
		})
		expect(saves).toBe(0)
		expect((await placementRects(own!.owner.api, own!.id)).get(only)).toEqual({
			x: 0,
			y: 0,
			w: 4,
			h: 2,
		})
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::drag-respects-grid-boundaries
test('a widget dragged past the right edge stops at the last column that fits it', async ({
	browser,
}) => {
	const [wide] = await seed([{ x: 0, y: 0, w: 4, h: 2 }])
	const { context, page } = await openEditing(browser, 1)
	try {
		const saved = nextLayoutSave(page, own!.id)
		await dragBy(page, wide, 10, 0)
		expect((await saved).status()).toBe(200)

		expect((await renderedRects(page)).get(wide)).toEqual({
			x: 8,
			y: 0,
			w: 4,
			h: 2,
		})
		expect((await placementRects(own!.owner.api, own!.id)).get(wide)).toEqual({
			x: 8,
			y: 0,
			w: 4,
			h: 2,
		})
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::drag-pushes-other-widgets
test('dragging a widget onto another pushes the other one down', async ({
	browser,
}) => {
	// b is taller than a for the reason given in the first drag test: an
	// equal-size b is pushed early and then covered by exactly half.
	const [a, b] = await seed([
		{ x: 0, y: 0, w: 4, h: 2 },
		{ x: 0, y: 2, w: 4, h: 3 },
	])
	const { context, page } = await openEditing(browser, 2)
	try {
		const saved = nextLayoutSave(page, own!.id)
		await dragBy(page, a, 0, 2)
		expect((await saved).status()).toBe(200)

		const rendered = await renderedRects(page)
		expect(rendered.get(a)).toEqual({ x: 0, y: 2, w: 4, h: 2 })
		expect(rendered.get(b)).toEqual({ x: 0, y: 4, w: 4, h: 3 })
		expect(anyOverlap([...rendered.values()])).toBe(false)

		const stored = await placementRects(own!.owner.api, own!.id)
		expect(stored.get(a)).toEqual({ x: 0, y: 2, w: 4, h: 2 })
		expect(stored.get(b)).toEqual({ x: 0, y: 4, w: 4, h: 3 })
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::drag-emits-position-update
// @e2e grid-layout::save-after-grid-change
// @e2e grid-layout::change-event-maps-grid-items-to-placements
test('one drag saves every placement, each matched by id with its new x, y, w and h', async ({
	browser,
}) => {
	const [a, b, c] = await seed([
		{ x: 0, y: 0, w: 4, h: 2 },
		{ x: 4, y: 0, w: 4, h: 3 },
		{ x: 8, y: 0, w: 4, h: 2 },
	])
	const { context, page } = await openEditing(browser, 3)
	try {
		const saved = nextLayoutSave(page, own!.id)
		await dragBy(page, a, 0, 4)
		const response = await saved
		expect(response.status()).toBe(200)

		// REQ-EDIT-002: the one Save carries every placement of the draft by id,
		// the moved one with its changed fields.
		const body = response.request().postDataJSON() as {
			draft: boolean
			placements: Array<Record<string, number>>
		}
		expect(body.draft).toBe(true)
		const sent = new Map(body.placements.map((p) => [Number(p.id), p]))
		expect([...sent.keys()].sort((l, r) => l - r)).toEqual(
			[a, b, c].sort((l, r) => l - r),
		)
		expect(sent.get(a)).toMatchObject({ gridY: 4 })

		// What the grid shows is what the server keeps.
		const rendered = await renderedRects(page)
		const stored = await placementRects(own!.owner.api, own!.id)
		expect(stored.get(a)).toEqual({ x: 0, y: 4, w: 4, h: 2 })
		expect(stored.get(b)).toEqual({ x: 4, y: 0, w: 4, h: 3 })
		expect(stored.get(c)).toEqual({ x: 8, y: 0, w: 4, h: 2 })
		for (const id of [a, b, c]) {
			expect(rendered.get(id)).toEqual(stored.get(id))
		}
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::multiple-rapid-changes
// REQ-EDIT-001/002: quick repositionings go into the draft, each counted, and
// the one Save stores the last position of every widget.
test('three quick repositionings are counted in the draft and stored by one Save', async ({ browser }) => {
	const ids = await seed([
		{ x: 0, y: 0, w: 2, h: 2 },
		{ x: 2, y: 0, w: 2, h: 2 },
		{ x: 4, y: 0, w: 2, h: 2 },
	])
	const { context, page } = await openEditing(browser, 3)
	try {
		const saves: Array<Array<Record<string, number>>> = []
		page.on('request', (req) => {
			if (
				req.method() === 'PUT'
				&& new RegExp(`/api/dashboard/${own!.id}(\\?|$)`).test(req.url())
			) {
				saves.push(
					(
						req.postDataJSON() as {
							placements: Array<Record<string, number>>
						}
					).placements,
				)
			}
		})
		for (const [index, id] of ids.entries()) {
			await dragBy(page, id, 0, 3 + index * 2)
			await expect(page.getByTestId('launchpad-edit-bar-draft')).toHaveText(
				index === 0 ? 'Draft · 1 change' : `Draft · ${index + 1} changes`,
			)
		}
		expect(saves).toHaveLength(0)

		expect((await nextLayoutSave(page, own!.id)).status()).toBe(200)
		expect(saves).toHaveLength(1)
		const last = new Map(saves[0].map((p) => [Number(p.id), p]))
		expect(last.get(ids[0])).toMatchObject({ gridY: 3 })
		expect(last.get(ids[1])).toMatchObject({ gridY: 5 })
		expect(last.get(ids[2])).toMatchObject({ gridY: 7 })
		await expect
			.poll(async () =>
				[...(await placementRects(own!.owner.api, own!.id)).values()]
					.map((r) => r.y)
					.sort(),
			)
			.toEqual([3, 5, 7])
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::concurrent-edits-from-multiple-tabs
test('two tabs each save their own move, and each Save keeps the other one', async ({
	browser,
}) => {
	const [a, b] = await seed([
		{ x: 0, y: 0, w: 4, h: 2 },
		{ x: 6, y: 0, w: 4, h: 2 },
	])
	const { context, page: first } = await openEditing(browser, 2)
	try {
		const second = await context.newPage()
		await second.setViewportSize({ width: 1600, height: 1000 })
		await second.goto('/index.php/apps/launchpad/')
		await expect(second.locator('.grid-stack > .grid-stack-item')).toHaveCount(
			2,
			{ timeout: 30_000 },
		)
		await enterEditMode(second)

		const firstSave = nextLayoutSave(first, own!.id)
		await dragBy(first, a, 0, 3)
		expect((await firstSave).status()).toBe(200)
		expect((await placementRects(own!.owner.api, own!.id)).get(a)).toEqual({
			x: 0,
			y: 3,
			w: 4,
			h: 2,
		})

		// The second tab still shows a at the top and now moves b.
		expect((await renderedRects(second)).get(a)).toEqual({
			x: 0,
			y: 0,
			w: 4,
			h: 2,
		})
		const secondSave = nextLayoutSave(second, own!.id)
		await dragBy(second, b, 0, 4)
		const response = await secondSave
		expect(response.status()).toBe(200)

		// Each Save stores only what its draft changed (REQ-EDIT-002, D4): the
		// first tab's move of a stays, the second tab's move of b is added.
		const stored = await placementRects(own!.owner.api, own!.id)
		expect(stored.get(a)).toEqual({ x: 0, y: 3, w: 4, h: 2 })
		expect(stored.get(b)).toEqual({ x: 6, y: 4, w: 4, h: 2 })
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::resize-a-widget-horizontally
test('widening a widget by two columns keeps its position and pushes the neighbour it reaches', async ({
	browser,
}) => {
	const [notes, neighbour] = await seed([
		{ x: 4, y: 0, w: 4, h: 3 },
		{ x: 8, y: 0, w: 4, h: 2 },
	])
	const { context, page } = await openEditing(browser, 2)
	try {
		const saved = nextLayoutSave(page, own!.id)
		await resizeBy(page, notes, 2, 0)
		expect((await saved).status()).toBe(200)

		const rendered = await renderedRects(page)
		expect(rendered.get(notes)).toEqual({ x: 4, y: 0, w: 6, h: 3 })
		expect(anyOverlap([...rendered.values()])).toBe(false)
		expect(rendered.get(neighbour)).not.toEqual({ x: 8, y: 0, w: 4, h: 2 })

		const stored = await placementRects(own!.owner.api, own!.id)
		expect(stored.get(notes)).toEqual({ x: 4, y: 0, w: 6, h: 3 })
		expect(anyOverlap([...stored.values()])).toBe(false)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::resize-a-widget-vertically
test('making a widget one row taller pushes the widget below it down', async ({
	browser,
}) => {
	const [weather, below] = await seed([
		{ x: 0, y: 0, w: 4, h: 2 },
		{ x: 0, y: 2, w: 4, h: 2 },
	])
	const { context, page } = await openEditing(browser, 2)
	try {
		const saved = nextLayoutSave(page, own!.id)
		await resizeBy(page, weather, 0, 1)
		expect((await saved).status()).toBe(200)

		const rendered = await renderedRects(page)
		expect(rendered.get(weather)).toEqual({ x: 0, y: 0, w: 4, h: 3 })
		expect(rendered.get(below)).toEqual({ x: 0, y: 3, w: 4, h: 2 })

		const stored = await placementRects(own!.owner.api, own!.id)
		expect(stored.get(weather)).toEqual({ x: 0, y: 0, w: 4, h: 3 })
		expect(stored.get(below)).toEqual({ x: 0, y: 3, w: 4, h: 2 })
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::resize-constrained-by-minimum-size
test('a widget cannot be resized below two columns by two rows', async ({
	browser,
}) => {
	const [shrinking] = await seed([{ x: 0, y: 0, w: 4, h: 3 }])
	const { context, page } = await openEditing(browser, 1)
	try {
		// GridStack reads gs-min-w / gs-min-h into the item's node and then
		// drops the attributes, so the node is where the minimum lives.
		const minimum = await item(page, shrinking).evaluate((el) => {
			const node = (el as HTMLElement & { gridstackNode?: { minW?: number, minH?: number } }).gridstackNode
			return { minW: node?.minW, minH: node?.minH }
		})
		expect(minimum).toEqual({ minW: 2, minH: 2 })

		const saved = nextLayoutSave(page, own!.id)
		await resizeBy(page, shrinking, -3, -2)
		expect((await saved).status()).toBe(200)

		expect((await renderedRects(page)).get(shrinking)).toEqual({
			x: 0,
			y: 0,
			w: 2,
			h: 2,
		})
		expect(
			(await placementRects(own!.owner.api, own!.id)).get(shrinking),
		).toEqual({ x: 0, y: 0, w: 2, h: 2 })
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::resize-constrained-by-grid-columns
test('a widget at the right edge cannot grow past the twelfth column', async ({
	browser,
}) => {
	const [edge] = await seed([{ x: 8, y: 0, w: 4, h: 2 }])
	const { context, page } = await openEditing(browser, 1)
	try {
		await resizeBy(page, edge, 2, 0)
		await page.waitForTimeout(1_000)

		const rendered = (await renderedRects(page)).get(edge)!
		expect(rendered.x + rendered.w).toBeLessThanOrEqual(12)
		// Either the width stays 4 at x=8, or GridStack moved it left to fit 6.
		expect([
			{ x: 8, y: 0, w: 4, h: 2 },
			{ x: 6, y: 0, w: 6, h: 2 },
		]).toContainEqual(rendered)
		const stored = (await placementRects(own!.owner.api, own!.id)).get(edge)!
		expect(stored).toEqual(rendered)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::resize-handles-not-visible-in-view-mode
test('in view mode hovering a widget edge shows no resize handle or resize cursor', async ({
	browser,
}) => {
	const [only] = await seed([{ x: 0, y: 0, w: 4, h: 2 }])
	const { context, page } = await openOwnDashboard(browser, own!, 1)
	try {
		const box = await item(page, only).boundingBox()
		const edgeX = box!.x + box!.width - 3
		const edgeY = box!.y + box!.height - 3
		await page.mouse.move(edgeX, edgeY)
		await expect(
			item(page, only).locator('.ui-resizable-handle:visible'),
		).toHaveCount(0)
		const cursor = await page.evaluate(
			([x, y]) => {
				const el = document.elementFromPoint(x, y)
				return el ? getComputedStyle(el).cursor : ''
			},
			[edgeX, edgeY],
		)
		expect(cursor).not.toMatch(/resize/)
	} finally {
		await context.close()
	}
})
