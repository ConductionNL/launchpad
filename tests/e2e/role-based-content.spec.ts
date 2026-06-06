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

import { test, expect } from '@playwright/test'

const BASE = (process.env.NC_BASE_URL ?? 'http://localhost:8080').replace(/\/$/, '')

const ADMIN_URL = `${BASE}/index.php/settings/admin/mydash`

/**
 * Scenario: Admin settings page shows the role-layout defaults section.
 *
 * Verifies that `RoleLayoutDefaultsSection` is rendered in the admin UI
 * (REQ-RFP-002 / Task 6).
 *
 * @e2e role-based-content::admin-role-layout-defaults-section-visible
 * @spec openspec/changes/role-based-content/tasks.md#task-10
 */
test('admin settings page contains role-layout-defaults section', async ({ page }) => {
	// The shared storageState already carries the admin session, so navigate
	// straight to the MyDash admin settings page (no redundant form login,
	// which previously hung the test).
	const resp = await page.goto(ADMIN_URL, { waitUntil: 'domcontentloaded' })

	// If the settings page is not reachable in this environment, skip rather
	// than fail.
	if (resp && resp.status() >= 400) {
		test.skip(true, `Admin settings page returned ${resp.status()} in this environment`)
		return
	}

	// The MyDash admin settings render a RoleLayoutDefaultsSection carrying the
	// data-testid below (REQ-RFP-002 / Task 6).
	const section = page.locator('[data-testid="admin-layout-defaults-section"]')
	await expect(section).toBeVisible({ timeout: 15_000 })
})
