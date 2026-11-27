/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The administration shows the twelve tabs the Beheer boards draw
 * (admin-drawn-tabs, REQ-ADM-032): Embeds and Search shortcuts are cards of
 * General, Communities a section of Group dashboards, App catalogue a
 * section of Controls; an old tab address opens the tab that holds it, on
 * that section, with the embed form's pre-selection kept. Runs as the admin
 * (the project's storage state).
 *
 * @e2e openspec/changes/archive/2026-11-27-admin-drawn-tabs/specs/admin-settings/spec.md
 */

import { expect, test } from '@playwright/test'
import { adminTab } from './support/adminTab.ts'

const ADMIN_PAGE = '/index.php/apps/launchpad/admin'

const DRAWN = [
	'general',
	'templates',
	'operations',
	'roles-permissions',
	'versioning-audit',
	'sharing',
	'kiosk',
	'org-navigation',
	'demo-data',
	'group-dashboards',
	'detail-fields',
	'profile-fields',
]

test.describe('REQ-ADM-032 the administration shows the twelve drawn tabs', () => {
	// @e2e admin-settings::the-tab-strip-as-drawn
	test('the tab strip shows the twelve drawn tabs, and General holds Embeds and Search shortcuts', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 })
		await page.goto(`${ADMIN_PAGE}/general`)
		const strip = page.getByTestId('cn-admin-settings-tabs')
		await expect(strip).toBeVisible({ timeout: 60_000 })
		await expect(strip.getByRole('tab')).toHaveCount(DRAWN.length)
		for (const id of DRAWN) {
			await expect(adminTab(page, id), `tab ${id}`).toHaveCount(1)
		}
		await expect(strip.getByRole('tab').first()).toHaveText('General')
		await expect(strip.getByRole('tab').last()).toHaveText('Profile fields')

		await expect(page.locator('[data-admin-section="embeds"] [data-test="embeds-tab"]')).toBeVisible()
		await expect(page.locator('[data-admin-section="search-shortcuts"] [data-testid="search-shortcuts-tab"]')).toBeVisible()
	})

	// @e2e admin-settings::an-old-address-opens-its-section
	test('an old tab address opens the tab that holds it, on that section', async ({ page }) => {
		const cases: Array<[string, string, string]> = [
			['app-catalogue', 'operations', '[data-testid="app-catalogue-tab"]'],
			['communities', 'group-dashboards', '[data-testid="communities-tab"]'],
			['search-shortcuts', 'general', '[data-testid="search-shortcuts-tab"]'],
			['embeds', 'general', '[data-test="embeds-tab"]'],
		]
		for (const [former, host, content] of cases) {
			await page.goto(`${ADMIN_PAGE}/${former}`)
			await expect(page).toHaveURL(new RegExp(`/admin/${host}\\?section=${former}$`))
			await expect(adminTab(page, host)).toHaveAttribute('aria-selected', 'true', { timeout: 60_000 })
			const section = page.locator(`[data-admin-section="${former}"]`)
			await expect(section.locator(content)).toBeVisible()
			await expect(section, `${former} is scrolled into view`).toBeInViewport()
		}
	})

	// @e2e admin-settings::a-link-into-the-embeds-keeps-its-pre-selection
	test('a link into the embeds keeps the dashboard pre-selection', async ({ page }) => {
		const query = new URLSearchParams({
			tab: 'embeds',
			embedType: 'dashboard',
			embedId: 'e2e-drawn-tabs-uuid',
			embedDashboard: '1',
		})
		await page.goto(`${ADMIN_PAGE}/embeds?${query}`)
		await expect(page).toHaveURL(/\/admin\/general\?section=embeds&embedType=dashboard&embedId=e2e-drawn-tabs-uuid&embedDashboard=1$/)
		await expect(page.locator('[data-test="embed-token-form"]')).toBeVisible({ timeout: 60_000 })
	})
})
