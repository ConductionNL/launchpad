/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the widget right-click context menu
 * (REQ-WDG-015..017, widget-context-menu OpenSpec change, issue #36).
 *
 * Scenarios covered (Task 11):
 *   - REQ-WDG-017: popover stays fully on-screen when right-clicking near
 *     the right viewport edge (popover clamped left so it fits).
 *   - REQ-WDG-017: popover stays fully on-screen when right-clicking near
 *     the bottom viewport edge (popover clamped up so it fits).
 *   - REQ-WDG-015 + persistence: removing a widget through the context-menu
 *     Remove button calls the placement-delete path and the widget does not
 *     reappear after a full page reload.
 *
 * NOTE: These tests require a running Nextcloud instance with the launchpad app
 * installed and at least one widget placement on the active dashboard. The
 * test harness must supply authenticated storage state via the global setup
 * at `tests/e2e/global-setup.ts`. In CI, the Hydra pipeline wires this up
 * automatically; when running locally use `npm run test:e2e` after
 * starting the dev stack.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widget-context-menu::popover-clamped-at-right-viewport-edge
 *   @e2e widget-context-menu::popover-clamped-at-bottom-viewport-edge
 *   @e2e widget-context-menu::remove-persists-across-reload
 */

import type { Page } from '@playwright/test'
import type { Locator } from '@playwright/test'

import { expect, test } from '@playwright/test'

/**
 * Wait for a locator's `boundingBox()` to stop changing before reading it.
 *
 * GridStack ships `.grid-stack-animate` (see
 * `node_modules/gridstack/dist/gridstack.css`), which puts a 300ms CSS
 * transition on `left/right/top/height/width` for every `.grid-stack-item`
 * whenever the column layout reflows — which `page.setViewportSize()`
 * triggers (REQ-GRID-007 responsive breakpoints, `moveScale` reflow). A
 * `boundingBox()` read straight after a resize can land mid-transition, so
 * the coordinates it returns describe an in-flight animation frame rather
 * than the item's rest position. That is harmless for clicks well inside a
 * widget, but the two edge-clamp tests below deliberately click within
 * 20-30px of the widget's boundary — enough for even a small animation
 * delta to land the click in the grid gutter instead, which is exactly the
 * "popover never opens" symptom this was chased down for. Poll until two
 * consecutive reads match (with a small margin for sub-pixel jitter)
 * instead of guessing a fixed delay, so this holds under any machine speed.
 *
 * @param {Locator} locator The element to wait for.
 * @param {number} [timeoutMs] Give up and return the last read after this long.
 * @return {Promise<{x: number, y: number, width: number, height: number}>} the stable box.
 */
async function waitForStableBox(locator: Locator, timeoutMs = 2_000) {
	const deadline = Date.now() + timeoutMs
	let previous = await locator.boundingBox()
	for (;;) {
		await new Promise((resolve) => setTimeout(resolve, 60))
		const current = await locator.boundingBox()
		if (!current) {
			throw new Error('Locator has no bounding box (detached or hidden).')
		}
		if (
			previous
			&& Math.abs(current.x - previous.x) < 0.5
			&& Math.abs(current.y - previous.y) < 0.5
			&& Math.abs(current.width - previous.width) < 0.5
			&& Math.abs(current.height - previous.height) < 0.5
		) {
			return current
		}
		previous = current
		if (Date.now() > deadline) {
			return current
		}
	}
}

/**
 * Helper: navigate to launchpad, wait for the grid to hydrate, and switch into
 * edit mode so right-click opens the popover.
 *
 * @param {import('@playwright/test').Page} page Playwright page fixture.
 */
async function openInEditMode(page: Page) {
	await page.goto('/index.php/apps/launchpad')
	try {
		await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
	} catch {
		await page.goto('/index.php/apps/launchpad')
		await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
	}

	// Open the sidebar and enter edit mode for the active personal dashboard
	// via its per-row cog menu ("Edit dashboard").
	await page.locator('.launchpad-sidebar-toggle').first().click()
	await page.waitForSelector('.dashboard-switcher-sidebar.open', {
		timeout: 8_000,
	})
	const activeRow = page
		.locator(
			'[data-source="user"].dashboard-switcher-sidebar__item.active, [data-source="user"].dashboard-switcher-sidebar__item',
		)
		.first()
	await activeRow.locator('.dashboard-row-actions button').first().click()
	await page.getByRole('menuitem', { name: /edit dashboard/i }).click()

	// Wait for edit mode class to appear on the grid container, then close the
	// sidebar so it does not occlude the grid for the right-click assertions.
	await page.waitForSelector('.launchpad-edit-mode', { timeout: 8_000 })
	const closeBtn = page.locator('.dashboard-switcher-sidebar__close').first()
	if (await closeBtn.isVisible().catch(() => false)) {
		await closeBtn.click()
		await page
			.waitForFunction(
				() => !document.querySelector('.dashboard-switcher-sidebar.open'),
				{ timeout: 5_000 },
			)
			.catch(() => null)
	}
}

test.describe('widget-context-menu (REQ-WDG-015..017)', () => {
	test.beforeEach(async ({ page }) => {
		await openInEditMode(page)
	})

	test('REQ-WDG-017 right edge: popover stays within viewport when right-clicked near right edge', async ({
		page,
	}) => {
		// Narrow the viewport so 50 px from the right puts us near the edge.
		await page.setViewportSize({ width: 800, height: 600 })

		const placement = page.locator('.grid-stack-item').first()
		await expect(placement).toBeVisible({ timeout: 5_000 })
		// GridStack positions items via gs-x/gs-y, not DOM order, so ".first()"
		// is not guaranteed to be near the top of the (internally-scrolling)
		// grid — on a long-lived dashboard with many placements it can easily
		// sit below the fold. `toBeVisible()` does not catch that (it checks
		// CSS visibility, not scroll position), but the raw `page.mouse.click`
		// below uses absolute viewport coordinates and silently hits nothing
		// if the item is scrolled out of view. Scroll it into view first so
		// the computed box reflects where it will actually be clicked.
		await placement.scrollIntoViewIfNeeded()

		// Right-click 30 px from the right edge (leaves < 150 px for the popover).
		// waitForStableBox (not a bare boundingBox()) — see its docblock: the
		// viewport resize above just triggered a GridStack column reflow, and
		// items animate to their new position over 300ms.
		const box = await waitForStableBox(placement)
		const x = Math.min(box.x + box.width - 30, 770)
		const y = box.y + box.height / 2

		await page.mouse.click(x, y, { button: 'right' })

		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 3_000 })

		const menuBox = await menu.boundingBox()
		expect(menuBox).not.toBeNull()
		// The menu's right edge must not overflow the 800 px viewport.
		// REQ-WDG-017 compliance check.

		expect(menuBox!.x + menuBox!.width).toBeLessThanOrEqual(800)

		// Dismiss via Cancel.
		await page.locator('[data-testid="ctx-cancel"]').click()
		await expect(menu).not.toBeVisible()
	})

	test('REQ-WDG-017 bottom edge: popover stays within viewport when right-clicked near bottom edge', async ({
		page,
	}) => {
		await page.setViewportSize({ width: 1200, height: 600 })

		const placement = page.locator('.grid-stack-item').first()
		await expect(placement).toBeVisible({ timeout: 5_000 })
		// See the right-edge test above: ".first()" can be scrolled out of
		// view on a long-lived dashboard, and the raw page.mouse.click below
		// uses absolute coordinates that miss if so.
		await placement.scrollIntoViewIfNeeded()

		// Right-click 20 px from the bottom edge (leaves < 132 px for the popover).
		// waitForStableBox — see its docblock: the viewport resize above just
		// triggered a GridStack column reflow, and items animate over 300ms.
		const box = await waitForStableBox(placement)
		const x = box.x + box.width / 2
		// Clamp y to avoid clicking outside the viewport if the widget is short.
		const y = Math.min(box.y + box.height - 20, 580)

		await page.mouse.click(x, y, { button: 'right' })

		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 3_000 })

		const menuBox = await menu.boundingBox()
		expect(menuBox).not.toBeNull()
		// REQ-WDG-017: the menu bottom must not exceed the 600 px viewport.

		expect(menuBox!.y + menuBox!.height).toBeLessThanOrEqual(600)

		await page.locator('[data-testid="ctx-cancel"]').click()
	})

	test('REQ-WDG-015 + persistence: Remove via context menu persists after reload', async ({
		page,
	}) => {
		test.setTimeout(60_000)

		// Right-click must land on rendered widget CONTENT to open the popover
		// (a bare grid-cell gap, or a container widget's inner grid, does not
		// forward the contextmenu). Pick a grid item that holds a simple
		// widget renderer content element and use its placement id.
		const placement = page
			.locator('.grid-stack-item')
			.filter({ has: page.locator('.cn-widget-wrapper__content') })
			.first()
		await expect(placement).toBeVisible({ timeout: 8_000 })
		const gsId = await placement.getAttribute('gs-id')
		expect(gsId).toBeTruthy()

		// Right-click the widget content (not the cell padding) to open the menu.
		await placement
			.locator('.cn-widget-wrapper__content')
			.first()
			.click({ button: 'right' })

		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 5_000 })

		// Click Remove — the popover closes and the widget disappears from the DOM.
		await page.locator('[data-testid="ctx-remove"]').click()
		await expect(menu).not.toBeVisible()
		// The removed widget's grid item must be gone from the DOM.
		await expect(page.locator(`[gs-id="${gsId}"]`)).toHaveCount(0, {
			timeout: 5_000,
		})

		// Reload and confirm the placement is absent (DELETE persisted).
		await page.reload({ waitUntil: 'domcontentloaded' })
		await page.waitForSelector('.launchpad-container', { timeout: 20_000 })
		await page.waitForTimeout(1_000)
		await expect(page.locator(`[gs-id="${gsId}"]`)).toHaveCount(0)
	})
})
