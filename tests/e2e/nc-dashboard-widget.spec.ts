/*
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Playwright end-to-end tests for the `nc-widget` placement type covering
 * Task 8 of the `nc-dashboard-widget-proxy` OpenSpec change.
 *
 * Scenarios (REQ-WDG-018, REQ-WDG-019, REQ-WDG-021):
 *  - `weather_status` widget renders natively when the bundle is present
 *  - widget falls back to the API list when the bundle is absent
 *  - empty-list state shows the translated "No items available" string
 *
 * NOTE: Playwright infrastructure must be bootstrapped before these run in CI.
 * The spec file is committed so it executes once `test:e2e` is wired in the
 * pipeline.
 *
 * Gate traceability:
 *   @e2e nc-dashboard-widget::native-render-when-bundle-present
 *   @e2e nc-dashboard-widget::api-fallback-when-bundle-absent
 *   @e2e nc-dashboard-widget::empty-list-state
 *
 * @spec openspec/changes/nc-dashboard-widget-proxy/tasks.md#task-8
 */

import { test, expect } from '@playwright/test'

const NEXTCLOUD_URL = process.env.NEXTCLOUD_URL || 'http://localhost:8080'

test.describe('nc-widget — Nextcloud Dashboard widget placement', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${NEXTCLOUD_URL}/index.php/apps/mydash`)
		// Tests assume the user is authenticated via Playwright storageState;
		// in CI this is handled by the Hydra harness global-setup.
	})

	/**
	 * REQ-WDG-019 scenario "Native callback already registered at mount":
	 * When the weather_status bundle is present and has registered its callback
	 * via OCA.Dashboard.register, the renderer must mount natively via the bridge
	 * and NOT issue the items API request.
	 */
	test('REQ-WDG-019: weather_status renders natively when the bundle is present', async ({ page }) => {
		// Intercept the items API to detect unexpected calls.
		let apiCalled = false
		await page.route('**/api/widgets/items**', async (route) => {
			apiCalled = true
			await route.continue()
		})

		// Inject a fake OCA.Dashboard.register callback BEFORE the app boots
		// so the bridge captures it synchronously.
		await page.addInitScript(() => {
			window.OCA = window.OCA || {}
			window.OCA.Dashboard = window.OCA.Dashboard || {}
			const orig = window.OCA.Dashboard.register
			window.OCA.Dashboard.register = (id, cb) => {
				if (orig) orig(id, cb)
				if (id === 'weather_status') {
					const wrappedCb = (container) => {
						container.innerHTML = '<div class="test-native-widget">Native weather widget</div>'
					}
					// Register directly on the bridge if available via global capture.
					if (typeof window.OCA.Dashboard._bridge_register === 'function') {
						window.OCA.Dashboard._bridge_register('weather_status', wrappedCb)
					}
				}
			}
		})

		// Add a nc-widget placement for weather_status via the AddWidget modal.
		await page.getByRole('button', { name: /add widget/i }).click()
		await page.getByText('Nextcloud Widget', { exact: true }).click()

		// The grid picker should show weather_status if the NC instance has it.
		const weatherCard = page.locator('[aria-label="Weather"]').first()
		if (await weatherCard.isVisible({ timeout: 3000 }).catch(() => false)) {
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
	 * When no bundle registers a callback within the 3 s polling window, the
	 * renderer must display the API list as the final state.
	 */
	test('REQ-WDG-019: widget falls back to API list when the bundle is absent', async ({ page }) => {
		// Intercept the widget items API with a synthetic response.
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

		// No OCA.Dashboard.register call → bridge has no callback → poll exhausts.
		await page.getByRole('button', { name: /add widget/i }).click()
		await page.getByText('Nextcloud Widget', { exact: true }).click()
		await page.getByRole('button', { name: /save|add/i }).click()

		// After the polling window (~3 s) the API list must be the final state.
		const body = page.locator('.nc-dashboard-widget__body')
		await expect(body).toBeVisible({ timeout: 6000 })

		// Verify at least one item link is rendered.
		const items = page.locator('.nc-dashboard-widget__item')
		await expect(items.first()).toBeVisible({ timeout: 6000 })
	})

	/**
	 * REQ-WDG-021 scenario "Empty-list state":
	 * When the items response is empty the cell must show the translated
	 * "No items available" string and no <a> items.
	 */
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

		await page.getByRole('button', { name: /add widget/i }).click()
		await page.getByText('Nextcloud Widget', { exact: true }).click()
		await page.getByRole('button', { name: /save|add/i }).click()

		// Empty state message must appear; no item links must be rendered.
		const emptyState = page.locator('.nc-dashboard-widget__empty')
		await expect(emptyState).toBeVisible({ timeout: 6000 })
		await expect(emptyState).toContainText(/no items available/i)

		await expect(page.locator('.nc-dashboard-widget__item')).toHaveCount(0)
	})
})
