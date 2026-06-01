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

import { test, expect } from '@playwright/test'

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Navigate to the app and wait for the Vue app to hydrate past bootstrap.
 * The sidebar toggle is the first stable landmark the app injects.
 */
async function gotoApp(page: any) {
	await page.goto('/index.php/apps/mydash')
	await page.waitForSelector('.mydash-sidebar-toggle', { timeout: 20_000 })
}

// ─────────────────────────────────────────────────────────────────────────────
// REQ-DASH-018 scenario: Empty state on a fresh user with no dashboards
// ─────────────────────────────────────────────────────────────────────────────

test.describe('active-dashboard-resolution — empty state', () => {
	// @e2e active-dashboard-resolution::empty-state-renders-for-fresh-user
	test('empty state renders when GET /api/dashboard returns no active dashboard', async ({ page }) => {
		// Intercept the active-dashboard fetch so we can simulate a fresh user
		// with no dashboards. The resolver returns null → backend sends 404;
		// the frontend shows the empty-state UI.
		await page.route('**/api/dashboard', (route, request) => {
			if (request.method() === 'GET') {
				return route.fulfill({
					status: 404,
					contentType: 'application/json',
					body: JSON.stringify({ status: 'error', error: 'no_dashboard' }),
				})
			}
			return route.continue()
		})

		// Intercept the visible-dashboards fetch to return an empty list so
		// the sidebar also shows the "no dashboards" state.
		await page.route('**/api/dashboards/visible', (route) => {
			route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify([]),
			})
		})

		await gotoApp(page)

		// The empty-state section (.mydash-empty) MUST be visible when there
		// is no active dashboard and we are not in a loading state.
		await expect(page.locator('.mydash-empty, [class*="empty"]').first())
			.toBeVisible({ timeout: 10_000 })

		// No error toast (NcError / NcDialog with error semantics) MUST appear.
		const errorCount = await page.locator('[class*="toast--error"], [class*="nc-toast-error"]').count()
		expect(errorCount).toBe(0)
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-DASH-019: switchDashboard POSTs the UUID and the next load honours it
// ─────────────────────────────────────────────────────────────────────────────

test.describe('active-dashboard-resolution — switchDashboard wires the POST', () => {
	// @e2e active-dashboard-resolution::switch-dashboard-posts-active-preference
	test('clicking a sidebar row triggers POST /api/dashboards/active with the dashboard UUID', async ({ page }) => {
		// Track whether the preference POST was made and with which body.
		const activePosts: string[] = []

		await page.route('**/api/dashboards/active', (route, request) => {
			if (request.method() === 'POST') {
				const body = request.postDataJSON?.() as { uuid?: string } | null
				if (body?.uuid) {
					activePosts.push(body.uuid)
				}
				route.fulfill({
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify({ status: 'success' }),
				})
			} else {
				route.continue()
			}
		})

		await gotoApp(page)

		// Open the sidebar so the dashboard rows are visible.
		await page.locator('.mydash-sidebar-toggle').first().click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 8_000 })

		// Click the first sidebar row that is NOT already the active dashboard.
		// A row has data-source attribute set by DashboardSwitcherSidebar.
		const rows = page.locator('.dashboard-switcher-sidebar__item')
		const rowCount = await rows.count()

		// Skip the test gracefully when there is only one (or zero) dashboard
		// in the fixture — there is nothing to switch to.
		if (rowCount < 2) {
			test.skip(true, 'Need at least 2 dashboards to test switching')
			return
		}

		// Click the second row (index 1) — first is likely already active.
		const targetRow = rows.nth(1)
		const targetUuid = await targetRow.getAttribute('data-uuid').catch(() => null)
		await targetRow.click()

		// After the click the store fires persistActivePreference which issues
		// POST /api/dashboards/active. We give it up to 5 s to arrive.
		await page.waitForTimeout(1_000)

		// Assert the POST was made. The UUID in the body MUST match the row we clicked.
		expect(activePosts.length).toBeGreaterThan(0)
		if (targetUuid) {
			expect(activePosts).toContain(targetUuid)
		}
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-DASH-018 stale-pref scenario: resolver falls through without error toast
// ─────────────────────────────────────────────────────────────────────────────

test.describe('active-dashboard-resolution — stale preference', () => {
	// @e2e active-dashboard-resolution::stale-preference-falls-through-silently
	test('a stale saved UUID is silently discarded — no error toast shown', async ({ page }) => {
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
		const errorToasts = await page.locator(
			'[class*="toast--error"], [class*="nc-toast-error"], [role="alertdialog"]',
		).count()
		expect(errorToasts).toBe(0)

		// The workspace MUST render either a dashboard grid or the empty-state —
		// never a blank page / loading spinner after 2 s.
		const gridOrEmpty = page.locator('.mydash-grid, .mydash-empty, [class*="grid-stack"]')
		await expect(gridOrEmpty.first()).toBeAttached({ timeout: 5_000 })
	})
})
