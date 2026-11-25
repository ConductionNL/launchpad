/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The one More menu of board LpMeer (runtime-shell REQ-SHELL-013): the only
 * control at the top, Settings at the bottom of the right column unfolding
 * in place, This dashboard ending in Delete, and Escape returning focus to
 * More.
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-shell::the-header-carries-only-more
 *   @e2e runtime-shell::settings-unfolds-in-place
 *   @e2e runtime-shell::this-dashboard-ends-in-delete
 *   @e2e runtime-shell::escape-returns-focus-to-more
 *   @e2e lpvandaag-start-page::a-person-switches-dashboards-from-meer
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */

import type { APIRequestContext, Browser, BrowserContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Principal } from './support/principals.ts'

import { expect, request, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import { API, createPrincipal, removePrincipal } from './support/principals.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const TRIGGER = `${MORE_MENU} [data-testid="launchpad-more-menu-trigger"]`
const PANEL = '[data-testid="launchpad-more-menu-panel"]'

let api: APIRequestContext
let seeded: SeededDashboard | null = null
// The scenarios of REQ-SHELL-013 that open with "a member" run as an
// ordinary account, not the suite's admin: the admin sees Admin settings
// and may edit every dashboard, so an admin-only run cannot show what a
// member's header and menu hold.
let member: Principal | null = null
let memberDashboard: SeededDashboard | null = null

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	// seedActiveDashboard also turns personal dashboards on, which the
	// member's own dashboard below needs.
	seeded = await seedActiveDashboard(api, `E2E More menu ${Date.now()}`)
	member = await createPrincipal(api, 'e2e-more-member')
	memberDashboard = await ownActiveDashboard(member.api, `E2E More menu member ${Date.now()}`)
})

test.afterAll(async () => {
	if (member !== null) {
		await removeSeededDashboard(member.api, memberDashboard)
	}
	await removeSeededDashboard(api, seeded)
	await removePrincipal(api, member)
	await api?.dispose()
})

/**
 * Create a dashboard as the calling account and make it that account's open
 * one. seedActiveDashboard cannot be used for a member: it first writes the
 * admin settings, which a member is refused.
 *
 * @param as a context acting as the owner.
 * @param name the dashboard name.
 * @return its id, uuid and name.
 */
async function ownActiveDashboard(as: APIRequestContext, name: string): Promise<SeededDashboard> {
	const created = await as.post(`${API}/dashboard`, { data: { name } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const id = Number(dash.id)
	expect(id, `no dashboard id in ${JSON.stringify(body)}`).toBeTruthy()
	const activate = await as.post(`${API}/dashboard/${id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const preference = await as.post(`${API}/dashboards/active`, { data: { uuid: dash.uuid } })
	expect(preference.status(), await preference.text()).toBeLessThan(300)
	return { id, uuid: dash.uuid, name }
}

/**
 * Log the member in, in a browser context of their own.
 *
 * @param browser the browser.
 * @return the context and page; the caller closes the context.
 */
async function memberSession(browser: Browser): Promise<{ context: BrowserContext; page: Page }> {
	expect(member, 'the member was not provisioned').not.toBeNull()
	return loginAs(browser, (member as Principal).username, (member as Principal).password)
}

/**
 * Open LaunchPad on the seeded dashboard and open More.
 *
 * @param page the page.
 * @return the open menu.
 */
async function openMore(page: Page) {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await page.locator(TRIGGER).click()
	const panel = page.locator(PANEL)
	await expect(panel).toBeVisible()
	return panel
}

// @e2e runtime-shell::the-header-carries-only-more
test('the header carries only More', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		await page.goto('/index.php/apps/launchpad/')
		await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
		// The member's start page is their own dashboard: one is open, so
		// the header is the one with This dashboard behind More.
		await expect(page.locator('[data-testid="launchpad-top-controls"]')).toHaveCount(1)
		const controls = page.locator('[data-testid="launchpad-top-controls"]')
		await expect(controls.getByRole('button')).toHaveCount(1)
		await expect(controls.getByRole('button')).toHaveText(/More/)
		await expect(page.locator(PANEL)).toBeHidden()
		// Nowhere on the page, not only in the controls row: none of the
		// header buttons the board removed may render as a button.
		for (const gone of ['Startpagina aanpassen', 'Menu', 'Edit', 'Add widget']) {
			await expect(page.getByRole('button', { name: gone, exact: true })).toHaveCount(0)
		}
		await expect(page.getByText('Startpagina aanpassen')).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e runtime-shell::settings-unfolds-in-place
test('Settings unfolds in place', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		const panel = await openMore(page)
		const toggle = panel.locator('[data-testid="launchpad-more-menu-settings"]')
		const columns = panel.locator('.lp-more-menu__column')
		await expect(columns).toHaveCount(2)
		// Settings sits in the right column only, under This dashboard's Delete
		// (the member owns the open dashboard, so Delete is offered).
		await expect(columns.nth(0).locator('[data-testid="launchpad-more-menu-settings"]')).toHaveCount(0)
		await expect(columns.nth(1).locator('[data-testid="launchpad-more-menu-settings"]')).toHaveCount(1)
		const deleteItem = columns.nth(1).locator('[data-testid="launchpad-more-menu-item-delete"]')
		await expect(deleteItem).toBeVisible()
		const deleteBox = await deleteItem.boundingBox()
		const toggleBox = await toggle.boundingBox()
		expect(deleteBox, 'Delete has no box').not.toBeNull()
		expect(toggleBox, 'Settings has no box').not.toBeNull()
		expect((toggleBox as { y: number }).y).toBeGreaterThan((deleteBox as { y: number }).y)

		// Folded when the menu opens.
		await expect(toggle).toHaveAttribute('aria-haspopup', 'true')
		await expect(toggle).toHaveAttribute('aria-expanded', 'false')
		const group = columns.nth(1).locator('[data-testid="launchpad-more-menu-group-settings"]')
		await expect(group).toBeHidden()

		await toggle.click()

		// THEN: expanded, the destinations show below it in the same
		// column, indented, and the menu stays open.
		await expect(toggle).toHaveAttribute('aria-expanded', 'true')
		await expect(group).toBeVisible()
		const personal = group.locator('[data-testid="launchpad-more-menu-item-personal-settings"]')
		await expect(personal).toBeVisible()
		// Admin settings is for administrators only.
		await expect(group.locator('[data-testid="launchpad-more-menu-item-admin-settings-link"]')).toHaveCount(0)
		const groupBox = await group.boundingBox()
		const toggleAfter = await toggle.boundingBox()
		expect(groupBox, 'the Settings group has no box').not.toBeNull()
		expect(toggleAfter, 'Settings has no box').not.toBeNull()
		expect((groupBox as { y: number }).y).toBeGreaterThanOrEqual(
			(toggleAfter as { y: number; height: number }).y + (toggleAfter as { height: number }).height - 1,
		)
		const personalBox = await personal.boundingBox()
		expect(personalBox, 'Personal settings has no box').not.toBeNull()
		expect((personalBox as { x: number }).x).toBeGreaterThan((toggleAfter as { x: number }).x)
		await expect(panel).toBeVisible()
	} finally {
		await context.close()
	}
})

test('the arrow keys follow the visual order, Settings last', async ({ page }) => {
	const panel = await openMore(page)
	// The menu puts focus on its first item once it has opened.
	await expect(panel.locator('[role^="menuitem"]:focus')).toHaveCount(1)
	await page.keyboard.press('End')
	await expect(page.locator('[data-testid="launchpad-more-menu-settings"]')).toBeFocused()
	await page.keyboard.press('ArrowUp')
	await expect(page.locator('[data-testid="launchpad-more-menu-item-delete"]')).toBeFocused()
})

// @e2e runtime-shell::this-dashboard-ends-in-delete
test('This dashboard ends in Delete', async ({ page }) => {
	// The suite's admin owns the seeded dashboard, so every owner item
	// (Share, Delete) is offered.
	const panel = await openMore(page)
	const group = panel.locator('[data-testid="launchpad-more-menu-group-this-dashboard"]')
	const labels = await group.getByRole('menuitem').allTextContents()
	// Board LpMeer labels the share item "Sharing".
	expect(labels.map((label) => label.trim())).toEqual([
		'Edit',
		'Add widget',
		'Sharing',
		'Copy link to this dashboard',
		'Duplicate as new dashboard',
		'Set as default',
		'Delete',
	])
	for (const gone of ['versions', 'config', 'schedule', 'unpublish', 'settings']) {
		await expect(panel.locator(`[data-testid="launchpad-more-menu-item-${gone}"]`)).toHaveCount(0)
	}
	await expect(group.getByRole('menuitem', { name: /Versions|Settings/ })).toHaveCount(0)
	await expect(group.locator('[data-testid="launchpad-more-menu-settings"]')).toHaveCount(0)
})

// @e2e runtime-shell::escape-returns-focus-to-more
test('Escape returns focus to More', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		const panel = await openMore(page)
		await page.keyboard.press('ArrowDown')
		await page.keyboard.press('ArrowDown')
		// The member is moving through the menu: focus is on an item in it,
		// not on More.
		await expect(panel.locator('[role^="menuitem"]:focus')).toHaveCount(1)
		await expect(page.locator(TRIGGER)).not.toBeFocused()
		await page.keyboard.press('Escape')
		await expect(panel).toBeHidden()
		await expect(page.locator(TRIGGER)).toBeFocused()
	} finally {
		await context.close()
	}
})

// As the member: Dashboards lists at most DASHBOARD_LIMIT (8) dashboards, and
// the suite's admin on a shared instance owns more than that.
test('a dashboard picked under Dashboards opens, and stays open after a reload', async ({ browser }) => {
	const owner = (member as Principal).api
	const other = await ownActiveDashboard(owner, `E2E More menu other ${Date.now()}`)
	const { context, page } = await memberSession(browser)
	try {
		// The second dashboard is now the active one; go back to the first through the API.
		const back = await owner.post(`${API}/dashboards/active`, { data: { uuid: memberDashboard?.uuid } })
		expect(back.status(), await back.text()).toBeLessThan(300)

		const panel = await openMore(page)
		const item = panel.locator(`[data-testid="launchpad-more-menu-item-dashboard-${other.id}"]`)
		await expect(item).toHaveText(new RegExp(other.name))
		// The pick is stored in the background; reopening reloads the page.
		const stored = page.waitForResponse((res) => res.url().includes('/api/dashboards/active') && res.request().method() === 'POST')
		await item.click()
		await expect(panel).toBeHidden()
		await stored

		const reopened = await openMore(page)
		await expect(reopened.locator(`[data-testid="launchpad-more-menu-item-dashboard-${other.id}"]`)).toHaveAttribute('aria-checked', 'true')
		await page.keyboard.press('Escape')

		await page.reload()
		const afterReload = await openMore(page)
		await expect(afterReload.locator(`[data-testid="launchpad-more-menu-item-dashboard-${other.id}"]`)).toHaveAttribute('aria-checked', 'true')
	} finally {
		await context.close()
		await removeSeededDashboard(owner, other)
	}
})
