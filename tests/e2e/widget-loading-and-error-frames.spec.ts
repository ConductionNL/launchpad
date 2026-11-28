/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Every widget's card renders at once with a loading state in place of its
 * content until the content mounts (widgets REQ-WDG-046). A widget below the
 * fold shows the state until the reader scrolls to it.
 *
 * Written from the change's scenarios; runs in the nightly against a live
 * instance (it is not run in the cloud sandbox). The scenario "A failing
 * widget stays in its own card" is excluded in the spec: no shipped widget
 * can be made to throw while it renders without changing its code; it is
 * pinned by src/components/__tests__/WidgetErrorBoundary.spec.js.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::a-widgets-card-shows-a-loading-state-first
 *
 * @spec openspec/specs/widgets/spec.md#requirement-every-widget-shows-its-own-loading-and-error-state-req-wdg-046
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

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let belowFold = 0

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * Place a text widget at row `y`.
 *
 * @param y the grid row.
 * @param text the widget's text.
 * @return its placement id.
 */
async function textAt(y: number, text: string): Promise<number> {
	const res = await admin.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: y,
			gridWidth: 4,
			gridHeight: 2,
			content: { text, contentMode: 'markdown' },
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	return Number((body.data ?? body).id)
}

test.beforeAll(async () => {
	admin = await adminContext()
	dashboard = await seedActiveDashboard(admin, `E2E Loading frames ${RUN}`)
	await clearSeededWidgets(admin, dashboard.id)
	await textAt(0, `on screen ${RUN}`)
	belowFold = await textAt(80, `below the fold ${RUN}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(admin, dashboard)
	await admin.dispose()
})

// @e2e widgets::a-widgets-card-shows-a-loading-state-first
test('a widget below the fold shows a loading state in its card until it comes into view', async ({ page }) => {
	await page.context().setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	await page.goto('/index.php/apps/launchpad/')

	const card = page.locator(`[data-testid="widget-placement-${belowFold}"]`)
	await expect(card).toHaveCount(1, { timeout: 30_000 })
	// widget-declared-placeholders: the card waits in its declared placeholder
	// (CnWidgetWrapper draws it and marks the card busy); no status of its own.
	const placeholder = card.getByTestId('cn-widget-wrapper-placeholder')
	await expect(placeholder).toHaveCount(1)
	await expect(card.locator('[aria-busy="true"]')).toHaveCount(1)
	await expect(card.getByTestId('widget-loading')).toHaveCount(0)

	await page.locator(`[data-placement-id="${belowFold}"]`).scrollIntoViewIfNeeded()
	await expect(placeholder).toHaveCount(0, { timeout: 5_000 })
	await expect(card.locator('[aria-busy="true"]')).toHaveCount(0)
	await expect(card.getByText(`below the fold ${RUN}`).first()).toBeVisible()
})
