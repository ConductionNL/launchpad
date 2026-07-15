/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Playwright end-to-end tests for keyboard-accessible widget repositioning
 * (grid-layout OpenSpec change keyboard-accessible-widget-repositioning).
 *
 * Scenarios covered:
 *   - A grid item is keyboard-focusable in edit mode (tabindex=0) and
 *     exposes an accessible role + name (WCAG 2.1 SC 2.1.1 / 4.1.2).
 *   - Focusing a grid item and pressing Enter opens the context menu; the
 *     new "Move" action opens the keyboard-operable move panel.
 *   - Inside the move panel, ArrowRight (twice) + Enter repositions the
 *     widget by the expected column offset — driven entirely by the
 *     keyboard, with NO pointer/mouse events dispatched — and the new
 *     gs-x persists after a reload.
 *   - Accessibility: the move panel exposes an accessible group name and
 *     traps focus (focus stays inside the panel, not the canvas behind it).
 *
 * NOTE: These tests require a running Nextcloud instance with the mydash
 * app installed and at least one widget placement on the active dashboard.
 * The harness supplies authenticated storage state via the global setup at
 * `tests/e2e/global-setup.ts`. In CI, the Hydra pipeline wires this up; run
 * locally with `npm run test:e2e` after starting the dev stack.
 *
 * Gate-19 @e2e traceability:
 *   @e2e keyboard-widget-repositioning::grid-item-keyboard-focusable-and-named
 *   @e2e keyboard-widget-repositioning::keyboard-move-persists-across-reload
 *   @e2e keyboard-widget-repositioning::move-panel-traps-focus
 */

import { test, expect } from '@playwright/test'

/**
 * Navigate to mydash, wait for the grid to hydrate, and switch into edit
 * mode so grid items become keyboard-focusable and the context menu opens.
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

	await page.locator('.mydash-sidebar-toggle').first().click()
	await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 8_000 })
	const activeRow = page.locator(
		'[data-source="user"].dashboard-switcher-sidebar__item.active, [data-source="user"].dashboard-switcher-sidebar__item',
	).first()
	await activeRow.locator('.dashboard-row-actions button').first().click()
	await page.getByRole('menuitem', { name: /edit dashboard/i }).click()

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

test.describe('keyboard-accessible widget repositioning (grid-layout)', () => {
	test.beforeEach(async ({ page }) => {
		await openInEditMode(page)
	})

	test('SC 4.1.2: grid items are keyboard-focusable and expose a role + accessible name', async ({ page }) => {
		const placement = page.locator('.grid-stack-item').first()
		await expect(placement).toBeVisible({ timeout: 5_000 })

		await expect(placement).toHaveAttribute('role', 'group')
		await expect(placement).toHaveAttribute('tabindex', '0')
		const label = await placement.getAttribute('aria-label')
		expect(label && label.trim().length).toBeTruthy()

		// The item can actually take keyboard focus.
		await placement.focus()
		const isFocused = await placement.evaluate((el) => el === document.activeElement)
		expect(isFocused).toBe(true)
	})

	test('SC 2.1.1: keyboard-only move repositions the widget and persists after reload', async ({ page }) => {
		test.setTimeout(60_000)

		const placement = page.locator('.grid-stack-item').first()
		await expect(placement).toBeVisible({ timeout: 8_000 })
		const gsId = await placement.getAttribute('gs-id')
		expect(gsId).toBeTruthy()
		const startX = parseInt((await placement.getAttribute('gs-x')) ?? '0', 10)

		// Focus the item and open the context menu with the keyboard only.
		await placement.focus()
		await page.keyboard.press('Enter')
		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 5_000 })

		// Activate "Move" (keyboard) to open the move panel.
		await page.locator('[data-testid="ctx-move"]').focus()
		await page.keyboard.press('Enter')
		const panel = page.locator('[data-test="widget-move-panel"]')
		await expect(panel).toBeVisible({ timeout: 5_000 })

		// Nudge right twice, then confirm — all via the keyboard.
		await page.keyboard.press('ArrowRight')
		await page.keyboard.press('ArrowRight')
		await page.keyboard.press('Enter')
		await expect(panel).not.toBeVisible({ timeout: 5_000 })

		// The widget's grid column advanced by two (clamped at the edge).
		const movedX = parseInt(
			(await page.locator(`[gs-id="${gsId}"]`).getAttribute('gs-x')) ?? '0',
			10,
		)
		expect(movedX).toBeGreaterThan(startX)

		// Reload and confirm the new position persisted.
		await page.reload({ waitUntil: 'domcontentloaded' })
		await page.waitForSelector('.mydash-container', { timeout: 20_000 })
		await page.waitForTimeout(1_000)
		const persistedX = parseInt(
			(await page.locator(`[gs-id="${gsId}"]`).getAttribute('gs-x')) ?? '0',
			10,
		)
		expect(persistedX).toBe(movedX)
	})

	test('accessibility: the move panel exposes an accessible group name and traps focus', async ({ page }) => {
		const placement = page.locator('.grid-stack-item').first()
		await expect(placement).toBeVisible({ timeout: 8_000 })

		await placement.focus()
		await page.keyboard.press('Enter')
		await expect(page.locator('[data-testid="widget-context-menu"]')).toBeVisible({ timeout: 5_000 })
		await page.locator('[data-testid="ctx-move"]').focus()
		await page.keyboard.press('Enter')

		const panel = page.locator('[data-test="widget-move-panel"]')
		await expect(panel).toBeVisible({ timeout: 5_000 })
		await expect(panel).toHaveAttribute('role', 'group')
		const label = await panel.getAttribute('aria-label')
		expect(label && label.trim().length).toBeTruthy()

		// Focus must stay within the modal/panel subtree, not leak to the
		// canvas behind it.
		await page.keyboard.press('Tab')
		const focusInsidePanel = await page.evaluate(() => {
			const modal = document.querySelector('[data-test="widget-move-panel"]')?.closest('.modal-wrapper, .modal-container, .nc-modal-stub')
			const active = document.activeElement
			const root = modal ?? document.querySelector('[data-test="widget-move-panel"]')
			return !!(root && active && root.contains(active))
		})
		expect(focusInsidePanel).toBe(true)

		// Escape cancels without persisting.
		await page.keyboard.press('Escape')
		await expect(panel).not.toBeVisible({ timeout: 5_000 })
	})
})
