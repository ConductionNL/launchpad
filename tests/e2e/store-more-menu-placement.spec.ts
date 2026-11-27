/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The More menu on the Marketplace page (runtime-shell REQ-SHELL-009, board
 * LpStore): the last button of the page header's action row, after Publish,
 * inside the content column.
 */

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const STORE_URL = `${BASE}/index.php/apps/launchpad/store`

test.describe('More on the Marketplace page', () => {
	/*
	 * @e2e runtime-shell::more-is-the-last-button-of-the-store-page-header
	 */
	test('More is the last button of the store page header, after Publish', async ({ page }) => {
		await page.goto(STORE_URL, { waitUntil: 'domcontentloaded' })
		const publish = page.locator('[data-testid="store-publish"]').first()
		await expect(publish).toBeVisible({ timeout: 60_000 })

		const row = page.locator('.cn-page-header > .cn-store-page__header-actions')
		const menu = row.locator('[data-testid="launchpad-page-menu"]')
		await expect(menu).toBeVisible()
		await expect(menu.locator('[data-testid="launchpad-more-menu-trigger"]')).toBeVisible()

		const publishBox = await publish.boundingBox()
		const menuBox = await menu.boundingBox()
		expect(publishBox && menuBox).toBeTruthy()
		expect(menuBox!.x).toBeGreaterThan(publishBox!.x + publishBox!.width - 1)
		expect(Math.abs(menuBox!.y - publishBox!.y)).toBeLessThan(8)
	})
})
