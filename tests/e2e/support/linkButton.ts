/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the link-button widget specs
 * (openspec/specs/link-button-widget).
 *
 * The `link` widget renders through `LinkButtonHost`, which delegates the
 * drawing to nc-vue's `CnLinkButtonWidget` and owns the createFile modal
 * itself. Every spec here seeds placements through
 * `POST /api/dashboard/{id}/widgets` with exactly the `content` a scenario
 * names, opens LaunchPad the way a person does, and asserts the rendered DOM.
 *
 * WHY THE SEEDING CONTEXT USES BASIC AUTH WITH `send: 'always'`
 * =============================================================
 * `playwright.config.ts` gives every context the admin's session cookie. A
 * cookie-carrying write without a CSRF token is refused with 412, so the API
 * context goes through `contextFor()`, which sends basic credentials on every
 * call and never rides the cookie.
 *
 * WHY EXTERNAL HOSTS ARE STUBBED
 * ==============================
 * The scenarios name `https://example.com` and `https://nc/...`. The CI
 * fixture has no guaranteed outbound internet, and `https://nc` resolves
 * nowhere, so `stubHost()` answers those origins inside the browser context.
 * The popup still opens at the real address; only the response is local.
 */

import type { APIRequestContext, BrowserContext, Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'
import { gotoLaunchPad } from '../fixtures/widget-flow.ts'
import { addWidgetFromMore, enterEditMode, switchToDashboard } from './dashboardSwitcher.ts'
import { contextFor } from './principals.ts'
import { chooseAndAdd } from './widgetPicker.ts'

export const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

export const API = '/index.php/apps/launchpad/api'

/** Where the admin's own files live over WebDAV. */
export const DAV = `/remote.php/dav/files/${encodeURIComponent(ADMIN.user)}`

/** The renderer's root, the single button, and a list item. */
export const WIDGET = '.cn-link-button-widget'
export const BUTTON = '.cn-link-button-widget__button'
export const LIST_ITEM = '.cn-link-button-widget__list-item'

/** The createFile modal LinkButtonHost renders. */
export const MODAL = '.link-button-host__modal-backdrop'

/** A placement content blob, as loose as the scenarios write it. */
export type LinkContent = Record<string, unknown>

/** A basic-auth admin context. The caller disposes it. */
export async function adminApi(): Promise<APIRequestContext> {
	return contextFor(ADMIN.user, ADMIN.pass)
}

/**
 * Seed one `link` placement with exactly the given content.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard to place it on.
 * @param content the placement content, verbatim.
 * @param box optional grid position and size.
 * @param box.gridX column.
 * @param box.gridY row.
 * @param box.gridWidth width in columns.
 * @param box.gridHeight height in rows.
 * @return the placement id.
 */
export async function seedLink(
	api: APIRequestContext,
	dashboardId: number,
	content: LinkContent,
	box: { gridX?: number; gridY?: number; gridWidth?: number; gridHeight?: number } = {},
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'link',
			gridX: box.gridX ?? 0,
			gridY: box.gridY ?? 0,
			gridWidth: box.gridWidth ?? 6,
			gridHeight: box.gridHeight ?? 3,
			content,
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id ?? body.placement?.id)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/**
 * Delete placements, tolerating ones that are already gone.
 *
 * @param api an admin context.
 * @param ids the placement ids; the array is emptied.
 * @return nothing.
 */
export async function removePlacements(api: APIRequestContext, ids: number[]): Promise<void> {
	while (ids.length > 0) {
		const id = ids.pop()
		await api.delete(`${API}/widgets/${id}`)
	}
}

/**
 * Read one placement back from the dashboard it sits on.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard.
 * @param placementId the placement.
 * @return the placement as the API serialises it.
 */
export async function readPlacement(
	api: APIRequestContext,
	dashboardId: number,
	placementId: number,
): Promise<{ id: number; widgetId: string; content: LinkContent }> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const placements: Array<{ id: number; widgetId: string; content: LinkContent }>
		= body.placements ?? body.data?.placements ?? []
	const found = placements.find((p) => Number(p.id) === placementId)
	expect(found, `placement ${placementId} is not on dashboard ${dashboardId}`).toBeTruthy()
	return found!
}

/**
 * The grid cell of one placement.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the cell.
 */
export function cell(page: Page, placementId: number): Locator {
	return page.locator(`[data-placement-id="${placementId}"]`)
}

/**
 * Open LaunchPad and wait until a placement's link widget has rendered.
 *
 * @param page the page.
 * @param placementId the placement to wait for.
 * @return the widget root inside that cell.
 */
export async function openWithWidget(page: Page, placementId: number): Promise<Locator> {
	await gotoLaunchPad(page)
	const widget = cell(page, placementId).locator(WIDGET)
	await expect(widget).toBeVisible({ timeout: 20_000 })
	await widget.scrollIntoViewIfNeeded()
	return widget
}

/**
 * Answer every request to an origin with a tiny local page, so a popup to it
 * loads without outbound internet.
 *
 * @param context the browser context the popup will open in.
 * @param origin e.g. `https://example.com`.
 * @return nothing.
 */
export async function stubHost(context: BrowserContext, origin: string): Promise<void> {
	await context.route(`${origin}/**`, (route) => route.fulfill({
		status: 200,
		contentType: 'text/html',
		body: '<!doctype html><title>stub</title><p>stub</p>',
	}))
}

/**
 * Collect every page the context opens from now on.
 *
 * @param context the browser context.
 * @return a live array of opened pages.
 */
export function collectPopups(context: BrowserContext): Page[] {
	const opened: Page[] = []
	context.on('page', (p) => opened.push(p))
	return opened
}

/**
 * Open the Add Widget modal from a named dashboard's own cog menu, with the
 * Link Button type chosen.
 *
 * @param page the page, on the LaunchPad workspace.
 * @param dashboardName the dashboard row to use.
 * @return the dialog.
 */
export async function openAddLinkForm(page: Page, dashboardName: string): Promise<Locator> {
	await switchToDashboard(page, dashboardName)
	await addWidgetFromMore(page)
	await chooseAndAdd(page.locator('[data-testid="widget-picker-type-link"]'))
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await expect(dialog.locator('.cn-link-button-widget-form')).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Open the content editor of an existing placement: edit mode, right-click,
 * Edit. The widget context menu only opens in edit mode.
 *
 * The contextmenu event is dispatched rather than clicked, for the reason
 * `label-widget-content-edit.spec.ts` records: the real right-click is
 * actionability-checked and the cell can report "outside of the viewport".
 * The handler is a plain `@contextmenu` listener, so the code path is the same.
 *
 * @param page the page, with the placement rendered.
 * @param placementId the placement.
 * @return the Edit Widget dialog.
 */
export async function openEditForm(page: Page, placementId: number): Promise<Locator> {
	if ((await page.locator('.launchpad-edit-mode').count()) === 0) {
		await enterEditMode(page)
	}
	await cell(page, placementId).dispatchEvent('contextmenu')
	await expect(page.locator('[data-testid="widget-context-menu"]')).toBeVisible({ timeout: 5_000 })
	await page.locator('[data-testid="ctx-edit"]').click()
	const dialog = page.getByRole('dialog', { name: /edit widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await expect(dialog.locator('.cn-link-button-widget-form')).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Pick an option of an `NcSelect` by its position.
 *
 * By position, not label text: NcSelect renders option labels through
 * `name-parts`, which splits the text across spans (see
 * `conditional-visibility-editor.spec.ts`).
 *
 * @param page the page (the dropdown is appended to the body).
 * @param scope where the select lives.
 * @param inputLabel the select's `inputLabel`.
 * @param index the option's position.
 * @return nothing.
 */
export async function pickOption(
	page: Page,
	scope: Locator,
	inputLabel: string,
	index: number,
): Promise<void> {
	await scope.getByLabel(inputLabel, { exact: true }).first().click()
	const options = page.locator('.vs__dropdown-menu .vs__dropdown-option')
	await expect(options.nth(index)).toBeVisible({ timeout: 5_000 })
	await options.nth(index).click()
}

/**
 * The text an `NcSelect` currently shows as selected.
 *
 * @param scope where the select lives.
 * @param inputLabel the select's `inputLabel`.
 * @return the selected value's locator.
 */
export function selectedOf(scope: Locator, inputLabel: string): Locator {
	return scope
		.getByLabel(inputLabel, { exact: true })
		.first()
		.locator('xpath=ancestor::div[contains(concat(" ", normalize-space(@class), " "), " v-select ")][1]')
		.locator('.vs__selected')
}

/**
 * Assert what an `NcSelect` shows as selected, ignoring whitespace: the
 * option label can be split across `name-parts` spans.
 *
 * @param scope where the select lives.
 * @param inputLabel the select's `inputLabel`.
 * @param text the option label expected.
 * @return nothing.
 */
export async function expectSelected(scope: Locator, inputLabel: string, text: string): Promise<void> {
	const squash = (value: string) => value.replace(/\s+/g, '')
	await expect
		.poll(
			async () => squash((await selectedOf(scope, inputLabel).allTextContents()).join('')),
			{ message: `the "${inputLabel}" select should show "${text}"` },
		)
		.toBe(squash(text))
}

/** Display-mode options, in the form's own order. */
export const DISPLAY = { button: 0, list: 1 }

/** Action-type options, in the form's own order. */
export const ACTION = { external: 0, internal: 1, createFile: 2 }

/**
 * The widget-level computed transform and shadow of an element.
 *
 * @param target the element.
 * @return its computed transform and box-shadow.
 */
export async function liftOf(target: Locator): Promise<{ transform: string; shadow: string }> {
	return target.evaluate((el) => {
		const style = getComputedStyle(el)
		return { transform: style.transform, shadow: style.boxShadow }
	})
}

/**
 * Put a file in the admin's Files root.
 *
 * @param api an admin context.
 * @param name the file name.
 * @param body its content.
 * @return nothing.
 */
export async function davPut(api: APIRequestContext, name: string, body: string): Promise<void> {
	const res = await api.put(`${DAV}/${encodeURIComponent(name)}`, { data: body })
	expect(res.status(), await res.text()).toBeLessThan(300)
}

/**
 * Read a file from the admin's Files root.
 *
 * @param api an admin context.
 * @param name the file name.
 * @return the status and the body.
 */
export async function davGet(
	api: APIRequestContext,
	name: string,
): Promise<{ status: number; body: string }> {
	const res = await api.get(`${DAV}/${encodeURIComponent(name)}`)
	return { status: res.status(), body: res.status() === 200 ? await res.text() : '' }
}

/**
 * The Files file id of a path, or null when it does not exist.
 *
 * @param api an admin context.
 * @param path the path below the admin's Files root.
 * @return the file id, or null.
 */
export async function davFileId(api: APIRequestContext, path: string): Promise<number | null> {
	const res = await api.fetch(`${DAV}/${path.split('/').map(encodeURIComponent).join('/')}`, {
		method: 'PROPFIND',
		headers: { Depth: '0', 'Content-Type': 'application/xml' },
		data: '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">'
			+ '<d:prop><oc:fileid/></d:prop></d:propfind>',
	})
	if (res.status() === 404) {
		return null
	}
	expect(res.status(), await res.text()).toBe(207)
	const match = /<oc:fileid>(\d+)<\/oc:fileid>/.exec(await res.text())
	return match ? Number(match[1]) : null
}

/**
 * Delete a file from the admin's Files root, tolerating one that is gone.
 *
 * @param api an admin context.
 * @param name the file name.
 * @return nothing.
 */
export async function davDelete(api: APIRequestContext, name: string): Promise<void> {
	await api.delete(`${DAV}/${encodeURIComponent(name)}`)
}
