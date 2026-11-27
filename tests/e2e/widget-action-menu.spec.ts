/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget action menu follows the mode (REQ-WDG-047, review 27 Nov 2026
 * item 21): in view mode no widget offers "Create embed token" and a tile
 * shows no "..." at all; in edit mode an administrator finds Create embed
 * token in the edit menu of a widget and of a tile, linking to the Embeds
 * section of launchpad's admin page with that widget chosen.
 *
 * Seeds its own dashboard: a new dashboard gets the default bundle, tiles
 * and a Files widget.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-047
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
import { adminContext } from './support/principals.ts'

const STAMP = Date.now().toString(36)
const EMBED = '[data-testid="widget-create-embed-token"]'

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * Open the seeded dashboard in view mode.
 *
 * @param page the page.
 */
async function openDashboard(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad', { waitUntil: 'domcontentloaded' })
	await expect(
		page.locator(`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${dashboard!.uuid}"]`),
	).toBeVisible({ timeout: 30_000 })
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
}

test.beforeAll(async () => {
	admin = await adminContext()
	dashboard = await seedActiveDashboard(admin, `E2E Widgetmenu ${STAMP}`)
})

test.afterAll(async () => {
	if (admin) {
		await removeSeededDashboard(admin, dashboard).catch(() => undefined)
		await admin.dispose()
	}
})

// @e2e widgets::view-mode-shows-no-embed-action
test('view mode: no widget offers Create embed token and a tile shows no menu', async ({ page }) => {
	await openDashboard(page)
	// The default bundle's Nextcloud tile (review item 21 saw the menu there).
	const tile = page.locator('.launchpad-widget, .tile-widget').filter({ hasText: /^\s*Nextcloud\s*$/ }).first()
	await expect(tile).toBeVisible({ timeout: 30_000 })
	await tile.hover()
	await expect(page.locator(EMBED)).toHaveCount(0)
	await expect(page.getByTestId('cn-widget-edit-cog')).toHaveCount(0)
	// An administrator's own dashboard: nothing to hide for themselves either.
	await expect(page.locator('.launchpad-widget__cog')).toHaveCount(0)
})

// @e2e widgets::edit-mode-offers-the-embed-action
test('edit mode: a widget and a tile carry Create embed token, linking to Embeds with it chosen', async ({ page }) => {
	await openDashboard(page)
	await enterEditMode(page)

	const placements = (await (await admin.get(`/index.php/apps/launchpad/api/dashboard/${dashboard!.id}`)).json())
	const list = ((placements.data ?? placements).placements ?? []) as Array<{ id: number, widgetId: string }>
	expect(list.length).toBeGreaterThan(0)

	const menus = page.getByTestId('cn-widget-edit-cog')
	await expect(menus.first()).toBeVisible({ timeout: 30_000 })
	const count = await menus.count()
	expect(count).toBe(list.length)

	for (const index of [0, count - 1]) {
		await menus.nth(index).locator('button').first().click()
		const entry = page.locator(EMBED).filter({ visible: true })
		await expect(entry).toHaveCount(1)
		await expect(entry).toContainText('Create embed token')
		// NcActionLink puts the test id on its list item or on the link.
		const href = await entry.evaluate((el) => (el.closest('a') ?? el.querySelector('a'))?.getAttribute('href') ?? null)
		expect(href ?? '').toContain('embedType=widget')
		const id = new URL(href!, 'http://x').searchParams.get('embedId')
		expect(list.map((p) => String(p.id))).toContain(id)
		await page.keyboard.press('Escape')
		await expect(entry).toHaveCount(0)
	}
})
