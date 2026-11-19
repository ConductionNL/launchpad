/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The "More" menu's "Add widget" opens the widget picker, which holds the
 * app catalogue, the office tile pack and the apps discovered on this
 * server. The picker was unreachable from the start page after the edit
 * toolbar went; this entry is its way in again.
 *
 * @e2e openspec/changes/launcher-discover-server-services/specs/tiles/spec.md
 * @e2e openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md
 * @e2e openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const API = '/index.php/apps/launchpad/api'
const STAMP = `${Date.now()}`
const ENTRY = `E2E catalogue ${STAMP}`

/*
 * The admin over basic auth, with an explicitly empty jar so the project's
 * stored browser session is not sent along.
 */
async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: `Basic ${Buffer.from(`${ADMIN.user}:${ADMIN.pass}`).toString('base64')}`,
		},
	})
}

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
let entryUuid: string | null = null

test.setTimeout(120_000)

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E add widget ${STAMP}`)
	const created = await api.post(`${API}/admin/catalogue`, {
		data: {
			name: ENTRY,
			description: 'Placed by the add-widget e2e test',
			linkType: 'url',
			linkValue: 'https://example.invalid/catalogue',
		},
	})
	expect(created.status(), await created.text()).toBe(201)
	entryUuid = (await created.json()).app?.uuid ?? null
})

test.afterAll(async () => {
	if (entryUuid !== null) {
		await api.delete(`${API}/admin/catalogue/${entryUuid}`)
	}
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

test('"Add widget" in the More menu opens the picker with its three sections', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad/')
	await page.locator(`${MORE_MENU} button`).first().click()
	await page.getByText('Add widget', { exact: true }).first().click()

	const picker = page.locator('.widget-picker')
	await expect(picker).toBeVisible({ timeout: 15_000 })
	await expect(picker.locator('[data-testid="widget-picker-catalogue"]')).toContainText(ENTRY)
	await expect(picker.locator('[data-testid="widget-picker-office"]')).toBeVisible()
	await expect(picker.locator('[data-testid="widget-picker-discovered"]')).toBeVisible()
})
