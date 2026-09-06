/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * End-to-end coverage for the admin Beheer ▸ Group dashboards tab
 * (admin-group-management Task 9). The tab renders one row per NC group
 * plus the synthetic `default` sentinel; per-row actions open the
 * Create and Manage modals.
 *
 * Scenarios covered:
 *   @e2e admin-group-management::tab-renders-one-row-per-group
 *   @e2e admin-group-management::create-modal-opens-with-form
 *   @e2e admin-group-management::manage-modal-opens-with-dashboard-list
 *
 * @spec openspec/changes/admin-group-management/tasks.md#task-9
 */

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const SETTINGS_URL = `${BASE}/index.php/settings/admin/launchpad`

test.describe('admin-group-management — Group dashboards tab', () => {
	test.beforeEach(async ({ page }) => {
		// Authenticate the browser context as admin so the settings page
		// is reachable. Re-uses NC's basic-auth response shape from
		// neighbouring specs.
		await page
			.context()
			.setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	})

	test('@e2e admin-group-management::tab-renders-one-row-per-group renders the tab with the default sentinel', async ({
		page,
	}) => {
		await page.goto(SETTINGS_URL)
		// Switch to the Group dashboards tab via its data-test slug.
		const tabButton = page.locator('[data-test="tab-group-dashboards"]')
		await expect(tabButton).toBeVisible({ timeout: 20_000 })
		await tabButton.click()

		// The default sentinel must always be present, regardless of
		// configured NC groups.
		await expect(
			page.locator('[data-test="group-dashboards-row-default"]'),
		).toBeVisible()
	})

	test('@e2e admin-group-management::create-modal-opens-with-form opens the create modal from the default row', async ({
		page,
	}) => {
		await page.goto(SETTINGS_URL)
		await page.locator('[data-test="tab-group-dashboards"]').click()
		await page.locator('[data-test="group-dashboards-create-default"]').click()

		const modal = page.locator('[data-test="create-group-dashboard-modal"]')
		await expect(modal).toBeVisible({ timeout: 10_000 })

		// The submit button MUST start disabled (empty name).
		const submit = page.locator('[data-test="create-group-dashboard-submit"]')
		await expect(submit).toBeDisabled()

		// Typing 2+ chars unlocks the submit button.
		// @nextcloud/vue v9 (the Vue-3 line) forwards NcTextField's fallthrough
		// attributes onto the inner <input> instead of the wrapper <div> the
		// v8/Vue-2 build used, so `data-test` IS the input — a descendant
		// `[data-test=…] input` selector matches nothing.
		await page
			.locator('input[data-test="create-group-dashboard-name"]')
			.fill('Ops')
		await expect(submit).toBeEnabled()

		// Cancel returns to the tab without firing a request.
		await page.locator('[data-test="create-group-dashboard-cancel"]').click()
		await expect(modal).toBeHidden()
	})

	test('@e2e admin-group-management::manage-modal-opens-with-dashboard-list opens the manage modal from the default row', async ({
		page,
	}) => {
		await page.goto(SETTINGS_URL)
		await page.locator('[data-test="tab-group-dashboards"]').click()
		await page.locator('[data-test="group-dashboards-manage-default"]').click()

		const modal = page.locator('[data-test="manage-group-dashboards-modal"]')
		await expect(modal).toBeVisible({ timeout: 10_000 })

		// Either the list or the empty-state placeholder must render —
		// the env may have no group-shared dashboards yet.
		const list = modal.locator('[data-test="manage-group-dashboards-list"]')
		const empty = modal.locator('[data-test="manage-group-dashboards-empty"]')
		await expect(list.or(empty)).toBeVisible()

		await modal.locator('[data-test="mgd-close"]').click()
		await expect(modal).toBeHidden()
	})
})
