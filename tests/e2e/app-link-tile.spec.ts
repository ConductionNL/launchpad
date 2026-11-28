/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for app-link-tile-widget (boards LpWidgetKiezen,
 * LpTegelToevoegen, LpTegelBewerken): one "App or link tile" in Add widget
 * whose form starts with An app or A link; an app tile for Files with its
 * count; a link tile with a title and web address; black text on a dark tile
 * warns below WCAG AA and still saves; the tile then draws in that colour.
 *
 * Every tile is placed in the edit-mode draft and the first one stored with
 * Save (edit-mode-draft-and-save), on a seeded dashboard, which the
 * spec removes afterwards.
 *
 * Gate-19 @e2e traceability:
 *   @e2e app-tile-widget::files-in-the-picker
 *   @e2e app-tile-widget::add-a-link-tile
 *   @e2e app-tile-widget::black-on-a-dark-tile-warns
 *   @e2e app-tile-widget::the-text-colour-is-used-on-the-tile
 *
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md
 */
import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}

let api: APIRequestContext
let seeded: SeededDashboard | null = null

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E App or link tile ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

/**
 * Open Add widget in edit mode and choose the App or link tile.
 *
 * @param page The page.
 */
async function openTileForm(page: Page): Promise<void> {
	await gotoLaunchPad(page)
	await enterEditMode(page)
	await page.getByTestId('launchpad-edit-bar-add-widget').click()
	const card = page.getByTestId('widget-picker-type-tile')
	await expect(card).toContainText('App or link tile')
	await card.click()
	await page.getByTestId('widget-picker-add').click()
	await expect(page.getByTestId('tile-mode')).toBeVisible()
}

test('Files in the picker: an app tile for Files is placed and marked On this dashboard', async ({ page }) => {
	await openTileForm(page)
	await page.getByTestId('tile-mode').getByText('An app', { exact: true }).click()
	const chooser = page.getByTestId('tile-app-chooser')
	await expect(chooser).toContainText('Choose the app')
	await chooser.getByTestId('tile-app-tick-app:files').check()
	await page.getByTestId('tile-editor-save').click()

	const grid = page.getByTestId('launchpad-dashboard-region')
	const tile = grid.locator('[data-testid="cn-app-tile"][href*="/apps/files"]')
	await expect(tile).toBeVisible()
	await expect(tile).toHaveAttribute('aria-label', /Files/)

	// edit-mode-draft-and-save REQ-EDIT-002: the tile sits in the draft until
	// Save; only a saved tile is "On this dashboard" after the page reloads.
	const saved = page.waitForResponse((res) =>
		res.request().method() === 'PUT'
		&& /\/apps\/launchpad\/api\/dashboard\/\d+$/.test(new URL(res.url()).pathname)
		&& res.request().postDataJSON()?.draft === true,
	)
	await page.getByTestId('launchpad-edit-bar-save').click()
	const response = await saved
	expect(response.status(), await response.text()).toBeLessThan(300)

	await openTileForm(page)
	await page.getByTestId('tile-mode').getByText('An app', { exact: true }).click()
	await expect(page.getByTestId('tile-app-tick-app:files')).toBeDisabled()
	await expect(page.getByTestId('tile-app-chooser')).toContainText('On this dashboard')
})

test('Add a link tile: a link tile with the title and web address is placed', async ({ page }) => {
	await openTileForm(page)
	await page.getByRole('textbox', { name: 'Name', exact: true }).fill('Intranet')
	await page.getByRole('textbox', { name: 'Web address', exact: true }).fill('https://intranet.zuiddrecht.nl')
	await page.getByTestId('tile-editor-save').click()

	const link = page.getByTestId('launchpad-dashboard-region').locator('a[href="https://intranet.zuiddrecht.nl"]')
	await expect(link).toBeVisible()
	await expect(link).toContainText('Intranet')
})

test('Black on a dark tile warns, Save stays available, and the tile draws in black', async ({ page }) => {
	await openTileForm(page)
	await page.getByRole('textbox', { name: 'Name', exact: true }).fill('Donker')
	await page.getByRole('textbox', { name: 'Web address', exact: true }).fill('https://donker.example.org')
	await page.locator('[data-test="tile-editor-swatch--sc-work-text"]').check()
	await page.getByTestId('tile-editor-text-black').check()

	const contrast = page.getByTestId('tile-editor-contrast')
	await expect(contrast).toContainText('below WCAG AA (4.5:1)')
	await expect(contrast).toHaveAttribute('role', 'alert')
	await expect(page.getByTestId('tile-editor-save')).toBeEnabled()
	await page.getByTestId('tile-editor-save').click()

	const title = page.getByTestId('launchpad-dashboard-region')
		.locator('a[href="https://donker.example.org"] .tile-widget__title')
	await expect(title).toBeVisible()
	await expect(title).toHaveCSS('color', 'rgb(0, 0, 0)')
})
