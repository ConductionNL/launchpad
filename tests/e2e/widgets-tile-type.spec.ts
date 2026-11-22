/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The `tile` widget type (openspec/specs/widgets REQ-WDG-022): offered in the
 * add-widget type picker, rendered from a placement's `content`, and still
 * rendered from the legacy flat `tile*` columns. Written for
 * e2e-coverage-programme, lane e3.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::tile-widget-appears-in-the-picker
 *   @e2e widgets::tile-renders-with-content-from-placement
 *   @e2e widgets::tile-renderer-supports-legacy-and-new-content-shapes
 *
 * @spec openspec/specs/widgets/spec.md
 */

import { expect, test } from '@playwright/test'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminApi,
	gridItem,
	openWorkspace,
	Seeds,
} from './support/widgetPlacements.ts'

const RUN = Date.now()

let seeds: Seeds

test.beforeAll(async () => {
	seeds = new Seeds(await adminApi())
})

test.afterAll(async () => {
	await seeds?.cleanup()
})

test.describe('tile widget type (REQ-WDG-022)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e widgets::tile-widget-appears-in-the-picker
	test('the add-widget type picker offers Tile and mounts the tile form', async ({
		page,
	}) => {
		await seeds.dashboard(`E2E tile picker ${RUN}`)
		await openWorkspace(page)
		await page
			.locator(`${MORE_MENU} [data-testid="cn-grouped-menu-trigger"]`)
			.click()
		await page
			.locator('[data-testid="cn-grouped-menu-item-add-widget"]')
			.click()
		await page.locator('[data-testid="widget-picker-type-tile"]').click()

		const dialog = page.getByRole('dialog', { name: 'Add Widget' })
		await expect(dialog).toBeVisible({ timeout: 10_000 })
		const select = dialog.locator('[data-testid="widget-type-select"]')
		const option = select.locator('[data-testid="widget-type-option-tile"]')
		await expect(option).toHaveCount(1)
		await expect(option).toHaveText('Tile')
		await expect(option).toBeEnabled()

		await select.selectOption('tile')
		await expect(select).toHaveValue('tile')
		const form = dialog.locator('.cn-add-widget-modal__form .cn-dash-tile-form')
		await expect(form).toBeVisible()
		await expect(form.getByLabel('Title', { exact: true })).toBeVisible()
	})

	// @e2e widgets::tile-renders-with-content-from-placement
	test('a tile placement renders its icon, label and colours and opens its app in view mode', async ({
		page,
	}) => {
		const dash = await seeds.dashboard(`E2E tile content ${RUN}`)
		const placement = await seeds.widget(dash, {
			widgetId: 'tile',
			gridWidth: 2,
			gridHeight: 2,
			content: {
				title: 'Files',
				icon: 'icon-folder',
				iconType: 'class',
				linkType: 'app',
				linkValue: '/apps/files',
				backgroundColor: '#3b82f6',
				textColor: '#ffffff',
			},
		})
		await openWorkspace(page, placement.id)
		await expect(page.locator('.launchpad-edit-mode')).toHaveCount(0)

		const tile = gridItem(page, placement.id).locator('.cn-dash-tile-widget')
		await expect(tile).toBeVisible()
		await expect(tile).toHaveCSS('background-color', 'rgb(59, 130, 246)')
		await expect(
			tile.locator('.cn-dash-tile-widget__icon-class.icon-folder'),
		).toHaveCount(1)
		const title = tile.locator('.cn-dash-tile-widget__title')
		await expect(title).toHaveText('Files')
		await expect(title).toHaveCSS('color', 'rgb(255, 255, 255)')

		const link = tile.locator('a.cn-dash-tile-widget__link')
		await expect(link).toHaveAttribute('href', /\/apps\/files$/)
		await link.click()
		await page.waitForURL(/\/apps\/files(\/|\?|$)/, { timeout: 30_000 })
	})

	// @e2e widgets::tile-renderer-supports-legacy-and-new-content-shapes
	test('a legacy flat-column tile with no content renders its title and icon without errors', async ({
		page,
	}) => {
		const errors: string[] = []
		page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`))
		page.on('console', (msg) => {
			if (msg.type() === 'error' && /content/i.test(msg.text())) {
				errors.push(`console: ${msg.text()}`)
			}
		})

		const dash = await seeds.dashboard(`E2E tile legacy ${RUN}`)
		const placement = await seeds.legacyTile(dash, {
			title: 'Old Tile',
			icon: '📁',
			iconType: 'emoji',
			linkType: 'app',
			linkValue: '/apps/files',
		})
		const stored = (await seeds.placements(dash)).find(
			(p) => p.id === placement.id,
		)
		expect(stored?.tileTitle).toBe('Old Tile')
		expect(stored?.tileIcon).toBe('📁')
		expect(stored?.tileType).toBe('custom')
		// The legacy shape carries no content blob (serialised as an empty object).
		expect(stored?.content ?? {}).toEqual({})

		await openWorkspace(page, placement.id)
		const cell = gridItem(page, placement.id)
		await expect(cell).toContainText('Old Tile')
		await expect(cell).toContainText('📁')
		await page.waitForTimeout(1_000)
		expect(errors).toEqual([])
	})
})
