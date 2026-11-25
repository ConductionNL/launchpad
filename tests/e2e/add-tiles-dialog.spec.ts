/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for add-tiles-dialog (board LpTegelToevoegen): add Word and
 * Excel from the Office section of Add tiles.
 *
 * The office tile pack is stubbed in the browser with Word, Excel,
 * PowerPoint and Outlook web, so the spec does not depend on which office
 * app the instance has. The tiles themselves are placed through the real
 * tile API on a seeded dashboard, which the spec removes afterwards.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::add-office-tiles
 *
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md
 */
import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}

const PACK = {
	tiles: [
		{ title: 'Word', linkType: 'url', linkValue: 'https://www.office.com/launch/word', icon: '', iconType: 'class' },
		{ title: 'Excel', linkType: 'url', linkValue: 'https://www.office.com/launch/excel', icon: '', iconType: 'class' },
		{ title: 'PowerPoint', linkType: 'url', linkValue: 'https://www.office.com/launch/powerpoint', icon: '', iconType: 'class' },
		{ title: 'Outlook web', linkType: 'url', linkValue: 'https://outlook.office.com/mail/', icon: '', iconType: 'class' },
	],
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
	seeded = await seedActiveDashboard(api, `E2E Add tiles ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

test('add Word and Excel from the Office section', async ({ page }) => {
	await page.route('**/apps/launchpad/api/office-tiles', (route) =>
		route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(PACK) }),
	)

	await gotoLaunchPad(page)
	await page.locator(MORE_MENU).click()
	await page.getByRole('menuitem', { name: /edit dashboard|edit/i }).first().click()
	await page.getByTestId('launchpad-edit-bar-add-tiles').click()

	const dialog = page.getByTestId('add-tiles')
	await expect(dialog).toBeVisible()
	const office = dialog.getByTestId('add-tiles-office')
	await expect(office).toContainText('Ready-made tiles for Office')

	await office.getByLabel('Word').check()
	await office.getByLabel('Excel').check()
	const confirm = dialog.getByTestId('add-tiles-confirm')
	await expect(confirm).toHaveText('Add 2 tiles')
	await confirm.click()

	await expect(dialog).toBeHidden()
	const grid = page.getByTestId('launchpad-dashboard-region')
	await expect(grid.getByText('Word', { exact: true })).toBeVisible()
	await expect(grid.getByText('Excel', { exact: true })).toBeVisible()
})
