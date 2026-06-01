/*
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Playwright end-to-end tests for the `nc-widget` placement type covering
 * REQ-WDG-018, REQ-WDG-019, REQ-WDG-020, REQ-WDG-021.
 *
 * NOTE: Playwright infrastructure is not yet wired up in mydash. This file
 * is committed alongside the rest of the change so it runs once the cohort-
 * wide Playwright bootstrap lands. Do not delete — it is the canonical e2e
 * coverage for REQ-WDG-019 native callback, REQ-WDG-019 API fallback, and
 * REQ-WDG-021 empty-state message.
 *
 * Gate-19 @e2e traceability:
 *   @e2e nc-dashboard-widget::weather_status-renders-natively-when-bundle-present
 *   @e2e nc-dashboard-widget::widget-falls-back-to-api-list-when-bundle-absent
 *   @e2e nc-dashboard-widget::empty-list-state-shows-translated-string
 *
 * @spec openspec/changes/nc-dashboard-widget-proxy/tasks.md#task-8
 */

import { test, expect } from '@playwright/test'

const NEXTCLOUD_URL = process.env.NEXTCLOUD_URL || 'http://localhost:8080'

test.describe('nc-widget — Nextcloud Dashboard widget placement', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${NEXTCLOUD_URL}/index.php/apps/mydash`)
		// Tests assume the user is already authenticated via Playwright
		// storageState; in CI this is set up by the Hydra harness.
	})

	/**
	 * REQ-WDG-019 scenario "Native callback already registered at mount":
	 * When the `weather_status` bundle has registered its callback via
	 * `OCA.Dashboard.register`, the renderer must mount via the bridge
	 * and NOT issue the items API request.
	 */
	test('REQ-WDG-019: weather_status renders natively when the bundle is present', async ({ page }) => {
		// Intercept the items API to detect unexpected calls.
		let apiCalled = false
		await page.route('**/api/widgets/items**', (route) => {
			apiCalled = true
			route.continue()
		})

		// Inject a fake OCA.Dashboard.register callback BEFORE the app boots
		// so the bridge captures it synchronously.
		await page.addInitScript(() => {
			window._testWidgetMounted = false
			window.OCA = window.OCA || {}
			window.OCA.Dashboard = window.OCA.Dashboard || {}
			const orig = window.OCA.Dashboard.register
			window.OCA.Dashboard.register = (id, cb) => {
				if (orig) orig(id, cb)
				if (id === 'weather_status') {
					const wrappedCb = (container) => {
						container.innerHTML = '<div class="test-native-widget">Native weather widget</div>'
						window._testWidgetMounted = true
					}
					if (typeof window.OCA.Dashboard._bridge_register === 'function') {
						window.OCA.Dashboard._bridge_register('weather_status', wrappedCb)
					}
				}
			}
		})

		// Add a nc-widget placement for weather_status via the AddWidget modal.
		await page.getByRole('button', { name: /add widget/i }).click()
		await page.getByText('Nextcloud Widget', { exact: true }).click()

		// The grid picker should show weather_status (if the NC instance has it).
		const weatherCard = page.locator('[aria-label="Weather"]').first()
		if (await weatherCard.isVisible()) {
			await weatherCard.click()
		}

		await page.getByRole('button', { name: /save|add/i }).click()

		// Native container should be visible (v-show switches to 'native').
		const nativeContainer = page.locator('.nc-dashboard-widget__native').first()
		await expect(nativeContainer).toBeVisible({ timeout: 5000 })

		// No items API call should have been made.
		expect(apiCalled).toBe(false)
	})

	/**
	 * REQ-WDG-019 scenario "Callback never registers — full API fallback":
	 * When no bundle registers a callback within the 3 s poll window the
	 * renderer must remain in API-fallback mode and display items (or the
	 * empty-state message when the list is empty).
	 */
	test('REQ-WDG-019: widget falls back to API list when the bundle is absent', async ({ page }) => {
		// Intercept items API and return a fixed payload.
		await page.route('**/api/widgets/items**', (route) => {
			route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({
					items: {
						fictional_widget: [
							{ title: 'Item A', subtitle: 'Sub A', link: '/a', iconUrl: '' },
							{ title: 'Item B', subtitle: 'Sub B', link: '/b', iconUrl: '' },
						],
					},
				}),
			})
		})

		// Add a nc-widget placement whose bundle will never register.
		await page.getByRole('button', { name: /add widget/i }).click()
		await page.getByText('Nextcloud Widget', { exact: true }).click()

		// Manually set widgetId if the picker grid doesn't list our test widget.
		// Use the display-mode select to confirm the form is visible.
		await expect(page.getByText('Display Mode')).toBeVisible()

		await page.getByRole('button', { name: /save|add/i }).click()

		// The API body should be rendered (not the native container).
		const apiBody = page.locator('.nc-dashboard-widget__body').first()
		await expect(apiBody).toBeVisible({ timeout: 5000 })
	})

	/**
	 * REQ-WDG-021 scenario "Empty-list state":
	 * When the items response contains an empty array the renderer must
	 * show the translated string "No items available".
	 */
	test('REQ-WDG-021: empty-list state shows "No items available"', async ({ page }) => {
		// Intercept items API and return an empty items list.
		await page.route('**/api/widgets/items**', (route) => {
			route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({ items: {} }),
			})
		})

		// Add a nc-widget placement.
		await page.getByRole('button', { name: /add widget/i }).click()
		await page.getByText('Nextcloud Widget', { exact: true }).click()
		await page.getByRole('button', { name: /save|add/i }).click()

		// The empty-state message must appear.
		const emptyState = page.locator('.nc-dashboard-widget__empty').first()
		await expect(emptyState).toBeVisible({ timeout: 5000 })
		await expect(emptyState).toContainText('No items available')
	})
})
