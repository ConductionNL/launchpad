/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * personal-settings REQ-PSET-001..003 (board LpPersoonlijk): Pieter opens
 * Persoonlijke instellingen from Meer, sets the summary email to Daily, and
 * shows a widget he hid on a shared dashboard again from the dialog.
 *
 * Written for the nightly; it needs a live instance.
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	allowManyPersonalDashboards,
	publication,
	removeTree,
} from './support/dashboardHierarchy.ts'
import {
	adminContext,
	API,
	createPrincipal,
	removePrincipal,
	seedDashboardWithWidget,
	shareWith,
} from './support/principals.ts'

const T = Date.now()

let admin: APIRequestContext
let restoreSettings: (() => Promise<void>) | null = null
let olga: Principal | null = null
let pieter: Principal | null = null
let team: { id: number; uuid: string; placementId: number } | null = null

/**
 * Open the settings dialog the way a person does: Meer, Instellingen,
 * Persoonlijke instellingen.
 *
 * @param page The page, on LaunchPad.
 * @return nothing.
 */
async function openPersonalSettings(page: Page): Promise<void> {
	await page.locator('[data-testid="launchpad-more-menu"] [data-testid="launchpad-more-menu-trigger"]').click()
	await page.locator('[data-testid="launchpad-more-menu-settings"]').click()
	await page.locator('[data-testid="launchpad-more-menu-item-personal-settings"]').click()
}

test.describe('personal settings dialog (REQ-PSET-001..003)', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(120_000)

	test.beforeAll(async () => {
		admin = await adminContext()
		restoreSettings = await allowManyPersonalDashboards(admin)
		olga = await createPrincipal(admin, 'e2e-pset-olga')
		pieter = await createPrincipal(admin, 'e2e-pset-pieter')

		team = await seedDashboardWithWidget(olga.api, `Intranet Zuiddrecht ${T}`)
		await publication(olga.api, team.uuid, 'publish')
		await shareWith(olga.api, team.id, pieter.username, 'view_only')

		const hidden = await pieter.api.put(`${API}/dashboards/${team.id}/personal-layer`, {
			data: { hidden: [team.placementId], overrides: {} },
		})
		expect(hidden.status(), await hidden.text()).toBe(200)
	})

	test.afterAll(async () => {
		await pieter?.api.put(`${API}/summary-mail/me`, { data: { frequency: 'off' } })
		await removeTree(olga?.api as APIRequestContext, team?.id)
		await removePrincipal(admin, olga)
		await removePrincipal(admin, pieter)
		await restoreSettings?.()
		await admin?.dispose()
	})

	// @e2e personal-settings::dialog-sections-daily-and-show-again
	test('Pieter sets Daily and shows his hidden widget again from the dialog', async ({ browser }) => {
		const session = await loginAs(browser, pieter!.username, pieter!.password)
		try {
			const page = session.page
			await page.goto('/index.php/apps/launchpad')
			await openPersonalSettings(page)

			// The sections in board order.
			const summary = page.getByTestId('launchpad-summary-mail')
			const hiddenSection = page.getByTestId('launchpad-hidden-widgets')
			await expect(summary).toBeVisible({ timeout: 30_000 })
			await expect(hiddenSection).toBeVisible()
			const summaryBox = await summary.boundingBox()
			const hiddenBox = await hiddenSection.boundingBox()
			expect(summaryBox!.y).toBeLessThan(hiddenBox!.y)

			// Daily is stored at once. The choices stay disabled until the stored choice has loaded.
			await expect(page.getByRole('radio', { name: /^Daily/ })).toBeEnabled({ timeout: 15_000 })
			const saved = page.waitForResponse(
				(res) => res.request().method() === 'PUT' && res.url().endsWith('/api/summary-mail/me'),
			)
			// The test id sits on NcCheckboxRadioSwitch's visually hidden input; click its visible label.
			const daily = page.getByTestId('launchpad-summary-mail-daily')
			await page.locator(`#${await daily.getAttribute('aria-labelledby')}`).click()
			expect((await saved).status()).toBe(200)
			const stored = await pieter!.api.get(`${API}/summary-mail/me`)
			expect((await stored.json()).frequency).toBe('daily')

			// The hidden widget is listed with its dashboard, and comes back.
			const row = page.getByTestId('launchpad-hidden-widget')
			await expect(row).toHaveCount(1)
			await expect(row).toContainText(`Intranet Zuiddrecht ${T}`)
			const shown = page.waitForResponse(
				(res) => res.request().method() === 'DELETE'
					&& res.url().endsWith(`/api/personal-layer/hidden/${team!.placementId}`),
			)
			await row.getByTestId('launchpad-hidden-widget-show').click()
			expect((await shown).status()).toBe(200)
			await expect(page.getByTestId('launchpad-hidden-widgets-none')).toBeVisible()

			const layer = await pieter!.api.get(`${API}/dashboards/${team!.id}/personal-layer`)
			expect((await layer.json()).hidden ?? []).toEqual([])
		} finally {
			await session.context.close()
		}
	})
})
