/*
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Playwright end-to-end tests for the widget right-click context menu
 * (REQ-WDG-015..017, widget-context-menu change).
 *
 * Scenarios:
 *   1. Popover stays fully on-screen when right-clicking near the right edge.
 *   2. Popover stays fully on-screen when right-clicking near the bottom edge.
 *   3. Removing a widget through the popover persists after page reload.
 *
 * NOTE: Playwright infrastructure requires a live Nextcloud instance with the
 * mydash app enabled and at least one personal dashboard with a widget
 * placement. The tests run in edit mode because the context menu is gated by
 * canEdit (REQ-WDG-015 view-mode guard).
 *
 * Gate-19 @e2e traceability:
 *   @e2e widget-context-menu::popover-clamped-right-edge
 *   @e2e widget-context-menu::popover-clamped-bottom-edge
 *   @e2e widget-context-menu::remove-persists-across-reload
 *
 * @spec openspec/changes/widget-context-menu/tasks.md#task-11
 */

import { test, expect } from '@playwright/test'

const APP_URL = '/index.php/apps/mydash'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Navigate to the mydash app and wait for the grid surface to mount.
 */
async function gotoMydash(page: import('@playwright/test').Page) {
	await page.goto(APP_URL)
	// Wait for either the modern workspace shell or the legacy Views root.
	await page.waitForSelector('.mydash-container, .workspace-shell__grid', {
		timeout: 20_000,
	})
}

/**
 * Enter edit mode by clicking the Edit toggle in the sidebar.
 * The sidebar must already be open (or the toolbar directly visible in
 * WorkspaceApp). Tries both known wiring points gracefully.
 */
async function enterEditMode(page: import('@playwright/test').Page) {
	// WorkspaceApp (runtime-shell): toolbar is always visible for admins.
	const toolbar = page.locator('.workspace-shell__toolbar')
	const toolbarVisible = await toolbar.isVisible().catch(() => false)
	if (toolbarVisible) {
		// Already in edit mode — toolbar shows we can add/save, no toggle needed.
		return
	}

	// Views.vue: look for a sidebar row action that enables edit mode.
	const sidebarToggle = page.locator('.mydash-sidebar-toggle').first()
	if (await sidebarToggle.isVisible().catch(() => false)) {
		await sidebarToggle.click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 8_000 })
	}

	// Click the first personal dashboard row's toggle-edit action if present.
	const editToggle = page.locator('[data-source="user"] .toggle-edit, [data-source="user"] [aria-label*="Edit"]').first()
	if (await editToggle.isVisible().catch(() => false)) {
		await editToggle.click()
		// Dismiss sidebar after toggling.
		await sidebarToggle.click().catch(() => undefined)
	}
}

/**
 * Right-click the first `.grid-stack-item` on the page and return the
 * bounding box of the opened `.widget-context-menu` element.
 * Throws when no grid item or no context menu appears within the timeout.
 */
async function rightClickFirstWidget(page: import('@playwright/test').Page) {
	const firstWidget = page.locator('.grid-stack-item').first()
	await expect(firstWidget).toBeVisible({ timeout: 15_000 })
	await firstWidget.click({ button: 'right' })
	const menu = page.locator('.widget-context-menu')
	await expect(menu).toBeVisible({ timeout: 5_000 })
	return menu.boundingBox()
}

// ---------------------------------------------------------------------------
// REQ-WDG-017: Position constraints
// ---------------------------------------------------------------------------

test.describe('REQ-WDG-017 Popover stays within viewport', () => {
	test.beforeEach(async ({ page }) => {
		await gotoMydash(page)
		await enterEditMode(page)
	})

	// @e2e widget-context-menu::popover-clamped-right-edge
	test('popover right edge does not exceed viewport width when right-clicking near the right edge', async ({ page }) => {
		const viewportSize = page.viewportSize()
		if (!viewportSize) {
			test.skip()
			return
		}
		const viewportWidth = viewportSize.width

		// Trigger a contextmenu event near the right edge — 50 px in from the
		// right — by dispatching directly so we don't depend on a specific
		// widget being at that coordinate.
		const firstWidget = page.locator('.grid-stack-item').first()
		await expect(firstWidget).toBeVisible({ timeout: 15_000 })

		const widgetBox = await firstWidget.boundingBox()
		if (!widgetBox) {
			test.skip()
			return
		}

		// Synthesise a right-click 50 px from the right edge of the viewport.
		const clickX = viewportWidth - 50
		const clickY = Math.min(widgetBox.y + widgetBox.height / 2, viewportSize.height / 2)

		await page.mouse.move(clickX, clickY)
		await page.dispatchEvent('.grid-stack-item', 'contextmenu', {
			clientX: clickX,
			clientY: clickY,
			bubbles: true,
			cancelable: true,
		})

		const menu = page.locator('.widget-context-menu')
		const menuVisible = await menu.isVisible().catch(() => false)
		if (!menuVisible) {
			// No widget under the dispatched coordinates; fall back to a plain right-click.
			await firstWidget.click({ button: 'right' })
			await expect(menu).toBeVisible({ timeout: 5_000 })
		}

		const menuBox = await menu.boundingBox()
		expect(menuBox).not.toBeNull()
		if (menuBox) {
			expect(menuBox.x + menuBox.width).toBeLessThanOrEqual(viewportWidth + 1)
		}
	})

	// @e2e widget-context-menu::popover-clamped-bottom-edge
	test('popover bottom edge does not exceed viewport height when right-clicking near the bottom edge', async ({ page }) => {
		const viewportSize = page.viewportSize()
		if (!viewportSize) {
			test.skip()
			return
		}
		const viewportHeight = viewportSize.height

		const firstWidget = page.locator('.grid-stack-item').first()
		await expect(firstWidget).toBeVisible({ timeout: 15_000 })

		// Synthesise a right-click 30 px from the bottom edge of the viewport.
		const widgetBox = await firstWidget.boundingBox()
		if (!widgetBox) {
			test.skip()
			return
		}

		const clickX = widgetBox.x + widgetBox.width / 2
		const clickY = viewportHeight - 30

		await page.dispatchEvent('.grid-stack-item', 'contextmenu', {
			clientX: clickX,
			clientY: clickY,
			bubbles: true,
			cancelable: true,
		})

		const menu = page.locator('.widget-context-menu')
		const menuVisible = await menu.isVisible().catch(() => false)
		if (!menuVisible) {
			await firstWidget.click({ button: 'right' })
			await expect(menu).toBeVisible({ timeout: 5_000 })
		}

		const menuBox = await menu.boundingBox()
		expect(menuBox).not.toBeNull()
		if (menuBox) {
			expect(menuBox.y + menuBox.height).toBeLessThanOrEqual(viewportHeight + 1)
		}
	})
})

// ---------------------------------------------------------------------------
// REQ-WDG-015: Remove persists across reload
// ---------------------------------------------------------------------------

test.describe('REQ-WDG-015 Remove via context menu persists', () => {
	// @e2e widget-context-menu::remove-persists-across-reload
	test('removing a widget through the popover is reflected after page reload', async ({ page }) => {
		await gotoMydash(page)
		await enterEditMode(page)

		const firstWidget = page.locator('.grid-stack-item').first()
		await expect(firstWidget).toBeVisible({ timeout: 15_000 })

		// Record the placement id before removal.
		const placementId = await firstWidget.getAttribute('gs-id')

		// Open the context menu and click Remove.
		await firstWidget.click({ button: 'right' })
		const menu = page.locator('.widget-context-menu')
		await expect(menu).toBeVisible({ timeout: 5_000 })

		const removeBtn = menu.locator('[data-testid="ctx-remove"]')
		await expect(removeBtn).toBeVisible({ timeout: 3_000 })

		// Intercept the DELETE request to confirm it fires.
		const deletePromise = page.waitForResponse(
			(res) => res.request().method() === 'DELETE' && res.url().includes('/placements/'),
			{ timeout: 10_000 },
		)
		await removeBtn.click()
		await deletePromise

		// The popover must be gone after Remove.
		await expect(menu).not.toBeVisible()

		// Reload and verify the placement is no longer in the DOM.
		await page.reload({ waitUntil: 'networkidle' })
		await page.waitForSelector('.mydash-container, .workspace-shell__grid', {
			timeout: 20_000,
		})

		if (placementId) {
			const removedItem = page.locator(`[gs-id="${placementId}"]`)
			await expect(removedItem).not.toBeVisible({ timeout: 5_000 })
		}
	})
})
