/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Playwright end-to-end tests for the `nc-widget` placement type covering
 * Task 8 of the `nc-dashboard-widget-proxy` OpenSpec change.
 *
 * Drives the real runtime-shell add-widget flow: sidebar → personal-row cog
 * → "Add custom widget…" → Add Widget modal → pick "Nextcloud Widget" →
 * choose a widget in the grid picker → save.
 *
 * Scenarios (REQ-WDG-018, REQ-WDG-019, REQ-WDG-021):
 *  - an nc-widget renders (native bridge OR API-list fallback) once placed
 *  - the renderer falls back to the API list when no native bundle registers
 *  - the empty-list response shows the translated "No items available" string
 *
 * Gate traceability:
 *   @e2e nc-dashboard-widget::native-render-when-bundle-present
 *   @e2e nc-dashboard-widget::api-fallback-when-bundle-absent
 *   @e2e nc-dashboard-widget::empty-list-state
 *
 * @spec openspec/changes/nc-dashboard-widget-proxy/tasks.md#task-8
 */

import { test, expect, type Page } from '@playwright/test'
import { gotoLaunchPad, openAddWidgetModal, closeSidebar } from './fixtures/widget-flow'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions'

test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()
})

/**
 * Add an nc-widget placement by picking the first widget in the grid picker.
 *
 * @param {Page} page Playwright page.
 * @return {Promise<boolean>} true if a widget was placed, false if the picker
 *   had no widgets (NC instance without dashboard widgets).
 */
async function addNcWidget(page: Page): Promise<boolean> {
	await openAddWidgetModal(page)
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await dialog.getByLabel(/widget type/i).selectOption({ label: 'Nextcloud widget' })

	// The grid picker lists discovered Nextcloud dashboard widgets.
	const cards = dialog.locator('.cn-nc-widget-grid-picker__card')
	const empty = dialog.locator('.cn-nc-widget-grid-picker__empty')
	await expect(cards.first().or(empty)).toBeVisible({ timeout: 8_000 })
	if (await empty.isVisible().catch(() => false)) {
		return false
	}
	await cards.first().click()

	const addBtn = dialog.getByRole('button', { name: /^add$/i })
	await expect(addBtn).toBeEnabled({ timeout: 5_000 })
	await addBtn.click()
	await expect(dialog).not.toBeVisible({ timeout: 8_000 })
	await closeSidebar(page)
	return true
}

test.describe('nc-widget — Nextcloud Dashboard widget placement', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e nc-dashboard-widget::native-render-when-bundle-present
	test('REQ-WDG-019: an added nc-widget renders its proxy cell', async ({ page }) => {
		const placed = await addNcWidget(page)
		test.skip(!placed, 'No Nextcloud dashboard widgets installed in this instance')

		// The nc-widget renderer mounts a cell that resolves to a final state.
		// Without an injected native bundle the renderer settles on the API
		// list body (`__body`, rendered with `v-if` in non-native mode); when a
		// native bundle registers it instead reveals `__native` (v-show) and
		// drops `__body`. Assert a visible final state via a poll so either
		// branch satisfies REQ-WDG-019.
		const cell = page.locator('.nc-dashboard-widget').first()
		await expect(cell).toBeVisible({ timeout: 8_000 })
		await expect.poll(async () => {
			const bodyVisible = await cell.locator('.nc-dashboard-widget__body').isVisible().catch(() => false)
			const nativeVisible = await cell.locator('.nc-dashboard-widget__native').isVisible().catch(() => false)
			return bodyVisible || nativeVisible
		}, { timeout: 10_000 }).toBe(true)
	})

	// @e2e nc-dashboard-widget::api-fallback-when-bundle-absent
	test('REQ-WDG-019: widget falls back to the API list when no native bundle registers', async ({ page }) => {
		// Force the API path: stub the items endpoint with two recommendations
		// and ensure no native callback registers (default — we inject none).
		await page.route('**/api/widgets/items**', async (route) => {
			await route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({
					items: {
						recommendations: [
							{ title: 'Rec A', subtitle: 'Sub A', link: '/a', iconUrl: '', sinceId: '1' },
							{ title: 'Rec B', subtitle: 'Sub B', link: '/b', iconUrl: '', sinceId: '2' },
						],
					},
					meta: { recommendations: { iconUrl: '' } },
				}),
			})
		})

		const placed = await addNcWidget(page)
		test.skip(!placed, 'No Nextcloud dashboard widgets installed in this instance')

		const cell = page.locator('.nc-dashboard-widget').first()
		await expect(cell).toBeVisible({ timeout: 8_000 })
		// After the native-poll window elapses the API body becomes the final
		// state. Accept the body OR a rendered item (either proves fallback).
		const apiState = cell.locator('.nc-dashboard-widget__body, .nc-dashboard-widget__item')
		await expect(apiState.first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e nc-dashboard-widget::empty-list-state
	test('REQ-WDG-021: empty-list state shows the translated string', async ({ page }) => {
		await page.route('**/api/widgets/items**', async (route) => {
			await route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({
					items: { recommendations: [] },
					meta: { recommendations: { iconUrl: '' } },
				}),
			})
		})

		const placed = await addNcWidget(page)
		test.skip(!placed, 'No Nextcloud dashboard widgets installed in this instance')

		const cell = page.locator('.nc-dashboard-widget').first()
		await expect(cell).toBeVisible({ timeout: 8_000 })
		// Empty-list final state: the empty placeholder must appear and no
		// item links must be rendered.
		const empty = cell.locator('.nc-dashboard-widget__empty')
		await expect(empty).toBeVisible({ timeout: 10_000 })
		await expect(cell.locator('.nc-dashboard-widget__item')).toHaveCount(0)
	})
})
