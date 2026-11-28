// SPDX-License-Identifier: EUPL-1.2
/*
 * Playwright end-to-end tests for the resource-serving capability.
 *
 * Covers REQ-RES-006 (serve) and REQ-RES-007 (list) — the read-side
 * endpoints added by the `resource-serving` OpenSpec change.
 *
 * NOTE: Playwright infrastructure is not yet wired up for this app.
 * This file is committed alongside the change so it runs once the
 * cohort-wide Playwright bootstrap lands. Do not delete — it is the
 * canonical e2e coverage for resource-serving.
 *
 * Gate-19 @e2e traceability:
 *   @e2e resource-serve::image-widget-renders-uploaded-resource
 *   @e2e resource-serve::unauthenticated-fetch-redirects-to-login
 *
 * @spec openspec/changes/resource-serving/tasks.md#task-8
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import * as path from 'path'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { closeSidebar, gotoLaunchPad } from './fixtures/widget-flow.ts'
// Resolve the target instance through the one module the whole suite (and the
// Playwright config) shares, so this spec can never target a different
// Nextcloud than the rest of the run — and can never fall back to the shared
// :8080 dev container, which no longer has a default at all.
import { BASE_URL as NEXTCLOUD_URL } from './support/baseUrl.ts'
import {
	clearSeededWidgets,
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { apiAs, openAddWidgetDialog } from './support/filesWidget.ts'

const APP_ID = process.env.APP_ID || 'launchpad'

let restoreWidgetRestriction: (() => Promise<void>) | null = null
// The image goes on a dashboard this file seeds and makes active, so the test
// never depends on the administrator already having a personal dashboard.
let api: APIRequestContext
let dashboard: SeededDashboard | null = null

test.beforeAll(async () => {
	api = await apiAs()
	dashboard = await seedActiveDashboard(api, `E2E Resource serve ${Date.now()}`)
	await clearSeededWidgets(api, dashboard.id)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

test.beforeAll(async () => {
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
})

test.describe('resource-serving', () => {
	test('REQ-RES-006: image widget renders uploaded resource via GET /apps/<app>/resource/<filename>', async ({
		page,
	}) => {
		await gotoLaunchPad(page)

		// Upload a tiny PNG through the drawn Image form (REQ-IMG-020): it opens
		// on Upload file, requires a text for who does not see the image, and
		// defers the upload to commit() so a cancelled dialog writes no
		// orphaned file. The serve-route URL only exists after Add is pressed.
		const dialog = await openAddWidgetDialog(page)
		await dialog.getByLabel(/widget type/i).selectOption({ label: 'Image' })
		await dialog
			.getByTestId('image-file-input')
			.setInputFiles(path.join(__dirname, 'fixtures', 'tiny.png'))
		await dialog.getByLabel(/text for who does not see the image/i).fill('A tiny image')

		// Pre-save the thumbnail is a local object-URL.
		await expect(dialog.getByTestId('image-chosen').locator('img')).toBeAttached({
			timeout: 15_000,
		})

		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		await addBtn.click()
		await expect(dialog).not.toBeVisible({ timeout: 8_000 })

		await closeSidebar(page)

		// The widget must render the image using the served URL.
		const anyImg = page
			.locator(`.cn-image-widget__img[src*="/apps/${APP_ID}/resource/"]`)
			.first()
		await expect(anyImg).toBeVisible({ timeout: 8_000 })

		// Verify the resource URL actually delivers image bytes (network-level).
		const src = (await anyImg.getAttribute('src')) ?? ''
		const response = await page.request.get(src)
		expect(response.status()).toBe(200)
		expect(response.headers()['content-type']).toMatch(/^image\//)
	})

	test('REQ-RES-006: unauthenticated direct fetch of resource redirects to login', async ({
		browser,
	}) => {
		// A fresh browser context with no stored auth cookies.
		const context = await browser.newContext({ storageState: undefined })
		const page = await context.newPage()

		// Point at an arbitrary (possibly non-existent) resource filename.
		// What matters is that the endpoint redirects instead of serving bytes.
		const response = await page.goto(
			`${NEXTCLOUD_URL}/index.php/apps/${APP_ID}/resource/test.png`,
			{ waitUntil: 'load' },
		)

		// Nextcloud redirects unauthenticated requests to /login (HTTP 302
		// followed by Playwright to a 200 on the login page).
		expect(page.url()).toMatch(/login|index\.php\/login/)

		// No resource bytes must have been served in the body.
		if (response !== null) {
			const body = await response.body()
			// A PNG header starts with the magic bytes \x89PNG.
			expect(body.slice(0, 4).toString('hex')).not.toBe('89504e47')
		}

		await context.close()
	})
})
