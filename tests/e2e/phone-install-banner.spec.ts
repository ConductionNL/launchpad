/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The install banner on a phone (phone-install-banner, REQ-PLB-009): at
 * 390 px the workplace offers to put LaunchPad on the home screen, Not now
 * hides it and it stays hidden after a reload; a 1440 px screen shows no
 * banner. Runs as the admin (the project's storage state).
 *
 * Written from the change's scenarios; it runs in the nightly against a
 * live instance.
 *
 * @e2e openspec/specs/platform-baseline/spec.md
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'

const APP = '/index.php/apps/launchpad'
/** Where "Not now" is kept (src/utils/installPrompt.js NOT_NOW_KEY). */
const NOT_NOW_KEY = 'launchpad.installBanner.notNowAt'

/**
 * Open the workplace with no "Not now" remembered.
 *
 * @param page The page.
 */
async function openFresh(page: Page): Promise<void> {
	await page.goto(APP)
	await page.evaluate((key) => window.localStorage.removeItem(key), NOT_NOW_KEY)
	await page.reload()
}

// @e2e platform-baseline::not-now
test('at 390 px the banner shows and Not now hides it after a reload', async ({ page }) => {
	await page.setViewportSize({ width: 390, height: 844 })
	await openFresh(page)

	const banner = page.getByTestId('install-banner')
	await expect(banner).toBeVisible({ timeout: 30_000 })
	await expect(page.getByTestId('install-banner-install')).toBeVisible()

	await page.getByTestId('install-banner-not-now').click()
	await expect(banner).toHaveCount(0)
	expect(await page.evaluate((key) => window.localStorage.getItem(key), NOT_NOW_KEY)).not.toBeNull()

	await page.reload()
	await expect(page.getByTestId('install-banner')).toHaveCount(0)
	await page.evaluate((key) => window.localStorage.removeItem(key), NOT_NOW_KEY)
})

// @e2e platform-baseline::a-desktop-screen
test('a 1440 px screen shows no install banner', async ({ page }) => {
	await page.setViewportSize({ width: 1440, height: 900 })
	await openFresh(page)

	await expect(page.locator('#content, #app-content-vue, .app-content').first()).toBeVisible({ timeout: 30_000 })
	await expect(page.getByTestId('install-banner')).toHaveCount(0)
})
