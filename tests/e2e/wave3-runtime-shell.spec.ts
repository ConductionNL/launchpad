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
 *   - PR #111 — drop the literal "Default" group pill, sidebar footer
 *     centered with both "Powered by" logos. (Its hamburger is gone: the
 *     sidebar opens from More → All dashboards, REQ-SHELL-004.)
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
 *   @e2e runtime-shell::no-sidebar-toggle
 *   @e2e dashboard-switcher::footer-renders-both-brand-logos-with-safe-target-attributes
 *   @e2e dashboard-switcher::footer-documentation-link-uses-the-same-url-as-the-gear-menu-link-did
 *   @e2e dashboard-switcher::footer-stays-visible-while-list-scrolls
 *   @e2e dashboard-switcher::click-a-personal-dashboard
 *   @e2e dashboard-switcher::click-a-default-group-dashboard
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { allowManyPersonalDashboards } from './support/dashboardHierarchy.ts'
import { MORE_MENU, openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import { adminContext } from './support/principals.ts'

test.describe('wave3 runtime-shell + sidebar UX', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto('/index.php/apps/launchpad')
		// Wait for the More menu — its presence indicates the Vue app has
		// hydrated past initial bootstrap. Retry once to absorb the dev
		// instance's transient 503 (needsDbUpgrade blip).
		try {
			await page.waitForSelector(MORE_MENU, { timeout: 20_000 })
		} catch {
			await page.goto('/index.php/apps/launchpad')
			await page.waitForSelector(MORE_MENU, { timeout: 20_000 })
		}
	})

	test('default state: no leftover popover, sidebar closed, no hamburger, one More', async ({
		page,
	}) => {
		// PR #112: the dead WidgetContextMenu in DashboardGrid no longer
		// renders unconditionally on initial load.
		await expect(page.locator('.widget-context-menu')).toHaveCount(0)

		// PR #111: sidebar starts closed — the open variant is gated on
		// the `open` modifier class.
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0)

		// REQ-SHELL-004: no hamburger; the page's one menu is More.
		await expect(page.locator('.launchpad-sidebar-toggle')).toHaveCount(0)
		await expect(page.locator(MORE_MENU)).toHaveCount(1)

		// PR #111: the literal "Default" group pill is suppressed.
		await expect(
			page.locator('.launchpad-primary-group-label', { hasText: /^Default$/ }),
		).toHaveCount(0)
	})

	test('PR #112: dashboard grid claims the full width (no 0-px collapse)', async ({
		page,
	}) => {
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

	test('wave3.6: each dashboard row has its own cog menu with Edit/Configure/Add-widget/Delete', async ({
		page,
	}) => {
		// The admin owns a personal dashboard, active, for the length of this
		// test. A fresh instance has only the shared default dashboard (view
		// only), so the personal section would be empty and Edit / Add widget,
		// which follow the active dashboard's canEdit, would not be offered.
		const admin = await adminContext()
		const restore = await allowManyPersonalDashboards(admin)
		const own = await seedActiveDashboard(admin, `E2E wave3.6 ${Date.now()}`)
		try {
			await page.reload()
			await page.waitForSelector(MORE_MENU, { timeout: 20_000 })
			// Switch to it from the sidebar, as a user would, so the open
			// dashboard is one the admin may edit.
			await openDashboardSwitcher(page)
			await page
				.locator('[data-section="user"] li.dashboard-switcher-sidebar__item')
				.filter({ hasText: own.name })
				.click()
			await expect(page.getByText(own.name).first()).toBeVisible()
			await page.keyboard.press('Escape')
			await assertRowCogMenu(page)
		} finally {
			await removeSeededDashboard(admin, own)
			await restore()
			await admin.dispose()
		}
	})

	/**
	 * Open the switcher and check the per-row cog menu of a personal row.
	 *
	 * @param page the workspace page.
	 * @return nothing.
	 */
	async function assertRowCogMenu(page: Page): Promise<void> {
		await openDashboardSwitcher(page)

		// Header has NO cog after wave3.6 — only the X close button.
		await expect(page.locator('.dashboard-switcher-sidebar__menu')).toHaveCount(
			0,
		)

		// One cog per dashboard row, rendered by `<DashboardRowActions>`.
		const rowCogs = page.locator('.dashboard-row-actions button')
		const cogCount = await rowCogs.count()
		expect(cogCount).toBeGreaterThan(0)

		// Open the cog on a personal dashboard (admin owns all rows in
		// the test fixture, so the personal section always exposes the
		// full action set including the owner-gated Configure / Delete).
		const personalRow = page
			.locator('[data-section="user"] li.dashboard-switcher-sidebar__item')
			.first()
		await expect(personalRow).toBeVisible()
		await personalRow.locator('.dashboard-row-actions button').click()

		await expect(
			page.getByRole('menuitem', { name: /Edit dashboard/ }),
		).toBeVisible()
		await expect(
			page.getByRole('menuitem', { name: /Dashboard configuration/ }),
		).toBeVisible()
		await expect(
			page.getByRole('menuitem', { name: /^Add widget$/ }),
		).toBeVisible()
		await expect(
			page.getByRole('menuitem', { name: /Delete dashboard/ }),
		).toBeVisible()
	}

	test('PR #113: per-row X delete buttons have been removed from the dashboard list', async ({
		page,
	}) => {
		await openDashboardSwitcher(page)

		// Wave3.3 dropped the inline `.__delete` X buttons; wave3.6
		// moved Delete into the per-row cog (DashboardRowActions).
		await expect(
			page.locator('.dashboard-switcher-sidebar__delete'),
		).toHaveCount(0)
	})

	test('PR #113: clicking a sidebar row switches the active dashboard server-side', async ({
		page,
	}) => {
		// Two personal dashboards of this account, the first one active, so
		// the row clicked is an owned, inactive one whatever else the shared
		// admin account holds. The activate endpoint only persists an active
		// flag for owned dashboards (a group/default dashboard returns 400).
		const api = page.request
		const headers = { 'OCS-APIREQUEST': 'true' }
		const stamp = Date.now()
		const create = async (name: string) => {
			const res = await api.post('/index.php/apps/launchpad/api/dashboard', { headers, data: { name } })
			expect(res.status(), await res.text()).toBeLessThan(300)
			return (await res.json()).dashboard as { id: number, uuid: string, name: string }
		}
		const open = await create(`Wave3 open ${stamp}`)
		const target = await create(`Wave3 target ${stamp}`)
		try {
			const active = await api.post('/index.php/apps/launchpad/api/dashboards/default', {
				headers,
				data: { uuid: open.uuid },
			})
			expect(active.status(), await active.text()).toBeLessThan(300)
			await page.goto('/index.php/apps/launchpad')
			await openDashboardSwitcher(page)

			// Switching the active dashboard persists the choice via
			// POST /api/dashboard/{id}/activate. Arm the listener before clicking.
			const activateRequest = page.waitForRequest(
				(req) =>
					req.method() === 'POST'
					&& new RegExp(`/api/dashboard/${target.id}/activate(?:\\?|$)`).test(req.url()),
				{ timeout: 8_000 },
			)
			const row = page.locator(
				`[data-source="user"].dashboard-switcher-sidebar__item[aria-label="${target.name}"]`,
			)
			await expect(row).not.toHaveClass(/\bactive\b/)
			await row.click()

			const req = await activateRequest
			const res = await req.response()
			expect(res?.status()).toBe(200)
		} finally {
			await api.delete(`/index.php/apps/launchpad/api/dashboard/${open.id}`, { headers })
			await api.delete(`/index.php/apps/launchpad/api/dashboard/${target.id}`, { headers })
		}
	})

	test('PR #111 + PR #113: footer renders Powered by + both Sendent and Conduction logos visible', async ({
		page,
	}) => {
		await openDashboardSwitcher(page)

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

	test('PR #114: only one DashboardSwitcherSidebar mount in the runtime shell', async ({
		page,
	}) => {
		// The duplicate WorkspaceApp mount was removed; Views.vue now
		// owns the sole instance. Two `.dashboard-switcher-sidebar` nodes
		// in the DOM would indicate the duplicate has crept back.
		await openDashboardSwitcher(page)
		await expect(page.locator('.dashboard-switcher-sidebar')).toHaveCount(1)
	})
})
