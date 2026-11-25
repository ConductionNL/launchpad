/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for widget-content-forms-board-sync (board LpWidgetInstellen):
 * configure the KCC waiting time live tile.
 *
 * The connection is stubbed in the browser: the connector status answers
 * available and integriq's `app_connection` list answers one row, "Contact
 * centre queue", last succeeded at 10:38. The spec drives the Live data form
 * (Connection card, the connection, path `wait.minutes`, view Value, unit
 * minutes) and checks the last success and the preview. It does not save:
 * saving would make Nextcloud fetch a source that does not exist here.
 *
 * Also the Image scenario: saving an image without alternative text is
 * refused and names the field.
 *
 * Gate-19 @e2e traceability:
 *   @e2e live-data-tile-widget::kcc-waiting-time
 *   @e2e image-widget::alternative-text-is-required
 *
 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md
 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md
 */
import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { gotoLaunchPad, openAddWidgetModal } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

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
	seeded = await seedActiveDashboard(api, `E2E Content forms ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

test('configure the KCC waiting time live tile', async ({ page }) => {
	await page.route('**/apps/launchpad/api/livetile/connector/status', (route) =>
		route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ available: true }) }),
	)
	await page.route('**/apps/openregister/api/objects/integriq/app_connection**', (route) =>
		route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({
				results: [{
					id: 'conn-kcc',
					sourceId: 'kcc-queue',
					title: 'Contact centre queue',
					status: 'ok',
					lastSuccessAt: '2026-11-25T10:38:00Z',
				}],
			}),
		}),
	)

	await gotoLaunchPad(page)
	await openAddWidgetModal(page, 'livetile')
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()

	await expect(dialog.getByText('Where does the value come from?')).toBeVisible()
	await dialog.getByTestId('live-tile-source-connector').check()

	await dialog.getByRole('combobox', { name: 'Connection' }).click()
	await page.getByRole('option', { name: 'Contact centre queue' }).click()
	await expect(dialog.getByTestId('live-tile-last-success')).toContainText('Last success:')
	await expect(dialog.getByTestId('live-tile-last-success')).not.toContainText('Not yet')

	await dialog.getByLabel('Path in the response').fill('wait.minutes')
	await dialog.getByTestId('live-tile-view-value').check()
	await dialog.getByLabel('Unit (optional)').fill('minutes')
	await dialog.getByLabel('Title').fill('KCC waiting time')

	const preview = dialog.getByTestId('live-tile-preview')
	await expect(preview).toContainText('KCC waiting time')
	await expect(preview).toContainText('minutes')
	await expect(preview).toContainText('Refreshes every 5 minutes')
})

test('an image without alternative text is refused and the field is named', async ({ page }) => {
	await gotoLaunchPad(page)
	await openAddWidgetModal(page, 'image')
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()

	await dialog.getByTestId('image-source-url').check()
	await dialog.getByLabel('Image URL').fill('https://example.org/team.png')
	// The dialog's submit button carries the first error as its title.
	const add = dialog.getByRole('button', { name: /^add$/i })
	await expect(add).toBeDisabled()
	await expect(add).toHaveAttribute('title', 'Alternative text is required')

	await dialog.getByLabel('Text for who does not see the image').fill('The team at the town hall')
	await expect(add).toBeEnabled()
})
