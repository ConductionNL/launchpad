/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The grid by keyboard and for assistive technology (grid-layout REQ-GRID-008,
 * REQ-GRID-KBD-001, REQ-GRID-KBD-002).
 *
 * A fresh throwaway account owns one dashboard with text widgets at exact
 * coordinates. The tests reach the widgets with Tab, open the widget menu with
 * Enter or Space, open "Move" and drive the move panel with the arrow keys,
 * then read the rendered `gs-*` attributes and the stored placements. No
 * pointer event is used once the page is in edit mode, except where a scenario
 * compares the keyboard menu with the right-click menu.
 *
 * keyboard-navigation-between-widgets and keyboard-widget-movement-in-edit-mode
 * (REQ-GRID-008) carry a never-built exclusion in the spec: view mode has no
 * tab stops and Ctrl+Arrow is no binding; keyboard movement shipped as
 * REQ-GRID-KBD-002, tested below. The accessible-name scenarios live in
 * grid-layout-accessible-name.spec.ts: they are expected red, and this file
 * runs serially, so a red there would skip every test after it here.
 *
 * Gate-19 @e2e traceability:
 *   @e2e grid-layout::grid-item-is-keyboard-focusable
 *   @e2e grid-layout::opening-the-move-panel-via-keyboard
 *   @e2e grid-layout::nudging-position-with-arrow-keys
 *   @e2e grid-layout::resizing-with-shiftarrow-keys
 *   @e2e grid-layout::confirming-and-cancelling
 *   @e2e grid-layout::collisions-are-resolved-the-same-way-as-drag
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

const MENU = '[data-testid="widget-context-menu"]'
const PANEL = '[data-test="widget-move-panel"]'
const READOUT = '[data-test="widget-move-readout"]'

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
	own = await setUpOwnDashboard('e2e-grid-kbd', `E2E grid keyboard ${Date.now()}`)
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
 * Open the dashboard in edit mode.
 *
 * @param browser The browser.
 * @param count How many widgets it holds.
 * @return The context and page.
 */
async function openEditing(browser: Browser, count: number) {
	const session = await openOwnDashboard(browser, own!, count)
	await enterEditMode(session.page)
	await expect(
		session.page.locator('.grid-stack > .grid-stack-item').first(),
	).toHaveAttribute('tabindex', '0')
	return session
}

/**
 * Focus a widget and open its move panel with the keyboard only.
 *
 * @param page The page.
 * @param id The placement id.
 * @return The panel.
 */
async function openMovePanel(page: Page, id: number): Promise<Locator> {
	await item(page, id).focus()
	await page.keyboard.press('Enter')
	await expect(page.locator(MENU)).toBeVisible()
	await page.locator('[data-testid="ctx-move"]').focus()
	await page.keyboard.press('Enter')
	const panel = page.locator(PANEL)
	await expect(panel).toBeVisible()
	await expect(panel).toBeFocused()
	return panel
}

/**
 * Count layout saves of the dashboard from now on.
 *
 * @param page The page.
 * @param dashboardId The dashboard.
 * @return A function returning the count so far.
 */
function countSaves(page: Page, dashboardId: number): () => number {
	let saves = 0
	page.on('request', (req) => {
		if (
			req.method() === 'PUT'
			&& new RegExp(`/api/dashboard/${dashboardId}(\\?|$)`).test(req.url())
		) {
			saves++
		}
	})
	return () => saves
}

// @e2e grid-layout::grid-item-is-keyboard-focusable
test('in edit mode Tab lands on each of three widgets in turn with a visible focus ring', async ({
	browser,
}) => {
	const ids = await seed([
		{ x: 0, y: 0, w: 4, h: 2 },
		{ x: 4, y: 0, w: 4, h: 2 },
		{ x: 8, y: 0, w: 4, h: 2 },
	])
	const { context, page } = await openEditing(browser, 3)
	try {
		for (const id of ids) {
			await expect(item(page, id)).toHaveAttribute('role', 'group')
			await expect(item(page, id)).toHaveAttribute('tabindex', '0')
		}
		// Start from the top of the document. A click on the container's corner
		// lands on the navigation toggle that overlaps it.
		await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur())

		const visited: number[] = []
		for (let press = 0; press < 120 && visited.length < ids.length; press++) {
			await page.keyboard.press('Tab')
			const focused = await page.evaluate(() => {
				const el = document.activeElement
				if (!el || !el.classList.contains('grid-stack-item')) {
					return null
				}
				const style = getComputedStyle(el)
				return {
					id: Number(el.getAttribute('gs-id')),
					outline: style.outlineStyle,
					width: style.outlineWidth,
				}
			})
			if (focused !== null && !visited.includes(focused.id)) {
				visited.push(focused.id)
				expect(focused.outline, `focus ring on ${focused.id}`).toBe('solid')
				expect(focused.width).toBe('2px')
			}
		}
		expect(visited).toEqual(ids)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::opening-the-move-panel-via-keyboard
test('Enter or Space on a focused widget opens the right-click menu, which offers Move', async ({
	browser,
}) => {
	const [only] = await seed([{ x: 0, y: 0, w: 4, h: 2 }])
	const { context, page } = await openEditing(browser, 1)
	try {
		const menu = page.locator(MENU)
		const entries = menu.getByRole('menuitem')

		// The right-click menu, for comparison.
		await item(page, only)
			.locator('.launchpad-grid-item')
			.click({ button: 'right' })
		await expect(menu).toBeVisible()
		const byMouse = await entries.allTextContents()
		await page.locator('[data-testid="ctx-cancel"]').click()
		await expect(menu).toHaveCount(0)

		for (const key of ['Enter', ' ']) {
			await item(page, only).focus()
			await page.keyboard.press(key)
			await expect(menu).toBeVisible()
			expect(await entries.allTextContents()).toEqual(byMouse)
			await expect(
				menu.getByRole('menuitem', { name: 'Move', exact: true }),
			).toBeVisible()
			await page.locator('[data-testid="ctx-cancel"]').click()
			await expect(menu).toHaveCount(0)
		}
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::nudging-position-with-arrow-keys
test('ArrowRight in the move panel makes the pending position x=3, y=3 and saves nothing yet', async ({
	browser,
}) => {
	const [only] = await seed([{ x: 2, y: 3, w: 3, h: 2 }])
	const { context, page } = await openEditing(browser, 1)
	try {
		const saves = countSaves(page, own!.id)
		const panel = await openMovePanel(page, only)
		await expect(panel.locator(READOUT)).toHaveText(
			'Column 3, row 4, 3 wide by 2 tall',
		)

		await page.keyboard.press('ArrowRight')
		await expect(panel.locator(READOUT)).toHaveText(
			'Column 4, row 4, 3 wide by 2 tall',
		)

		await page.waitForTimeout(1_000)
		expect(saves()).toBe(0)
		expect((await renderedRects(page)).get(only)).toEqual({
			x: 2,
			y: 3,
			w: 3,
			h: 2,
		})
		expect((await placementRects(own!.owner.api, own!.id)).get(only)).toEqual({
			x: 2,
			y: 3,
			w: 3,
			h: 2,
		})
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::resizing-with-shiftarrow-keys
test('Shift+ArrowRight widens the pending size to 4x2 and Shift+arrows never go below 2x2', async ({
	browser,
}) => {
	const [only] = await seed([{ x: 0, y: 0, w: 3, h: 2 }])
	const { context, page } = await openEditing(browser, 1)
	try {
		const panel = await openMovePanel(page, only)
		await page.keyboard.press('Shift+ArrowRight')
		await expect(panel.locator(READOUT)).toHaveText(
			'Column 1, row 1, 4 wide by 2 tall',
		)

		for (let i = 0; i < 4; i++) {
			await page.keyboard.press('Shift+ArrowLeft')
			await page.keyboard.press('Shift+ArrowUp')
		}
		await expect(panel.locator(READOUT)).toHaveText(
			'Column 1, row 1, 2 wide by 2 tall',
		)
		expect((await placementRects(own!.owner.api, own!.id)).get(only)).toEqual({
			x: 0,
			y: 0,
			w: 3,
			h: 2,
		})
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::confirming-and-cancelling
test('Enter puts the pending change in the draft that Save stores, Escape closes without saving', async ({
	browser,
}) => {
	const [only] = await seed([{ x: 2, y: 3, w: 3, h: 2 }])
	const { context, page } = await openEditing(browser, 1)
	try {
		// Enter: the same `PUT /api/dashboard/{id}` a drag sends.
		const panel = await openMovePanel(page, only)
		await page.keyboard.press('ArrowRight')
		await page.keyboard.press('Shift+ArrowDown')
		const saved = nextLayoutSave(page, own!.id)
		await page.keyboard.press('Enter')
		const response = await saved
		expect(response.status()).toBe(200)
		const sent = (
			response.request().postDataJSON() as {
				placements: Array<Record<string, number>>
			}
		).placements
		// REQ-EDIT-002: the draft Save carries the fields the move changed.
		expect(sent.find((p) => Number(p.id) === only)).toMatchObject({
			gridX: 3,
			gridHeight: 3,
		})
		await expect(panel).toHaveCount(0)
		await expect
			.poll(async () => (await renderedRects(page)).get(only))
			.toEqual({ x: 3, y: 3, w: 3, h: 3 })
		expect((await placementRects(own!.owner.api, own!.id)).get(only)).toEqual({
			x: 3,
			y: 3,
			w: 3,
			h: 3,
		})

		// Escape: nothing is saved and the panel closes. Save ended edit
		// mode, so it is entered again first.
		await enterEditMode(page)
		const saves = countSaves(page, own!.id)
		await openMovePanel(page, only)
		await page.keyboard.press('ArrowLeft')
		await page.keyboard.press('Shift+ArrowRight')
		await page.keyboard.press('Escape')
		await expect(page.locator(PANEL)).toHaveCount(0)
		await page.waitForTimeout(1_000)
		expect(saves()).toBe(0)
		expect((await renderedRects(page)).get(only)).toEqual({
			x: 3,
			y: 3,
			w: 3,
			h: 3,
		})
		expect((await placementRects(own!.owner.api, own!.id)).get(only)).toEqual({
			x: 3,
			y: 3,
			w: 3,
			h: 3,
		})
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::collisions-are-resolved-the-same-way-as-drag
test('a confirmed keyboard move onto a neighbour pushes the neighbour down, leaving no overlap', async ({
	browser,
}) => {
	const [moved, neighbour] = await seed([
		{ x: 0, y: 0, w: 4, h: 2 },
		{ x: 4, y: 0, w: 4, h: 2 },
	])
	const { context, page } = await openEditing(browser, 2)
	try {
		await openMovePanel(page, moved)
		await page.keyboard.press('ArrowRight')
		const saved = nextLayoutSave(page, own!.id)
		await page.keyboard.press('Enter')
		expect((await saved).status()).toBe(200)

		const stored = await placementRects(own!.owner.api, own!.id)
		expect(stored.get(moved)).toEqual({ x: 1, y: 0, w: 4, h: 2 })
		expect(stored.get(neighbour)).toEqual({ x: 4, y: 2, w: 4, h: 2 })
		expect(anyOverlap([...stored.values()])).toBe(false)
		await expect
			.poll(async () => anyOverlap([...(await renderedRects(page)).values()]))
			.toBe(false)
		expect((await renderedRects(page)).get(moved)).toEqual({
			x: 1,
			y: 0,
			w: 4,
			h: 2,
		})
	} finally {
		await context.close()
	}
})
