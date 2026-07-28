/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * End-to-end coverage for the allow_user_dashboards runtime flag
 * (REQ-ASET-003 extended, REQ-ASET-015).
 *
 * Scenarios covered:
 *   @e2e allow-personal-dashboards-flag::sidebar-add-button-hidden-when-flag-off
 *   @e2e allow-personal-dashboards-flag::sidebar-add-button-visible-when-flag-on
 *   @e2e allow-personal-dashboards-flag::direct-create-post-returns-403
 *   @e2e allow-personal-dashboards-flag::direct-fork-post-returns-403
 *
 * @spec openspec/changes/allow-personal-dashboards-flag/tasks.md#task-3.4
 * @spec openspec/changes/allow-personal-dashboards-flag/tasks.md#task-3.5
 */

import { test, expect, type APIRequestContext } from '@playwright/test'

const BASE  = (process.env.NC_BASE_URL  ?? 'http://localhost:8080').replace(/\/$/, '')
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const SETTINGS_URL = `${BASE}/index.php/apps/launchpad/api/admin/settings`
const APP_URL      = '/index.php/apps/launchpad'

/** Make an authenticated request context using HTTP Basic auth. */
async function adminApi(playwright: { request: { newContext: typeof import('@playwright/test').request.newContext } }): Promise<APIRequestContext> {
	return playwright.request.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/** Toggle the allow_user_dashboards admin flag. */
async function setAllowUserDashboards(api: APIRequestContext, enabled: boolean): Promise<void> {
	const res = await api.put(SETTINGS_URL, { data: { allowUserDash: enabled } })
	// Non-200 is not fatal for test setup — the flag may be unchanged if
	// the environment already matches.  We log rather than throw so the
	// test still runs and produces a meaningful failure message.
	if (!res.ok()) {
		// eslint-disable-next-line no-console
		console.warn(`setAllowUserDashboards(${enabled}): PUT returned ${res.status()}`)
	}
}

/** Wait for the workspace Vue shell to hydrate past initial bootstrap. */
async function waitForShell(page: ReturnType<typeof test.extend>['page']): Promise<void> {
	await page.goto(APP_URL)
	await page.waitForSelector('.launchpad-floating-controls, .workspace-shell', { timeout: 20_000 })
}

/** Open the dashboard-switcher sidebar via the floating toggle button. */
async function openSidebar(page: ReturnType<typeof test.extend>['page']): Promise<void> {
	// The floating hamburger toggle is always visible in the workspace shell.
	// Target `.launchpad-sidebar-toggle` EXACTLY: `.launchpad-floating-controls`
	// also hosts the dashboard-cog and Share buttons, and the cog precedes the
	// toggle in DOM order — so a comma-selector `.first()` resolves to the cog
	// and opens the wrong popover, leaving the sidebar closed.
	const ham = page.locator('.launchpad-sidebar-toggle').first()
	await expect(ham).toBeVisible({ timeout: 10_000 })
	// Only click if the sidebar is not already open.
	const isOpen = await page.locator('.dashboard-switcher-sidebar.open').isVisible().catch(() => false)
	if (!isOpen) {
		await ham.click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 8_000 })
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Task 3.4 — Button visibility matches the flag state (REQ-ASET-015 frontend)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('allow-personal-dashboards-flag — sidebar button visibility', () => {
	let api: APIRequestContext

	test.beforeAll(async ({ playwright }) => {
		api = await adminApi({ request: playwright.request })
	})

	test.afterAll(async () => {
		// Always restore to enabled so the shared fixture stays usable.
		await setAllowUserDashboards(api, true)
		await api.dispose()
	})

	// @e2e allow-personal-dashboards-flag::sidebar-add-button-hidden-when-flag-off
	test('Add-Dashboard button is hidden in sidebar when allowUserDashboards is false', async ({ page }) => {
		await setAllowUserDashboards(api, false)

		// Full page reload so the server re-pushes the updated initial state.
		await waitForShell(page)

		await openSidebar(page)
		const sidebar = page.locator('.dashboard-switcher-sidebar')
		const addBtn  = sidebar.getByRole('button', { name: /add dashboard|dashboard toevoegen/i })

		// The button MUST be absent from the DOM (v-if removes it entirely).
		await expect(addBtn).toHaveCount(0)
	})

	// @e2e allow-personal-dashboards-flag::sidebar-add-button-visible-when-flag-on
	test('Add-Dashboard button is visible in sidebar when allowUserDashboards is true', async ({ page }) => {
		await setAllowUserDashboards(api, true)

		// Reload to pick up the new initial state.
		await waitForShell(page)

		await openSidebar(page)
		const sidebar = page.locator('.dashboard-switcher-sidebar')
		const addBtn  = sidebar.getByRole('button', { name: /add dashboard|dashboard toevoegen/i })

		await expect(addBtn).toBeVisible({ timeout: 8_000 })
	})
})

// NOTE: The defense-in-depth "API-level 403 enforcement" scenarios
// (direct-create-post-returns-403 / direct-fork-post-returns-403) assert
// raw /api responses, not the UI. Per the gate-19 program they have been
// relocated to tests/e2e/api-direct/allow-personal-dashboards-flag.api.spec.ts
// and their contract coverage lives in Newman.
