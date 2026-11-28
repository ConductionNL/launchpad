/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Seeding helpers for the widgets e2e specs (openspec/specs/widgets): an admin
 * request context over basic auth, dashboards that become the admin's active
 * one, placements added through the real `POST /api/dashboard/{id}/widgets`
 * and `POST /api/dashboard/{id}/tile` routes, a re-read of a dashboard's
 * placements, and the mocked Nextcloud dashboard item envelopes the nc-widget
 * renderer reads.
 *
 * Everything a spec seeds goes into one `Seeds` bag, and `cleanup()` removes
 * every dashboard in it (placements go with their dashboard).
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './dashboardFixture.ts'

import { expect, request } from '@playwright/test'
import { BASE_URL } from './baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './dashboardFixture.ts'
import { enterEditMode } from './dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

/** The LaunchPad API root. */
export const LP_API = '/index.php/apps/launchpad/api'

/** One placement as the API returns it. */
export type Placement = Record<string, unknown> & { id: number; widgetId: string }

/**
 * The admin over basic auth, with an explicitly empty cookie jar so the
 * project's stored browser session is not what authenticates the call.
 *
 * @return the context; the caller disposes it.
 */
export async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: `Basic ${Buffer.from(`${ADMIN.user}:${ADMIN.pass}`).toString('base64')}`,
		},
		timeout: 60_000,
	})
}

/** What a spec seeded, for `cleanup()`. */
export class Seeds {
	readonly dashboards: SeededDashboard[] = []

	/**
	 * @param api the admin context.
	 */
	constructor(readonly api: APIRequestContext) {}

	/**
	 * Create a dashboard and make it the admin's active one.
	 *
	 * @param name its display name.
	 * @return the seeded dashboard.
	 */
	async dashboard(name: string): Promise<SeededDashboard> {
		const dash = await seedActiveDashboard(this.api, name)
		this.dashboards.push(dash)
		return dash
	}

	/**
	 * Make an already-seeded dashboard the admin's active one again.
	 *
	 * @param dash the dashboard.
	 * @return nothing.
	 */
	async activate(dash: SeededDashboard): Promise<void> {
		const legacy = await this.api.post(`${LP_API}/dashboard/${dash.id}/activate`)
		expect(legacy.status(), await legacy.text()).toBeLessThan(300)
		const pref = await this.api.post(`${LP_API}/dashboards/default`, {
			data: { uuid: dash.uuid },
		})
		expect(pref.status(), await pref.text()).toBeLessThan(300)
	}

	/**
	 * Add a placement through `POST /api/dashboard/{id}/widgets`.
	 *
	 * @param dash the dashboard.
	 * @param data widgetId, grid position and content.
	 * @return the created placement.
	 */
	async widget(
		dash: SeededDashboard,
		data: Record<string, unknown>,
	): Promise<Placement> {
		const res = await this.api.post(`${LP_API}/dashboard/${dash.id}/widgets`, {
			data: { gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 3, ...data },
		})
		expect(res.status(), await res.text()).toBe(201)
		const body = await res.json()
		expect(Number(body.id), JSON.stringify(body)).toBeGreaterThan(0)
		return { ...body, id: Number(body.id) }
	}

	/**
	 * Add a legacy flat-column tile through `POST /api/dashboard/{id}/tile`.
	 *
	 * @param dash the dashboard.
	 * @param data the tile fields.
	 * @return the created placement.
	 */
	async legacyTile(
		dash: SeededDashboard,
		data: Record<string, unknown>,
	): Promise<Placement> {
		const res = await this.api.post(`${LP_API}/dashboard/${dash.id}/tile`, {
			data: { gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2, ...data },
		})
		expect(res.status(), await res.text()).toBe(201)
		const body = await res.json()
		return { ...body, id: Number(body.id) }
	}

	/**
	 * Re-read a dashboard's placements from the server.
	 *
	 * @param dash the dashboard.
	 * @return its placements.
	 */
	async placements(dash: SeededDashboard): Promise<Placement[]> {
		const res = await this.api.get(`${LP_API}/dashboard/${dash.id}`)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		const list =
			body.placements
			?? body.dashboard?.placements
			?? body.data?.placements
			?? []
		return (list as Placement[]).map((p) => ({ ...p, id: Number(p.id) }))
	}

	/**
	 * Remove every seeded dashboard and dispose the context.
	 *
	 * @return nothing.
	 */
	async cleanup(): Promise<void> {
		for (const dash of this.dashboards) {
			await removeSeededDashboard(this.api, dash)
		}
		await this.api.dispose()
	}
}

/**
 * The grid cell of a placement: the element that owns the `@contextmenu`
 * listener in Views.vue.
 *
 * @param page the page.
 * @param id the placement id.
 * @return the locator.
 */
export function gridItem(page: Page, id: number) {
	return page.locator(`.launchpad-grid-item[data-placement-id="${id}"]`)
}

/**
 * Open the workspace on the active dashboard and wait for one placement.
 *
 * @param page the page.
 * @param id a placement that must render.
 * @return nothing.
 */
export async function openWorkspace(page: Page, id?: number): Promise<void> {
	await page.goto('/index.php/apps/launchpad')
	await expect(page.locator('[data-testid="launchpad-more-menu"]')).toBeVisible({
		timeout: 30_000,
	})
	if (id !== undefined) {
		await expect(gridItem(page, id)).toBeVisible({ timeout: 20_000 })
	}
}

/**
 * Open the workspace and put the active dashboard in edit mode (More → Edit).
 *
 * @param page the page.
 * @param id a placement that must render.
 * @return nothing.
 */
export async function openInEditMode(page: Page, id: number): Promise<void> {
	await openWorkspace(page, id)
	await enterEditMode(page)
}

/**
 * Right-click a point through the real `contextmenu` path: dispatch the event
 * at the given viewport coordinates on the placement's rendered content, and
 * report whether a handler cancelled it (a cancelled `contextmenu` is the one
 * where the browser does NOT show its own menu).
 *
 * Dispatched rather than `mouse.click({ button: 'right' })` because a real
 * pointer click goes to whatever element sits at those coordinates, and the
 * scenarios name exact viewport coordinates that need not lie inside the
 * cell; `label-widget-content-edit.spec.ts` records the same remedy for the
 * actionability failures a real click on a grid cell hit in CI. The handler
 * under test (Views.vue `@contextmenu` → useGridManager.onWidgetRightClick)
 * is exactly the one a real click reaches.
 *
 * @param page the page.
 * @param id the placement id.
 * @param x viewport X.
 * @param y viewport Y.
 * @return true when the browser's own context menu would be shown.
 */
export async function rightClickAt(
	page: Page,
	id: number,
	x: number,
	y: number,
): Promise<boolean> {
	const target = gridItem(page, id).locator('.cn-widget-wrapper__content').first()
	await expect(target).toBeVisible()
	return target.evaluate(
		(el, at) =>
			el.dispatchEvent(
				new MouseEvent('contextmenu', {
					bubbles: true,
					cancelable: true,
					composed: true,
					button: 2,
					clientX: at.x,
					clientY: at.y,
				}),
			),
		{ x, y },
	)
}

/**
 * The centre of a placement's rendered content, in viewport coordinates.
 *
 * @param page the page.
 * @param id the placement id.
 * @return the point.
 */
export async function contentCentre(
	page: Page,
	id: number,
): Promise<{ x: number; y: number }> {
	const target = gridItem(page, id).locator('.cn-widget-wrapper__content').first()
	await target.scrollIntoViewIfNeeded()
	const box = await target.boundingBox()
	expect(box, 'placement content has no box').not.toBeNull()
	return {
		x: Math.round(box!.x + box!.width / 2),
		y: Math.round(box!.y + box!.height / 2),
	}
}

/**
 * The OCS envelope `GET /ocs/v2.php/apps/dashboard/api/v2/widget-items`
 * answers with, for one widget.
 *
 * @param widgetId the Nextcloud widget id.
 * @param items the items.
 * @return the JSON body.
 */
export function ocsWidgetItems(
	widgetId: string,
	items: Array<Record<string, unknown>>,
): string {
	return JSON.stringify({
		ocs: {
			meta: { status: 'ok', statuscode: 200, message: 'OK' },
			data: {
				[widgetId]: {
					items,
					emptyContentMessage: '',
					halfEmptyContentMessage: '',
				},
			},
		},
	})
}

/**
 * Whether a request is the GET /api/widgets catalogue (not /api/widgets/items).
 *
 * @param url the request URL.
 * @return true for the widget catalogue.
 */
export function isWidgetCatalogue(url: URL): boolean {
	return url.pathname.endsWith('/apps/launchpad/api/widgets')
}
