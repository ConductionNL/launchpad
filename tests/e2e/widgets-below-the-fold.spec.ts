/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A widget below the fold mounts its content when it comes into view, once
 * the page has settled, or at once in edit mode (widgets REQ-WDG-044). The
 * card is in the grid from the start; `.launchpad-lazy-mount[data-mounted]`
 * inside it says whether its content has mounted.
 *
 * Written from the change's scenarios; runs in the nightly against a live
 * instance (it is not run in the cloud sandbox).
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::the-case-list-below-the-fold-waits-for-the-reader
 *   @e2e widgets::everything-has-loaded-before-a-download
 *   @e2e widgets::edit-mode-shows-every-widget-at-once
 *
 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import {
	clearSeededWidgets,
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import { adminContext, API } from './support/principals.ts'

const RUN = Date.now()
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let onScreen = 0
let belowFold = 0

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/** Place a text widget at row `y`; answers its placement id. */
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
	dashboard = await seedActiveDashboard(admin, `E2E Below the fold ${RUN}`)
	await clearSeededWidgets(admin, dashboard.id)
	onScreen = await textAt(0, `on screen ${RUN}`)
	// 80 rows of 60 px: several screens below the first.
	belowFold = await textAt(80, `below the fold ${RUN}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(admin, dashboard)
	await admin.dispose()
})

/** The lazy-mount box inside a placement's card. */
function lazyBox(page: Page, placementId: number) {
	return page.locator(
		`[data-testid="widget-placement-${placementId}"] .launchpad-lazy-mount`,
	)
}

async function openStartPage(page: Page): Promise<void> {
	await page
		.context()
		.setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	await page.goto('/index.php/apps/launchpad/')
	await expect(lazyBox(page, onScreen)).toHaveAttribute('data-mounted', 'true', {
		timeout: 30_000,
	})
}

// @e2e widgets::the-case-list-below-the-fold-waits-for-the-reader
test('the widget below the fold mounts when the reader scrolls to it', async ({ page }) => {
	await openStartPage(page)
	await expect(page.locator(`[data-testid="widget-placement-${belowFold}"]`)).toHaveCount(1)
	await expect(lazyBox(page, belowFold)).toHaveAttribute('data-mounted', 'false')

	await page.locator(`[data-placement-id="${belowFold}"]`).scrollIntoViewIfNeeded()
	await expect(lazyBox(page, belowFold)).toHaveAttribute('data-mounted', 'true', {
		timeout: 5_000,
	})
	await expect(page.getByText(`below the fold ${RUN}`)).toBeVisible()
})

// @e2e widgets::everything-has-loaded-before-a-download
test('without scrolling, the widget below the fold mounts once the page has settled', async ({ page }) => {
	await openStartPage(page)
	await expect(lazyBox(page, belowFold)).toHaveAttribute('data-mounted', 'true', {
		timeout: 6_000,
	})
})

// @e2e widgets::edit-mode-shows-every-widget-at-once
test('in edit mode every widget mounts at once', async ({ page }) => {
	await openStartPage(page)
	await enterEditMode(page)
	await expect(lazyBox(page, belowFold)).toHaveAttribute('data-mounted', 'true', {
		timeout: 2_000,
	})
})
