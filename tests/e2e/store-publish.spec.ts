/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Publish on the marketplace (store-update-and-publish, REQ-STORE-013): an
 * administrator opens Publish, picks one of their dashboards, fills in the
 * title and a version, and the page posts it to LaunchPad's publish endpoint;
 * afterwards the item's card reads "Own publication".
 *
 * CI has no second registry, so the register is stubbed at the page's own
 * items request and the publish answer is stubbed too: what this proves is
 * the page's publish flow. The export without personal data, the version rule
 * and the 403 for a member are pinned by tests/Unit/Service/StorePublishServiceTest.php
 * and tests/Unit/Controller/StoreControllerTest.php.
 */

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const STORE_URL = `${BASE}/index.php/apps/launchpad/store`

/**
 * The published card, as the register answers after the publish.
 *
 * @param own Whether this instance published it.
 * @return The card.
 */
function card(own: boolean): Record<string, unknown> {
	return {
		slug: 'raads-en-collegewerk',
		title: 'Raads- en collegewerk',
		publisher: 'Gemeente Tilburg',
		description: 'Agenda, besluiten en moties.',
		category: 'Management',
		version: '0.9.0',
		widgets: 3,
		...(own ? { typeName: 'Own publication', ownPublication: true } : {}),
	}
}

test.describe('Publish on the marketplace', () => {
	/*
	 * @e2e dashboard-store::the-administrator-publishes-raads-en-collegewerk
	 */
	test('The administrator publishes a dashboard and its card reads Own publication', async ({ page }) => {
		let published: Record<string, unknown> | null = null
		await page.route('**/apps/launchpad/api/store/items**', (route) => route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({
				outcome: 'ok',
				cards: published ? [card(true)] : [],
				kinds: [],
				builtIn: [],
				registry: { name: 'VNG dashboardregister', url: 'https://register.vng.nl/dashboards' },
				checkedAt: new Date().toISOString(),
			}),
		}))
		await page.route('**/apps/launchpad/api/store/publishable**', (route) => route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ objects: [{ schema: 'dashboard', id: 'uuid-raad', title: 'Raads- en collegewerk' }] }),
		}))
		await page.route('**/apps/launchpad/api/store/publish', (route) => {
			published = route.request().postDataJSON()
			return route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({ outcome: 'ok', slug: 'raads-en-collegewerk' }),
			})
		})

		await page.goto(STORE_URL, { waitUntil: 'domcontentloaded' })
		await page.locator('[data-testid="store-publish"]').first().click({ timeout: 60_000 })

		const dialog = page.locator('[data-testid="store-publish-dialog"]')
		await expect(dialog).toBeVisible()
		await dialog.locator('[data-testid="store-publish-object"]', { hasText: 'Raads- en collegewerk' }).locator('input').check()
		// NcTextField puts the data-testid on its <input> itself, so the
		// fields are found by their label, as a user finds them.
		await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill('Raads- en collegewerk')
		await dialog.getByRole('textbox', { name: 'Version', exact: true }).fill('0.9.0')
		await dialog.locator('[data-testid="store-publish-submit"]').click()

		await expect.poll(() => published).not.toBeNull()
		expect(published).toMatchObject({
			item: { slug: 'raads-en-collegewerk', version: '0.9.0' },
			objects: [{ schema: 'dashboard', id: 'uuid-raad' }],
		})

		const item = page.locator('[data-testid="store-results"] > li', { hasText: 'Raads- en collegewerk' })
		await expect(item).toContainText(/Own publication|Eigen publicatie/, { timeout: 60_000 })
	})

	/*
	 * @e2e dashboard-store::the-same-version-twice
	 *
	 * The version rule itself (a version at or below the published one is
	 * refused with 409 version_not_higher) runs server-side against the
	 * register and is pinned by StorePublishServiceTest; CI has no register,
	 * so the answer is the service's own refusal, and what this proves is
	 * that the administrator reads it and nothing is published.
	 */
	test('Publishing the same version again is refused with a message asking for a higher version', async ({ page }) => {
		await page.route('**/apps/launchpad/api/store/items**', (route) => route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({
				outcome: 'ok',
				cards: [card(true)],
				kinds: [],
				builtIn: [],
				registry: { name: 'VNG dashboardregister', url: 'https://register.vng.nl/dashboards' },
				checkedAt: new Date().toISOString(),
			}),
		}))
		await page.route('**/apps/launchpad/api/store/publishable**', (route) => route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ objects: [{ schema: 'dashboard', id: 'uuid-raad', title: 'Raads- en collegewerk' }] }),
		}))
		let attempts = 0
		await page.route('**/apps/launchpad/api/store/publish', (route) => {
			attempts += 1
			return route.fulfill({
				status: 409,
				contentType: 'application/json',
				body: JSON.stringify({
					outcome: 'version_not_higher',
					slug: '',
					message: 'Version 0.9.0 of this dashboard was published before. Publish it with a higher version.',
				}),
			})
		})

		await page.goto(STORE_URL, { waitUntil: 'domcontentloaded' })
		await page.locator('[data-testid="store-publish"]').first().click({ timeout: 60_000 })
		const dialog = page.locator('[data-testid="store-publish-dialog"]')
		await expect(dialog).toBeVisible()
		await dialog.locator('[data-testid="store-publish-object"]', { hasText: 'Raads- en collegewerk' }).locator('input').check()
		await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill('Raads- en collegewerk')
		await dialog.getByRole('textbox', { name: 'Version', exact: true }).fill('0.9.0')
		await dialog.locator('[data-testid="store-publish-submit"]').click()

		await expect.poll(() => attempts).toBe(1)
		await expect(page.getByText(/Publish it with a higher version/)).toBeVisible()
		// Nothing was published: the dialog stays open for a new version.
		await expect(dialog).toBeVisible()
	})
})
