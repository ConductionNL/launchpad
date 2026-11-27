/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Tabs widget (layout-widgets-replace-multi-tile D1): added through Add
 * widget, Layout; its form adds a second tab; each tab shows its own widgets
 * on the well's inner grid; the strip follows the WAI-ARIA tabs pattern; and
 * the server keeps wells and tabs to three nested levels (REQ-CONT-006).
 *
 * The widgets inside the tabs are written over the API here. Putting an App
 * or link tile in a tab through the UI and the edit-mode Save join this file
 * when the app-link-tile-widget and edit-mode-draft-and-save changes land.
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { enterEditMode, saveEditDraft } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const RUN = Date.now()

let admin: APIRequestContext
let owner: Principal | null = null
let dashboard: { id: number; slug: string } | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await allowPersonalDashboards(admin, true)
	owner = await createPrincipal(admin, 'e2e-tabs')
	const created = await owner.api.post(`${API}/dashboard`, { data: { name: `E2E Tabs ${RUN}`, slug: `e2e-tabs-${RUN}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	dashboard = { id: Number(dash.id), slug: String(dash.slug) }
})

test.afterAll(async () => {
	if (dashboard !== null && owner !== null) {
		await owner.api.delete(`${API}/dashboard/${dashboard.id}`)
	}
	await removePrincipal(admin, owner)
	await admin.dispose()
})

/** A text widget as a child of a tab or a well. */
function text(value: string) {
	return { type: 'text', gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2, content: { text: value, contentMode: 'markdown' } }
}

test('Tabs is added through Add widget, Layout, with a second tab from its form', async ({ browser }) => {
	const { context, page } = await loginAs(browser, owner!.username, owner!.password)
	try {
		await page.goto(`${APP}/${dashboard!.slug}`)
		await enterEditMode(page)
		await page.locator('[data-testid="launchpad-edit-bar-add-widget"]').click()
		await page.getByRole('button', { name: /^Layout/ }).first().click()
		await page.getByText('Widgets grouped under tabs.').click()
		await page.getByRole('button', { name: 'Add widget', exact: true }).last().click()
		await expect(page.locator('[data-testid="tabs-widget-form"]')).toBeVisible()
		await page.locator('[data-testid="tabs-widget-form-add"]').click()
		await page.getByRole('dialog').getByRole('button', { name: 'Add', exact: true }).click()

		const widget = page.locator('[data-testid="tabs-widget"]').last()
		await expect(widget.getByRole('tab')).toHaveText(['Tab 1', 'Tab 2'])
		// REQ-EDIT-002: the edit-mode Save stores the new widget.
		expect(await saveEditDraft(page)).toBe(200)
		const stored = await (await owner!.api.get(`${API}/dashboard/${dashboard!.id}`)).json()
		const tabs = (stored.placements ?? []).find((row: { widgetId: string }) => row.widgetId === 'tabs')
		expect(tabs?.content?.tabs).toHaveLength(2)
	} finally {
		await context.close()
	}
})

test('each tab shows its own widgets, and the arrow keys, Home and End move between tabs', async ({ browser }) => {
	const res = await owner!.api.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
		data: {
			widgetId: 'tabs', gridX: 0, gridY: 8, gridWidth: 6, gridHeight: 5,
			content: { title: 'Werk', tabs: [
				{ id: 'team', title: 'Team', placements: [text(`team ${RUN}`)] },
				{ id: 'projects', title: 'Projects', placements: [text(`projects ${RUN}`)] },
				{ id: 'news', title: 'News', placements: [] },
			] },
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)

	const { context, page } = await loginAs(browser, owner!.username, owner!.password)
	try {
		await page.goto(`${APP}/${dashboard!.slug}`)
		const widget = page.locator('[data-testid="tabs-widget"]').filter({ has: page.getByRole('tab', { name: 'Projects' }) })
		const strip = widget.getByRole('tablist', { name: 'Werk' })
		await expect(strip).toBeVisible({ timeout: 30_000 })
		const tabs = strip.getByRole('tab')
		await expect(tabs.nth(0)).toHaveAttribute('aria-selected', 'true')
		await expect(widget.getByRole('tabpanel')).toContainText(`team ${RUN}`)

		await tabs.nth(0).focus()
		await page.keyboard.press('ArrowRight')
		await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true')
		await expect(tabs.nth(1)).toBeFocused()
		await expect(widget.getByRole('tabpanel')).toContainText(`projects ${RUN}`)
		await expect(widget.getByRole('tabpanel')).not.toContainText(`team ${RUN}`)

		await page.keyboard.press('End')
		await expect(tabs.nth(2)).toHaveAttribute('aria-selected', 'true')
		await page.keyboard.press('ArrowRight')
		await expect(tabs.nth(0)).toHaveAttribute('aria-selected', 'true')
		await page.keyboard.press('ArrowLeft')
		await expect(tabs.nth(2)).toHaveAttribute('aria-selected', 'true')
		await page.keyboard.press('Home')
		await expect(tabs.nth(0)).toHaveAttribute('aria-selected', 'true')
	} finally {
		await context.close()
	}
})

test('wells and tabs nest at most three levels deep', async () => {
	const nest = (levels: number, asTabs: boolean): Record<string, unknown> => {
		const child = levels === 0
			? text('deepest')
			: { type: asTabs ? 'container' : 'tabs', gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2, content: nest(levels - 1, !asTabs) }
		return asTabs ? { tabs: [{ id: 'a', title: 'A', placements: [child] }] } : { placements: [child] }
	}
	const place = (levels: number) => owner!.api.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
		data: { widgetId: 'tabs', gridX: 6, gridY: 0, gridWidth: 6, gridHeight: 5, content: nest(levels, true) },
	})

	expect((await place(3)).status()).toBeLessThan(300)
	const refused = await place(4)
	expect(refused.status()).toBe(400)
	expect((await refused.json()).error).toBe('container_depth_exceeded')
})

// REQ-TABS-002 "Tiles in two tabs": a dashboard tile moves into a tab through
// the tile form's Group, which lists each tab as "<Tabs title> > <tab title>".
test('a tile moves into a tab through its Group field', async ({ browser }) => {
	const title = `Zaaksysteem ${RUN}`
	const tabsTitle = `Werk ${RUN}`
	const placed = await owner!.api.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
		data: {
			widgetId: 'tabs',
			gridX: 0,
			gridY: 12,
			gridWidth: 6,
			gridHeight: 4,
			content: { title: tabsTitle, tabs: [{ id: 'team', title: 'Team', placements: [] }, { id: 'samen', title: 'Samenwerken', placements: [] }] },
		},
	})
	expect(placed.status(), await placed.text()).toBeLessThan(300)
	const tabsId = Number((await placed.json()).id)
	const tile = await owner!.api.post(`${API}/dashboard/${dashboard!.id}/tile`, {
		data: { title, linkType: 'url', linkValue: 'https://example.org/zaken', gridX: 6, gridY: 12, gridWidth: 2, gridHeight: 2 },
	})
	expect(tile.status(), await tile.text()).toBeLessThan(300)

	const { context, page } = await loginAs(browser, owner!.username, owner!.password)
	try {
		await page.goto(`${APP}/${dashboard!.slug}`)
		const card = page.locator('.tile-widget').filter({ hasText: title })
		await expect(card).toBeVisible({ timeout: 30_000 })
		await enterEditMode(page)
		await card.getByRole('button', { name: 'Tile menu' }).click()
		await page.getByRole('menuitem', { name: 'Edit tile' }).click()

		const group = page.locator('[data-testid="tile-group"]')
		await group.click()
		await page.locator('.vs__dropdown-option').filter({ hasText: `${tabsTitle} > Samenwerken` }).first().click()
		const saved = page.waitForResponse((res) => res.url().includes(`/widgets/${tabsId}`) && res.request().method() === 'PUT')
		await page.getByRole('button', { name: 'Save', exact: true }).click()
		expect((await saved).status()).toBeLessThan(300)
		await expect(card).toHaveCount(0, { timeout: 15_000 })
	} finally {
		await context.close()
	}

	const stored = await (await owner!.api.get(`${API}/dashboard/${dashboard!.id}`)).json()
	const tabs = (stored.placements ?? []).find((row: { id: number }) => Number(row.id) === tabsId)
	expect(tabs.content.tabs[0].placements).toHaveLength(0)
	expect(tabs.content.tabs[1].placements).toHaveLength(1)
	expect(tabs.content.tabs[1].placements[0].content.title).toBe(title)
	expect((stored.placements ?? []).some((row: { content?: { title?: string } }) => row.content?.title === title)).toBe(false)
})
