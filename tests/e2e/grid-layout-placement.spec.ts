/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Where a new widget lands, and how the grid follows widgets that are added or
 * removed while the page is open (grid-layout REQ-GRID-006, REQ-GRID-011).
 *
 * Each test gives a fresh throwaway account one dashboard with text widgets at
 * exact coordinates (seeded through the API), then adds a Label through the
 * product's own "Add widget" → "Custom widget" flow, or removes one through the widget
 * menu. The add flow sends no size, so the new widget takes the 4x4 default.
 * The test reads the created placement from the server's answer, the rendered
 * `gs-*` attributes, every save the page sends, and the stored layout.
 *
 * Gate-19 @e2e traceability:
 *   @e2e grid-layout::append-below-the-only-existing-widget
 *   @e2e grid-layout::append-below-the-lowest-widget-regardless-of-array-order
 *   @e2e grid-layout::first-widget-on-an-empty-dashboard
 *   @e2e grid-layout::existing-widgets-are-never-pushed-when-the-top-region-is-full
 *   @e2e grid-layout::default-size-on-omitted-dimensions
 *   @e2e grid-layout::persistence-after-placement
 *   @e2e grid-layout::add-widget-to-partially-filled-grid-incremental-render
 *   @e2e grid-layout::remove-widget-syncs-grid
 *   @e2e grid-layout::new-placement-added-to-props
 *   @e2e grid-layout::placement-removed-from-props
 *
 * @spec openspec/specs/grid-layout/spec.md
 */

import type { Page, Request } from '@playwright/test'
import type { OwnDashboard, Rect } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
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
import { chooseAndAdd } from './support/widgetPicker.ts'

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
	own = await setUpOwnDashboard(
		'e2e-grid-place',
		`E2E grid placement ${Date.now()}`,
	)
	const ids: number[] = []
	for (const [index, rect] of rects.entries()) {
		ids.push(
			await seedTextWidget(own.owner.api, own.id, rect, `Widget ${index + 1}`),
		)
	}
	return ids
}

/**
 * Every write the page sends to the dashboard's layout or a placement.
 *
 * @param page The page.
 * @return The recorded requests.
 */
function recordWrites(page: Page): Request[] {
	const writes: Request[] = []
	page.on('request', (req) => {
		if (
			['PUT', 'PATCH'].includes(req.method())
			&& /\/api\/(dashboard\/\d+|widgets\/\d+)(\?|$)/.test(req.url())
		) {
			writes.push(req)
		}
	})
	return writes
}

/**
 * Add a Label through More menu → "Add widget" → "Custom widget" and return the
 * placement the server created.
 *
 * @param page The page.
 * @param text The label text.
 * @return The created placement's id and rectangle.
 */
async function addLabel(
	page: Page,
	text: string,
): Promise<{ id: number; rect: Rect }> {
	await page
		.locator(MORE_MENU)
		.locator('[data-testid="launchpad-more-menu-trigger"]')
		.click()
	await page
		.locator('[data-testid="launchpad-more-menu-item-add-widget"]')
		.click()
	await chooseAndAdd(page.locator('[data-testid="widget-picker-type-label"]'))
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await dialog
		.getByLabel(/label text/i)
		.first()
		.fill(text)

	// REQ-EDIT-001/002: the new widget joins the draft; the edit-mode Save
	// creates it, in the one dashboard request.
	await dialog.getByRole('button', { name: /^add$/i }).click()
	await expect(dialog).toBeHidden({ timeout: 10_000 })
	const created = page.waitForResponse(
		(res) =>
			res.request().method() === 'PUT'
			&& /\/api\/dashboard\/\d+(\?|$)/.test(res.url())
			&& res.request().postDataJSON()?.draft === true,
	)
	await page.getByTestId('launchpad-edit-bar-save').click()
	const response = await created
	expect(response.status(), await response.text()).toBe(200)
	const sent = (response.request().postDataJSON() as { placements: Array<Record<string, unknown>> })
		.placements.find((p) => p.id === undefined)!
	expect(sent.gridWidth ?? sent.w, 'the add flow sends the default width').toBe(4)
	expect(sent.gridHeight ?? sent.h, 'the add flow sends the default height').toBe(
		4,
	)
	const body = await response.json()
	const stored = ((body.data ?? body).placements ?? []) as Array<Record<string, unknown>>
	const placement = stored.find((p) => (p.content as { text?: string } | null)?.text === text
		|| JSON.stringify(p.content ?? '').includes(text))!
	return {
		id: Number(placement.id),
		rect: {
			x: Number(placement.gridX),
			y: Number(placement.gridY),
			w: Number(placement.gridWidth),
			h: Number(placement.gridHeight),
		},
	}
}

/**
 * Assert that no write the page sent moved an existing widget.
 *
 * @param writes The recorded writes.
 * @param before The existing widgets' rectangles.
 */
function assertNothingMoved(writes: Request[], before: Map<number, Rect>): void {
	for (const req of writes) {
		const url = req.url()
		const widgetWrite = url.match(/\/api\/widgets\/(\d+)/)
		if (widgetWrite) {
			expect(
				before.has(Number(widgetWrite[1])),
				`an existing widget was written: ${url}`,
			).toBe(false)
			continue
		}
		const body = req.postDataJSON() as {
			placements?: Array<Record<string, number>>
		} | null
		for (const p of body?.placements ?? []) {
			const old = before.get(Number(p.id))
			if (old) {
				// A draft Save sends only the fields that changed (REQ-EDIT-002).
				const moved = { x: p.gridX ?? old.x, y: p.gridY ?? old.y, w: p.gridWidth ?? old.w, h: p.gridHeight ?? old.h }
				expect(moved).toEqual(old)
			}
		}
	}
}

// @e2e grid-layout::append-below-the-only-existing-widget
// @e2e grid-layout::persistence-after-placement
test('a new widget goes in a fresh row under the only widget, which is not moved or rewritten', async ({
	browser,
}) => {
	const [existing] = await seed([{ x: 0, y: 0, w: 6, h: 4 }])
	const { context, page } = await openOwnDashboard(browser, own!, 1)
	try {
		const writes = recordWrites(page)
		const added = await addLabel(page, `Below ${Date.now()}`)
		expect(added.rect).toEqual({ x: 0, y: 4, w: 4, h: 4 })

		await expect(page.locator('.grid-stack > .grid-stack-item')).toHaveCount(2)
		await expect
			.poll(async () => (await renderedRects(page)).get(added.id))
			.toEqual({ x: 0, y: 4, w: 4, h: 4 })
		expect((await renderedRects(page)).get(existing)).toEqual({
			x: 0,
			y: 0,
			w: 6,
			h: 4,
		})

		await page.waitForTimeout(1_000)
		assertNothingMoved(writes, new Map([[existing, { x: 0, y: 0, w: 6, h: 4 }]]))

		// Persisted: the server holds the new widget where it was placed.
		const stored = await placementRects(own!.owner.api, own!.id)
		expect(stored.get(existing)).toEqual({ x: 0, y: 0, w: 6, h: 4 })
		expect(stored.get(added.id)).toEqual({ x: 0, y: 4, w: 4, h: 4 })

		// And it is still there after a reload.
		await page.reload()
		await expect(page.locator('.grid-stack > .grid-stack-item')).toHaveCount(2, {
			timeout: 30_000,
		})
		expect((await renderedRects(page)).get(added.id)).toEqual({
			x: 0,
			y: 4,
			w: 4,
			h: 4,
		})
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::append-below-the-lowest-widget-regardless-of-array-order
test('the new widget goes under the lowest bottom edge, not under the last widget in the list', async ({
	browser,
}) => {
	// The lower widget is created first, so it comes first in the placements.
	const [lower, upper] = await seed([
		{ x: 0, y: 5, w: 6, h: 3 },
		{ x: 6, y: 0, w: 6, h: 2 },
	])
	const { context, page } = await openOwnDashboard(browser, own!, 2)
	try {
		const writes = recordWrites(page)
		const added = await addLabel(page, `Lowest ${Date.now()}`)
		expect({ x: added.rect.x, y: added.rect.y }).toEqual({ x: 0, y: 8 })

		await page.waitForTimeout(1_000)
		const before = new Map([
			[lower, { x: 0, y: 5, w: 6, h: 3 }],
			[upper, { x: 6, y: 0, w: 6, h: 2 }],
		])
		assertNothingMoved(writes, before)
		const stored = await placementRects(own!.owner.api, own!.id)
		expect(stored.get(lower)).toEqual(before.get(lower))
		expect(stored.get(upper)).toEqual(before.get(upper))
		expect(stored.get(added.id)).toMatchObject({ x: 0, y: 8 })
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::first-widget-on-an-empty-dashboard
// @e2e grid-layout::default-size-on-omitted-dimensions
test('on an empty dashboard the first widget takes the 4x4 default at the top-left', async ({
	browser,
}) => {
	await seed([])
	const session = await openOwnDashboard(browser, own!, 0)
	const { context, page } = session
	try {
		const added = await addLabel(page, `First ${Date.now()}`)
		expect(added.rect).toEqual({ x: 0, y: 0, w: 4, h: 4 })
		await expect
			.poll(async () => (await renderedRects(page)).get(added.id))
			.toEqual({ x: 0, y: 0, w: 4, h: 4 })
		expect(
			(await placementRects(own!.owner.api, own!.id)).get(added.id),
		).toEqual({ x: 0, y: 0, w: 4, h: 4 })
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::existing-widgets-are-never-pushed-when-the-top-region-is-full
test('with the top four rows full the new widget goes below them and nothing is pushed', async ({
	browser,
}) => {
	const rects: Rect[] = [
		{ x: 0, y: 0, w: 4, h: 4 },
		{ x: 4, y: 0, w: 4, h: 4 },
		{ x: 8, y: 0, w: 4, h: 4 },
	]
	const ids = await seed(rects)
	const { context, page } = await openOwnDashboard(browser, own!, 3)
	try {
		const writes = recordWrites(page)
		const added = await addLabel(page, `Full ${Date.now()}`)
		expect(added.rect).toEqual({ x: 0, y: 4, w: 4, h: 4 })

		await page.waitForTimeout(1_000)
		const before = new Map(ids.map((id, index) => [id, rects[index]]))
		assertNothingMoved(writes, before)
		const rendered = await renderedRects(page)
		const stored = await placementRects(own!.owner.api, own!.id)
		for (const [id, rect] of before) {
			expect(rendered.get(id)).toEqual(rect)
			expect(stored.get(id)).toEqual(rect)
		}
		expect(anyOverlap([...stored.values()])).toBe(false)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::add-widget-to-partially-filled-grid-incremental-render
// @e2e grid-layout::new-placement-added-to-props
test('an added widget appears on the open grid as a GridStack item, without a reload', async ({
	browser,
}) => {
	await seed([
		{ x: 0, y: 0, w: 4, h: 3 },
		{ x: 4, y: 0, w: 4, h: 3 },
	])
	const { context, page } = await openOwnDashboard(browser, own!, 2)
	try {
		let navigations = 0
		page.on('framenavigated', (frame) => {
			if (frame === page.mainFrame()) {
				navigations++
			}
		})
		const added = await addLabel(page, `Incremental ${Date.now()}`)
		expect(added.rect).toEqual({ x: 0, y: 3, w: 4, h: 4 })

		const node = page.locator(
			`.grid-stack > .grid-stack-item[gs-id="${added.id}"]`,
		)
		await expect(node).toHaveCount(1)
		await expect(node).toHaveAttribute('gs-x', '0')
		await expect(node).toHaveAttribute('gs-y', '3')
		// GridStack took the element on: it carries an engine node.
		await expect
			.poll(() =>
				node.evaluate((el) =>
					Boolean(
						(el as HTMLElement & { gridstackNode?: unknown })
							.gridstackNode,
					),
				),
			)
			.toBe(true)
		expect(
			await page.evaluate(() => {
				const grid = (
					document.querySelector('.grid-stack') as HTMLElement & {
						gridstack: { engine: { nodes: unknown[] } }
					}
				).gridstack
				return grid.engine.nodes.length
			}),
		).toBe(3)
		expect(navigations).toBe(0)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::remove-widget-syncs-grid
// @e2e grid-layout::placement-removed-from-props
test('a widget removed from the widget menu leaves the grid and the rest stays put', async ({
	browser,
}) => {
	const [removed, kept] = await seed([
		{ x: 0, y: 0, w: 4, h: 2 },
		{ x: 4, y: 0, w: 4, h: 2 },
	])
	const { context, page } = await openOwnDashboard(browser, own!, 2)
	try {
		await page
			.locator(MORE_MENU)
			.locator('[data-testid="launchpad-more-menu-trigger"]')
			.click()
		await page.locator('[data-testid="launchpad-more-menu-item-edit"]').click()
		await expect(page.locator('.launchpad-edit-mode')).toBeVisible()

		await page
			.locator(
				`.grid-stack > .grid-stack-item[gs-id="${removed}"] .launchpad-grid-item`,
			)
			.click({ button: 'right' })
		// REQ-EDIT-001: the removal is in the draft; nothing is deleted yet.
		const writes = recordWrites(page)
		await page.locator('[data-testid="ctx-remove"]').click()
		await expect(page.getByTestId('launchpad-edit-bar-draft')).toHaveText('Draft · 1 change')
		expect(writes).toHaveLength(0)

		await expect(
			page.locator(`.grid-stack > .grid-stack-item[gs-id="${removed}"]`),
		).toHaveCount(0)
		await expect
			.poll(() =>
				page.evaluate(() => {
					const grid = (
						document.querySelector('.grid-stack') as HTMLElement & {
							gridstack: { engine: { nodes: Array<{ id?: unknown }> } }
						}
					).gridstack
					return grid.engine.nodes.map((n) => String(n.id))
				}),
			)
			.toEqual([String(kept)])
		expect((await renderedRects(page)).get(kept)).toEqual({
			x: 4,
			y: 0,
			w: 4,
			h: 2,
		})

		// Save stores the removal (REQ-EDIT-002).
		expect((await nextLayoutSave(page, own!.id)).status()).toBe(200)
		const stored = await placementRects(own!.owner.api, own!.id)
		expect([...stored.keys()]).toEqual([kept])
	} finally {
		await context.close()
	}
})
