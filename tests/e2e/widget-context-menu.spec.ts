/*
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
 * NOTE: These tests require a running Nextcloud instance with the mydash app
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

import { test, expect } from '@playwright/test'

/**
 * Helper: navigate to mydash, wait for the grid to hydrate, and switch into
 * edit mode so right-click opens the popover.
 *
 * @param {import('@playwright/test').Page} page Playwright page fixture.
 */
async function openInEditMode(page: import('@playwright/test').Page) {
	await page.goto('/index.php/apps/mydash')
	try {
		await page.waitForSelector('.mydash-sidebar-toggle', { timeout: 20_000 })
	} catch {
		await page.goto('/index.php/apps/mydash')
		await page.waitForSelector('.mydash-sidebar-toggle', { timeout: 20_000 })
	}

	// Open the sidebar and enter edit mode for the active personal dashboard
	// via its per-row cog menu ("Edit dashboard").
	await page.locator('.mydash-sidebar-toggle').first().click()
	await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 8_000 })
	const activeRow = page.locator(
		'[data-source="user"].dashboard-switcher-sidebar__item.active, [data-source="user"].dashboard-switcher-sidebar__item',
	).first()
	await activeRow.locator('.dashboard-row-actions button').first().click()
	await page.getByRole('menuitem', { name: /edit dashboard/i }).click()

	// Wait for edit mode class to appear on the grid container, then close the
	// sidebar so it does not occlude the grid for the right-click assertions.
	await page.waitForSelector('.mydash-edit-mode', { timeout: 8_000 })
	const closeBtn = page.locator('.dashboard-switcher-sidebar__close').first()
	if (await closeBtn.isVisible().catch(() => false)) {
		await closeBtn.click()
		await page.waitForFunction(
			() => !document.querySelector('.dashboard-switcher-sidebar.open'),
			{ timeout: 5_000 },
		).catch(() => null)
	}
}

test.describe('widget-context-menu (REQ-WDG-015..017)', () => {
	test.beforeEach(async ({ page }) => {
		await openInEditMode(page)
	})

	test('REQ-WDG-017 right edge: popover stays within viewport when right-clicked near right edge', async ({ page }) => {
		// Narrow the viewport so 50 px from the right puts us near the edge.
		await page.setViewportSize({ width: 800, height: 600 })

		const placement = page.locator('.grid-stack-item').first()
		await expect(placement).toBeVisible({ timeout: 5_000 })

		// Right-click 30 px from the right edge (leaves < 150 px for the popover).
		const box = await placement.boundingBox()
		if (!box) throw new Error('No bounding box for first grid-stack-item')
		const x = Math.min(box.x + box.width - 30, 770)
		const y = box.y + box.height / 2

		await page.mouse.click(x, y, { button: 'right' })

		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 3_000 })

		const menuBox = await menu.boundingBox()
		expect(menuBox).not.toBeNull()
		// The menu's right edge must not overflow the 800 px viewport.
		// REQ-WDG-017 compliance check.
		// eslint-disable-next-line @typescript-eslint/no-non-null-assertion
		expect(menuBox!.x + menuBox!.width).toBeLessThanOrEqual(800)

		// Dismiss via Cancel.
		await page.locator('[data-testid="ctx-cancel"]').click()
		await expect(menu).not.toBeVisible()
	})

	test('REQ-WDG-017 bottom edge: popover stays within viewport when right-clicked near bottom edge', async ({ page }) => {
		await page.setViewportSize({ width: 1200, height: 600 })

		const placement = page.locator('.grid-stack-item').first()
		await expect(placement).toBeVisible({ timeout: 5_000 })

		// Right-click 20 px from the bottom edge (leaves < 132 px for the popover).
		const box = await placement.boundingBox()
		if (!box) throw new Error('No bounding box for first grid-stack-item')
		const x = box.x + box.width / 2
		// Clamp y to avoid clicking outside the viewport if the widget is short.
		const y = Math.min(box.y + box.height - 20, 580)

		await page.mouse.click(x, y, { button: 'right' })

		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 3_000 })

		const menuBox = await menu.boundingBox()
		expect(menuBox).not.toBeNull()
		// REQ-WDG-017: the menu bottom must not exceed the 600 px viewport.
		// eslint-disable-next-line @typescript-eslint/no-non-null-assertion
		expect(menuBox!.y + menuBox!.height).toBeLessThanOrEqual(600)

		await page.locator('[data-testid="ctx-cancel"]').click()
	})

	test('REQ-WDG-015 + persistence: Remove via context menu persists after reload', async ({ page }) => {
		// Identify the first widget placement on the grid.
		const firstPlacement = page.locator('.grid-stack-item').first()
		await expect(firstPlacement).toBeVisible({ timeout: 5_000 })

		// Capture a stable identifier from the placement element.
		const gsId = await firstPlacement.getAttribute('gs-id')
		expect(gsId).toBeTruthy()

		// Right-click to open the context menu.
		const box = await firstPlacement.boundingBox()
		if (!box) throw new Error('No bounding box for first grid-stack-item')
		await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2, { button: 'right' })

		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 3_000 })

		// Click Remove — the popover closes and the widget disappears from the DOM.
		await page.locator('[data-testid="ctx-remove"]').click()
		await expect(menu).not.toBeVisible()
		// The removed widget's grid item must be gone from the DOM.
		await expect(page.locator(`[gs-id="${gsId}"]`)).toHaveCount(0, { timeout: 5_000 })

		// Reload and confirm the placement is absent (DELETE /api/placements/{id} persisted).
		await page.reload({ waitUntil: 'networkidle' })
		await page.waitForSelector('.mydash-container', { timeout: 15_000 })
		await expect(page.locator(`[gs-id="${gsId}"]`)).toHaveCount(0)
	})
})
