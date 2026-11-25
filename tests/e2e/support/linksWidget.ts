/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the `links` widget specs
 * (openspec/specs/links-widget/spec.md).
 *
 * WHERE THE WIDGET COMES FROM
 * ===========================
 * The renderer is `CnLinksWidget` and the form is `CnLinksWidgetForm`, both
 * from `@conduction/nextcloud-vue`. They self-register the `links` type in the
 * shared dashboard widget registry, LaunchPad lists it in
 * `lib/widget-types.json`, and `WidgetRenderer.vue` dispatches a placement
 * whose `widgetId` is `links` to the renderer with `placement.content` as the
 * `content` prop. So a placement seeded with
 * `POST /api/dashboard/{id}/widgets {widgetId: 'links', content}` renders
 * exactly what a placement saved through the Add Widget modal renders.
 *
 * Every spec seeds its OWN dashboard per test and deletes it afterwards, so a
 * test never sees another test's placements.
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './dashboardFixture.ts'

import { expect, request } from '@playwright/test'
import { BASE_URL } from './baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './dashboardFixture.ts'
import { MORE_MENU, openDashboardSwitcher } from './dashboardSwitcher.ts'

export const API = '/index.php/apps/launchpad/api'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? process.env.ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? process.env.ADMIN_PASSWORD ?? 'admin',
}

/** One link as the spec describes it. */
export interface LinkSpec {
	label: string
	url: string
	icon?: string
	description?: string
}

/** One section as the spec describes it. */
export interface SectionSpec {
	title: string
	links: LinkSpec[]
}

/** A placement as `GET /api/dashboard/{id}` returns it. */
export interface Placement {
	id: number
	widgetId: string
	content: Record<string, any>
}

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

/**
 * Create a dashboard, active for the admin, named after the test.
 *
 * @param api the admin context.
 * @param label a readable label.
 * @return the dashboard.
 */
export async function seedLinksDashboard(
	api: APIRequestContext,
	label: string,
): Promise<SeededDashboard> {
	const stamp = `${Date.now()}-${Math.floor(Math.random() * 10_000)}`
	return seedActiveDashboard(api, `E2E Links ${label} ${stamp}`)
}

/**
 * Delete a dashboard seeded by `seedLinksDashboard`; its placements go with it.
 *
 * @param api the admin context.
 * @param dashboard the dashboard, or null.
 * @return nothing.
 */
export async function removeLinksDashboard(
	api: APIRequestContext,
	dashboard: SeededDashboard | null,
): Promise<void> {
	await removeSeededDashboard(api, dashboard)
}

/**
 * POST a links placement. Returns the raw response so refusal tests can read
 * the status.
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @param content the placement content.
 * @param grid optional grid geometry.
 * @param grid.gridX column.
 * @param grid.gridY row.
 * @param grid.gridWidth width in grid columns.
 * @param grid.gridHeight height in grid rows.
 * @return the response.
 */
export async function postLinksPlacement(
	api: APIRequestContext,
	dashboardId: number,
	content: Record<string, unknown>,
	grid: {
		gridX?: number
		gridY?: number
		gridWidth?: number
		gridHeight?: number
	} = {},
) {
	return api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'links',
			gridX: grid.gridX ?? 0,
			gridY: grid.gridY ?? 0,
			gridWidth: grid.gridWidth ?? 12,
			gridHeight: grid.gridHeight ?? 6,
			content,
		},
	})
}

/**
 * Seed a links placement and return its id; fails the test when refused.
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @param content the placement content.
 * @param grid optional grid geometry (see `postLinksPlacement`).
 * @return the placement id.
 */
export async function seedLinksPlacement(
	api: APIRequestContext,
	dashboardId: number,
	content: Record<string, unknown>,
	grid: {
		gridX?: number
		gridY?: number
		gridWidth?: number
		gridHeight?: number
	} = {},
): Promise<number> {
	const res = await postLinksPlacement(api, dashboardId, content, grid)
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id ?? body.placement?.id)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/**
 * Every placement on a dashboard, read back as the admin.
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @return the placements.
 */
export async function placementsOf(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Placement[]> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return body.placements ?? body.dashboard?.placements ?? []
}

/**
 * The links placements on a dashboard, oldest first.
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @return the links placements.
 */
export async function linksPlacementsOf(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Placement[]> {
	return (await placementsOf(api, dashboardId))
		.filter((p) => p.widgetId === 'links')
		.sort((a, b) => Number(a.id) - Number(b.id))
}

/**
 * One placement's stored content, read back from the API.
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @param placementId the placement.
 * @return its content.
 */
export async function storedContent(
	api: APIRequestContext,
	dashboardId: number,
	placementId: number,
): Promise<Record<string, any>> {
	const placement = (await placementsOf(api, dashboardId)).find(
		(p) => Number(p.id) === placementId,
	)
	expect(
		placement,
		`placement ${placementId} is not on dashboard ${dashboardId}`,
	).toBeTruthy()
	return placement!.content ?? {}
}

/**
 * Wait until the dashboard carries `count` links placements and return the
 * newest one. Used after a UI save, which persists asynchronously.
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @param count how many links placements must exist.
 * @return the newest links placement.
 */
export async function awaitLinksPlacement(
	api: APIRequestContext,
	dashboardId: number,
	count = 1,
): Promise<Placement> {
	await expect
		.poll(async () => (await linksPlacementsOf(api, dashboardId)).length, {
			message: 'the saved links widget never reached the server',
			timeout: 15_000,
		})
		.toBe(count)
	const all = await linksPlacementsOf(api, dashboardId)
	return all[all.length - 1]
}

/**
 * Open LaunchPad on the active (just seeded) dashboard and wait for the shell.
 *
 * @param page the page.
 * @return nothing.
 */
export async function gotoDashboard(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad/')
	try {
		await page.waitForSelector(MORE_MENU, { timeout: 30_000 })
	} catch {
		// Transient dev-instance 503 (needsDbUpgrade blip) — retry once.
		await page.goto('/index.php/apps/launchpad/')
		await page.waitForSelector(MORE_MENU, { timeout: 30_000 })
	}
}

/**
 * The rendered links widget of one placement.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the `.cn-links-widget` root inside that grid item.
 */
export function linksWidget(page: Page, placementId: number): Locator {
	return page.locator(`[data-placement-id="${placementId}"] .cn-links-widget`)
}

/**
 * Close the dashboard switcher sidebar when it is open.
 *
 * @param page the page.
 * @return nothing.
 */
export async function closeSwitcher(page: Page): Promise<void> {
	const close = page
		.locator(
			'.dashboard-switcher-sidebar.open .dashboard-switcher-sidebar__close',
		)
		.first()
	if (await close.isVisible().catch(() => false)) {
		await close.click()
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0)
	}
}

/**
 * Open the Add Widget modal for one dashboard, through that dashboard's row
 * cog in the switcher ("Add widget" → "Custom widget"), the way a person does.
 *
 * @param page the page, on LaunchPad.
 * @param dashboardName the seeded dashboard's name.
 * @return the dialog.
 */
export async function openAddWidget(
	page: Page,
	dashboardName: string,
): Promise<Locator> {
	if ((await page.locator('.dashboard-switcher-sidebar.open').count()) === 0) {
		await openDashboardSwitcher(page)
	}
	const row = page
		.locator('[data-source="user"].dashboard-switcher-sidebar__item')
		.filter({ hasText: dashboardName })
	await expect(row).toBeVisible({ timeout: 10_000 })
	await row.locator('.dashboard-row-actions button').first().click()
	await page.getByRole('menuitem', { name: /^add widget$/i }).click()
	await page.locator('[data-testid="widget-picker-type-links"]').dblclick()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Open the Add Widget modal and pick the Links type.
 *
 * @param page the page, on LaunchPad.
 * @param dashboardName the seeded dashboard's name.
 * @return the dialog and the links sub-form inside it.
 */
export async function openLinksForm(
	page: Page,
	dashboardName: string,
): Promise<{ dialog: Locator; form: Locator }> {
	const dialog = await openAddWidget(page, dashboardName)
	const form = dialog.locator('.cn-links-form')
	await expect(form).toBeVisible({ timeout: 10_000 })
	return { dialog, form }
}

/**
 * The section blocks of the links form.
 *
 * @param form the `.cn-links-form` locator.
 * @return the sections.
 */
export function formSections(form: Locator): Locator {
	return form.locator('.cn-links-form__section')
}

/**
 * Fill one link row's label and URL (and optionally its description).
 *
 * @param row the `.cn-links-form__link-row` locator.
 * @param link the values.
 * @return nothing.
 */
export async function fillLinkRow(row: Locator, link: LinkSpec): Promise<void> {
	await row.getByPlaceholder('Label', { exact: true }).fill(link.label)
	await row.getByPlaceholder('URL', { exact: true }).fill(link.url)
	if (link.description !== undefined) {
		await row
			.getByPlaceholder('Description (optional)', { exact: true })
			.fill(link.description)
	}
}

/**
 * Click "Add section", give the new section a title and fill its link rows
 * (adding rows with "Add link" as needed).
 *
 * @param form the `.cn-links-form` locator.
 * @param section the section to type in.
 * @return nothing.
 */
export async function typeSection(
	form: Locator,
	section: SectionSpec,
): Promise<void> {
	const before = await formSections(form).count()
	await form.getByRole('button', { name: /add section/i }).click()
	const block = formSections(form).nth(before)
	await expect(block).toBeVisible()
	await block
		.getByPlaceholder('Section title', { exact: true })
		.fill(section.title)
	for (let i = 0; i < section.links.length; i++) {
		const rows = block.locator('.cn-links-form__link-row')
		if ((await rows.count()) <= i) {
			await block.getByRole('button', { name: /add link/i }).click()
		}
		await fillLinkRow(rows.nth(i), section.links[i])
	}
}

/**
 * The modal's primary button ("Add" in create mode, "Save" in edit mode).
 *
 * @param dialog the dialog.
 * @return the button.
 */
export function saveButton(dialog: Locator): Locator {
	return dialog.locator('[data-testid="add-widget-save"]')
}

/**
 * Press the primary button and wait for the modal to close.
 *
 * @param dialog the dialog.
 * @return nothing.
 */
export async function submit(dialog: Locator): Promise<void> {
	await expect(saveButton(dialog)).toBeEnabled({ timeout: 5_000 })
	await saveButton(dialog).click()
	await expect(dialog).toBeHidden({ timeout: 10_000 })
}

/**
 * Add a links widget through the modal with one section and one link, after
 * letting `configure` touch the form (options, extra sections).
 *
 * @param page the page, on LaunchPad.
 * @param api the admin context.
 * @param dashboard the seeded dashboard.
 * @param configure optional form edits before saving.
 * @return the stored content of the new placement.
 */
export async function addLinksThroughModal(
	page: Page,
	api: APIRequestContext,
	dashboard: SeededDashboard,
	configure?: (form: Locator) => Promise<void>,
): Promise<{ placement: Placement; content: Record<string, any> }> {
	const existing = (await linksPlacementsOf(api, dashboard.id)).length
	const { dialog, form } = await openLinksForm(page, dashboard.name)
	await typeSection(form, {
		title: 'Tools',
		links: [{ label: 'Docs', url: 'https://example.com/docs' }],
	})
	if (configure) {
		await configure(form)
	}
	await submit(dialog)
	await closeSwitcher(page)
	const placement = await awaitLinksPlacement(api, dashboard.id, existing + 1)
	return { placement, content: placement.content ?? {} }
}

/**
 * Open the edit modal of one placement through its widget cog. Enters edit
 * mode first when the dashboard is not already in it.
 *
 * @param page the page, on LaunchPad.
 * @param placementId the placement.
 * @return the dialog and the links sub-form inside it.
 */
export async function openEditForm(
	page: Page,
	placementId: number,
): Promise<{ dialog: Locator; form: Locator }> {
	if ((await page.locator('.launchpad-edit-mode').count()) === 0) {
		const more = page.locator(MORE_MENU)
		await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
		await page.locator('[data-testid="launchpad-more-menu-item-edit"]').click()
		await expect(page.locator('.launchpad-edit-mode')).toBeVisible({
			timeout: 10_000,
		})
	}
	const item = page.locator(`[data-placement-id="${placementId}"]`)
	await item.getByRole('button', { name: 'Widget menu' }).click()
	await page.getByRole('menuitem', { name: 'Edit widget' }).click()
	const dialog = page.getByRole('dialog', { name: /edit widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	const form = dialog.locator('.cn-links-form')
	await expect(form).toBeVisible()
	return { dialog, form }
}

/**
 * Content with one section of the given links, merged with options.
 *
 * @param links the links.
 * @param options extra content keys.
 * @return the content.
 */
export function oneSection(
	links: LinkSpec[],
	options: Record<string, unknown> = {},
): Record<string, unknown> {
	return { sections: [{ title: 'Section', links }], ...options }
}

/**
 * One control of the form's layout-options panel, by the text of its label.
 *
 * Found through the wrapping `<label>` rather than `getByLabel`: a label that
 * wraps a select or number input folds the control's current value into the
 * accessible name ("Columns 3"), so an exact name match would miss it.
 *
 * @param form the `.cn-links-form` locator.
 * @param name the label text, e.g. "Columns" or "Open in new tab".
 * @return the input or select.
 */
export function formOption(form: Locator, name: string): Locator {
	return form
		.locator('label.cn-links-form__option')
		.filter({
			has: form.page().locator('span').getByText(name, { exact: true }),
		})
		.locator('input, select')
}
