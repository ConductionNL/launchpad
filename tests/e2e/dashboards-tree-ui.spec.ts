/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard tree in the interface: nesting in the switcher, the
 * breadcrumb above a child dashboard, and the parent field of the dashboard
 * settings refusing a cycle (openspec/specs/dashboards/spec.md,
 * REQ-TREEUI-001..003).
 *
 * Sanne is a throwaway account whose dashboards are seeded through the API
 * (HR with the children Onboarding and Vacation rules); everything she does
 * after that goes through the switcher, the breadcrumb and the settings
 * dialog. Pieter is a second account without any parent relation.
 *
 * The scenario names HR a GROUP dashboard. No endpoint can give a group
 * dashboard a parent or put a dashboard under one in the same section:
 * createGroup() and updateGroup() take no parentUuid
 * (lib/Controller/DashboardApiController.php:1123,1215), the personal PUT
 * refuses a row its caller does not own (DashboardService::updateDashboard),
 * and the switcher nests only within one section
 * (DashboardSwitcherSidebar.vue splitForest). So the tree is Sanne's own;
 * it renders through the same treeRows() as any section.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::nested-onboarding-pages
 *   @e2e dashboards::no-parents
 *   @e2e dashboards::breadcrumb
 *   @e2e dashboards::cycle-refused
 *   @e2e dashboards::the-hint-names-where-the-tree-appears
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext, Browser, Locator, Page } from '@playwright/test'
import type { DashboardRow } from './support/dashboardHierarchy.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	allowManyPersonalDashboards,
	createDashboard,
	readDashboard,
	removeTree,
} from './support/dashboardHierarchy.ts'
import { MORE_MENU, openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const T = Date.now()
const HR = `HR ${T}`
const ROW = '.dashboard-switcher-sidebar__item'

let admin: APIRequestContext
let restoreSettings: (() => Promise<void>) | null = null
let sanne: Principal | null = null
let pieter: Principal | null = null
let hr: DashboardRow
let onboarding: DashboardRow
let vacation: DashboardRow
let pieterA: DashboardRow
let pieterB: DashboardRow

/**
 * Make a dashboard the principal's active one, as the switcher does.
 *
 * @param who the principal.
 * @param dash the dashboard.
 * @return nothing.
 */
async function activate(who: Principal, dash: DashboardRow): Promise<void> {
	const legacy = await who.api.post(`${API}/dashboard/${dash.id}/activate`)
	expect(legacy.status(), await legacy.text()).toBeLessThan(300)
	const preference = await who.api.post(`${API}/dashboards/active`, {
		data: { uuid: dash.uuid },
	})
	expect(preference.status(), await preference.text()).toBeLessThan(300)
}

/**
 * Log a principal in on the start page with a dashboard active.
 *
 * @param browser the browser.
 * @param who the principal.
 * @param dash the dashboard to open.
 * @return the context and page.
 */
async function openAs(browser: Browser, who: Principal, dash: DashboardRow) {
	await activate(who, dash)
	const session = await loginAs(browser, who.username, who.password)
	await session.page.goto('/index.php/apps/launchpad')
	await expect(
		session.page.getByTestId('launchpad-dashboard-region'),
	).toHaveAttribute('data-dashboard-uuid', dash.uuid, { timeout: 30_000 })
	return session
}

/**
 * A switcher row by its dashboard name.
 *
 * @param page the page.
 * @param name the name.
 * @return the row.
 */
function row(page: Page, name: string): Locator {
	return page.locator(
		`.dashboard-switcher-sidebar.open ${ROW}[aria-label="${name}"]`,
	)
}

test.describe('dashboards — tree in the interface (REQ-TREEUI-001..003)', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(120_000)

	test.beforeAll(async () => {
		admin = await adminContext()
		restoreSettings = await allowManyPersonalDashboards(admin)
		sanne = await createPrincipal(admin, 'e2e-treeui-sanne')
		pieter = await createPrincipal(admin, 'e2e-treeui-pieter')

		hr = await createDashboard(sanne.api, { name: HR })
		onboarding = await createDashboard(sanne.api, {
			name: 'Onboarding',
			parentUuid: hr.uuid,
		})
		vacation = await createDashboard(sanne.api, {
			name: 'Vacation rules',
			parentUuid: hr.uuid,
		})

		pieterA = await createDashboard(pieter.api, { name: `Pieter A ${T}` })
		pieterB = await createDashboard(pieter.api, { name: `Pieter B ${T}` })
	})

	test.afterAll(async () => {
		await removeTree(sanne?.api as APIRequestContext, hr?.id)
		await removeTree(pieter?.api as APIRequestContext, pieterA?.id)
		await removeTree(pieter?.api as APIRequestContext, pieterB?.id)
		await removePrincipal(admin, sanne)
		await removePrincipal(admin, pieter)
		await restoreSettings?.()
		await admin?.dispose()
	})

	// @e2e dashboards::nested-onboarding-pages
	test('a parent shows an expand control and, expanded, lists its children indented under it', async ({
		browser,
	}) => {
		const { context, page } = await openAs(browser, sanne!, hr)
		try {
			await openDashboardSwitcher(page)
			const parent = row(page, HR)
			await expect(parent).toHaveAttribute('data-depth', '0')
			const expander = parent.getByTestId(`tree-expand-${hr.uuid}`)
			await expect(expander).toBeVisible()
			await expect(expander).toHaveAttribute('aria-expanded', 'false')
			await expect(expander).toHaveAttribute('aria-label', `Expand ${HR}`)
			await expect(row(page, 'Onboarding')).toHaveCount(0)
			await expect(row(page, 'Vacation rules')).toHaveCount(0)

			await expander.click()
			await expect(expander).toHaveAttribute('aria-expanded', 'true')

			for (const name of [onboarding.name, vacation.name]) {
				const kid = row(page, name)
				await expect(kid).toBeVisible()
				await expect(kid).toHaveAttribute('data-depth', '1')
				await expect(kid).toHaveCSS('padding-inline-start', '20px')
			}

			// Listed right under HR, in order.
			const section = page
				.locator(
					'.dashboard-switcher-sidebar.open .dashboard-switcher-sidebar__section',
				)
				.filter({
					// `has` matches inside the section, so it cannot name the
					// sidebar ancestor that `row()` starts from.
					has: page.locator(`${ROW}[aria-label="${HR}"]`),
				})
			const names = await section
				.locator(ROW)
				.evaluateAll((rows) =>
					rows.map((el) => el.getAttribute('aria-label')),
				)
			const at = names.indexOf(HR)
			expect(names.slice(at, at + 3)).toEqual([
				HR,
				'Onboarding',
				'Vacation rules',
			])
			expect(names.filter((name) => name === 'Onboarding')).toHaveLength(1)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboards::no-parents
	test('without any parent the switcher is the flat list it always was', async ({
		browser,
	}) => {
		const { context, page } = await openAs(browser, pieter!, pieterA)
		try {
			await openDashboardSwitcher(page)
			const sidebar = page.locator('.dashboard-switcher-sidebar.open')
			await expect(row(page, pieterA.name)).toBeVisible()
			await expect(row(page, pieterB.name)).toBeVisible()

			await expect(
				sidebar.locator('[data-testid^="tree-expand-"]'),
			).toHaveCount(0)
			const depths = await sidebar
				.locator(ROW)
				.evaluateAll((rows) =>
					rows.map((el) => el.getAttribute('data-depth')),
				)
			expect(depths.length).toBeGreaterThanOrEqual(2)
			expect(new Set(depths)).toEqual(new Set(['0']))
			for (const name of [pieterA.name, pieterB.name]) {
				expect(
					await row(page, name).evaluate(
						(el) => (el as HTMLElement).style.paddingInlineStart,
					),
				).toBe('')
			}

			// Personal dashboards keep their personal section.
			await expect(
				sidebar.locator('[data-section="user"]').locator(ROW),
			).toHaveCount(2)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboards::breadcrumb
	test('a child dashboard shows "HR / Onboarding", with HR leading back to HR', async ({
		browser,
	}) => {
		const { context, page } = await openAs(browser, sanne!, onboarding)
		try {
			const nav = page.getByRole('navigation', { name: 'Dashboard location' })
			await expect(nav).toBeVisible({ timeout: 30_000 })
			await expect(nav).toHaveText(
				new RegExp(`^\\s*${HR}\\s*/\\s*Onboarding\\s*$`),
			)
			await expect(nav.locator('[aria-current="page"]')).toHaveText(
				'Onboarding',
			)

			const back = nav.getByRole('button', { name: HR })
			await expect(back).toBeVisible()
			await back.click()
			await expect(
				page.getByTestId('launchpad-dashboard-region'),
			).toHaveAttribute('data-dashboard-uuid', hr.uuid)
			// HR is a top-level dashboard: no trail of its own.
			await expect(nav).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboards::the-hint-names-where-the-tree-appears
	test('the hint under the parent select says the tree appears under Dashboards in More', async ({
		browser,
	}) => {
		const { context, page } = await openAs(browser, sanne!, onboarding)
		try {
			const more = page.locator(MORE_MENU)
			await expect(more).toBeVisible({ timeout: 30_000 })
			await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
			await page.locator('[data-testid="cn-grouped-menu-item-config"]').click()

			await expect(page.getByTestId('dashboard-parent-hint')).toHaveText(
				'This builds a tree of dashboards under Dashboards in the More menu',
				{ timeout: 10_000 },
			)
			await expect(
				page.locator('[data-test="config-panel-general"]'),
			).not.toContainText(/side ?bar/i)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboards::cycle-refused
	test('setting a child as the parent of its own parent shows the cycle message and saves nothing', async ({
		browser,
	}) => {
		const { context, page } = await openAs(browser, sanne!, hr)
		try {
			const more = page.locator(MORE_MENU)
			await expect(more).toBeVisible({ timeout: 30_000 })
			await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
			await page.locator('[data-testid="cn-grouped-menu-item-config"]').click()

			const select = page
				.getByTestId('dashboard-parent-select')
				.or(page.getByLabel('Parent dashboard'))
				.first()
			await expect(select).toBeVisible({ timeout: 10_000 })
			await select.click()
			// NcSelect splits a long option label over two spans, so the
			// option's accessible name is not the plain dashboard name.
			await page
				.getByRole('option')
				.filter({ has: page.locator('[title="Onboarding"]') })
				.click()

			const saved = page.waitForResponse(
				(res) =>
					res.request().method() === 'PUT'
					&& res.url().endsWith(`/api/dashboard/${hr.id}`),
			)
			await page.getByTestId('dashboard-save-button').click()
			expect((await saved).status()).toBe(400)

			await expect(page.getByTestId('dashboard-config-error')).toHaveText(
				'That dashboard sits under this one, so it cannot be its parent.',
			)
			await expect(page.getByTestId('dashboard-save-button')).toBeVisible()
		} finally {
			await context.close()
		}

		expect(
			(await readDashboard(sanne!.api, hr.id)).dashboard?.parentUuid,
		).toBeNull()
		expect(
			(await readDashboard(sanne!.api, onboarding.id)).dashboard?.parentUuid,
		).toBe(hr.uuid)
	})
})
