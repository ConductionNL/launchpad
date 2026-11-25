/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the `quicklinks` widget specs
 * (openspec/specs/quicklinks-widget).
 *
 * The renderer is nc-vue's CnQuicklinksWidget and the form its
 * CnQuicklinksWidgetForm; LaunchPad dispatches both through the communal
 * widget registry under the type key `quicklinks`. Selectors below are the
 * classes those components actually render.
 *
 * Every spec seeds ONE dashboard for the admin in `beforeAll`, seeds the
 * placements a test needs through `POST /api/dashboard/{id}/widgets`, and
 * clears every placement on that dashboard in `afterEach`, so tests never see
 * each other's widgets and nothing outlives the run.
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect, request } from '@playwright/test'
import { BASE_URL } from './baseUrl.ts'
import { enterEditMode, openDashboardSwitcher } from './dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

export const API = '/index.php/apps/launchpad/api'

/** The registry defaults for a new quicklinks placement (REQ-QLNK-002). */
export const QUICKLINKS_DEFAULTS = Object.freeze({
	links: [],
	iconSize: 'medium',
	iconShape: 'rounded',
	showLabels: true,
	labelPosition: 'below',
	columns: 'auto',
	tileBackgroundStyle: 'transparent',
	hoverEffect: 'lift',
})

export interface QuicklinkEntry {
	label: string
	url: string | null
	icon?: string
	color?: string
	openInNewTab?: boolean
}

export interface Placement {
	id: number
	widgetId: string
	gridX: number
	gridY: number
	gridWidth: number
	gridHeight: number
	content: Record<string, unknown>
}

/**
 * The admin over basic auth, with an explicitly empty jar so the project's
 * stored browser session is not sent along.
 *
 * @return the context; the caller disposes it.
 */
export async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
			Authorization: `Basic ${Buffer.from(`${ADMIN.user}:${ADMIN.pass}`).toString('base64')}`,
		},
	})
}

/**
 * Seed a quicklinks placement on a dashboard (seeding only, never the
 * behaviour under test).
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @param content the placement's `content` blob, exactly as persisted.
 * @param rect optional grid rectangle.
 * @return the new placement id.
 */
export async function seedQuicklinks(
	api: APIRequestContext,
	dashboardId: number,
	content: Record<string, unknown>,
	rect: Partial<{ gridX: number, gridY: number, gridWidth: number, gridHeight: number }> = {},
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'quicklinks',
			gridX: rect.gridX ?? 0,
			gridY: rect.gridY ?? 0,
			gridWidth: rect.gridWidth ?? 6,
			gridHeight: rect.gridHeight ?? 4,
			content,
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/**
 * Read the dashboard's placements back from the server.
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @return every placement on it.
 */
export async function placementsOf(api: APIRequestContext, dashboardId: number): Promise<Placement[]> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (body.placements ?? body.data?.placements ?? []) as Placement[]
}

/**
 * The quicklinks placements on a dashboard.
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @return the placements whose widgetId is `quicklinks`.
 */
export async function quicklinksOn(api: APIRequestContext, dashboardId: number): Promise<Placement[]> {
	return (await placementsOf(api, dashboardId)).filter((p) => p.widgetId === 'quicklinks')
}

/**
 * Remove every placement on a dashboard, so the next test starts empty.
 *
 * @param api the admin context.
 * @param dashboardId the dashboard; 0 is ignored.
 * @return nothing.
 */
export async function clearPlacements(api: APIRequestContext, dashboardId: number): Promise<void> {
	if (!dashboardId) {
		return
	}
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	if (res.status() !== 200) {
		return
	}
	const body = await res.json()
	for (const p of (body.placements ?? body.data?.placements ?? []) as Placement[]) {
		await api.delete(`${API}/widgets/${p.id}`)
	}
}

/**
 * The rendered quicklinks root inside one placement.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the `.cn-quicklinks-widget` locator.
 */
export function quicklinksWidget(page: Page, placementId: number): Locator {
	return page.locator(`[data-testid="widget-placement-${placementId}"] .cn-quicklinks-widget`)
}

/**
 * Open LaunchPad on the active dashboard and wait for one placement's widget.
 *
 * @param page the page.
 * @param placementId the placement to wait for.
 * @return the widget root.
 */
export async function openWidget(page: Page, placementId: number): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad/')
	const widget = quicklinksWidget(page, placementId)
	await expect(widget).toBeVisible({ timeout: 30_000 })
	await widget.scrollIntoViewIfNeeded()
	return widget
}

/**
 * The Add/Edit Widget dialog that holds the quicklinks sub-form.
 *
 * @param page the page.
 * @return the dialog locator.
 */
export function quicklinksDialog(page: Page): Locator {
	return page.getByRole('dialog').filter({ has: page.locator('.cn-quicklinks-widget-form') })
}

/**
 * Open the Add Widget modal from the named dashboard's own row menu, pick
 * "Quicklinks" from the type select, and return the dialog.
 *
 * @param page the page, on LaunchPad.
 * @param dashboardName the seeded dashboard's name.
 * @return the dialog with the quicklinks form mounted.
 */
export async function openAddQuicklinks(page: Page, dashboardName: string): Promise<Locator> {
	await openDashboardSwitcher(page)
	const row = page
		.locator('[data-source="user"].dashboard-switcher-sidebar__item')
		.filter({ hasText: dashboardName })
		.first()
	await expect(row).toBeVisible({ timeout: 10_000 })
	await row.locator('.dashboard-row-actions button').first().click()
	await page.getByRole('menuitem', { name: /^add widget$/i }).click()
	await page.locator('[data-testid="widget-picker-type-quicklinks"]').dblclick()

	const modal = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(modal).toBeVisible({ timeout: 10_000 })
	const option = modal.locator('[data-testid="widget-type-option-quicklinks"]')
	await expect(option, 'the type picker must offer Quicklinks').toHaveCount(1)
	await expect(option).toHaveText('Quicklinks')

	const dialog = quicklinksDialog(page)
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Put the dashboard in edit mode and open one placement's content editor
 * through its edit cog, the way a person does.
 *
 * @param page the page, on LaunchPad with the placement rendered.
 * @param placementId the placement.
 * @return the Edit Widget dialog.
 */
export async function openEditForm(page: Page, placementId: number): Promise<Locator> {
	if ((await page.locator('.launchpad-edit-mode').count()) === 0) {
		await enterEditMode(page)
	}
	await page
		.locator(`[data-testid="widget-placement-${placementId}"]`)
		.getByRole('button', { name: 'Widget menu' })
		.click()
	await page.getByRole('menuitem', { name: 'Edit widget' }).click()
	const dialog = quicklinksDialog(page)
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * One of the form's NcSelect controls, found by its visible label.
 *
 * @param dialog the dialog.
 * @param label the control's label, e.g. "Icon size".
 * @return the `.v-select` locator.
 */
export function formSelect(dialog: Locator, label: string): Locator {
	return dialog
		.locator('.cn-quicklinks-widget-form__settings .v-select')
		.filter({ has: dialog.page().locator('label', { hasText: new RegExp(`^\\s*${label}\\s*$`) }) })
}

/**
 * Record every `window.open` call into `window.__opened`, optionally still
 * opening the tab. Must be installed before navigation.
 *
 * @param page the page.
 * @param passthrough true to also call the real `window.open`.
 * @return nothing.
 */
export async function recordWindowOpen(page: Page, passthrough: boolean): Promise<void> {
	await page.addInitScript((through: boolean) => {
		const w = window as unknown as Record<string, unknown>
		w.__opened = []
		const original = window.open.bind(window)
		window.open = ((...args: Parameters<typeof window.open>) => {
			;(w.__opened as unknown[]).push(args.map((a) => (a === undefined ? undefined : String(a))))
			return through ? original(...args) : null
		}) as typeof window.open
	}, passthrough)
}

/**
 * The `window.open` calls recorded so far.
 *
 * @param page the page.
 * @return the argument lists.
 */
export async function openedCalls(page: Page): Promise<unknown[][]> {
	return page.evaluate(() => ((window as unknown as Record<string, unknown>).__opened ?? []) as unknown[][])
}

/**
 * Resolve a CSS colour expression (e.g. `var(--color-primary)`) to the
 * `rgb(...)` string the browser computes for it in the element's context.
 *
 * @param scope an element whose cascade the expression resolves in.
 * @param value the CSS colour expression.
 * @return the computed colour.
 */
export async function resolveColor(scope: Locator, value: string): Promise<string> {
	return scope.evaluate((el, v) => {
		const probe = document.createElement('span')
		probe.style.color = v
		el.appendChild(probe)
		const color = getComputedStyle(probe).color
		probe.remove()
		return color
	}, value)
}

/**
 * The element's content-box height (client height minus vertical padding).
 *
 * @param el the element.
 * @return the height in px.
 */
export async function contentHeight(el: Locator): Promise<number> {
	return el.evaluate((node) => {
		const cs = getComputedStyle(node)
		return node.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom)
	})
}

/**
 * Build `n` links with distinct labels and external URLs.
 *
 * @param n how many.
 * @param prefix label prefix.
 * @return the links.
 */
export function makeLinks(n: number, prefix = 'Link'): QuicklinkEntry[] {
	return Array.from({ length: n }, (_, i) => ({
		label: `${prefix} ${i + 1}`,
		url: `https://example.com/${prefix.toLowerCase()}-${i + 1}`,
		icon: 'Star',
	}))
}
