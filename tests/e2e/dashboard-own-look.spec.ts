/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Own look on Details (own-look-fields, REQ-THM-005 and REQ-THM-006, board
 * LpDashboardInstellingen): every colour field shows its picker beside its
 * input on one row at 1440 px; the background image is a labelled upload with
 * a styled button, a preview and Remove; a saved image becomes the
 * dashboard's background, and after Remove the dashboard follows the theme.
 * Runs as the admin (the project's storage state).
 *
 * @e2e openspec/changes/own-look-fields/specs/dashboards/spec.md
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
// A 1x1 PNG, enough for an upload and a preview.
const PNG = Buffer.from(
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
	'base64',
)

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

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

/**
 * Save the settings and wait for the dashboard's PUT.
 *
 * @param page The page.
 */
async function save(page: Page): Promise<void> {
	const saved = page.waitForResponse(
		(res) => res.request().method() === 'PUT' && res.url().endsWith(`/api/dashboard/${dashboard!.id}`),
	)
	await page.getByTestId('dashboard-save-button').click()
	expect((await saved).status()).toBe(200)
}

test.beforeAll(async () => {
	admin = await adminContext()
	dashboard = await seedActiveDashboard(admin, `E2E Eigen uiterlijk ${STAMP}`)
})

test.afterAll(async () => {
	if (admin) {
		await removeSeededDashboard(admin, dashboard).catch(() => undefined)
		await admin.dispose()
	}
})

// @e2e dashboards::the-accent-field
test('each colour field and its picker stand on one row at 1440 px', async ({ page }) => {
	await page.setViewportSize({ width: 1440, height: 900 })
	const panel = await openDetails(page)
	const look = panel.locator('[data-test="config-look"]')
	await expect(look).toBeVisible()

	for (const field of ['look-accent', 'look-background']) {
		const input = inputOf(look, field)
		const row = look.getByTestId(field).locator('xpath=ancestor::div[contains(@class, "dashboard-config__look-row")][1]')
		const picker = row.locator('input[type="color"]')
		await expect(picker, `${field} has a picker`).toHaveCount(1)
		const a = (await input.boundingBox())!
		const b = (await picker.boundingBox())!
		// One row, as board LpDashboardInstellingen draws it: the swatch sits
		// left of the hex field and overlaps it vertically.
		expect(b.x + b.width, `${field}: picker beside the input, on its left`).toBeLessThanOrEqual(a.x + 1)
		expect(b.y, `${field}: not wrapped below`).toBeLessThan(a.y + a.height)
		expect(b.y + b.height, `${field}: not wrapped above`).toBeGreaterThan(a.y)
	}
})

// @e2e dashboards::set-and-remove-a-background-image
test('a background image uploads with the styled button, previews, saves and is removed again', async ({ page }) => {
	await page.setViewportSize({ width: 1440, height: 900 })
	let panel = await openDetails(page)
	const image = panel.getByTestId('look-image')
	await expect(image).toContainText('Background image')
	await expect(image).toContainText('Shown behind this dashboard; leave empty to follow the theme.')
	// The browser's bare file input is not shown (clipped, out of the tab
	// order and the accessibility tree); the styled button is.
	const bare = image.getByTestId('look-image-input')
	await expect(bare).toHaveAttribute('aria-hidden', 'true')
	await expect(bare).toHaveAttribute('tabindex', '-1')
	const shown = await bare.evaluate((el) => {
		const box = el.getBoundingClientRect()
		const style = getComputedStyle(el)
		return box.width * box.height > 1 && style.clipPath === 'none' && style.clip === 'auto'
	})
	expect(shown, 'the bare file input takes no visible area').toBe(false)
	const upload = image.getByTestId('look-image-upload')
	await expect(upload).toHaveText(/Upload image/)

	const chooser = page.waitForEvent('filechooser')
	await upload.click()
	await (await chooser).setFiles({ name: `look-${STAMP}.png`, mimeType: 'image/png', buffer: PNG })
	await expect(image.getByTestId('look-image-preview')).toBeVisible({ timeout: 20_000 })
	await expect(image.getByTestId('look-image-clear')).toHaveText('Remove')
	await expect(upload).toHaveText(/Choose another image/)
	await save(page)

	const stored = (await readDashboard(admin, dashboard!.id)).dashboard
	expect(String(stored?.themeBackgroundResource ?? '')).not.toBe('')
	const region = page.getByTestId('launchpad-dashboard-region')
	await expect(region).toHaveCSS('background-image', /\/apps\/launchpad\/resource\//)

	// Remove: the dashboard follows the theme again.
	panel = await openDetails(page)
	await panel.getByTestId('look-image-clear').click()
	await expect(panel.getByTestId('look-image-preview')).toHaveCount(0)
	await save(page)
	const cleared = (await readDashboard(admin, dashboard!.id)).dashboard
	expect(String(cleared?.themeBackgroundResource ?? '')).toBe('')
	await expect(page.getByTestId('launchpad-dashboard-region')).toHaveCSS('background-image', 'none')
})
