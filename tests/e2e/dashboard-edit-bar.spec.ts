/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The edit bar of board LpBewerken and the Versions tab of board LpVersies.
 * Edit mode shows the bar with Columns 6, 8 or 12, Add widget, Dashboard
 * settings and Done; Dashboard settings opens the settings window, whose
 * last tab is Versions.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::edit-mode-shows-the-edit-bar
 *   @e2e dashboards::columns-change-the-grid-and-are-kept
 *   @e2e dashboards::done-leaves-edit-mode
 *   @e2e dashboards::the-versions-tab-lists-the-saved-versions
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const BAR = '[data-testid="launchpad-edit-bar"]'

let api: APIRequestContext
let seeded: SeededDashboard | null = null

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E Edit bar ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

/**
 * Open LaunchPad on the seeded dashboard and put it in edit mode.
 *
 * @param page the page.
 * @return the edit bar.
 */
async function openInEditMode(page: Page) {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await enterEditMode(page)
	const bar = page.locator(BAR)
	await expect(bar).toBeVisible()
	return bar
}

test('edit mode shows the edit bar with its four controls', async ({ page }) => {
	const bar = await openInEditMode(page)
	await expect(bar).toHaveAttribute('role', 'toolbar')
	await expect(bar).toContainText(seeded!.name)
	for (const columns of ['6', '8', '12']) {
		await expect(bar.getByRole('button', { name: columns, exact: true })).toBeVisible()
	}
	await expect(bar.getByRole('button', { name: 'Add widget' })).toBeVisible()
	await expect(bar.getByRole('button', { name: 'Dashboard settings' })).toHaveAttribute('aria-haspopup', 'dialog')
	await expect(bar.getByRole('button', { name: 'Done' })).toBeVisible()
	// The More menu docks at the end of the bar, so it never covers Done.
	await expect(bar.locator(MORE_MENU)).toBeVisible()
})

test('columns change the grid and are kept', async ({ page }) => {
	const bar = await openInEditMode(page)
	await bar.locator('[data-testid="launchpad-edit-bar-columns-8"]').click()
	await expect(bar.locator('[data-testid="launchpad-edit-bar-columns-8"]')).toHaveAttribute('aria-pressed', 'true')
	await expect.poll(async () => {
		const res = await api.get(`/index.php/apps/launchpad/api/dashboard/${seeded!.id}`)
		const body = await res.json()
		return Number((body.dashboard ?? body).gridColumns)
	}).toBe(8)
	await bar.locator('[data-testid="launchpad-edit-bar-columns-12"]').click()
	await expect(bar.locator('[data-testid="launchpad-edit-bar-columns-12"]')).toHaveAttribute('aria-pressed', 'true')
})

test('Done leaves edit mode', async ({ page }) => {
	const bar = await openInEditMode(page)
	await bar.getByRole('button', { name: 'Done' }).click()
	await expect(page.locator(BAR)).toHaveCount(0)
	await expect(page.locator('.launchpad-edit-mode')).toHaveCount(0)
})

test('the Versions tab lists the saved versions', async ({ page }) => {
	const bar = await openInEditMode(page)
	await bar.getByRole('button', { name: 'Dashboard settings' }).click()
	const versions = page.locator('[data-test="config-tab-versions"]')
	await expect(versions).toBeVisible({ timeout: 10_000 })
	await versions.click()
	await expect(page.locator('[data-test="config-panel-versions"] [data-testid="version-history"]')).toBeVisible({ timeout: 10_000 })
})
