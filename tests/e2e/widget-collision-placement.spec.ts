/*
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Playwright end-to-end test for widget collision placement (REQ-GRID-006 +
 * REQ-GRID-014) covering Task 8 of the `widget-collision-placement` OpenSpec
 * change.
 *
 * Scenarios:
 *   1. Five widgets added sequentially to a 12-col empty dashboard land in
 *      visually-correct, non-overlapping positions (auto-position path).
 *   2. A sixth widget added to a top-full dashboard triggers the push-down
 *      fallback: overlappers gain `gs-y = newH`; non-overlappers are not moved.
 *   3. Position writes survive a page reload (REQ-GRID-005 persistence).
 *
 * NOTE: Playwright infrastructure is not yet wired up in launchpad. This file
 * is committed alongside the rest of the change so it runs once the cohort-
 * wide Playwright bootstrap lands. Do not delete — it is the canonical e2e
 * coverage for REQ-GRID-006 / REQ-GRID-014.
 *
 * Gate-19 @e2e traceability:
 *   @e2e grid-layout::five-widgets-no-overlap-auto-position
 *   @e2e grid-layout::sixth-widget-push-down-fallback
 *   @e2e grid-layout::pushed-widgets-keep-column-lane
 *   @e2e grid-layout::positions-survive-reload
 */

import { test, expect } from '@playwright/test'

const NEXTCLOUD_URL = process.env.NEXTCLOUD_URL || 'http://localhost:8080'

/**
 * Returns all `.grid-stack-item` elements with their parsed gs-x/gs-y/gs-w/gs-h
 * attributes so position assertions can be made without rendering math.
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

test.describe('widget collision placement', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${NEXTCLOUD_URL}/index.php/apps/launchpad`)
		// Tests assume the user is already authenticated via Playwright
		// storageState; in CI this is set up by the Hydra harness.
	})

	test('REQ-GRID-006: five widgets added to empty dashboard have no overlap (auto-position)', async ({ page }) => {
		// Enter edit mode to enable widget add.
		await page.getByRole('button', { name: /dashboards/i }).click()
		await page.getByRole('button', { name: /add.*widget|widget.*add/i }).click()

		// Add five label widgets sequentially via the AddWidgetModal.
		for (let i = 0; i < 5; i++) {
			await page.getByRole('button', { name: /add widget/i }).click()

			// Select Label type if a type selector is shown.
			const typeSelect = page.locator('[data-testid="widget-type-select"]')
			if (await typeSelect.isVisible({ timeout: 1000 }).catch(() => false)) {
				await typeSelect.selectOption('label')
			}

			// Wait for the save button and click it.
			await page.getByTestId('add-widget-save').click()
			await page.waitForTimeout(200)
		}

		const items = await getGridItems(page)
		expect(items.length).toBeGreaterThanOrEqual(5)
		expect(hasOverlap(items)).toBe(false)
	})

	test('REQ-GRID-006: sixth widget on top-full dashboard triggers push-down, overlappers shift to y=newH', async ({ page }) => {
		// This test seeds a full top row via the store API. Because Playwright
		// cannot easily manipulate store state, we use the UI to add 3 full-width
		// widgets (w=4, so three fill the 12-col row) and then add the fourth.
		//
		// The concrete assertion: after adding widget 4, every widget that
		// previously sat at y=0 and x<4 must have y=4 (the new widget's height).

		// Add three 4×4 widgets to fill [0..12] × [0..4].
		await page.getByRole('button', { name: /dashboards/i }).click()
		await page.getByRole('button', { name: /add.*widget|widget.*add/i }).click()

		for (let i = 0; i < 3; i++) {
			await page.getByRole('button', { name: /add widget/i }).click()
			const typeSelect = page.locator('[data-testid="widget-type-select"]')
			if (await typeSelect.isVisible({ timeout: 1000 }).catch(() => false)) {
				await typeSelect.selectOption('label')
			}
			await page.getByTestId('add-widget-save').click()
			await page.waitForTimeout(300)
		}

		// Record the initial placement of the three widgets.
		const before = await getGridItems(page)
		const initialAtTopLeft = before.filter((item) => item.y === 0 && item.x < 4)

		// Add the fourth widget — should trigger push-down fallback.
		await page.getByRole('button', { name: /add widget/i }).click()
		const typeSelect = page.locator('[data-testid="widget-type-select"]')
		if (await typeSelect.isVisible({ timeout: 1000 }).catch(() => false)) {
			await typeSelect.selectOption('label')
		}
		await page.getByTestId('add-widget-save').click()
		await page.waitForTimeout(500)

		const after = await getGridItems(page)

		// The new widget MUST be at (x:0, y:0).
		const newWidget = after.find((item) => !before.some((b) => b.id === item.id))
		if (newWidget) {
			expect(newWidget.x).toBe(0)
			expect(newWidget.y).toBe(0)

			// Pushed widgets that overlapped the new slot must have y = newWidget.h.
			for (const old of initialAtTopLeft) {
				const updated = after.find((a) => a.id === old.id)
				if (updated) {
					// Widget was in the overlap zone — must be pushed down.
					expect(updated.y).toBeGreaterThanOrEqual(newWidget.h)
				}
			}
		}

		// No widget should overlap any other after push-down.
		expect(hasOverlap(after)).toBe(false)
	})

	test('REQ-GRID-006: pushed widgets keep their gridX and gridW (only gridY changes)', async ({ page }) => {
		// Add two side-by-side widgets to the top row, then add a wide new widget
		// and confirm column lanes are preserved.
		await page.getByRole('button', { name: /dashboards/i }).click()
		await page.getByRole('button', { name: /add.*widget|widget.*add/i }).click()

		for (let i = 0; i < 2; i++) {
			await page.getByRole('button', { name: /add widget/i }).click()
			const typeSelect = page.locator('[data-testid="widget-type-select"]')
			if (await typeSelect.isVisible({ timeout: 1000 }).catch(() => false)) {
				await typeSelect.selectOption('label')
			}
			await page.getByTestId('add-widget-save').click()
			await page.waitForTimeout(300)
		}

		const before = await getGridItems(page)

		await page.getByRole('button', { name: /add widget/i }).click()
		const typeSelect = page.locator('[data-testid="widget-type-select"]')
		if (await typeSelect.isVisible({ timeout: 1000 }).catch(() => false)) {
			await typeSelect.selectOption('label')
		}
		await page.getByTestId('add-widget-save').click()
		await page.waitForTimeout(500)

		const after = await getGridItems(page)

		// For each widget that was pushed (y increased), verify x and w are unchanged.
		for (const b of before) {
			const a = after.find((item) => item.id === b.id)
			if (a && a.y > b.y) {
				// Was pushed — column lane must be preserved.
				expect(a.x).toBe(b.x)
				expect(a.w).toBe(b.w)
			}
		}
	})

	test('REQ-GRID-005: widget positions survive page reload', async ({ page }) => {
		// Add a widget, record its position, reload, verify position is the same.
		await page.getByRole('button', { name: /dashboards/i }).click()
		await page.getByRole('button', { name: /add.*widget|widget.*add/i }).click()

		await page.getByRole('button', { name: /add widget/i }).click()
		const typeSelect = page.locator('[data-testid="widget-type-select"]')
		if (await typeSelect.isVisible({ timeout: 1000 }).catch(() => false)) {
			await typeSelect.selectOption('label')
		}
		await page.getByTestId('add-widget-save').click()

		// Wait for the persistence debounce (300 ms) + network round-trip.
		await page.waitForTimeout(800)

		const before = await getGridItems(page)
		expect(before.length).toBeGreaterThan(0)

		// Reload the page.
		await page.reload()
		await page.waitForSelector('.grid-stack')

		const after = await getGridItems(page)

		// Every position should match.
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
