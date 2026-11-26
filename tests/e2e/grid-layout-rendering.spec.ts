/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What a grid cell renders for a tile placement and for a widget placement,
 * and how tall a cell is (grid-layout REQ-GRID-009, REQ-GRID-012).
 *
 * A fresh throwaway account owns one dashboard. A tile is placed through
 * `POST /api/dashboard/{id}/tile`, a Nextcloud dashboard widget and a widget
 * id nobody registered through `POST /api/dashboard/{id}/widgets`; the tests
 * read the rendered cells in view and edit mode.
 *
 * Gate-19 @e2e traceability:
 *   @e2e grid-layout::tile-placement-renders-tilewidget
 *   @e2e grid-layout::regular-widget-placement-renders-widgetwrapper
 *   @e2e grid-layout::tilewidget-receives-edit-mode-prop
 *   @e2e grid-layout::widgetwrapper-receives-placement-and-widget-data
 *
 * @spec openspec/specs/grid-layout/spec.md
 */

import type { Locator, Page } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	openOwnDashboard,
	readPlacements,
	seedTextWidget,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'
import { API } from './support/principals.ts'

const STAMP = `${Date.now()}`
const TILE_TITLE = `E2E Mijn bestanden ${STAMP}`
const MISSING_WIDGET = `e2e-unregistered-${STAMP}`

let own: OwnDashboard
let tileId = 0
let ncWidget: { id: string; title: string } | null = null
let ncPlacementId = 0
let missingPlacementId = 0

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	own = await setUpOwnDashboard('e2e-grid-render', `E2E grid rendering ${STAMP}`)
	const api = own.owner.api

	const tile = await api.post(`${API}/dashboard/${own.id}/tile`, {
		data: {
			title: TILE_TITLE,
			icon: '📁',
			iconType: 'emoji',
			bgColor: '#3b82f6',
			textColor: '#ffffff',
			linkType: 'app',
			linkValue: 'files',
			gridX: 0,
			gridY: 0,
		},
	})
	expect(tile.status(), await tile.text()).toBe(201)
	tileId = Number((await tile.json()).id)

	// A Nextcloud dashboard widget this account may place, taken from the
	// server's own list rather than assumed.
	const listed = await api.get(`${API}/widgets`)
	expect(listed.status(), await listed.text()).toBe(200)
	const body = await listed.json()
	const widgets = (
		Array.isArray(body) ? body : (body.data ?? body.widgets ?? [])
	) as Array<Record<string, any>>
	const candidate = widgets.find(
		(w) =>
			typeof w.id === 'string'
			&& typeof w.title === 'string'
			&& w.title !== '',
	)
	expect(
		candidate,
		`no Nextcloud widget listed: ${JSON.stringify(body).slice(0, 300)}`,
	).toBeTruthy()
	ncWidget = { id: candidate!.id, title: candidate!.title }

	const nc = await api.post(`${API}/dashboard/${own.id}/widgets`, {
		data: {
			widgetId: ncWidget.id,
			gridX: 2,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		},
	})
	expect(nc.status(), await nc.text()).toBe(201)
	ncPlacementId = Number((await nc.json()).id)

	const missing = await api.post(`${API}/dashboard/${own.id}/widgets`, {
		data: {
			widgetId: MISSING_WIDGET,
			gridX: 6,
			gridY: 0,
			gridWidth: 2,
			gridHeight: 2,
		},
	})
	expect(missing.status(), await missing.text()).toBe(201)
	missingPlacementId = Number((await missing.json()).id)

	// The fourth item the tests below wait for; the height scenario that
	// measured it runs in grid-layout-defects.spec.ts, on its own dashboard.
	await seedTextWidget(
		api,
		own.id,
		{ x: 8, y: 0, w: 4, h: 4 },
		'Four rows tall',
	)
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
})

/**
 * The grid item of a placement.
 *
 * @param page The page.
 * @param id The placement id.
 * @return The item.
 */
function item(page: Page, id: number): Locator {
	return page.locator(`.grid-stack > .grid-stack-item[gs-id="${id}"]`)
}

// @e2e grid-layout::tile-placement-renders-tilewidget
test('a custom tile placement renders the launcher tile from its inline fields, not a widget wrapper', async ({
	browser,
}) => {
	const stored = (await readPlacements(own.owner.api, own.id)).find(
		(p) => Number(p.id) === tileId,
	)!
	expect(stored.tileType).toBe('custom')

	const { context, page } = await openOwnDashboard(browser, own, 4)
	try {
		const cell = item(page, tileId)
		const tile = cell.locator('.tile-widget')
		await expect(tile).toHaveCount(1)
		await expect(cell.locator('.launchpad-widget')).toHaveCount(0)
		await expect(tile.locator('.tile-widget__title')).toHaveText(TILE_TITLE)
		await expect(tile.locator('.tile-widget__emoji')).toHaveText('📁')
		expect(
			await tile.evaluate((el) =>
				el.style.getPropertyValue('--tile-bg-color'),
			),
		).toBe('#3b82f6')
		expect(
			await tile.evaluate((el) =>
				el.style.getPropertyValue('--tile-text-color'),
			),
		).toBe('#ffffff')
		await expect(tile.locator('a.tile-widget__link')).toHaveAttribute(
			'href',
			/\/apps\/files$/,
		)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::regular-widget-placement-renders-widgetwrapper
test('a widget placement renders the widget wrapper with the widget the server lists', async ({
	browser,
}) => {
	const { context, page } = await openOwnDashboard(browser, own, 4)
	try {
		const cell = item(page, ncPlacementId)
		const wrapper = cell.locator(
			`.launchpad-widget[data-widget-id="${ncWidget!.id}"]`,
		)
		await expect(wrapper).toHaveCount(1)
		await expect(wrapper).toHaveAttribute(
			'data-testid',
			`widget-placement-${ncPlacementId}`,
		)
		await expect(cell.locator('.tile-widget')).toHaveCount(0)
		// The header carries the title of the widget resolved from the list.
		await expect(wrapper).toContainText(ncWidget!.title)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::tilewidget-receives-edit-mode-prop
test('in edit mode a tile shows its edit control, and choosing Edit opens the tile editor', async ({
	browser,
}) => {
	const { context, page } = await openOwnDashboard(browser, own, 4)
	try {
		const tile = item(page, tileId).locator('.tile-widget')
		await expect(tile.getByRole('button', { name: 'Tile menu' })).toHaveCount(0)

		await enterEditMode(page)
		const cog = tile.getByRole('button', { name: 'Tile menu' })
		await expect(cog).toBeVisible()
		const before = page.url()
		await cog.click()
		await page.getByRole('menuitem', { name: 'Edit tile' }).click()
		const editor = page.getByRole('dialog', { name: 'Edit Tile' })
		await expect(editor).toBeVisible()
		await expect(editor.getByLabel('Name', { exact: true })).toHaveValue(TILE_TITLE)
		expect(page.url()).toBe(before)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::widgetwrapper-receives-placement-and-widget-data
test('a widget id nobody registered still renders its wrapper, without breaking the grid', async ({
	browser,
}) => {
	const errors: string[] = []
	const { context, page } = await openOwnDashboard(browser, own, 4)
	try {
		page.on('pageerror', (error) => errors.push(error.message))
		await page.reload()
		await expect(page.locator('.grid-stack > .grid-stack-item')).toHaveCount(4, {
			timeout: 30_000,
		})

		// The placement is handed over: the wrapper names the placement and its widget id.
		const missing = item(page, missingPlacementId).locator('.launchpad-widget')
		await expect(missing).toHaveCount(1)
		await expect(missing).toHaveAttribute('data-widget-id', MISSING_WIDGET)
		await expect(missing).toHaveAttribute(
			'data-testid',
			`widget-placement-${missingPlacementId}`,
		)
		// The listed widget is resolved next to it.
		await expect(
			item(page, ncPlacementId).locator('.launchpad-widget'),
		).toContainText(ncWidget!.title)
		expect(errors).toEqual([])
	} finally {
		await context.close()
	}
})

