/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The first-paint shell prints nextcloud-vue's placeholder markup in every
 * frame, and the mounted card waits in the same shape
 * (widget-declared-placeholders task 4): a Conduction Nextcloud widget that
 * declares its shape (launchpad_links: rows), the Talk widget (rows, from the
 * default) and a chart placement (block, from the registry).
 *
 * Written from the change's scenarios; runs in the nightly against a live
 * instance (it is not run in the cloud sandbox).
 *
 * Gate-19 @e2e traceability:
 *   @e2e first-paint-shell::the-shell-and-the-card-draw-the-same-shape
 *
 * @spec openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-placeholder-shape-comes-from-the-widgets-declaration-req-fps-009
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import {
	clearSeededWidgets,
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { adminContext, API } from './support/principals.ts'

const RUN = Date.now()
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

/** The placements and the shape each must wait in, everywhere. */
const CASES: Array<{ widgetId: string, shape: string, id?: number }> = [
	{ widgetId: 'launchpad_links', shape: 'rows' },
	{ widgetId: 'spreed', shape: 'rows' },
	{ widgetId: 'chart', shape: 'block' },
]

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	dashboard = await seedActiveDashboard(admin, `E2E First paint shapes ${RUN}`)
	await clearSeededWidgets(admin, dashboard.id)
	// Below the fold, so the mounted card still waits when the page has loaded.
	for (const [index, entry] of CASES.entries()) {
		const res = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
			data: { widgetId: entry.widgetId, gridX: index * 4, gridY: 80, gridWidth: 4, gridHeight: 4 },
		})
		expect(res.status(), await res.text()).toBeLessThan(300)
		const body = await res.json()
		entry.id = Number((body.data ?? body).id)
	}
})

test.afterAll(async () => {
	await removeSeededDashboard(admin, dashboard)
	await admin.dispose()
})

// @e2e first-paint-shell::the-shell-and-the-card-draw-the-same-shape
test('the shell and the mounted card draw the same placeholder shape', async ({ page }) => {
	await page.context().setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })

	// The shell alone: the app's scripts never arrive, so the server's frames stay.
	await page.route(/\/apps\/launchpad\/js\//, (route) => route.abort())
	await page.goto('/index.php/apps/launchpad/')
	const shell = page.locator('#launchpad-first-paint')
	for (const entry of CASES) {
		const frame = shell.locator(`[data-placement-id="${entry.id}"]`)
		await expect(frame.locator('.cn-widget-placeholder')).toHaveAttribute('data-shape', entry.shape)
		await expect(frame.locator('.cn-widget-placeholder')).toHaveAttribute('aria-hidden', 'true')
	}

	// The mounted page: each card waits below the fold in the same shape.
	await page.unroute(/\/apps\/launchpad\/js\//)
	await page.goto('/index.php/apps/launchpad/')
	for (const entry of CASES) {
		const card = page.locator(`[data-testid="widget-placement-${entry.id}"]`)
		await expect(card).toHaveCount(1, { timeout: 30_000 })
		await expect(card.locator('.cn-widget-placeholder')).toHaveAttribute('data-shape', entry.shape)
	}
})
