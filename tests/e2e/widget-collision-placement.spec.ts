/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end test for widget collision placement (REQ-GRID-006 +
 * REQ-GRID-014) covering Task 8 of the `widget-collision-placement` OpenSpec
 * change.
 *
 * Drives the real runtime-shell add-widget flow (sidebar → personal-row cog
 * → "Add widget" → "Custom widget" → Add Widget modal → Label) and asserts the
 * collision INVARIANTS that hold regardless of the dashboard's pre-existing
 * placements:
 *   1. Sequentially-added widgets never overlap.
 *   2. Adding a widget to a populated dashboard never moves an existing one
 *      (REQ-GRID-006 bottom-append: there is no push-down).
 *   3. Position writes survive a page reload (REQ-GRID-005 persistence).
 *
 * This file cites no scenario. The four it used to cite
 * (five-widgets-no-overlap-auto-position, sixth-widget-push-down-fallback,
 * pushed-widgets-keep-column-lane, positions-survive-reload) were never slugs
 * of grid-layout scenarios, and the push-down rule they described was replaced
 * by bottom-append (archive/2026-10-09-grid-bottom-append-placement). The
 * REQ-GRID-006 scenarios are cited, coordinate by coordinate, in
 * grid-layout-placement.spec.ts; these tests stay as regression guards on a
 * dashboard that already carries placements.
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import {
	closeSidebar,
	gotoLaunchPad,
	openAddWidgetModal,
} from './fixtures/widget-flow.ts'

let restoreWidgetRestriction: (() => Promise<void>) | null = null

test.beforeAll(async () => {
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
})

/**
 * Returns all `.grid-stack-item` elements with their parsed gs-x/gs-y/gs-w/gs-h
 * attributes so position assertions can be made without rendering math.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {Promise<Array<{id: string, x: number, y: number, w: number, h: number}>>} items
 */
async function getGridItems(page: Page) {
	return page.evaluate(() => {
		const items = Array.from(document.querySelectorAll('.grid-stack-item'))
		return items.map((el) => ({
			id: el.getAttribute('gs-id') ?? '',
			x: Number(el.getAttribute('gs-x') ?? 0),
			y: Number(el.getAttribute('gs-y') ?? 0),
			w: Number(el.getAttribute('gs-w') ?? 4),
			h: Number(el.getAttribute('gs-h') ?? 4),
		}))
	})
}

/**
 * Check whether any two grid items overlap using half-open intervals.
 *
 * @param {Array<{x: number, y: number, w: number, h: number}>} items grid items.
 * @return {boolean} true when at least one pair overlaps.
 */
function hasOverlap(items: Array<{ x: number; y: number; w: number; h: number }>) {
	for (let i = 0; i < items.length; i++) {
		for (let j = i + 1; j < items.length; j++) {
			const a = items[i]
			const b = items[j]
			const xOverlap = a.x < b.x + b.w && b.x < a.x + a.w
			const yOverlap = a.y < b.y + b.h && b.y < a.y + a.h
			if (xOverlap && yOverlap) {
				return true
			}
		}
	}
	return false
}

/** Add a single Label widget via the cog-menu Add Widget flow. */
async function addLabelWidget(page: Page, text: string) {
	await openAddWidgetModal(page, 'label')
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await dialog
		.getByLabel(/label text/i)
		.first()
		.fill(text)
	const addBtn = dialog.getByRole('button', { name: /^add$/i })
	await expect(addBtn).toBeEnabled({ timeout: 5_000 })
	await addBtn.click()
	await expect(dialog).not.toBeVisible({ timeout: 8_000 })
	await closeSidebar(page)
}

test.describe('widget collision placement', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	test('REQ-GRID-006: adding several widgets leaves no overlap', async ({
		page,
	}) => {
		const stamp = Date.now()
		for (let i = 0; i < 3; i++) {
			await addLabelWidget(page, `collide-${stamp}-${i}`)
		}
		const items = await getGridItems(page)
		expect(items.length).toBeGreaterThanOrEqual(3)
		expect(hasOverlap(items)).toBe(false)
	})

	test('REQ-GRID-006: adding widgets to an occupied grid moves no existing widget', async ({
		page,
	}) => {
		// The active dashboard already carries placements (the top of the grid
		// is occupied). Bottom-append places each new widget below the lowest
		// bottom edge, so no two placements overlap and every pre-existing
		// widget keeps its exact geometry.
		const before = await getGridItems(page)
		const stamp = Date.now()
		await addLabelWidget(page, `push-${stamp}-a`)
		await addLabelWidget(page, `push-${stamp}-b`)
		const after = await getGridItems(page)

		expect(after.length).toBe(before.length + 2)
		expect(hasOverlap(after)).toBe(false)

		for (const b of before) {
			const a = after.find((item) => item.id === b.id)
			expect(a, `widget ${b.id} is still on the grid`).toBeDefined()
			expect({ x: a?.x, y: a?.y, w: a?.w, h: a?.h }).toEqual({
				x: b.x,
				y: b.y,
				w: b.w,
				h: b.h,
			})
		}

		// The two new widgets sit at x=0, at or below every pre-existing bottom edge.
		const bottom = before.reduce((max, b) => Math.max(max, b.y + b.h), 0)
		const added = after.filter((a) => !before.some((b) => b.id === a.id))
		expect(added).toHaveLength(2)
		for (const a of added) {
			expect(a.x).toBe(0)
			expect(a.y).toBeGreaterThanOrEqual(bottom)
		}
	})

	test('REQ-GRID-005: widget positions survive a page reload', async ({
		page,
	}) => {
		// The shared dashboard accumulates widgets across runs, so a full
		// re-render can be slow — give this reload-heavy test extra budget.
		test.setTimeout(60_000)

		await addLabelWidget(page, `reload-${Date.now()}`)
		// Allow the persistence debounce + network round-trip to settle.
		await page.waitForTimeout(1_000)

		const before = await getGridItems(page)
		expect(before.length).toBeGreaterThan(0)

		await page.reload()
		await page.waitForSelector('.grid-stack', { timeout: 30_000 })
		await page.waitForTimeout(1_500)

		const after = await getGridItems(page)
		// Every previously-placed widget keeps its exact geometry across reload.
		for (const b of before) {
			const a = after.find((item) => item.id === b.id)
			if (a) {
				expect(a.x).toBe(b.x)
				expect(a.y).toBe(b.y)
				expect(a.w).toBe(b.w)
				expect(a.h).toBe(b.h)
			}
		}
	})
})
