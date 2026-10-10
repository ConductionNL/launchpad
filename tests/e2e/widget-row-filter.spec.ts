/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * In-widget search filters the rendered rows and never the saved search
 * (dashboards-and-who-may-see-them REQ-DWMS-008). Written in the build lane
 * and not yet run: the live pass runs it (decision 139).
 *
 * Scenario covered:
 *   @e2e dashboards-and-who-may-see-them::filtering-a-widget-leaves-its-definition-alone
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

let api: APIRequestContext
let seeded: SeededDashboard | null = null
let placementId = 0

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E RowFilter ${Date.now()}`)
	// The people widget renders rows from the instance's users, so a fresh
	// rig has at least the admin and the e2e grantee to filter between.
	const res = await api.post(
		`/index.php/apps/launchpad/api/dashboard/${seeded.id}/widgets`,
		{
			data: {
				widgetId: 'people',
				gridX: 0,
				gridY: 0,
				gridWidth: 6,
				gridHeight: 6,
			},
		},
	)
	expect(res.ok()).toBeTruthy()
	const body = await res.json()
	placementId = Number(body.id ?? body.placement?.id ?? body.data?.id)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

async function placement(): Promise<unknown> {
	const res = await api.get(
		`/index.php/apps/launchpad/api/dashboard/${seeded?.id}`,
	)
	const body = await res.json()
	const payload = body.data ?? body
	return (payload.placements ?? []).find(
		(p: { id: number }) => Number(p.id) === placementId,
	)
}

test('filtering a widget hides rows until reload and leaves the placement alone', async ({
	page,
}) => {
	// @e2e dashboards-and-who-may-see-them::filtering-a-widget-leaves-its-definition-alone
	const before = JSON.stringify(await placement())

	await page.goto('/index.php/apps/launchpad')
	const widget = page.getByTestId(`widget-placement-${placementId}`)
	await expect(widget).toBeVisible({ timeout: 20_000 })
	const rows = widget.locator('li:not([data-lp-filtered-out])')
	await expect(rows.first()).toBeVisible()
	const total = await rows.count()

	await widget
		.getByTestId('widget-row-filter')
		.locator('input')
		.fill('zzz-no-such-person')
	await expect(widget.getByTestId('widget-row-filter-empty')).toBeVisible()
	await expect(rows).toHaveCount(0)

	await page.reload()
	await expect(widget.locator('li:not([data-lp-filtered-out])')).toHaveCount(total)
	expect(JSON.stringify(await placement())).toBe(before)
})
