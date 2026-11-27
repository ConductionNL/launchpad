/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A well leaves out tiles the viewer may not open (REQ-CONT-008) and can fill
 * itself with the viewer's own apps (REQ-CONT-009): pinned first, at most the
 * limit, and a search field that narrows them by name.
 *
 * The viewer's apps are read from the page's own app menu (Nextcloud's boot
 * data), so the expectations follow whatever apps the instance gives a new
 * account rather than a fixed list.
 *
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-6
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const RUN = Date.now()
const NOT_INSTALLED = 'e2e-app-that-is-not-installed'

let admin: APIRequestContext
let viewer: Principal | null = null
let dashboard: { id: number; slug: string } | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await allowPersonalDashboards(admin, true)
	viewer = await createPrincipal(admin, 'e2e-well-apps')
	const created = await viewer.api.post(`${API}/dashboard`, { data: { name: `E2E Wells ${RUN}`, slug: `e2e-wells-${RUN}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	dashboard = { id: Number(dash.id), slug: String(dash.slug) }
})

test.afterAll(async () => {
	if (dashboard !== null && viewer !== null) {
		await viewer.api.delete(`${API}/dashboard/${dashboard.id}`)
	}
	await removePrincipal(admin, viewer)
	await admin.dispose()
})

/** The viewer's app menu as the page carries it, in menu order. */
async function appMenu(page: Page): Promise<Array<{ id: string; name: string }>> {
	return page.evaluate(() => {
		const el = document.getElementById('initial-state-core-apps') as HTMLInputElement | null
		const apps = el ? JSON.parse(atob(el.value)) : []
		return (Array.isArray(apps) ? apps : Object.values(apps))
			.sort((a: { order?: number }, b: { order?: number }) => (a.order ?? 0) - (b.order ?? 0))
			.map((app: { id: string; name: string }) => ({ id: app.id, name: app.name }))
	})
}

/** Place a well on the dashboard. */
async function placeWell(content: Record<string, unknown>, gridY: number): Promise<void> {
	const res = await viewer!.api.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
		data: { widgetId: 'container', gridX: 0, gridY, gridWidth: 12, gridHeight: 5, content },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
}

test('a well leaves out an app tile the viewer may not open and closes up', async ({ browser }) => {
	await placeWell({
		title: `Placed ${RUN}`,
		placements: [
			{ type: 'tile', gridX: 0, gridY: 0, gridWidth: 1, gridHeight: 2, content: { mode: 'app', appId: 'files' } },
			{ type: 'tile', gridX: 1, gridY: 0, gridWidth: 1, gridHeight: 2, content: { mode: 'app', appId: NOT_INSTALLED } },
			{ type: 'text', gridX: 2, gridY: 0, gridWidth: 1, gridHeight: 2, content: { text: `note ${RUN}`, contentMode: 'markdown' } },
		],
	}, 0)

	const { context, page } = await loginAs(browser, viewer!.username, viewer!.password)
	try {
		await page.goto(`${APP}/${dashboard!.slug}`)
		const well = page.locator('.container-widget').filter({ hasText: `Placed ${RUN}` })
		await expect(well).toContainText(`note ${RUN}`, { timeout: 30_000 })
		const cells = well.locator('.launchpad-container-grid > .container-widget__child')
		await expect(cells).toHaveCount(2)
		await expect(cells.nth(1)).toHaveAttribute('gs-x', '1')
	} finally {
		await context.close()
	}
})

test('a well with an app list shows the viewer\'s apps, pinned first, at most the limit, and the filter narrows them', async ({ browser }) => {
	const { context, page } = await loginAs(browser, viewer!.username, viewer!.password)
	try {
		await page.goto(`${APP}/${dashboard!.slug}`)
		const menu = (await appMenu(page)).filter((app) => app.id !== 'launchpad')
		expect(menu.length, 'CONTROL: a new account has at least two apps besides Launchpad').toBeGreaterThanOrEqual(2)
		const pinned = menu[menu.length - 1]

		await placeWell({
			title: `My apps ${RUN}`,
			appList: { pinned: [pinned.id, NOT_INSTALLED], exclude: ['launchpad'], mostUsedLimit: 1, showFilter: true },
			placements: [],
		}, 6)
		await page.reload()

		const well = page.locator('.container-widget').filter({ hasText: `My apps ${RUN}` })
		const flow = well.getByTestId('container-flow')
		await expect(flow).toBeVisible({ timeout: 30_000 })
		const cells = flow.locator('.container-widget__cell')
		// The pinned app, then one more (the limit); the app the viewer lacks is not there.
		await expect(cells).toHaveCount(2)

		const first = menu[0]
		await well.getByTestId('container-app-filter').fill(first.name.slice(0, 3))
		const matching = menu.filter((app) => app.name.toLocaleLowerCase().includes(first.name.slice(0, 3).toLocaleLowerCase()))
		await expect(cells).toHaveCount(matching.length)

		await well.getByTestId('container-app-filter').fill('')
		await expect(cells).toHaveCount(2)
	} finally {
		await context.close()
	}
})

test('the start page shows "My apps" as a well of the viewer\'s apps, and Add widget offers no multi-tile', async ({ browser }) => {
	const fresh = await createPrincipal(admin, 'e2e-well-start')
	const { context, page } = await loginAs(browser, fresh.username, fresh.password)
	try {
		await page.goto(APP)
		const menu = (await appMenu(page)).filter((app) => app.id !== 'launchpad')
		const flow = page.getByTestId('container-flow').first()
		await expect(flow).toBeVisible({ timeout: 30_000 })
		// The seed lists up to six apps after the pinned ones (none pinned).
		await expect(flow.locator('.container-widget__cell')).toHaveCount(Math.min(6, menu.length))

		const stored = await (await fresh.api.get(`${API}/dashboards/visible`)).json()
		const rows = Array.isArray(stored) ? stored : (stored.items ?? stored.dashboards ?? [])
		for (const row of rows) {
			const detail = await (await fresh.api.get(`${API}/dashboard/${row.id}`)).json()
			const types = (detail.placements ?? []).map((placement: { widgetId: string }) => placement.widgetId)
			expect(types, 'no seeded dashboard holds a multi-tile').not.toContain('app-tiles')
		}
	} finally {
		await context.close()
		await removePrincipal(admin, fresh)
	}

	const own = await loginAs(browser, viewer!.username, viewer!.password)
	try {
		await own.page.goto(`${APP}/${dashboard!.slug}`)
		await enterEditMode(own.page)
		await own.page.locator('[data-testid="launchpad-edit-bar-add-widget"]').click()
		await expect(own.page.getByText('Widgets grouped under tabs.')).toBeVisible()
		await expect(own.page.getByText('Tiles for several apps at once.')).toHaveCount(0)
	} finally {
		await own.context.close()
	}
})
