/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Launchpad's admin page in Nextcloud's settings framework
 * (admin-page-in-nextcloud-settings): an administrator opens
 * `/settings/admin/launchpad` and sees Nextcloud's admin menu with Launchpad
 * selected, the version card first and the tabs (REQ-ADM-032); the former
 * address redirects to the tab it names (REQ-ADM-033); a member gets no
 * page; a person who holds only the delegated announcements setting sees only
 * the Announcements tab, and the admin API refuses them (REQ-ADM-034).
 *
 * @e2e openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md
 */

import { expect, test } from '@playwright/test'
import { deprovisionUser, loginAs, provisionThrowawayUser } from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import { addToGroup, adminContext, createGroup, removeGroup } from './support/principals.ts'

const SETTINGS_PAGE = '/index.php/settings/admin/launchpad'
const ANNOUNCEMENTS_CLASS = 'OCA\\LaunchPad\\Settings\\LaunchPadAnnouncementsAdmin'
// Nextcloud's delegation endpoint lives under the settings app's own prefix
// (`/apps/settings/` + the route), as its admin page calls it.
const DELEGATION_URL = '/index.php/apps/settings/settings/authorizedgroups/saveSettings'

test.describe('REQ-ADM-032 the admin page lives in Nextcloud\'s settings framework', () => {
	// On an instance whose setup is not done the setup wizard opens by itself
	// on the first visit in a browser session (ADR-042). These tests are about
	// the page and its tabs, so the session has already been offered it.
	test.beforeEach(async ({ page }) => {
		await page.addInitScript(() => {
			window.sessionStorage.setItem('launchpad:setup-wizard-offered', '1')
		})
	})

	// @e2e admin-settings::an-administrator-opens-the-page
	test('an administrator sees the settings menu, the version card first and the tabs', async ({ page }) => {
		await page.goto(SETTINGS_PAGE)
		const strip = page.getByTestId('cn-admin-settings-tabs')
		await expect(strip).toBeVisible({ timeout: 60_000 })
		// Nextcloud's admin settings menu, with Launchpad selected
		// (`#app-navigation-vue` since Nextcloud 33's Vue settings navigation).
		const navigation = page.locator('#app-navigation-vue, #app-navigation').first()
		await expect(navigation).toBeVisible()
		await expect(navigation.locator('a[href$="/settings/admin/launchpad"]')).toHaveAttribute('aria-current', /page|true/)
		// The version card comes before the first settings section.
		const card = page.locator('.cn-version-info').first()
		await expect(card).toBeVisible()
		await expect(page.getByTestId('admin-version-restart-setup')).toBeVisible()
		const cardBox = await card.boundingBox()
		const sectionBox = await page.getByTestId('admin-default-settings').boundingBox()
		expect(cardBox && sectionBox && cardBox.y < sectionBox.y, 'the version card comes first').toBeTruthy()
		// No Launchpad header or More menu.
		await expect(page.locator('[data-testid="dashboard-more-menu"]')).toHaveCount(0)
		await expect(adminTab(page, 'general')).toHaveAttribute('aria-selected', 'true')
		await expect(adminTab(page, 'announcements')).toHaveCount(1)
	})

	// @e2e admin-settings::a-tab-by-its-link
	test('a fragment opens its tab, and Back returns to the previous one', async ({ page }) => {
		await page.goto(`${SETTINGS_PAGE}#sharing`)
		await expect(adminTab(page, 'sharing')).toHaveAttribute('aria-selected', 'true', { timeout: 60_000 })
		await adminTab(page, 'kiosk').click()
		await expect(page).toHaveURL(/#kiosk$/)
		await page.goBack()
		await expect(adminTab(page, 'sharing')).toHaveAttribute('aria-selected', 'true')
	})
})

test.describe('REQ-ADM-033 the old admin route redirects', () => {
	// @e2e admin-settings::an-old-bookmark
	test('an old bookmark lands on the tab it names', async ({ page }) => {
		await page.goto('/index.php/apps/launchpad/admin/sharing')
		await expect(page).toHaveURL(/\/settings\/admin\/launchpad#sharing$/)
		await expect(adminTab(page, 'sharing')).toHaveAttribute('aria-selected', 'true', { timeout: 60_000 })
	})
})

test.describe('REQ-ADM-034 delegated admin settings per area', () => {
	test('a member gets no page', async ({ browser }) => {
		const member = await provisionThrowawayUser('e2e-adm-member')
		const { context, page } = await loginAs(browser, member.username, member.password)
		try {
			await page.goto(SETTINGS_PAGE)
			await expect(page.locator('#launchpad-admin-settings')).toHaveCount(0)
			await expect(page.getByTestId('cn-admin-settings-tabs')).toHaveCount(0)
		} finally {
			await context.close()
			await deprovisionUser(member.username)
		}
	})

	// @e2e admin-settings::an-announcements-manager-who-is-not-an-administrator
	test('an announcements manager sees only the Announcements tab, and the admin API refuses them', async ({ browser }) => {
		const api = await adminContext()
		const group = await createGroup(api, `e2e-ann-managers-${Date.now()}`)
		const femke = await provisionThrowawayUser('e2e-adm-femke')
		const { context, page } = await loginAs(browser, femke.username, femke.password)
		try {
			await addToGroup(api, femke.username, group)
			const delegated = await api.post(DELEGATION_URL, {
				data: { newGroups: [{ gid: group, displayName: group }], class: ANNOUNCEMENTS_CLASS },
			})
			expect(delegated.ok(), 'the announcements setting is delegated').toBeTruthy()

			await page.goto(SETTINGS_PAGE)
			const strip = page.getByTestId('cn-admin-settings-tabs')
			await expect(strip).toBeVisible({ timeout: 60_000 })
			await expect(strip.getByRole('tab')).toHaveCount(1)
			await expect(adminTab(page, 'announcements')).toHaveAttribute('aria-selected', 'true')
			await expect(page.locator('.cn-version-info')).toHaveCount(0)

			const refused = await page.request.put('/index.php/apps/launchpad/api/admin/settings', {
				data: { allowUserDash: false },
				headers: { requesttoken: await page.evaluate(() => (window as unknown as { OC: { requestToken: string } }).OC.requestToken) },
			})
			expect(refused.status()).toBe(403)
		} finally {
			await context.close()
			await api.post(DELEGATION_URL, {
				data: { newGroups: [], class: ANNOUNCEMENTS_CLASS },
			})
			await deprovisionUser(femke.username)
			await removeGroup(api, group)
			await api.dispose()
		}
	})
})
