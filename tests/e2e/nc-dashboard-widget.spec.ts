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
 */

import { test, expect } from '@playwright/test'

const NEXTCLOUD_URL = process.env.NEXTCLOUD_URL || 'http://localhost:8080'

test.describe('nc-widget placement (nc-dashboard-widget-proxy)', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${NEXTCLOUD_URL}/index.php/apps/mydash`)
		// Tests assume the user is authenticated via Playwright storageState;
		// in CI this is handled by the Hydra harness global-setup.
	})

	/**
	 * REQ-WDG-019 scenario "Native callback already registered at mount":
	 * When the weather_status bundle is present and has called
	 * OCA.Dashboard.register('weather_status', cb), the renderer must mount
	 * natively via widgetBridge.mountWidget — no API request should fire.
	 */
	test('renders natively when the widget bundle is present (REQ-WDG-019)', async ({ page }) => {
		// Inject a mock callback before the page loads the app bundle.
		// This simulates an NC widget bundle that self-registers on load.
		await page.addInitScript(() => {
			(window as Window & typeof globalThis & { OCA: Record<string, unknown> }).OCA = (window as Window & typeof globalThis & { OCA: Record<string, unknown> }).OCA || {}
			;(window as Window & typeof globalThis & { OCA: Record<string, unknown> }).OCA.Dashboard = {
				register: (id: string, cb: (el: HTMLElement) => void) => {
					// Persist so the bridge singleton sees it.
					;((window as Window & typeof globalThis & { __mockCallbacks: Record<string, (el: HTMLElement) => void> }).__mockCallbacks = (window as Window & typeof globalThis & { __mockCallbacks: Record<string, (el: HTMLElement) => void> }).__mockCallbacks || {})[id] = cb
				},
			}
			// Pre-register weather_status callback so it is present at mount.
			;(window as Window & typeof globalThis & { OCA: Record<string, unknown> }).OCA.Dashboard.register('weather_status', (el: HTMLElement) => {
				el.innerHTML = '<div class="mock-weather-native">Weather native render</div>'
			})
		})

		// Add an nc-widget placement for weather_status via the UI.
		await page.getByRole('button', { name: /add widget/i }).click()
		await page.getByText('Nextcloud Widget', { exact: true }).click()

		// Select the weather_status widget from the picker.
		const pickerOption = page.locator('.nc-widget-grid-picker__card', { hasText: /weather/i }).first()
		if (await pickerOption.isVisible({ timeout: 3000 }).catch(() => false)) {
			await pickerOption.click()
		}

		await page.getByRole('button', { name: /save|add/i }).click()

		// The native container must be visible; API list must NOT appear.
		const nativeContainer = page.locator('.nc-dashboard-widget__native')
		await expect(nativeContainer).toBeVisible({ timeout: 5000 })

		// The mock callback injects a known class — verify it rendered.
		const nativeContent = page.locator('.mock-weather-native')
		await expect(nativeContent).toBeVisible({ timeout: 5000 })

		// The API fallback body must NOT be rendered alongside the native container.
		await expect(page.locator('.nc-dashboard-widget__body')).toHaveCount(0)
	})

	/**
	 * REQ-WDG-019 scenario "Callback never registers — full API fallback":
	 * When no bundle registers a callback within the 3 s polling window, the
	 * renderer must display the API list as the final state.
	 */
	test('falls back to API list when the widget bundle is absent (REQ-WDG-019)', async ({ page }) => {
		// Intercept the widget items API so the test does not depend on a real NC.
		await page.route('**/api/widgets/items*', async (route) => {
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

		// Pick any available widget from the picker, fall through to API mode.
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
	test('shows "No items available" when the API returns an empty list (REQ-WDG-021)', async ({ page }) => {
		await page.route('**/api/widgets/items*', async (route) => {
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
