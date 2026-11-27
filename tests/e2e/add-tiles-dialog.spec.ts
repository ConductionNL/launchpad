/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for the ready-made Office tiles in the App or link tile form
 * (app-link-tile-widget REQ-APPTILE-004, board LpTegelToevoegen; the former
 * Add tiles dialog): add Word and Excel from the "Choose the app" list.
 *
 * The office tile pack is stubbed in the browser with Word, Excel,
 * PowerPoint and Outlook web, so the spec does not depend on which office
 * app the instance has. The tiles themselves are placed through the real
 * tile API on a seeded dashboard, which the spec removes afterwards.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::add-office-tiles
 *
 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md
 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
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
import { enterEditMode } from './support/dashboardSwitcher.ts'

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
	await enterEditMode(page)
	await page.getByTestId('launchpad-edit-bar-add-widget').click()
	await page.getByTestId('widget-picker-type-tile').click()
	await page.getByTestId('widget-picker-add').click()
	await page.getByTestId('tile-mode').getByText('An app', { exact: true }).click()

	const chooser = page.getByTestId('tile-app-chooser')
	await expect(chooser).toBeVisible()
	await chooser.getByRole('listitem').filter({ hasText: 'Word' }).getByRole('checkbox').check()
	await chooser.getByRole('listitem').filter({ hasText: 'Excel' }).getByRole('checkbox').check()
	const confirm = page.getByTestId('tile-editor-save')
	await expect(confirm).toHaveText('Add 2 tiles')
	await confirm.click()

	await expect(chooser).toBeHidden()
	const grid = page.getByTestId('launchpad-dashboard-region')
	await expect(grid.getByText('Word', { exact: true })).toBeVisible()
	await expect(grid.getByText('Excel', { exact: true })).toBeVisible()
})
