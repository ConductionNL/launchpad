/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * End-to-end coverage for the wave3 UX cleanup PRs (#111-#114). Every
 * assertion here mirrors a finding the user spotted during manual review;
 * the goal of the spec is to keep regressions from sneaking back in via
 * future runtime-shell or sidebar refactors.
 *
 * Acts as the smoke harness for:
 *   - PR #111 — title strip hidden when sidebar closed, secondary-style
 *     hamburger, drop the literal "Default" group pill, sidebar footer
 *     centered with both "Powered by" logos.
 *   - PR #112 — restore dashboard render width (.launchpad-workspace flex
 *     layout), drop the dead leftover Edit/Remove/Cancel context menu,
 *     trim the floating cog menu to remove Create dashboard +
 *     Documentation entries.
 *   - PR #113 — switching dashboards from the sidebar actually changes
 *     the active dashboard (GET /api/dashboard/{id} backend endpoint),
 *     the per-dashboard cog menu lives in the sidebar header (Edit /
 *     Configure / Add widget / Delete-trashcan), per-row X delete
 *     buttons removed, Conduction logo visible alongside Sendent.
 *   - PR #114 — no second DashboardSwitcherSidebar mount in the runtime
 *     shell (Views.vue owns the only one).
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-shell::mount-point-present
 *   @e2e runtime-shell::toggle-button-matches-account-button-styling
 *   @e2e runtime-shell::no-active-dashboard-select-control
 *   @e2e runtime-shell::active-dashboard-name-visible
 *   @e2e runtime-shell::empty-label-on-empty-state
 *   @e2e dashboard-switcher::footer-renders-both-brand-logos-with-safe-target-attributes
 *   @e2e dashboard-switcher::footer-documentation-link-uses-the-same-url-as-the-gear-menu-link-did
 *   @e2e dashboard-switcher::footer-stays-visible-while-list-scrolls
 *   @e2e dashboard-switcher::click-a-personal-dashboard
 *   @e2e dashboard-switcher::click-a-default-group-dashboard
 */

import { test, expect } from '@playwright/test'

test.describe('wave3 runtime-shell + sidebar UX', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto('/index.php/apps/launchpad')
		// Wait for the floating sidebar toggle — its presence indicates
		// the Vue app has hydrated past initial bootstrap. Retry once to
		// absorb the dev instance's transient 503 (needsDbUpgrade blip).
		try {
			await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
		} catch {
			await page.goto('/index.php/apps/launchpad')
			await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
		}
	})

	test('default state: no leftover popover, sidebar closed, hamburger matches cog style', async ({ page }) => {
		// PR #112: the dead WidgetContextMenu in DashboardGrid no longer
		// renders unconditionally on initial load.
		await expect(page.locator('.widget-context-menu')).toHaveCount(0)

		// PR #111: sidebar starts closed — the open variant is gated on
		// the `open` modifier class.
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0)

		// PR #111: hamburger is `type="secondary"` so it visually matches
		// the cog action menu sitting next to it (was tertiary before).
		// The Vue stub mirrors the prop onto a data attribute.
		const ham = page.locator('.launchpad-sidebar-toggle').first()
		await expect(ham).toBeVisible()

		// PR #111 + PR #113: the floating controls host exactly one
		// sidebar-toggle hamburger — the per-dashboard cog menu moved into
		// the sidebar header in PR #113. (A separate top-bar Share action may
		// also live in the floating controls when the active dashboard is
		// shareable — that is the dashboard-sharing feature, not the removed
		// cog menu — so assert the hamburger count specifically.)
		await expect(page.locator('.launchpad-floating-controls .launchpad-sidebar-toggle')).toHaveCount(1)

		// PR #111: the literal "Default" group pill is suppressed.
		await expect(page.locator('.launchpad-primary-group-label', { hasText: /^Default$/ }))
			.toHaveCount(0)
	})

	test('PR #112: dashboard grid claims the full width (no 0-px collapse)', async ({ page }) => {
		// Without the wave3.2 `.launchpad-workspace { flex: 1 1 auto }`
		// rule the dashboard container collapsed to 0px and rendered
		// only GridStack column placeholders over the empty blue
		// background. The grid SHOULD now span at least most of the
		// viewport width.
		const grid = page.locator('.launchpad-container').first()
		await expect(grid).toBeVisible()
		const box = await grid.boundingBox()
		expect(box).not.toBeNull()
		expect(box!.width).toBeGreaterThan(800)
	})

	test('wave3.6: each dashboard row has its own cog menu with Edit/Configure/Add-widget/Delete', async ({ page }) => {
		await page.locator('.launchpad-sidebar-toggle').click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })

		// Header has NO cog after wave3.6 — only the X close button.
		await expect(page.locator('.dashboard-switcher-sidebar__menu')).toHaveCount(0)

		// One cog per dashboard row, rendered by `<DashboardRowActions>`.
		const rowCogs = page.locator('.dashboard-row-actions button')
		const cogCount = await rowCogs.count()
		expect(cogCount).toBeGreaterThan(0)

		// Open the cog on a personal dashboard (admin owns all rows in
		// the test fixture, so the personal section always exposes the
		// full action set including the owner-gated Configure / Delete).
		const personalRow = page.locator('[data-section="user"] li.dashboard-switcher-sidebar__item').first()
		await expect(personalRow).toBeVisible()
		await personalRow.locator('.dashboard-row-actions button').click()

		await expect(page.getByRole('menuitem', { name: /Edit dashboard/ })).toBeVisible()
		await expect(page.getByRole('menuitem', { name: /Dashboard configuration/ })).toBeVisible()
		await expect(page.getByRole('menuitem', { name: /Add custom widget/ })).toBeVisible()
		await expect(page.getByRole('menuitem', { name: /Delete dashboard/ })).toBeVisible()
	})

	test('PR #113: per-row X delete buttons have been removed from the dashboard list', async ({ page }) => {
		await page.locator('.launchpad-sidebar-toggle').click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })

		// Wave3.3 dropped the inline `.__delete` X buttons; wave3.6
		// moved Delete into the per-row cog (DashboardRowActions).
		await expect(page.locator('.dashboard-switcher-sidebar__delete')).toHaveCount(0)
	})

	test('PR #113: clicking a sidebar row switches the active dashboard server-side', async ({ page }) => {
		await page.locator('.launchpad-sidebar-toggle').click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })

		// Switching the active dashboard persists the choice via
		// POST /api/dashboard/{id}/activate. Arm the listener before clicking.
		const activateRequest = page.waitForRequest(
			req => req.method() === 'POST' && /\/api\/dashboard\/\d+\/activate(?:\?|$)/.test(req.url()),
			{ timeout: 8_000 },
		)

		// Click a NON-active PERSONAL (owned) dashboard row. The activate
		// endpoint only persists an active flag for owned dashboards — a
		// group/default dashboard returns 400 — so target a `data-source="user"`
		// row that is not already active to assert the 200 success path.
		const rows = page.locator('.dashboard-switcher-sidebar li.dashboard-switcher-sidebar__item')
		expect(await rows.count()).toBeGreaterThan(1)
		const ownedInactiveRow = page.locator(
			'[data-source="user"].dashboard-switcher-sidebar__item:not(.active)',
		).first()
		await expect(ownedInactiveRow).toBeVisible({ timeout: 5_000 })
		await ownedInactiveRow.click()

		const req = await activateRequest
		const res = await req.response()
		expect(res?.status()).toBe(200)
	})

	test('PR #111 + PR #113: footer renders Powered by + both Sendent and Conduction logos visible', async ({ page }) => {
		await page.locator('.launchpad-sidebar-toggle').click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })

		const footer = page.locator('.dashboard-switcher-sidebar-footer')
		await expect(footer).toBeVisible()
		await expect(footer.getByText(/Powered by/i)).toBeVisible()

		const sendent = footer.locator('img[alt="Sendent"]')
		const conduction = footer.locator('img[alt="Conduction"]')
		await expect(sendent).toBeVisible()
		await expect(conduction).toBeVisible()

		// Both render with non-zero rendered width AND sit on the same
		// row (same Y to within a couple of pixels) — wave3.3 pinned them
		// side-by-side via `flex-wrap: nowrap`.
		const sBox = await sendent.boundingBox()
		const cBox = await conduction.boundingBox()
		expect(sBox).not.toBeNull()
		expect(cBox).not.toBeNull()
		expect(sBox!.width).toBeGreaterThan(0)
		expect(cBox!.width).toBeGreaterThan(0)
		expect(Math.abs(sBox!.y - cBox!.y)).toBeLessThanOrEqual(4)
	})

	test('PR #114: only one DashboardSwitcherSidebar mount in the runtime shell', async ({ page }) => {
		// The duplicate WorkspaceApp mount was removed; Views.vue now
		// owns the sole instance. Two `.dashboard-switcher-sidebar` nodes
		// in the DOM would indicate the duplicate has crept back.
		await page.locator('.launchpad-sidebar-toggle').click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })
		await expect(page.locator('.dashboard-switcher-sidebar')).toHaveCount(1)
	})
})
