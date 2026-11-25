/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for dashboard-connections-widget (board LpManagement,
 * "Koppelingen van dit dashboard"): a dashboard with a live tile and the
 * connections widget lists Live tiles with its state, and leaves out the
 * connections no widget on it uses.
 *
 * Integriq's `app_connection` list is stubbed in the browser, so the states
 * are fixed. The spec needs integriq on the instance, because the widget is
 * only shown where integriq is enabled; without it the test skips.
 *
 * Gate-19 @e2e traceability:
 *   @e2e app-connections::the-management-dashboards-connections
 *
 * @spec openspec/changes/dashboard-connections-widget/specs/app-connections/spec.md
 */
import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	clearSeededWidgets,
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}

const API = '/index.php/apps/launchpad/api'

let api: APIRequestContext
let seeded: SeededDashboard | null = null
let integriq = false

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	const info = await api.get('/ocs/v2.php/cloud/apps/integriq?format=json')
	integriq = info.ok() && (await info.json())?.ocs?.data?.active !== false
	seeded = await seedActiveDashboard(api, `E2E Connections ${Date.now()}`)
	await clearSeededWidgets(api, seeded.id)
	for (const widget of [
		{ widgetId: 'livetile', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 3, customTitle: 'Wachttijd KCC', content: {} },
		{ widgetId: 'connections', gridX: 4, gridY: 0, gridWidth: 8, gridHeight: 4, customTitle: 'Koppelingen van dit dashboard', content: {} },
	]) {
		const res = await api.post(`${API}/dashboard/${seeded.id}/widgets`, { data: widget })
		expect(res.status(), await res.text()).toBeLessThan(300)
	}
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

test('the connections widget lists Live tiles with its state and not the weather provider', async ({ page }) => {
	test.skip(!integriq, 'integriq is not on this instance, so the widget is not shown')
	await page.route('**/apps/openregister/api/objects/integriq/app_connection**', (route) =>
		route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({
				results: [
					{ key: 'live-tiles', title: 'Live tiles', status: 'configured', lastSuccessAt: '2026-11-25T10:44:00Z' },
					{ key: 'weather', title: 'Weather provider', status: 'configured' },
				],
			}),
		}),
	)
	await gotoLaunchPad(page)
	const widget = page.locator('[data-testid="connections-widget"]')
	const live = widget.locator('[data-testid="connection-live-tiles"]')
	await expect(live).toContainText('Live tiles')
	await expect(live.locator('[data-testid="connection-status"]')).toHaveText('Configured')
	await expect(live.locator('[data-testid="connection-success"]')).not.toContainText('Not yet')
	await expect(widget.locator('[data-testid="connection-weather"]')).toHaveCount(0)
})
