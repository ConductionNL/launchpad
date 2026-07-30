/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * End-to-end coverage for the `runtime-shell` capability.
 *
 * Covers the hamburger sidebar toggle, the fixed backdrop, and the
 * empty-state CTA branches. The in-page edit toolbar was removed — editing
 * actions now live in the per-dashboard cog menu (DashboardRowActions) — so
 * the former toolbar / Save-Layout scenarios no longer apply. These tests
 * run against a live Nextcloud instance with the launchpad app enabled.
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-shell::hamburger-toggles-sidebar
 *   @e2e runtime-shell::backdrop-click-closes-sidebar
 *   @e2e runtime-shell::empty-state-with-allow-user-dashboards
 *   @e2e runtime-shell::empty-state-without-allow-user-dashboards
 *   @e2e action-authorization::fresh-install-is-usable-by-non-admins
 *
 * @spec openspec/changes/runtime-shell/tasks.md#task-9
 * @spec openspec/changes/runtime-shell/tasks.md#task-10
 * @spec openspec/architecture/adr-023-action-authorization.md
 */

import { test, expect, request as pwRequest } from '@playwright/test'
import { provisionThrowawayUser, deprovisionUser, loginAs } from './fixtures/secondary-user'

const BASE = (process.env.NC_BASE_URL ?? 'http://localhost:8080').replace(/\/$/, '')
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const APP_URL = '/index.php/apps/launchpad'
const SETTINGS_URL = `${BASE}/index.php/apps/launchpad/api/admin/settings`

/**
 * Toggle the allow_user_dashboards admin flag (mirrors the helper in
 * allow-personal-dashboards-flag.spec.ts).
 *
 * MUST use its own Basic-Auth + `OCS-APIRequest` context, NOT the built-in
 * cookie-authenticated `request` fixture: a cookie-carrying request without
 * a CSRF `requesttoken` gets rejected outright by Nextcloud's CSRF check on
 * this state-changing route (measured: `PUT` returns 412). That failure was
 * previously swallowed by a `console.warn` instead of failing the test, so
 * both empty-state scenarios below ran against whatever the flag happened
 * to already be, not the value each scenario asked for — throws now so a
 * write failure can never again look like a passing test.
 *
 * @param {boolean} enabled The value to set.
 * @return {Promise<void>}
 */
async function setAllowUserDashboards(enabled: boolean): Promise<void> {
	const api = await pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	try {
		const res = await api.put(SETTINGS_URL, { data: { allowUserDash: enabled } })
		if (!res.ok()) {
			throw new Error(`setAllowUserDashboards(${enabled}): PUT ${SETTINGS_URL} returned ${res.status()}`)
		}
	} finally {
		await api.dispose()
	}
}

/**
 * Wait for the Vue shell to hydrate past initial bootstrap.
 * The floating sidebar toggle indicates the workspace has mounted.
 */
async function waitForShell(page: ReturnType<typeof test.extend>['page']) {
	await page.goto(APP_URL)
	await page.waitForSelector('.launchpad-floating-controls, .workspace-shell', { timeout: 15_000 })
}

test.describe('REQ-SHELL-004: hamburger sidebar toggle (wave3 fixture)', () => {
	test.beforeEach(async ({ page }) => {
		await waitForShell(page)
	})

	test('hamburger opens the sidebar', async ({ page }) => {
		const ham = page.locator('.launchpad-sidebar-toggle').first()
		await expect(ham).toBeVisible()

		// Sidebar should be closed initially.
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0)

		await ham.click()
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(1)
	})

	test('clicking the hamburger again closes the sidebar', async ({ page }) => {
		const ham = page.locator('.launchpad-sidebar-toggle').first()
		await ham.click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })

		await ham.click()
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0)
	})
})

test.describe('REQ-SHELL-006: backdrop click closes sidebar', () => {
	test.beforeEach(async ({ page }) => {
		await waitForShell(page)
	})

	test('clicking the backdrop area closes the sidebar', async ({ page }) => {
		// Open the sidebar first.
		const ham = page.locator('.launchpad-sidebar-toggle').first()
		await ham.click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })

		// Click the backdrop (area outside the sidebar panel).
		const backdrop = page.locator('.launchpad-sidebar-backdrop')
		await expect(backdrop).toBeVisible()
		await backdrop.click()

		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0)
	})

	test('clicking inside the sidebar panel does NOT close the sidebar', async ({ page }) => {
		const ham = page.locator('.launchpad-sidebar-toggle').first()
		await ham.click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })

		// Click on a safe non-interactive area inside the sidebar (its header).
		const sidebar = page.locator('.dashboard-switcher-sidebar')
		await sidebar.click({ position: { x: 10, y: 5 } })

		// The sidebar should remain open.
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(1)
	})
})

test.describe('REQ-SHELL-005: empty state', () => {
	// The empty-state branch (WorkspaceApp.vue `v-else` on `hasActiveDashboard`)
	// is driven ENTIRELY by the server-rendered initial-state key
	// `activeDashboardId`, NOT by any /api fetch — so route-mocking the
	// dashboard list cannot trigger it. Reaching it requires a Nextcloud
	// account that resolves to zero dashboards (no personal, no group, no
	// default). The shared admin fixture always has dashboards, so each
	// scenario provisions its own throwaway account via the OCS
	// provisioning API (`fixtures/secondary-user.ts`) and logs in as it in
	// a fresh browser context, then tears it down afterwards.
	let throwawayUser: string | undefined

	test.afterEach(async () => {
		if (throwawayUser) {
			await deprovisionUser(throwawayUser)
			throwawayUser = undefined
		}
	})

	// @e2e runtime-shell::empty-state-with-allow-user-dashboards
	test('empty state renders with Create CTA when allowUserDashboards is true', async ({ browser }) => {
		await setAllowUserDashboards(true)
		const { username, password } = await provisionThrowawayUser()
		throwawayUser = username

		const { context, page } = await loginAs(browser, username, password)
		try {
			await page.goto(APP_URL)
			const empty = page.locator('.workspace-shell__empty')
			await expect(empty).toBeVisible({ timeout: 15_000 })
			await expect(empty.locator('.workspace-shell__empty-title')).toHaveText(/no dashboards available/i)
			await expect(empty.locator('.workspace-shell__empty-cta')).toBeVisible()
			await expect(empty.locator('.workspace-shell__empty-cta')).toHaveText(/create your first dashboard/i)
		} finally {
			await context.close()
		}
	})

	// @e2e runtime-shell::empty-state-without-allow-user-dashboards
	test('empty state renders without Create CTA when allowUserDashboards is false', async ({ browser }) => {
		await setAllowUserDashboards(false)
		const { username, password } = await provisionThrowawayUser()
		throwawayUser = username

		const { context, page } = await loginAs(browser, username, password)
		try {
			await page.goto(APP_URL)
			const empty = page.locator('.workspace-shell__empty')
			await expect(empty).toBeVisible({ timeout: 15_000 })
			await expect(empty.locator('.workspace-shell__empty-title')).toHaveText(/no dashboards available/i)
			await expect(empty.locator('.workspace-shell__empty-hint')).toHaveText(/contact your administrator/i)
			await expect(empty.locator('.workspace-shell__empty-cta')).toHaveCount(0)
		} finally {
			await context.close()
			// Restore the default so later specs in the run see the flag on.
			await setAllowUserDashboards(true)
		}
	})
})

/*
 * ADR-023 — a fresh install must be usable by ordinary, non-admin users.
 *
 * These are the only scenarios in the whole suite that make an authenticated
 * request as a genuinely non-admin, non-elevated account (every other spec
 * runs as admin via the shared global-setup.ts storageState), which is why
 * the all-admin action matrix survived this long unnoticed.
 *
 * Before the non-admin baseline shipped, the seeded matrix mapped EVERY
 * declared action to ["admin"], so a brand-new account got
 * `OCSForbiddenException: Action 'dashboard.list' requires admin rights`
 * on the very first AJAX call the app makes — while the empty state
 * cheerfully offered it a "Create your first dashboard" button that could
 * only ever 403.
 */
test.describe('ADR-023: fresh install is usable by non-admins', () => {
	let throwawayUser: string | undefined

	test.afterEach(async () => {
		if (throwawayUser) {
			await deprovisionUser(throwawayUser)
			throwawayUser = undefined
		}
	})

	/**
	 * Build an API context authenticated as an ordinary (non-admin) user.
	 *
	 * @param {string} username The account.
	 * @param {string} password Its password.
	 * @return {Promise<import('@playwright/test').APIRequestContext>} the context.
	 */
	async function userApi(username: string, password: string) {
		return pwRequest.newContext({
			baseURL: BASE,
			httpCredentials: { username, password },
			extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
		})
	}

	// @e2e action-authorization::fresh-install-is-usable-by-non-admins
	test('an ordinary user may list and read dashboards, but not touch admin surfaces', async () => {
		test.setTimeout(90_000)

		const { username, password } = await provisionThrowawayUser('e2e-nonadmin')
		throwawayUser = username

		const api = await userApi(username, password)
		try {
			// The ordinary end-user surface must NOT be forbidden. A 403 here
			// is exactly the defect: the app refusing its own users.
			for (const path of [
				'/index.php/apps/launchpad/api/dashboards',
				'/index.php/apps/launchpad/api/dashboards/visible',
				'/index.php/apps/launchpad/api/widgets',
			]) {
				const res = await api.get(path)
				expect(
					res.status(),
					`${path} must not be forbidden for an ordinary user`,
				).not.toBe(403)
			}

			// The other half of the decision: administrative surfaces stay
			// admin-only. If this ever returns 200 the baseline has been
			// over-broadened into a privilege escalation.
			// NOTE: this MUST be the real route (`appinfo/routes.php`:
			// `analytics#instanceSummary` -> `/api/admin/analytics/summary`).
			// An earlier draft of this test used a made-up path and got 404,
			// which would have sailed through a `not.toBe(403)` style check —
			// a 404 proves nothing about authorization.
			const analytics = await api.get(
				'/index.php/apps/launchpad/api/admin/analytics/summary',
			)
			expect(
				analytics.status(),
				'instance-wide analytics must stay admin-only',
			).toBe(403)
		} finally {
			await api.dispose()
		}
	})

	// @e2e action-authorization::fresh-install-is-usable-by-non-admins
	test('the empty-state Create CTA it is offered actually works', async ({ browser }) => {
		test.setTimeout(90_000)

		await setAllowUserDashboards(true)
		const { username, password } = await provisionThrowawayUser('e2e-nonadmin-cta')
		throwawayUser = username

		const { context, page } = await loginAs(browser, username, password)
		try {
			await page.goto(APP_URL)
			const cta = page.locator('.workspace-shell__empty-cta')
			await expect(cta).toBeVisible({ timeout: 20_000 })

			// Pre-baseline this click 403'd on `dashboard.create` and the
			// user stayed stranded on the empty state forever.
			const [res] = await Promise.all([
				page.waitForResponse(
					(r) => r.url().includes('/apps/launchpad/api/dashboard')
						&& r.request().method() === 'POST',
					{ timeout: 25_000 },
				),
				cta.click(),
			])
			expect(
				res.status(),
				'the Create CTA the app itself offers must not be forbidden',
			).toBe(200)

			// `hasActiveDashboard` comes from the server-rendered initial
			// state, so the empty state only clears on the next load — which
			// is also the honest end-to-end check that the row persisted.
			await page.reload()
			await expect(
				page.locator('.workspace-shell__empty'),
				'after creating a dashboard the user must not land on the empty state again',
			).toHaveCount(0, { timeout: 25_000 })
		} finally {
			await context.close()
		}
	})
})
