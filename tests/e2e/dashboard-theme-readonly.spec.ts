/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Details tab shows the instance theme, read-only (dashboard-theme-readonly,
 * REQ-THM-004, and REQ-THM-001 as modified): the dashboard settings open on
 * Details, "Theme" under Display reads the theme the instance uses and cannot
 * be changed, the tab offers no theme choice and no layout preset, and an
 * accent set under Own look saves while the theme stays what it was.
 *
 * The expected theme name is read from the instance's own capabilities (the
 * active thematiq theme when it publishes one, else Nextcloud's theming
 * name), so the test holds whatever the instance is called. The admin owns a
 * dashboard seeded for the run, removed afterwards.
 *
 * Written from the change's scenarios; it runs in the nightly against a
 * live instance.
 *
 * @e2e openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { readDashboard } from './support/dashboardHierarchy.ts'
import { MORE_MENU, openDashboardSettings } from './support/dashboardSwitcher.ts'
import { adminContext } from './support/principals.ts'

const STAMP = Date.now().toString(36)
const GREEN = '#1f5c3a'

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let themeName = ''

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * The theme name the instance publishes (src/utils/instanceTheme.js).
 *
 * @return the active thematiq theme, else the theming name.
 */
async function instanceThemeName(): Promise<string> {
	const res = await admin.get('/ocs/v2.php/cloud/capabilities?format=json')
	expect(res.status(), await res.text()).toBe(200)
	const capabilities = (await res.json())?.ocs?.data?.capabilities ?? {}
	const thematiq = capabilities.nldesign?.tokenSet?.name
	if (typeof thematiq === 'string' && thematiq.trim() !== '') {
		return thematiq.trim()
	}
	const theming = capabilities.theming?.name
	return typeof theming === 'string' ? theming.trim() : ''
}

/**
 * Open the seeded dashboard's settings on the Details tab.
 *
 * @param page the page.
 * @return the Details panel.
 */
async function openDetails(page: Page): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad', { waitUntil: 'domcontentloaded' })
	await expect(
		page.locator(
			`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${dashboard!.uuid}"]`,
		),
	).toBeVisible({ timeout: 30_000 })
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await openDashboardSettings(page)
	const tab = page.locator('[data-test="config-tab-general"]')
	await expect(tab).toContainText('Details')
	await tab.click()
	const panel = page.locator('[data-test="config-panel-general"]')
	await expect(panel).toBeVisible()
	return panel
}

/**
 * The input of a text field found by its test id.
 *
 * @param scope where to look.
 * @param testId the field's test id.
 * @return the input.
 */
function inputOf(scope: Locator, testId: string): Locator {
	return scope
		.locator(`input[data-testid="${testId}"], [data-testid="${testId}"] input`)
		.first()
}

test.beforeAll(async () => {
	admin = await adminContext()
	themeName = await instanceThemeName()
	dashboard = await seedActiveDashboard(admin, `E2E Thema ${STAMP}`)
})

test.afterAll(async () => {
	if (admin) {
		await removeSeededDashboard(admin, dashboard).catch(() => undefined)
		await admin.dispose()
	}
})

// @e2e dashboards::the-theme-of-zuiddrecht
test('Details shows the instance theme read-only, with no theme choice and no layout field', async ({
	page,
}) => {
	expect(themeName, 'the instance publishes a theme name').not.toBe('')
	const panel = await openDetails(page)

	// The tab strip has no separate Look tab.
	await expect(page.locator('[data-test="config-tab-look"]')).toHaveCount(0)

	const display = panel.locator('[data-test="config-display"]')
	await expect(display.getByRole('heading', { name: 'Display', exact: true })).toBeVisible()
	const theme = inputOf(display, 'display-theme')
	await expect(theme).toHaveValue(themeName)
	await expect(theme).not.toBeEditable()
	await expect(display.getByTestId('display-theme-hint')).toHaveText(
		'The theme is the same for every dashboard. Set this dashboard\'s own colours under Own look.',
	)

	// Typing into it changes nothing.
	await theme.press('End')
	await page.keyboard.type('X')
	await expect(theme).toHaveValue(themeName)

	// No theme choice and no layout preset anywhere on the tab.
	await expect(panel.getByRole('combobox', { name: /theme/i })).toHaveCount(0)
	await expect(panel.getByRole('combobox', { name: /layout/i })).toHaveCount(0)
	await expect(panel.getByLabel(/layout/i)).toHaveCount(0)
	await expect(panel.getByText(/layout of this dashboard/i)).toHaveCount(0)
})

// @e2e dashboards::the-own-look-stays-a-choice
// @e2e dashboards::a-green-team-dashboard
test('an accent set under Own look on Details saves, and Theme still reads the instance theme', async ({
	page,
}) => {
	const panel = await openDetails(page)
	const look = panel.locator('[data-test="config-look"]')
	await expect(look.getByRole('heading', { name: 'Own look', exact: true })).toBeVisible()

	await inputOf(look, 'look-accent').fill(GREEN)
	await expect(page.getByTestId('look-format-error')).toHaveCount(0)
	await expect(page.getByTestId('look-contrast-error')).toHaveCount(0)
	const saved = page.waitForResponse(
		(res) =>
			res.request().method() === 'PUT'
			&& res.url().endsWith(`/api/dashboard/${dashboard!.id}`),
	)
	await page.getByTestId('dashboard-save-button').click()
	expect((await saved).status()).toBe(200)

	const stored = (await readDashboard(admin, dashboard!.id)).dashboard
	expect(String(stored?.themeAccent).toLowerCase()).toBe(GREEN)

	// The dashboard takes the accent…
	const region = page.getByTestId('launchpad-dashboard-region')
	await expect
		.poll(async () =>
			(await region.evaluate((el) =>
				(el as HTMLElement).style.getPropertyValue('--color-primary-element').trim(),
			)).toLowerCase(),
		)
		.toBe(GREEN)

	// …and the theme is still the instance's, unchanged.
	const reopened = await openDetails(page)
	await expect(inputOf(reopened, 'look-accent')).toHaveValue(GREEN)
	await expect(inputOf(reopened.locator('[data-test="config-display"]'), 'display-theme')).toHaveValue(themeName)
})
