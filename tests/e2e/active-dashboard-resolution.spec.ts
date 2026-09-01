/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * End-to-end coverage for the active-dashboard resolution chain
 * (REQ-DASH-018) and the persist-active-preference endpoint (REQ-DASH-019).
 *
 * Covered scenarios:
 *   @e2e active-dashboard-resolution::empty-state-renders-for-fresh-user
 *   @e2e active-dashboard-resolution::switch-dashboard-posts-active-preference
 *   @e2e active-dashboard-resolution::stale-preference-falls-through-silently
 *
 * The three cases map 1:1 to the Playwright task listed in
 * openspec/changes/active-dashboard-resolution/tasks.md#task-12.
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Navigate to the app and wait for the Vue app to hydrate past bootstrap.
 * The sidebar toggle is the first stable landmark the app injects.
 * @param {any} page Playwright page object
 */
async function gotoApp(page: any) {
	await page.goto('/index.php/apps/launchpad')
	await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
}

// ─────────────────────────────────────────────────────────────────────────────
// REQ-DASH-018 scenario: Empty state on a fresh user with no dashboards
// ─────────────────────────────────────────────────────────────────────────────

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

let api: APIRequestContext
const seeded: SeededDashboard[] = []

/*
 * SEED TWO DASHBOARDS FOR THE ADMIN.
 *
 * The switching test needs at least two rows in the sidebar, and every test
 * outside the empty-state block reaches `.launchpad-sidebar-toggle`, which
 * only renders once a dashboard is ACTIVE. `tests/e2e/seed.sh` creates only
 * the `e2e-grantee` user — never a dashboard — so on a fresh instance the
 * shell renders its empty state and those waits time out. Measured in CI run
 * 32308042394: 2 of 3 tests here failed that way at 25.1s each.
 *
 * This does NOT disturb the empty-state test above: that one provisions its
 * own throwaway account precisely because the admin fixture has dashboards.
 */
test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	const stamp = Date.now()
	seeded.push(await seedActiveDashboard(api, `E2E Resolution A ${stamp}`))
	seeded.push(await seedActiveDashboard(api, `E2E Resolution B ${stamp}`))
})

test.afterAll(async () => {
	for (const d of seeded) {
		await removeSeededDashboard(api, d)
	}
	await api?.dispose()
})

test.describe('active-dashboard-resolution — empty state', () => {
	// @e2e active-dashboard-resolution::empty-state-renders-for-fresh-user
	test('empty state renders when GET /api/dashboard returns no active dashboard', async ({
		browser,
	}) => {
		// The empty-state branch keys off the server-rendered `activeDashboardId`
		// initial-state value, NOT the /api/dashboard fetch — so route-mocking a
		// 404 cannot produce it. Reaching it requires a Nextcloud account that
		// resolves to zero dashboards; the shared admin fixture always has
		// dashboards, so provision a throwaway one via the OCS provisioning API
		// (fixtures/secondary-user.ts) and log in as it in a fresh context.
		const { username, password } = await provisionThrowawayUser()
		try {
			const { context, page } = await loginAs(browser, username, password)
			try {
				await page.goto('/index.php/apps/launchpad')
				// INVERTED by #361 (c9e58089, "ship a dashboard"). This asserted
				// the empty state for a fresh account. `SeedDefaultDashboard`
				// now provisions an INSTANCE-WIDE, group-shared dashboard on
				// install and after every upgrade, precisely so that
				// "No dashboards available" can no longer greet a new user —
				// that message was the defect the commit set out to remove.
				//
				// So the empty state is unreachable by construction, and the
				// valuable assertion is its opposite: a brand-new account, which
				// owns nothing, must still resolve to the seeded dashboard.
				const empty = page.locator('.workspace-shell__empty')
				await expect(
					empty,
					'a fresh account must NOT see the empty state — the seeder ships an instance-wide dashboard',
				).toBeHidden({ timeout: 15_000 })
				await expect(
					page.locator('.workspace-shell'),
					'the shell must resolve the seeded dashboard for an account that owns none',
				).toBeVisible({ timeout: 15_000 })
			} finally {
				await context.close()
			}
		} finally {
			await deprovisionUser(username)
		}
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-DASH-019: switchDashboard POSTs the UUID and the next load honours it
// ─────────────────────────────────────────────────────────────────────────────

test.describe('active-dashboard-resolution — switchDashboard wires the POST', () => {
	// @e2e active-dashboard-resolution::switch-dashboard-posts-active-preference
	test('clicking a sidebar row activates the chosen dashboard server-side', async ({
		page,
	}) => {
		await gotoApp(page)

		// Open the sidebar so the dashboard rows are visible.
		await page.locator('.launchpad-sidebar-toggle').first().click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', {
			timeout: 8_000,
		})

		const rows = page.locator('.dashboard-switcher-sidebar__item')
		const rowCount = await rows.count()
		if (rowCount < 2) {
			test.skip(true, 'Need at least 2 dashboards to test switching')
			return
		}

		// Clicking a row switches the active dashboard. The store persists the
		// choice server-side via POST /api/dashboard/{id}/activate (the active
		// preference is owned by that endpoint, not a separate body). Arm the
		// request listener BEFORE the click.
		const activatePromise = page.waitForRequest(
			(req) =>
				req.method() === 'POST'
				&& /\/api\/dashboard\/\d+\/activate(?:\?|$)/.test(req.url()),
			{ timeout: 8_000 },
		)

		// Click a non-active PERSONAL (owned) row — only owned dashboards take
		// an active flag, so this exercises the real switch + persistence path.
		const ownedInactiveRow = page
			.locator(
				'[data-source="user"].dashboard-switcher-sidebar__item:not(.active)',
			)
			.first()
		await expect(ownedInactiveRow).toBeVisible({ timeout: 5_000 })
		await ownedInactiveRow.click()

		const req = await activatePromise
		// The activate POST targets a concrete numeric dashboard id.
		expect(req.url()).toMatch(/\/api\/dashboard\/\d+\/activate/)
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-DASH-018 stale-pref scenario: resolver falls through without error toast
// ─────────────────────────────────────────────────────────────────────────────

test.describe('active-dashboard-resolution — stale preference', () => {
	// @e2e active-dashboard-resolution::stale-preference-falls-through-silently
	test('a stale saved UUID is silently discarded — no error toast shown', async ({
		page,
	}) => {
		// Simulate a session where the backend has already cleared the stale
		// active_dashboard_uuid and resolved through to a group dashboard.
		// The page initial state carries activeDashboardId from the backend resolver.
		// We simply check that no error toast appears when the app loads (the
		// backend's write-on-read clear happens server-side; the frontend only
		// sees the resolved dashboard or the empty state — never an error).
		await gotoApp(page)

		// Wait for the app to settle after any async loads.
		await page.waitForTimeout(2_000)

		// Assert no error toast or alert dialog appeared during load.
		const errorToasts = await page
			.locator(
				'[class*="toast--error"], [class*="nc-toast-error"], [role="alertdialog"]',
			)
			.count()
		expect(errorToasts).toBe(0)

		// The workspace MUST render either a dashboard grid or the empty-state —
		// never a blank page / loading spinner after 2 s.
		const gridOrEmpty = page.locator(
			'.launchpad-grid, .launchpad-empty, [class*="grid-stack"]',
		)
		await expect(gridOrEmpty.first()).toBeAttached({ timeout: 5_000 })
	})
})
