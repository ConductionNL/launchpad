/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the `menu` widget specs (openspec/specs/menu-widget).
 *
 * The renderer is nc-vue's `CnMenuWidget`, dispatched by LaunchPad's
 * `WidgetRenderer` from a placement whose `widgetId` is `menu`. Its config
 * lives in the placement's `content` blob, so every rendering scenario is
 * seeded the same way: a dashboard of its own, one `menu` placement carrying
 * exactly the content the scenario names, and the dashboard made active.
 *
 * ACTIVE-ITEM SCENARIOS NEED AN ADDRESS, NOT JUST A DASHBOARD
 * ==========================================================
 * The widget highlights the item whose URL matches `window.location.pathname`
 * below the router base (`/index.php/apps/launchpad`). A dashboard with a slug
 * is served at `<base>/<slug-chain>` and the shell keeps that canonical path
 * in the address bar (dashboard-deeplinking REQ-DDL-003), so a slug — or a
 * chain of parent slugs — is how a spec puts the page on a path an item can
 * match. `seedMenuDashboard({ slug, parentUuid })` does exactly that.
 *
 * Every seeded dashboard is returned to the caller, which removes it in an
 * `afterEach` through `removeDashboards()`: children before parents.
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'
import { gotoLaunchPad } from '../fixtures/widget-flow.ts'
import { addAndSaveDraft, addWidgetFromMore } from './dashboardSwitcher.ts'
import { chooseAndAdd } from './widgetPicker.ts'

/** The app root, as the router base reads it. */
export const APP = '/index.php/apps/launchpad'

/** The LaunchPad API root. */
export const API = `${APP}/api`

/** The text the empty menu shows (REQ-MENU-011). */
export const EMPTY_TEXT = 'No menu items yet — click the gear icon to add some.'

/** The six-field default content the registry seeds a new menu with (REQ-MENU-001). */
export const DEFAULT_CONTENT = {
	items: [],
	style: 'dropdown',
	orientation: 'horizontal',
	showIcons: true,
	expandedByDefault: false,
	activeItemHighlight: 'underline',
}

/** A seeded dashboard holding one menu placement. */
export interface MenuDashboard {
	id: number
	uuid: string
	slug: string | null
	placementId: number
}

/** What a scenario seeds. */
export interface MenuSeed {
	/** Placement content, exactly as the scenario names it. */
	content: Record<string, unknown>
	/** A slug, so the dashboard has an address of its own. */
	slug?: string
	/** The parent dashboard's uuid, so the slug joins a chain. */
	parentUuid?: string
}

/**
 * Create a personal dashboard, optionally addressable by slug.
 *
 * @param api an admin context.
 * @param name its name.
 * @param slug its slug, or undefined for none.
 * @param parentUuid its parent's uuid, or undefined for a root.
 * @return its id and uuid.
 */
export async function createDashboard(
	api: APIRequestContext,
	name: string,
	slug?: string,
	parentUuid?: string,
): Promise<{ id: number; uuid: string }> {
	const enable = await api.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)

	const data: Record<string, unknown> = { name }
	if (slug) {
		data.slug = slug
	}
	if (parentUuid) {
		data.parentUuid = parentUuid
	}
	const created = await api.post(`${API}/dashboard`, { data })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const id = Number(dash.id)
	expect(id, `no dashboard id in ${JSON.stringify(body)}`).toBeTruthy()
	return { id, uuid: String(dash.uuid) }
}

/**
 * Make a dashboard the calling user's active one, through BOTH mechanisms
 * (`dashboardFixture.ts` explains why one is not enough).
 *
 * @param api an admin context.
 * @param dashboard the dashboard.
 * @param dashboard.id its id.
 * @param dashboard.uuid its uuid.
 * @return nothing.
 */
export async function activate(
	api: APIRequestContext,
	dashboard: { id: number; uuid: string },
): Promise<void> {
	const legacy = await api.post(`${API}/dashboard/${dashboard.id}/activate`)
	expect(legacy.status(), await legacy.text()).toBeLessThan(300)
	const preference = await api.post(`${API}/dashboards/default`, {
		data: { uuid: dashboard.uuid },
	})
	expect(preference.status(), await preference.text()).toBeLessThan(300)
}

/**
 * Place one `menu` widget on a dashboard. Large enough that an open
 * dropdown and its flyout fit inside the cell rather than being clipped.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard.
 * @param content the placement content.
 * @return the raw response, so a refusal can be asserted.
 */
export async function postMenuPlacement(
	api: APIRequestContext,
	dashboardId: number,
	content: Record<string, unknown>,
) {
	return api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'menu',
			gridX: 0,
			gridY: 0,
			gridWidth: 8,
			gridHeight: 8,
			content,
		},
	})
}

/**
 * Seed a dashboard with one menu placement and make it active.
 *
 * @param api an admin context.
 * @param name the dashboard's name.
 * @param seed the content, and optionally a slug and parent.
 * @return the dashboard and its placement.
 */
export async function seedMenuDashboard(
	api: APIRequestContext,
	name: string,
	seed: MenuSeed,
): Promise<MenuDashboard> {
	const dash = await createDashboard(api, name, seed.slug, seed.parentUuid)
	const placed = await postMenuPlacement(api, dash.id, seed.content)
	expect(placed.status(), await placed.text()).toBeLessThan(300)
	const body = await placed.json()
	const placementId = Number(body.id ?? body.data?.id)
	expect(placementId, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	await activate(api, dash)
	return { ...dash, slug: seed.slug ?? null, placementId }
}

/**
 * Delete dashboards, last-created first so a child goes before its parent.
 * Tolerates ids that are already gone.
 *
 * @param api an admin context.
 * @param ids the dashboard ids, in creation order. Emptied on return.
 * @return nothing.
 */
export async function removeDashboards(api: APIRequestContext, ids: number[]): Promise<void> {
	while (ids.length > 0) {
		const id = ids.pop()
		await api.delete(`${API}/dashboard/${id}`)
	}
}

/**
 * The placements of a dashboard, read back from the API.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard.
 * @return its placements.
 */
export async function readPlacements(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Array<Record<string, any>>> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return body.placements ?? body.dashboard?.placements ?? body.data?.placements ?? []
}

/**
 * The rendered menu widget. Each spec seeds a dashboard holding exactly one.
 *
 * @param page the page.
 * @return the widget root.
 */
export function menuWidget(page: Page): Locator {
	return page.locator('.cn-menu-widget').first()
}

/**
 * Open the seeded dashboard and wait for its menu widget to render. A
 * dashboard with a slug is opened at its own address; one without through
 * the app root, where the active-dashboard preference selects it.
 *
 * @param page the page.
 * @param dashboard the seeded dashboard.
 * @param path the slug-chain to open, when it is longer than the slug.
 * @return the widget root.
 */
export async function openMenuDashboard(
	page: Page,
	dashboard: MenuDashboard,
	path?: string,
): Promise<Locator> {
	const chain = path ?? dashboard.slug
	if (chain) {
		const response = await page.goto(`${APP}/${chain}`)
		expect(response?.status()).toBe(200)
	} else {
		await gotoLaunchPad(page)
	}
	const widget = menuWidget(page)
	await expect(widget).toBeVisible({ timeout: 20_000 })
	return widget
}

/**
 * A tree row (caret + label) by its exact label.
 *
 * @param widget the widget root.
 * @param label the item's label.
 * @return the row.
 */
export function treeRow(widget: Locator, label: string): Locator {
	return widget
		.locator('.cn-menu-tree-node__row')
		.filter({ has: widget.page().getByText(label, { exact: true }) })
}

/**
 * Open the Add Widget modal of the open dashboard (More → Add widget) and
 * pick the `menu` type. Leaves the modal open with the menu form mounted.
 *
 * @param page the page, on the start page.
 * @return the dialog.
 */
export async function openMenuForm(page: Page): Promise<Locator> {
	await addWidgetFromMore(page)
	await chooseAndAdd(page.locator('[data-testid="widget-picker-type-menu"]'))
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	const typeSelect = dialog.getByLabel(/widget type/i)
	await expect(typeSelect.locator('option[value="menu"]')).toHaveText(/menu/i)
	await typeSelect.selectOption('menu')
	await expect(dialog.locator('.cn-menu-form')).toBeVisible({ timeout: 5_000 })
	return dialog
}

/**
 * Pick an option of an `NcSelect` by its position. NcSelect is vue-select,
 * so its options exist only while it is open.
 *
 * @param page the page.
 * @param select the `.v-select` root.
 * @param index the option's position.
 * @return nothing.
 */
export async function chooseOption(page: Page, select: Locator, index: number): Promise<void> {
	await select.locator('.vs__dropdown-toggle').click()
	const options = page.locator('.vs__dropdown-menu .vs__dropdown-option')
	await expect(options.nth(index)).toBeVisible({ timeout: 5_000 })
	await options.nth(index).click()
}

/** A placement the Add Widget modal created, as the server stored it. */
export interface SavedPlacement {
	dashboardId: number
	placementId: number
	content: Record<string, any>
}

/**
 * Press Add and return what the server stored, read back by id.
 *
 * @param page the page.
 * @param dialog the Add Widget dialog.
 * @param api an admin context to read back with.
 * @return the stored placement.
 */
export async function submitMenuForm(
	page: Page,
	dialog: Locator,
	api: APIRequestContext,
): Promise<SavedPlacement> {
	// REQ-EDIT-001/002: Add puts the menu in the draft; Save stores it.
	const { dashboardId, placement } = await addAndSaveDraft(page, dialog)
	const placementId = Number(placement.id)

	const stored = (await readPlacements(api, dashboardId)).find((p) => Number(p.id) === placementId)
	expect(stored, `placement ${placementId} was not stored on dashboard ${dashboardId}`).toBeTruthy()
	return { dashboardId, placementId, content: stored!.content ?? {} }
}
