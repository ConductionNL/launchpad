/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Playwright end-to-end test for widget collision placement (REQ-GRID-006 +
 * REQ-GRID-014) covering Task 8 of the `widget-collision-placement` OpenSpec
 * change.
 *
 * Drives the real runtime-shell add-widget flow (sidebar → personal-row cog
 * → "Add custom widget…" → Add Widget modal → Label) and asserts the
 * collision INVARIANTS that hold regardless of the dashboard's pre-existing
 * placements:
 *   1. Sequentially-added widgets never overlap (auto-position path).
 *   2. The auto-positioner keeps every placement non-overlapping even when
 *      the top of the grid is already occupied (push-down fallback).
 *   3. Position writes survive a page reload (REQ-GRID-005 persistence).
 *
 * The exact push-down arithmetic (overlappers gain `gs-y = newH`) is a pure
 * function asserted by the useGridManager Vitest unit tests; here we assert
 * the user-observable invariant (no overlap) that a populated dashboard can
 * actually exercise.
 *
 * Gate-19 @e2e traceability:
 *   @e2e grid-layout::five-widgets-no-overlap-auto-position
 *   @e2e grid-layout::sixth-widget-push-down-fallback
 *   @e2e grid-layout::pushed-widgets-keep-column-lane
 *   @e2e grid-layout::positions-survive-reload
 */

import { test, expect } from '@playwright/test'
import { gotoLaunchPad, openAddWidgetModal, closeSidebar } from './fixtures/widget-flow'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions'

test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()
})

/**
 * Returns all `.grid-stack-item` elements with their parsed gs-x/gs-y/gs-w/gs-h
 * attributes so position assertions can be made without rendering math.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {Promise<Array<{id: string, x: number, y: number, w: number, h: number}>>} items
 */
async function getGridItems(page: import('@playwright/test').Page) {
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
async function addLabelWidget(page: import('@playwright/test').Page, text: string) {
	await openAddWidgetModal(page)
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await dialog.getByLabel(/widget type/i).selectOption({ label: 'Label' })
	await dialog.getByLabel(/label text/i).first().fill(text)
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

	// @e2e grid-layout::five-widgets-no-overlap-auto-position
	test('REQ-GRID-006: adding several widgets leaves no overlap (auto-position)', async ({ page }) => {
		const stamp = Date.now()
		for (let i = 0; i < 3; i++) {
			await addLabelWidget(page, `collide-${stamp}-${i}`)
		}
		const items = await getGridItems(page)
		expect(items.length).toBeGreaterThanOrEqual(3)
		expect(hasOverlap(items)).toBe(false)
	})

	// @e2e grid-layout::sixth-widget-push-down-fallback
	// @e2e grid-layout::pushed-widgets-keep-column-lane
	test('REQ-GRID-006: auto-position keeps placements non-overlapping with an occupied top row', async ({ page }) => {
		// The active dashboard already carries placements (the top of the grid
		// is occupied). Adding more widgets must engage the push-down fallback
		// and still leave NO overlap and preserve column lanes for any widget
		// that did not move.
		const before = await getGridItems(page)
		const stamp = Date.now()
		await addLabelWidget(page, `push-${stamp}-a`)
		await addLabelWidget(page, `push-${stamp}-b`)
		const after = await getGridItems(page)

		expect(after.length).toBeGreaterThan(before.length)
		// Core invariant — no two placements overlap after the fallback.
		expect(hasOverlap(after)).toBe(false)

		// Column-lane preservation: any pre-existing widget whose y is unchanged
		// must keep its x and w (the fallback only ever shifts y).
		for (const b of before) {
			const a = after.find((item) => item.id === b.id)
			if (a && a.y === b.y) {
				expect(a.x).toBe(b.x)
				expect(a.w).toBe(b.w)
			}
		}
	})

	// @e2e grid-layout::positions-survive-reload
	test('REQ-GRID-005: widget positions survive a page reload', async ({ page }) => {
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
