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

import { test, expect } from '@playwright/test'
import * as path from 'path'

const NEXTCLOUD_URL = process.env.NEXTCLOUD_URL || 'http://localhost:8080'
const APP_ID = process.env.APP_ID || 'mydash'

test.describe('resource-serving', () => {
	test('REQ-RES-006: image widget renders uploaded resource via GET /apps/<app>/resource/<filename>', async ({ page }) => {
		// Log in and navigate to the app dashboard.
		await page.goto(`${NEXTCLOUD_URL}/index.php/apps/${APP_ID}`)

		// Upload a tiny PNG via the admin resource upload UI.
		await page.getByRole('button', { name: /add widget/i }).click()
		await page.getByLabel('Widget type').selectOption({ label: 'Image' })

		const fileChooserPromise = page.waitForEvent('filechooser')
		await page.getByLabel(/upload image/i).click()
		const fc = await fileChooserPromise
		await fc.setFiles(path.join(__dirname, 'fixtures', 'tiny.png'))

		// Wait for the URL field to be populated with the serve route.
		const urlInput = page.getByLabel(/image url/i)
		await expect(urlInput).toHaveValue(new RegExp(`/apps/${APP_ID}/resource/`))

		await page.getByRole('button', { name: /save|add/i }).click()

		// The widget must render the image using the served URL.
		const img = page.locator('.image-widget__img')
		await expect(img).toBeVisible()
		await expect(img).toHaveAttribute('src', new RegExp(`/apps/${APP_ID}/resource/`))

		// Verify the resource URL actually delivers bytes (network-level).
		const src = await img.getAttribute('src') ?? ''
		const response = await page.request.get(src)
		expect(response.status()).toBe(200)
		const contentType = response.headers()['content-type']
		expect(contentType).toMatch(/^image\//)
	})

	test('REQ-RES-006: unauthenticated direct fetch of resource redirects to login', async ({ browser }) => {
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
