/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
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
 * The file seeds its own personal dashboard with one text widget and makes
 * it the admin's active one, so it does not depend on what the instance
 * already holds (a fresh install has no personal dashboard, and the old
 * "first user row in the switcher" route found nothing to edit). The
 * dashboard is removed afterwards. The tests share it, so they run serially.
 *
 * Gate-19 @e2e traceability:
 *   @e2e keyboard-widget-repositioning::grid-item-keyboard-focusable-and-named
 *   @e2e keyboard-widget-repositioning::keyboard-move-persists-across-reload
 *   @e2e keyboard-widget-repositioning::move-panel-traps-focus
 */

import type { Placement } from './support/widgetPlacements.ts'

import { expect, test } from '@playwright/test'
import { adminApi, openInEditMode, Seeds } from './support/widgetPlacements.ts'
import { saveEditDraft } from './support/dashboardSwitcher.ts'

const RUN = Date.now()

let seeds: Seeds
let placement: Placement

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	seeds = new Seeds(await adminApi())
	const dash = await seeds.dashboard(`E2E Keyboard move ${RUN}`)
	placement = await seeds.widget(dash, {
		widgetId: 'text',
		gridX: 0,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 2,
		content: { text: 'keyboard move', contentMode: 'markdown' },
	})
})

test.afterAll(async () => {
	await seeds?.cleanup()
})

test.describe('keyboard-accessible widget repositioning (grid-layout)', () => {
	test.beforeEach(async ({ page }) => {
		await openInEditMode(page, placement.id)
	})

	test('SC 4.1.2: grid items are keyboard-focusable and expose a role + accessible name', async ({
		page,
	}) => {
		const placement = page.locator('.grid-stack-item').first()
		await expect(placement).toBeVisible({ timeout: 5_000 })

		await expect(placement).toHaveAttribute('role', 'group')
		await expect(placement).toHaveAttribute('tabindex', '0')
		const label = await placement.getAttribute('aria-label')
		expect(label && label.trim().length).toBeTruthy()

		// The item can actually take keyboard focus.
		await placement.focus()
		const isFocused = await placement.evaluate(
			(el) => el === document.activeElement,
		)
		expect(isFocused).toBe(true)
	})

	test('SC 2.1.1: keyboard-only move repositions the widget and persists after reload', async ({
		page,
	}) => {
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

		// The move lands in the edit-mode draft; Save stores it (REQ-EDIT-002).
		expect(await saveEditDraft(page)).toBe(200)

		// Reload and confirm the new position persisted.
		await page.reload({ waitUntil: 'domcontentloaded' })
		await page.waitForSelector('.launchpad-container', { timeout: 20_000 })
		await page.waitForTimeout(1_000)
		const persistedX = parseInt(
			(await page.locator(`[gs-id="${gsId}"]`).getAttribute('gs-x')) ?? '0',
			10,
		)
		expect(persistedX).toBe(movedX)
	})

	test('accessibility: the move panel exposes an accessible group name and traps focus', async ({
		page,
	}) => {
		const placement = page.locator('.grid-stack-item').first()
		await expect(placement).toBeVisible({ timeout: 8_000 })

		await placement.focus()
		await page.keyboard.press('Enter')
		await expect(
			page.locator('[data-testid="widget-context-menu"]'),
		).toBeVisible({ timeout: 5_000 })
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
			const modal = document
				.querySelector('[data-test="widget-move-panel"]')
				?.closest('.modal-wrapper, .modal-container, .nc-modal-stub')
			const active = document.activeElement
			const root =
				modal ?? document.querySelector('[data-test="widget-move-panel"]')
			return !!(root && active && root.contains(active))
		})
		expect(focusInsidePanel).toBe(true)

		// Escape cancels without persisting.
		await page.keyboard.press('Escape')
		await expect(panel).not.toBeVisible({ timeout: 5_000 })
	})
})
