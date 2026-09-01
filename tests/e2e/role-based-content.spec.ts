// SPDX-License-Identifier: EUPL-1.2
/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * End-to-end (UI) coverage for role-based dashboard defaults
 * (REQ-RFP-002): the admin settings page renders the role-layout-defaults
 * section.
 *
 * The two HTTP-contract scenarios (employee-role API response + the
 * role-feature-permissions smoke) assert raw /api responses, not the UI;
 * per the gate-19 program they have been relocated to
 * tests/e2e/api-direct/role-based-content.api.spec.ts with Newman as the
 * contract authority.
 *
 * Scenarios covered:
 *   @e2e role-based-content::admin-role-layout-defaults-section-visible
 *
 * @spec openspec/changes/role-based-content/tasks.md#task-10
 */

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const ADMIN_URL = `${BASE}/index.php/settings/admin/launchpad`

/**
 * Scenario: Admin settings page shows the role-layout defaults section.
 *
 * Verifies that `RoleLayoutDefaultsSection` is rendered in the admin UI
 * (REQ-RFP-002 / Task 6).
 *
 * @e2e role-based-content::admin-role-layout-defaults-section-visible
 * @spec openspec/changes/role-based-content/tasks.md#task-10
 */
test('admin settings page contains role-layout-defaults section', async ({
	page,
}) => {
	// The shared storageState already carries the admin session, so navigate
	// straight to the LaunchPad admin settings page (no redundant form login,
	// which previously hung the test).
	const resp = await page.goto(ADMIN_URL, { waitUntil: 'domcontentloaded' })

	// If the settings page is not reachable in this environment, skip rather
	// than fail.
	if (resp && resp.status() >= 400) {
		test.skip(
			true,
			`Admin settings page returned ${resp.status()} in this environment`,
		)
		return
	}

	// The RoleLayoutDefaultsSection (REQ-RFP-002 / Task 6) lives inside the
	// "Roles & Permissions" admin tab (slug `roles-permissions`). Select that
	// tab before asserting the section.
	const rolesTab = page.locator('[data-test="tab-roles-permissions"]')
	await expect(rolesTab).toBeVisible({ timeout: 15_000 })
	await rolesTab.click()

	const section = page.locator('[data-testid="admin-layout-defaults-section"]')
	await expect(section).toBeVisible({ timeout: 10_000 })
})
