/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The marketplace as board LpStore draws it (store-page-board-sync): the
 * register banner, the dashboard lenses with the register's categories, 8
 * cards a page, and an installed card whose Open goes to its dashboard.
 *
 * CI has one instance and no second registry to publish templates, so the
 * registry is stubbed at the page's own items request: the answer has the
 * shape `GET /api/store/items` returns (outcome, cards, registry, checkedAt),
 * which tests/Unit/Controller/StoreControllerTest.php pins on the server side.
 * What this proves is the page; the route is proven there.
 */

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const STORE_URL = `${BASE}/index.php/apps/launchpad/store`

const CATEGORIES = ['Intranet', 'Intranet', 'Intranet', 'Management']

/**
 * 23 templates as the VNG register would serve them: the first installed, the
 * second with a newer version than the installed one.
 *
 * @return The cards.
 */
function cards(): Array<Record<string, unknown>> {
	return Array.from({ length: 23 }, (_, i) => ({
		slug: `template-${i}`,
		title: i === 0 ? 'Intranet gemeente' : `Template ${i}`,
		publisher: 'VNG',
		description: 'A dashboard template.',
		category: CATEGORIES[i % CATEGORIES.length],
		version: i === 1 ? '1.1.0' : '2.3.0',
		widgets: 6,
		usage: 12,
		installed: i <= 1,
		installedVersion: i === 1 ? '1.0.0' : '2.3.0',
		updateAvailable: i === 1,
		openUrl: i === 0 ? '/index.php/apps/launchpad/dashboards/1' : '',
	}))
}

test.describe('the marketplace as board LpStore draws it', () => {
	test.beforeEach(async ({ page }) => {
		await page.route('**/apps/launchpad/api/store/items**', (route) =>
			route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({
					outcome: 'ok',
					cards: cards(),
					kinds: [],
					builtIn: [],
					registry: { name: 'VNG dashboardregister', url: 'https://register.vng.nl/dashboards' },
					checkedAt: new Date().toISOString(),
				}),
			}),
		)
	})

	/*
	 * @e2e dashboard-store::the-vng-register-is-connected
	 */
	test('shows the banner, the lenses with the categories and 8 cards', async ({ page }) => {
		await page.goto(STORE_URL, { waitUntil: 'domcontentloaded' })

		const banner = page.locator('[data-testid="store-banner"]')
		await expect(banner).toBeVisible({ timeout: 60_000 })
		await expect(banner.locator('[data-testid="store-banner-name"]')).toHaveText('VNG dashboardregister')
		await expect(banner.locator('[data-testid="store-banner-address"]')).toHaveText('register.vng.nl/dashboards')
		await expect(banner.locator('[data-testid="store-banner-checked"]')).toBeVisible()
		await expect(banner.locator('[data-testid="store-banner-state"]')).toBeVisible()

		await expect(page.locator('[data-testid="store-lens-all"]')).toContainText('23')
		await expect(page.locator('[data-testid="store-lens-installed"]')).toContainText('2')
		await expect(page.locator('[data-testid="store-lens-updates"]')).toContainText('1')
		await expect(page.locator('[data-testid="store-lens-category:Intranet"]')).toContainText('18')
		await expect(page.locator('[data-testid="store-lens-category:Management"]')).toContainText('5')
		await expect(page.locator('[data-testid^="store-lens-app-template"]')).toHaveCount(0)

		await expect(page.locator('[data-testid="store-results"] > li')).toHaveCount(8)
		await expect(page.locator('[data-testid="store-pager-label"]')).toBeVisible()
		const first = page.locator('[data-testid="store-results"] > li').first()
		await expect(first.locator('[data-testid="store-card-widgets"]')).toBeVisible()
		await expect(first.locator('[data-testid="store-card-usage"]')).toBeVisible()
	})

	/*
	 * @e2e dashboard-store::intranet-gemeente-is-installed
	 */
	test('an installed card opens its dashboard', async ({ page }) => {
		await page.goto(STORE_URL, { waitUntil: 'domcontentloaded' })
		const card = page.locator('[data-testid="store-results"] > li', { hasText: 'Intranet gemeente' })
		await expect(card.locator('[data-testid="store-card-state"]')).toBeVisible({ timeout: 60_000 })
		const open = card.locator('[data-testid="store-card-action"]')
		await expect(open).toHaveAttribute('href', /\/apps\/launchpad\/dashboards\/1$/)
	})

	/*
	 * @e2e dashboard-store::a-newer-version-in-the-register
	 */
	test('a newer register version counts under Update available', async ({ page }) => {
		await page.goto(STORE_URL, { waitUntil: 'domcontentloaded' })
		await page.locator('[data-testid="store-lens-updates"]').click({ timeout: 60_000 })
		await expect(page.locator('[data-testid="store-results"] > li')).toHaveCount(1)
	})
})
