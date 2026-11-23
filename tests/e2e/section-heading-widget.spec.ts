/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The section heading widget, "Tussenkop" (widgets REQ-WDG-031): a
 * full-width heading with one line of explanation, on the page ground.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::a-section-heading-heads-a-group
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
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

let api: APIRequestContext
let seeded: SeededDashboard | null = null

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E Section heading ${Date.now()}`)
	const res = await api.post(`/index.php/apps/launchpad/api/dashboard/${seeded.id}/widgets`, {
		data: {
			widgetId: 'section-heading',
			gridX: 0,
			gridY: 0,
			gridWidth: 12,
			gridHeight: 1,
			showTitle: 0,
			content: { title: 'Samenwerken', description: 'De tegels van het team' },
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

test('a section heading heads a group', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	const heading = page.locator('[data-testid="section-heading-widget"]')
	await expect(heading).toBeVisible({ timeout: 15_000 })
	await expect(heading.getByRole('heading', { level: 2 })).toHaveText('Samenwerken')
	await expect(heading).toContainText('De tegels van het team')
	await expect(heading).toHaveCSS('border-bottom-width', '1px')
	const grid = await page.locator('[data-testid="launchpad-dashboard-region"]').boundingBox()
	const box = await heading.boundingBox()
	expect(box!.width).toBeGreaterThan(grid!.width * 0.9)
})
