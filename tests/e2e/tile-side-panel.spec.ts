/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A tile that opens in the panel beside the tiles (launcher-tile-open-behaviour,
 * REQ-TOB-003 and REQ-TOB-004): the Files app opens next to the grid, Escape
 * closes the panel and gives focus back to the tile, a second panel tile
 * replaces the panel, and an outside host that is not on the embed allow-list
 * gets the fallback card with "Open in new tab" instead of an empty frame.
 *
 * The viewer is a throwaway non-admin account with its own dashboard, logged in
 * from an empty storage state.
 *
 * @e2e openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
 */

import type { Browser, BrowserContext, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const STAMP = `${Date.now()}`
const FILES = `Bestanden ${STAMP}`
const ACTIVITY = `Activiteit ${STAMP}`
const PORTAL = `Portaal ${STAMP}`

let viewer: Principal

/**
 * Add a tile to the viewer's dashboard that opens in the side panel.
 *
 * @param dashboardId The dashboard.
 * @param title The tile title.
 * @param linkType `app` or `url`.
 * @param linkValue The app id or the address.
 * @param gridX The column.
 */
async function addPanelTile(dashboardId: number, title: string, linkType: string, linkValue: string, gridX: number): Promise<void> {
	const tile = await viewer.api.post(`${API}/dashboard/${dashboardId}/tile`, {
		data: { title, linkType, linkValue, gridX, gridY: 0, gridWidth: 2, gridHeight: 2 },
	})
	expect(tile.status(), await tile.text()).toBe(201)
	const id = Number((await tile.json()).id)
	const target = await viewer.api.put(`${API}/widgets/${id}`, { data: { content: { linkTarget: 'side-panel' } } })
	expect(target.status(), await target.text()).toBeLessThan(300)
}

/**
 * Log the viewer in on their dashboard; the caller closes the context.
 *
 * @param browser The browser.
 * @return The context and page.
 */
async function openAsViewer(browser: Browser): Promise<{ context: BrowserContext, page: Page }> {
	const session = await loginAs(browser, viewer.username, viewer.password)
	await session.page.goto('/index.php/apps/launchpad/')
	await expect(tileLink(session.page, FILES)).toBeVisible({ timeout: 30_000 })
	return session
}

/**
 * The link of a tile by its title.
 *
 * @param page The page.
 * @param title The tile title.
 * @return The link.
 */
function tileLink(page: Page, title: string) {
	return page.locator('.tile-widget__link').filter({ hasText: title })
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	const admin = await adminContext()
	try {
		await allowPersonalDashboards(admin, true)
		viewer = await createPrincipal(admin, 'e2e-tob-viewer')
	} finally {
		await admin.dispose()
	}
	await assertActingAs(viewer.api, viewer.username)

	const created = await viewer.api.post(`${API}/dashboard`, { data: { name: `E2E side panel ${STAMP}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dashboard = body.dashboard ?? body.data?.dashboard ?? body
	expect((await viewer.api.post(`${API}/dashboard/${dashboard.id}/activate`)).status()).toBeLessThan(300)
	expect((await viewer.api.post(`${API}/dashboards/default`, { data: { uuid: dashboard.uuid } })).status()).toBeLessThan(300)

	await addPanelTile(Number(dashboard.id), FILES, 'app', 'files', 0)
	await addPanelTile(Number(dashboard.id), ACTIVITY, 'app', 'activity', 2)
	await addPanelTile(Number(dashboard.id), PORTAL, 'url', 'https://portal.example.com/', 4)
})

test.afterAll(async () => {
	if (viewer) {
		const admin = await adminContext()
		try {
			await removePrincipal(admin, viewer)
		} finally {
			await admin.dispose()
		}
	}
})

// @e2e tiles::files-opens-beside-the-tiles
test('Files opens beside the tiles and Escape gives focus back to the tile', async ({ browser }) => {
	const { context, page } = await openAsViewer(browser)
	try {
		await tileLink(page, FILES).click()
		const panel = page.locator('[data-test="tile-side-panel"]')
		await expect(panel).toBeVisible({ timeout: 10_000 })
		await expect(panel.locator('[data-test="tile-side-panel-frame"]')).toHaveAttribute('src', /\/apps\/files/)
		await expect(panel.locator('.tile-side-panel__external')).toBeVisible()
		await expect(tileLink(page, ACTIVITY)).toBeVisible()

		await page.keyboard.press('Escape')
		await expect(panel).toBeHidden()
		await expect(tileLink(page, FILES)).toBeFocused()
	} finally {
		await context.close()
	}
})

// @e2e tiles::opening-a-second-tile-replaces-the-panel
test('opening a second panel tile replaces the panel', async ({ browser }) => {
	const { context, page } = await openAsViewer(browser)
	try {
		await tileLink(page, FILES).click()
		await expect(page.locator('[data-test="tile-side-panel"]')).toBeVisible({ timeout: 10_000 })
		await tileLink(page, ACTIVITY).click()
		const panels = page.locator('[data-test="tile-side-panel"]')
		await expect(panels).toHaveCount(1)
		await expect(panels.locator('[data-test="tile-side-panel-frame"]')).toHaveAttribute('src', /\/apps\/activity/)
		await panels.locator('[data-test="tile-side-panel-close"]').click()
		await expect(panels).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e tiles::outside-host-not-on-the-allow-list
test('an outside host that is not allowed shows the fallback card, not an empty frame', async ({ browser }) => {
	const { context, page } = await openAsViewer(browser)
	try {
		await tileLink(page, PORTAL).click()
		const panel = page.locator('[data-test="tile-side-panel"]')
		await expect(panel.locator('[data-test="tile-side-panel-fallback"]')).toBeVisible({ timeout: 15_000 })
		await expect(panel.locator('[data-test="tile-side-panel-frame"]')).toHaveCount(0)
		await expect(panel.locator('[data-test="tile-side-panel-fallback"] a')).toHaveAttribute('href', 'https://portal.example.com/')
	} finally {
		await context.close()
	}
})
