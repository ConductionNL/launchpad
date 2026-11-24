/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The administration as a Launchpad page over the full width
 * (admin-full-width-page, REQ-ADM-030 and REQ-ADM-031): beheer opens
 * Administration settings from More and lands on `/apps/launchpad/admin/general`
 * in the Launchpad shell, without Nextcloud's settings menu; switching tabs
 * changes the address and a deep link opens its tab; Pieter, who is no
 * administrator, is answered 403 and gets nothing of the page; Nextcloud's
 * own settings entry is one line and a link to the page. Runs as the admin
 * (the project's storage state), Pieter as a throwaway account.
 *
 * Written from the change's scenarios; it runs in the nightly against a
 * live instance.
 *
 * @e2e openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import { MORE_MENU, openSettingsGroup } from './support/dashboardSwitcher.ts'

const APP = '/index.php/apps/launchpad'
const ADMIN_PAGE = `${APP}/admin`

/**
 * Assert the administration page is on screen, in the Launchpad shell and
 * without Nextcloud's settings menu, on one tab.
 *
 * @param page The page.
 * @param tab The tab's id.
 * @param label The tab's English label.
 */
async function expectAdminPageOn(page: Page, tab: string, label: string): Promise<void> {
	// /admin is General's address too: the router leaves the default tab out.
	const address = tab === 'general' ? '(/general)?' : `/${tab}`
	await expect(page).toHaveURL(new RegExp(`/apps/launchpad/admin${address}(\\?|#|$)`))
	const title = page.locator('.cn-admin-settings-shell__title')
	await expect(title).toHaveText(`Launchpad administration: ${label.toLocaleLowerCase()}`, { timeout: 60_000 })
	await expect(page.getByTestId('cn-admin-settings-breadcrumbs')).toContainText(
		new RegExp(`Administration settings\\s*/\\s*Launchpad\\s*/\\s*${label}`),
	)
	await expect(adminTab(page, tab)).toHaveAttribute('aria-selected', 'true')
	await expect(page.locator('[data-testid="AdminPage"]')).toBeVisible()

	// The Launchpad top bar: the shell's More menu, and no settings menu or
	// navigation panel beside the page.
	await expect(page.locator('[data-testid="launchpad-page-menu"]')).toBeVisible()
	await expect(page.locator('#app-navigation-vue, #app-navigation, .app-navigation')).toHaveCount(0)

	// The tabs take the full width of the content.
	const content = await page.locator('#app-content-vue').boundingBox()
	const tabs = await page.getByTestId('cn-admin-settings-tabs').boundingBox()
	expect(content, 'the content area rendered').not.toBeNull()
	expect(tabs, 'the tab strip rendered').not.toBeNull()
	// Nextcloud keeps an 8px body margin around the content; a navigation
	// panel beside it would be some 300px wide.
	expect(content!.x, 'no menu to the left of the content').toBeLessThan(16)
	expect(tabs!.width, 'the tabs span the content').toBeGreaterThan(content!.width * 0.8)
}

test.describe('REQ-ADM-030 the administration is a Launchpad page over the full width', () => {
	// @e2e admin-settings::open-the-administration
	test('beheer opens Administration settings from More and sees no left menu', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 })
		await page.goto(`${APP}/store`, { waitUntil: 'domcontentloaded' })
		const more = page.locator('[data-testid="launchpad-page-menu"]').locator(MORE_MENU)
		await expect(more).toBeVisible({ timeout: 60_000 })
		await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
		await openSettingsGroup(page)
		await page.locator('[data-testid="launchpad-more-menu-item-admin-settings-link"]').click()

		await expectAdminPageOn(page, 'general', 'General')
		await expect(page.getByTestId('admin-default-settings')).toBeVisible({ timeout: 30_000 })
	})

	// @e2e admin-settings::open-the-administration
	test('switching tabs changes the address, and Back and a deep link open the tab', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 })
		await page.goto(`${ADMIN_PAGE}/general`)
		await expectAdminPageOn(page, 'general', 'General')

		await adminTab(page, 'sharing').click()
		await expectAdminPageOn(page, 'sharing', 'Sharing')

		await page.goBack()
		await expectAdminPageOn(page, 'general', 'General')

		await page.goto(`${ADMIN_PAGE}/templates`)
		await expectAdminPageOn(page, 'templates', 'Templates')

		// An address without a tab, or with an unknown one, opens General.
		await page.goto(ADMIN_PAGE)
		await expect(page.locator('.cn-admin-settings-shell__title')).toHaveText(
			'Launchpad administration: general',
			{ timeout: 60_000 },
		)
		await page.goto(`${ADMIN_PAGE}/no-such-tab`)
		await expectAdminPageOn(page, 'general', 'General')
	})

	test.describe('a reader who is no administrator', () => {
		test.describe.configure({ timeout: 180_000 })

		let pieter: { username: string; password: string }

		test.beforeAll(async () => {
			pieter = await provisionThrowawayUser('e2e-pieter')
		})

		test.afterAll(async () => {
			if (pieter) {
				await deprovisionUser(pieter.username)
			}
		})

		// @e2e admin-settings::not-an-administrator
		test('Pieter is answered 403 and gets nothing of the page', async ({ browser }) => {
			const { context, page } = await loginAs(browser, pieter.username, pieter.password)
			try {
				const response = await page.goto(`${ADMIN_PAGE}/general`, { waitUntil: 'domcontentloaded' })
				expect(response?.status()).toBe(403)
				await expect(page.locator('[data-testid="AdminPage"]')).toHaveCount(0)
				await expect(page.getByTestId('cn-admin-settings-tabs')).toHaveCount(0)
				const html = await response!.text()
				expect(html, 'the 403 carries no administration state').not.toContain('initial-state-launchpad-allGroups')
				expect(html, 'the 403 loads no Launchpad script').not.toContain('launchpad-main.js')
			} finally {
				await context.close()
			}
		})
	})
})

test.describe('REQ-ADM-031 Nextcloud\'s settings point to the page', () => {
	// @e2e admin-settings::from-nextclouds-settings
	test('the Launchpad entry is one line and a link that opens the page', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 })
		await page.goto('/index.php/settings/admin/launchpad')
		const entry = page.getByTestId('launchpad-admin-settings-link')
		await expect(entry).toBeVisible({ timeout: 30_000 })
		await expect(entry).toContainText('The Launchpad administration is on a page of its own.')
		const link = entry.getByRole('link', { name: 'Open Launchpad administration' })
		// The router leaves the default tab out of the address: /admin opens General.
		await expect(link).toHaveAttribute('href', /\/apps\/launchpad\/admin(\/general)?$/)
		await expect(page.getByTestId('cn-admin-settings-tabs')).toHaveCount(0)

		await link.click()
		await expectAdminPageOn(page, 'general', 'General')
	})
})
