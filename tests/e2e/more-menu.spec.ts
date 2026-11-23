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

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const TRIGGER = `${MORE_MENU} [data-testid="launchpad-more-menu-trigger"]`
const PANEL = '[data-testid="launchpad-more-menu-panel"]'

let api: APIRequestContext
let seeded: SeededDashboard | null = null

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E More menu ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

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

test('the header carries only More', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	const controls = page.locator('[data-testid="launchpad-top-controls"]')
	await expect(controls.getByRole('button')).toHaveCount(1)
	await expect(controls.getByRole('button')).toHaveText(/More/)
	await expect(page.getByText('Startpagina aanpassen')).toHaveCount(0)
})

test('Settings unfolds in place', async ({ page }) => {
	const panel = await openMore(page)
	const toggle = panel.locator('[data-testid="launchpad-more-menu-settings"]')
	const columns = panel.locator('.lp-more-menu__column')
	await expect(columns).toHaveCount(2)
	await expect(columns.nth(0).locator('[data-testid="launchpad-more-menu-settings"]')).toHaveCount(0)
	await expect(columns.nth(1).locator('[data-testid="launchpad-more-menu-settings"]')).toHaveCount(1)
	const deleteBox = await panel.locator('[data-testid="launchpad-more-menu-item-delete"]').boundingBox()
	const toggleBox = await toggle.boundingBox()
	expect(toggleBox && deleteBox && toggleBox.y > deleteBox.y).toBe(true)
	await expect(toggle).toHaveAttribute('aria-haspopup', 'true')
	await expect(toggle).toHaveAttribute('aria-expanded', 'false')
	await expect(panel.locator('[data-testid="launchpad-more-menu-item-StoreMenu"]')).toBeHidden()
	await toggle.click()
	await expect(toggle).toHaveAttribute('aria-expanded', 'true')
	await expect(panel.locator('[data-testid="launchpad-more-menu-item-StoreMenu"]')).toBeVisible()
	await expect(panel.locator('[data-testid="launchpad-more-menu-item-personal-settings"]')).toBeVisible()
	await expect(columns.nth(1).locator('[data-testid="launchpad-more-menu-group-settings"]')).toBeVisible()
	await expect(panel).toBeVisible()
})

test('the arrow keys follow the visual order, Settings last', async ({ page }) => {
	await openMore(page)
	await page.keyboard.press('End')
	await expect(page.locator('[data-testid="launchpad-more-menu-settings"]')).toBeFocused()
	await page.keyboard.press('ArrowUp')
	await expect(page.locator('[data-testid="launchpad-more-menu-item-delete"]')).toBeFocused()
})

test('This dashboard ends in Delete', async ({ page }) => {
	const panel = await openMore(page)
	const group = panel.locator('[data-testid="launchpad-more-menu-group-this-dashboard"]')
	const labels = await group.getByRole('menuitem').allTextContents()
	expect(labels.map((label) => label.trim())).toEqual([
		'Edit',
		'Add widget',
		'Sharing',
		'Copy link to this dashboard',
		'Duplicate as new dashboard',
		expect.stringMatching(/Set as default|Default dashboard/),
		'Delete',
	])
	for (const gone of ['versions', 'config', 'schedule', 'unpublish']) {
		await expect(panel.locator(`[data-testid="launchpad-more-menu-item-${gone}"]`)).toHaveCount(0)
	}
})

test('Escape returns focus to More', async ({ page }) => {
	const panel = await openMore(page)
	await page.keyboard.press('ArrowDown')
	await page.keyboard.press('Escape')
	await expect(panel).toBeHidden()
	await expect(page.locator(TRIGGER)).toBeFocused()
})

test('a dashboard picked under Dashboards opens, and stays open after a reload', async ({ page }) => {
	const other = await seedActiveDashboard(api, `E2E More menu other ${Date.now()}`)
	try {
		// The second seed is now the active one; go back to the first through the API.
		const back = await api.post('/index.php/apps/launchpad/api/dashboards/active', { data: { uuid: seeded?.uuid } })
		expect(back.status(), await back.text()).toBeLessThan(300)

		const panel = await openMore(page)
		const item = panel.locator(`[data-testid="launchpad-more-menu-item-dashboard-${other.id}"]`)
		await expect(item).toHaveText(new RegExp(other.name))
		await item.click()
		await expect(panel).toBeHidden()

		const reopened = await openMore(page)
		await expect(reopened.locator(`[data-testid="launchpad-more-menu-item-dashboard-${other.id}"]`)).toHaveAttribute('aria-checked', 'true')
		await page.keyboard.press('Escape')

		await page.reload()
		const afterReload = await openMore(page)
		await expect(afterReload.locator(`[data-testid="launchpad-more-menu-item-dashboard-${other.id}"]`)).toHaveAttribute('aria-checked', 'true')
	} finally {
		await removeSeededDashboard(api, other)
	}
})
