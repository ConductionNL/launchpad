/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * axe checks (WCAG 2.2 AA rules) on two launcher forms, each scoped to the
 * form itself so findings in Nextcloud's own chrome do not count:
 *
 *   - the admin section for allowed schemes and sign-on templates
 *     (launcher-tile-launch-types task 6, `TileLaunchSettings`);
 *   - the "Can't find your app?" request form in the add flow
 *     (launcher-app-catalogue task 7, `WidgetPickerModal`).
 *
 * A serious or critical violation fails the test; the full result is
 * attached to the report.
 */

import type { Page, TestInfo } from '@playwright/test'

import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

/**
 * Run axe on one part of the page and fail on serious or critical findings.
 *
 * @param page The page.
 * @param selector The part to check.
 * @param testInfo The test, for the attachment.
 */
async function expectNoSeriousViolations(page: Page, selector: string, testInfo: TestInfo): Promise<void> {
	const results = await new AxeBuilder({ page }).include(selector).withTags(WCAG_TAGS).analyze()
	await testInfo.attach('axe-results.json', { body: JSON.stringify(results, null, 2), contentType: 'application/json' })
	const serious = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')
	expect(serious.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)).toEqual([])
}

// @e2e exclude an accessibility check of the admin launch settings, not a scenario of the tiles spec
test('the admin section for schemes and sign-on templates has no serious axe findings', async ({ page }, testInfo) => {
	await page.goto('/index.php/settings/admin/launchpad')
	await adminTab(page, 'general').click()
	const section = page.getByTestId('admin-tile-launch')
	await expect(section).toBeVisible({ timeout: 30_000 })
	await expectNoSeriousViolations(page, '[data-testid="admin-tile-launch"]', testInfo)
})

// @e2e exclude an accessibility check of the request form, not a scenario of the app-catalogue spec
test('the "Can\'t find your app?" request form has no serious axe findings', async ({ browser }, testInfo) => {
	// A new account's first login provisions its home, and axe walks the whole modal.
	test.setTimeout(180_000)
	const admin = await adminContext()
	const member = await createPrincipal(admin, 'e2e-axe-request')
	try {
		await allowPersonalDashboards(admin, true)
		const created = await member.api.post(`${API}/dashboard`, { data: { name: `E2E axe ${Date.now()}` } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dashboard = body.dashboard ?? body.data?.dashboard ?? body
		expect((await member.api.post(`${API}/dashboard/${dashboard.id}/activate`)).status()).toBeLessThan(300)
		expect((await member.api.post(`${API}/dashboards/default`, { data: { uuid: dashboard.uuid } })).status()).toBeLessThan(300)

		const { context, page } = await loginAs(browser, member.username, member.password)
		try {
			await page.goto('/index.php/apps/launchpad/')
			const tour = page.getByRole('button', { name: 'Close tour' })
			if (await tour.waitFor({ state: 'visible', timeout: 5_000 }).then(() => true, () => false)) {
				await tour.click()
			}
			const more = page.locator(MORE_MENU)
			await expect(more).toBeVisible({ timeout: 30_000 })
			await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
			await page.locator('[data-testid="launchpad-more-menu-item-add-widget"]').click()
			const request = page.locator('[data-testid="widget-picker-request"]')
			await request.getByRole('button', { name: /can't find your app/i }).click()
			await expect(request.locator('form')).toBeVisible({ timeout: 10_000 })
			await expectNoSeriousViolations(page, '[data-testid="widget-picker-request"]', testInfo)
		} finally {
			await context.close()
		}
	} finally {
		await removePrincipal(admin, member)
		await admin.dispose()
	}
})
