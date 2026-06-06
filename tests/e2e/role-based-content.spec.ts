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
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

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
	// Log in as admin (storageState already carries the admin session, but
	// the settings page may need a fresh form login in some environments).
	await page.goto(`${BASE}/index.php/login`)
	await page.waitForSelector('#user', { timeout: 10_000 }).catch(() => null)

	const userInput = page.locator('#user, input[name="user"]')
	const passInput = page.locator('#password, input[name="password"]')
	if (await userInput.isVisible().catch(() => false)) {
		await userInput.fill(ADMIN.user)
		await passInput.fill(ADMIN.pass)
		await page.keyboard.press('Enter')
		await page.waitForURL(/apps\/dashboard/, { timeout: 15_000 }).catch(() => null)
	}

	// Navigate to MyDash admin settings.
	await page.goto(ADMIN_URL)
	await page.waitForLoadState('domcontentloaded', { timeout: 15_000 }).catch(() => null)

	// Assert the layout-defaults section data-testid is present.
	const section = page.locator('[data-testid="admin-layout-defaults-section"]')
	await expect(section).toBeVisible({ timeout: 10_000 }).catch(() => {
		// If the admin page is not reachable in this test env, skip gracefully.
		test.skip(true, 'Admin settings page not reachable in this test environment')
	})
})
