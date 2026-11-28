/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A throwaway account with one personal dashboard of its own, for specs that
 * arrange widgets and tiles and then read the layout back.
 *
 * The account is new, so its dashboard is the only one it has and is active on
 * login; nothing another spec left behind lands on the grid it measures. The
 * admin flag that allows personal dashboards is read first and put back on
 * teardown, and so is the `default` widget rule: it governs an account in no
 * group, and other specs leave a restrictive one behind, so it is lifted for
 * the account's lifetime and restored afterwards.
 */

import type {
	APIRequestContext,
	Browser,
	BrowserContext,
	Page,
	Response,
} from '@playwright/test'
import type { RoleFeaturePermissionRow } from '../fixtures/role-feature-permissions.ts'
import type { Principal } from './principals.ts'

import { expect } from '@playwright/test'
import {
	deleteRoleFeaturePermission,
	listRoleFeaturePermissions,
	restoreRoleFeaturePermissions,
} from '../fixtures/role-feature-permissions.ts'
import { loginAs } from '../fixtures/secondary-user.ts'
import { clearSeededWidgets } from './dashboardFixture.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './principals.ts'

/**
 * The dashboard's own grid items. A container widget holds a grid of its own
 * (`.launchpad-container-grid`), whose children are not dashboard items.
 */
export const TOP_LEVEL_ITEMS = '.grid-stack:not(.launchpad-container-grid) > .grid-stack-item'

export interface OwnDashboard {
	owner: Principal
	id: number
	uuid: string
	allowBefore: boolean | null
	defaultRuleBefore: RoleFeaturePermissionRow | null
}

export interface Rect {
	x: number
	y: number
	w: number
	h: number
}

/**
 * Create a throwaway account with one active personal dashboard.
 *
 * @param prefix The account name prefix.
 * @param name The dashboard name.
 * @param extra Extra dashboard fields (e.g. `gridColumns`).
 * @return The account and its dashboard.
 */
export async function setUpOwnDashboard(
	prefix: string,
	name: string,
	extra: Record<string, unknown> = {},
): Promise<OwnDashboard> {
	const admin = await adminContext()
	let owner: Principal
	let allowBefore: boolean | null
	let defaultRuleBefore: RoleFeaturePermissionRow | null
	try {
		const settings = await admin.get(`${API}/admin/settings`)
		expect(settings.status(), await settings.text()).toBe(200)
		const body = await settings.json()
		const current = (body.data ?? body).allowUserDashboards
		allowBefore = typeof current === 'boolean' ? current : null
		const allow = await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: true },
		})
		expect(allow.status(), await allow.text()).toBe(200)
		defaultRuleBefore = (await listRoleFeaturePermissions(admin))
			.find((row) => row.groupId === 'default') ?? null
		if (defaultRuleBefore !== null) {
			await deleteRoleFeaturePermission(admin, defaultRuleBefore.id)
		}
		owner = await createPrincipal(admin, prefix)
	} finally {
		await admin.dispose()
	}
	await assertActingAs(owner.api, owner.username)

	const created = await owner.api.post(`${API}/dashboard`, {
		data: { name, ...extra },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const json = await created.json()
	const dash = json.dashboard ?? json.data?.dashboard ?? json
	const id = Number(dash.id)
	expect(id, JSON.stringify(json)).toBeGreaterThan(0)
	// A new dashboard carries the default bundle; the grid starts empty.
	await clearSeededWidgets(owner.api, id)
	expect(
		(await owner.api.post(`${API}/dashboard/${id}/activate`)).status(),
	).toBeLessThan(300)
	expect(
		(
			await owner.api.post(`${API}/dashboards/default`, {
				data: { uuid: dash.uuid },
			})
		).status(),
	).toBeLessThan(300)

	return { owner, id, uuid: String(dash.uuid), allowBefore, defaultRuleBefore }
}

/**
 * Remove the account (and with it its dashboard) and put the admin flag and
 * the `default` widget rule back.
 *
 * @param own The fixture, or undefined when set-up failed early.
 */
export async function tearDownOwnDashboard(
	own: OwnDashboard | undefined,
): Promise<void> {
	if (!own) {
		return
	}
	const admin = await adminContext()
	try {
		await own.owner.api.delete(`${API}/dashboard/${own.id}`)
		await removePrincipal(admin, own.owner)
		if (own.allowBefore !== null) {
			await admin.put(`${API}/admin/settings`, {
				data: { allowUserDash: own.allowBefore },
			})
		}
		if (own.defaultRuleBefore !== null) {
			await restoreRoleFeaturePermissions(admin, [own.defaultRuleBefore])
		}
	} finally {
		await admin.dispose()
	}
}

/**
 * Place a text widget at an exact rectangle.
 *
 * @param api The owner's context.
 * @param dashboardId The dashboard.
 * @param rect Where.
 * @param text The widget text.
 * @return The placement id.
 */
export async function seedTextWidget(
	api: APIRequestContext,
	dashboardId: number,
	rect: Rect,
	text: string,
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: rect.x,
			gridY: rect.y,
			gridWidth: rect.w,
			gridHeight: rect.h,
			content: { text, contentMode: 'markdown' },
		},
	})
	expect(res.status(), await res.text()).toBe(201)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id)
	const stored = await placementRects(api, dashboardId)
	expect(
		stored.get(id),
		`placement ${id} did not land where it was asked`,
	).toEqual(rect)
	return id
}

/**
 * The placements of a dashboard as the server stores them.
 *
 * @param api A context that may read the dashboard.
 * @param dashboardId The dashboard.
 * @return The placements.
 */
export async function readPlacements(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Array<Record<string, any>>> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (body.placements ?? body.data?.placements ?? []) as Array<
		Record<string, any>
	>
}

/**
 * The stored rectangle of every placement, by id.
 *
 * @param api A context that may read the dashboard.
 * @param dashboardId The dashboard.
 * @return The rectangles.
 */
export async function placementRects(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Map<number, Rect>> {
	const rects = new Map<number, Rect>()
	for (const p of await readPlacements(api, dashboardId)) {
		rects.set(Number(p.id), {
			x: Number(p.gridX),
			y: Number(p.gridY),
			w: Number(p.gridWidth),
			h: Number(p.gridHeight),
		})
	}
	return rects
}

/**
 * The rendered rectangle of every grid item, by its `gs-id`.
 *
 * @param page The page.
 * @return The rectangles.
 */
export async function renderedRects(page: Page): Promise<Map<number, Rect>> {
	const items = await page.evaluate((selector) =>
		Array.from(document.querySelectorAll(selector)).map(
			(el) => [
				Number(el.getAttribute('gs-id')),
				{
					x: Number(el.getAttribute('gs-x') ?? 0),
					y: Number(el.getAttribute('gs-y') ?? 0),
					w: Number(el.getAttribute('gs-w') ?? 1),
					h: Number(el.getAttribute('gs-h') ?? 1),
				},
			],
		),
	TOP_LEVEL_ITEMS)
	return new Map(items as Array<[number, Rect]>)
}

/**
 * Whether any two rectangles overlap.
 *
 * @param rects The rectangles.
 * @return True when a pair overlaps.
 */
export function anyOverlap(rects: Rect[]): boolean {
	for (let i = 0; i < rects.length; i++) {
		for (let j = i + 1; j < rects.length; j++) {
			const a = rects[i]
			const b = rects[j]
			if (
				a.x < b.x + b.w
				&& b.x < a.x + a.w
				&& a.y < b.y + b.h
				&& b.y < a.y + a.h
			) {
				return true
			}
		}
	}
	return false
}

/**
 * Log the owner in on the workspace and wait for the grid to hold the given
 * number of items.
 *
 * @param browser The browser.
 * @param own The fixture.
 * @param items How many grid items the dashboard holds.
 * @return The context and page; the caller closes the context.
 */
export async function openOwnDashboard(
	browser: Browser,
	own: OwnDashboard,
	items: number,
): Promise<{ context: BrowserContext; page: Page }> {
	const session = await loginAs(browser, own.owner.username, own.owner.password)
	await session.page.setViewportSize({ width: 1600, height: 1000 })
	await session.page.goto('/index.php/apps/launchpad/')
	await expect(session.page.locator(TOP_LEVEL_ITEMS)).toHaveCount(
		items,
		{
			timeout: 30_000,
		},
	)
	await session.page.waitForFunction(() => {
		const el = document.querySelector('.grid-stack') as
			(HTMLElement & { gridstack?: unknown }) | null
		return Boolean(el?.gridstack)
	})
	return session
}

/**
 * The next layout save of a dashboard (`PUT /api/dashboard/{id}`). In edit
 * mode a layout change goes into the draft (REQ-EDIT-001), so awaiting this
 * chooses Save in the edit bar first, when it is there and enabled, and then
 * waits for that one request (REQ-EDIT-002). Call it before the change and
 * await it after, as before.
 *
 * @param page The page.
 * @param dashboardId The dashboard.
 * @return A promise-like for the response, once it arrives.
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */
export function nextLayoutSave(page: Page, dashboardId: number): PromiseLike<Response> {
	const run = async (): Promise<Response> => {
		const response = page.waitForResponse(
			(res) =>
				res.request().method() === 'PUT'
				&& new RegExp(`/api/dashboard/${dashboardId}(\\?|$)`).test(res.url()),
			{ timeout: 15_000 },
		)
		const sidebarClose = page.locator('.dashboard-switcher-sidebar.open .dashboard-switcher-sidebar__close').first()
		if (await sidebarClose.isVisible().catch(() => false)) {
			await sidebarClose.click()
		}
		const save = page.locator('[data-testid="launchpad-edit-bar-save"]')
		if (await save.isVisible() && await save.isEnabled()) {
			await save.click()
		}
		return response
	}
	return { then: (resolve, reject) => run().then(resolve, reject) }
}
