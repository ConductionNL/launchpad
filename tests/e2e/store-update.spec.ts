/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Update on the marketplace (store-update-and-publish, REQ-STORE-012): a card
 * whose register version is newer than the installed one offers Update, the
 * page posts it to the item's install endpoint, and the server updates the
 * installed dashboard in place (StoreController::install hands an installed
 * item to StoreUpdateService). Afterwards the card shows Installed.
 *
 * CI has no second registry, so the register is stubbed at the page's own
 * items request and the install answer is stubbed too: what this proves is
 * the page's Update flow; the in-place update itself is pinned by
 * tests/Unit/Service/StoreUpdateServiceTest.php.
 */

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const STORE_URL = `${BASE}/index.php/apps/launchpad/store`

/**
 * The sociaal domein card, before or after the update.
 *
 * @param updated Whether the update ran.
 * @return The card.
 */
function card(updated: boolean): Record<string, unknown> {
	return {
		slug: 'managementinfo-sociaal-domein',
		title: 'Managementinfo sociaal domein',
		publisher: 'VNG',
		description: 'A dashboard template.',
		category: 'Management',
		version: '1.1.0',
		widgets: 7,
		usage: 4,
		installed: true,
		installedVersion: updated ? '1.1.0' : '1.0.0',
		updateAvailable: !updated,
		status: updated ? 'installed' : 'update',
		openUrl: '/index.php/apps/launchpad/dashboards/1',
	}
}

test.describe('Update on the marketplace', () => {
	/*
	 * @e2e openspec/changes/archive/2026-11-27-store-update-and-publish/specs/dashboard-store/spec.md#scenario-the-sociaal-domein-dashboard-is-updated
	 */
	test('Update brings the installed dashboard to the newer version and the card shows Installed', async ({ page }) => {
		let updated = false
		await page.route('**/apps/launchpad/api/store/items**', (route) => {
			const request = route.request()
			if (request.method() === 'POST' && request.url().endsWith('/managementinfo-sociaal-domein/install')) {
				updated = true
				return route.fulfill({
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify({ success: true, upToDate: false, components: [], version: '1.1.0', message: 'Updated to version 1.1.0.' }),
				})
			}
			return route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({
					outcome: 'ok',
					cards: [card(updated)],
					kinds: [],
					builtIn: [],
					registry: { name: 'VNG dashboardregister', url: 'https://register.vng.nl/dashboards' },
					checkedAt: new Date().toISOString(),
				}),
			})
		})

		await page.goto(STORE_URL, { waitUntil: 'domcontentloaded' })
		const item = page.locator('[data-testid="store-results"] > li', { hasText: 'Managementinfo sociaal domein' })
		const action = item.locator('[data-testid="store-card-action"]')
		await expect(action).toHaveText(/Update|Bijwerken/, { timeout: 60_000 })

		const posted = page.waitForRequest((request) => request.method() === 'POST'
			&& request.url().endsWith('/api/store/items/managementinfo-sociaal-domein/install'))
		await action.click()
		await posted

		await page.reload({ waitUntil: 'domcontentloaded' })
		await expect(item.locator('[data-testid="store-card-action"]')).toHaveAttribute('href', /\/apps\/launchpad\/dashboards\/1$/, { timeout: 60_000 })
		await expect(page.locator('[data-testid="store-lens-updates"]')).toContainText('0')
	})
})
