/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the `text` widget e2e specs
 * (tests/e2e/text-display-widget*.spec.ts).
 *
 * WHAT IS REAL HERE
 * =================
 * The API is used for two things only: seeding a placement with exactly the
 * `content` a scenario names (POST /api/dashboard/{id}/widgets, the payload
 * `seedDashboardWithWidget` in principals.ts uses) and reading the stored
 * `content` back (GET /api/dashboard/{id} -> placements[]). Everything a person
 * does goes through the product's own controls: the More menu's
 * "Add widget" → "Custom widget", the Add/Edit Widget modal, the right-click context
 * menu's Edit, and the text form's own selects, textarea and table editor.
 *
 * Selectors come from the real components in @conduction/nextcloud-vue:
 * CnTextWidget (renderer), CnTextWidgetForm (sub-form), CnTextTableEditor
 * (table editor) and CnAddWidgetModal (the dialog), plus LaunchPad's own
 * Views.vue (`.launchpad-grid-item[data-placement-id]`) and
 * WidgetContextMenu.vue (`widget-context-menu`, `ctx-edit`).
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect, request } from '@playwright/test'
import { BASE_URL } from './baseUrl.ts'
import { enterEditMode, MORE_MENU } from './dashboardSwitcher.ts'

export const API = '/index.php/apps/launchpad/api'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

export interface Cell {
	text: string
	rowSpan?: number
	colSpan?: number
}

export interface TableData {
	headerRow: boolean
	columnAlignments: string[]
	rows: Cell[][]
}

/** A stored placement as GET /api/dashboard/{id} returns it. */
export interface StoredPlacement {
	id: number
	widgetId: string
	content: Record<string, any> | null
}

/** One cell of the table editor's grid, read from the DOM. */
export interface EditorCell {
	text: string
	visible: boolean
	colSpan: number
	rowSpan: number
	align: string
}

/** One cell of the rendered (dashboard) table, read from the DOM. */
export interface RenderedCell {
	tag: string
	text: string
	visible: boolean
	colSpan: number
	rowSpan: number
	align: string
	placeholder: boolean
}

/**
 * An admin API context (basic auth with an explicit header).
 *
 * @return the context; the caller disposes it.
 */
export async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

let nextRow = 0

/**
 * Seed a `text` placement with the given content (seeding only).
 *
 * Placements are stacked down the grid so they never collide.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard.
 * @param content the placement's `content` blob, exactly as the scenario names it.
 * @param size the grid size; 6x3 by default.
 * @param size.w grid width.
 * @param size.h grid height.
 * @return the new placement id.
 */
export async function seedText(
	api: APIRequestContext,
	dashboardId: number,
	content: Record<string, unknown>,
	size: { w: number; h: number } = { w: 6, h: 3 },
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: nextRow,
			gridWidth: size.w,
			gridHeight: size.h,
			content,
		},
	})
	nextRow += size.h
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id ?? body.placement?.id)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/**
 * The placements on a dashboard, read back as the admin who owns it.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard.
 * @return the stored placements.
 */
export async function storedPlacements(
	api: APIRequestContext,
	dashboardId: number,
): Promise<StoredPlacement[]> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return body.placements ?? body.dashboard?.placements ?? []
}

/**
 * The stored `content` of one placement.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard.
 * @param placementId the placement.
 * @return the content blob.
 */
export async function storedContent(
	api: APIRequestContext,
	dashboardId: number,
	placementId: number,
): Promise<Record<string, any>> {
	const found = (await storedPlacements(api, dashboardId)).find(
		(p) => Number(p.id) === placementId,
	)
	expect(found, `placement ${placementId} is not stored on ${dashboardId}`).toBeTruthy()
	return found!.content ?? {}
}

/**
 * The rendered text widget of one placement on the dashboard.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the `.cn-text-widget` root.
 */
export function textWidget(page: Page, placementId: number): Locator {
	return page.locator(
		`.launchpad-grid-item[data-placement-id="${placementId}"] .cn-text-widget`,
	)
}

/**
 * Put the active dashboard in edit mode unless it already is.
 *
 * @param page the page.
 * @return nothing.
 */
export async function ensureEditMode(page: Page): Promise<void> {
	if ((await page.locator('.launchpad-edit-mode').count()) === 0) {
		await enterEditMode(page)
	}
}

/**
 * Open More → "Add widget" → "Custom widget" and pick the Text type.
 *
 * @param page the page, on the seeded active dashboard.
 * @return the dialog and the text sub-form.
 */
export async function openAddTextForm(
	page: Page,
): Promise<{ dialog: Locator; form: Locator }> {
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
	await page.locator('[data-testid="cn-grouped-menu-item-add-widget"]').click()
	await page.locator('[data-testid="widget-picker-custom-widget"]').click()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await dialog.getByTestId('widget-type-select').selectOption('text')
	const form = dialog.locator('.cn-text-widget-form')
	await expect(form).toBeVisible({ timeout: 5_000 })
	return { dialog, form }
}

/**
 * Open the content editor of a placed text widget the way a person does:
 * edit mode, right-click the widget, Edit.
 *
 * `dispatchEvent('contextmenu')` for the reason label-widget-content-edit
 * gives: the handler is a plain `@contextmenu` listener on the grid item, and a
 * real right-click is actionability-checked against a cell whose position
 * depends on what earlier specs left on the grid.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the Edit Widget dialog and the text sub-form.
 */
export async function openEditTextForm(
	page: Page,
	placementId: number,
): Promise<{ dialog: Locator; form: Locator }> {
	await ensureEditMode(page)
	const item = page.locator(`.launchpad-grid-item[data-placement-id="${placementId}"]`)
	await expect(item).toBeAttached({ timeout: 15_000 })
	await item.dispatchEvent('contextmenu')
	await expect(page.getByTestId('widget-context-menu')).toBeVisible({ timeout: 5_000 })
	await page.getByTestId('ctx-edit').click()
	const dialog = page.getByRole('dialog', { name: /edit widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	const form = dialog.locator('.cn-text-widget-form')
	await expect(form).toBeVisible({ timeout: 5_000 })
	return { dialog, form }
}

/**
 * An `NcSelect` in the form, found by its input label.
 *
 * @param form the text sub-form.
 * @param label the select's label ("Content type", "Mode", "Alignment").
 * @return the `.v-select` root.
 */
export function ncSelect(form: Locator, label: string): Locator {
	return form
		.locator('.v-select')
		.filter({ has: form.page().getByRole('combobox', { name: label, exact: true }) })
		.first()
}

/**
 * The option an `NcSelect` currently shows as selected.
 *
 * @param select the `.v-select` root.
 * @return its selected label, trimmed.
 */
export async function selectedOption(select: Locator): Promise<string> {
	return (await select.locator('.vs__selected').first().innerText()).trim()
}

/**
 * Open an `NcSelect` and click the option whose whole text is `label`.
 *
 * @param select the `.v-select` root.
 * @param label the option's visible label.
 * @return nothing.
 */
export async function chooseOption(select: Locator, label: string): Promise<void> {
	await select.getByRole('combobox').first().click()
	const escaped = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
	const option = select
		.page()
		.locator('.vs__dropdown-menu .vs__dropdown-option')
		.filter({ hasText: new RegExp(`^\\s*${escaped}\\s*$`) })
		.first()
	await expect(option).toBeVisible({ timeout: 5_000 })
	await option.click()
	await expect.poll(() => selectedOption(select)).toBe(label)
}

/**
 * The text sub-form's textarea (the plain one in HTML mode, the markdown
 * editor's in markdown mode).
 *
 * @param form the text sub-form.
 * @return the textarea.
 */
export function textArea(form: Locator): Locator {
	return form.locator('textarea').first()
}

/**
 * Press the dialog's Add button and wait for the new placement to be stored.
 *
 * @param page the page.
 * @param dialog the Add Widget dialog.
 * @param api an admin context.
 * @param dashboardId the dashboard the placement lands on.
 * @return the new placement id and the create request's HTTP status.
 */
export async function submitAdd(
	page: Page,
	dialog: Locator,
	api: APIRequestContext,
	dashboardId: number,
): Promise<{ id: number; status: number }> {
	const before = new Set((await storedPlacements(api, dashboardId)).map((p) => Number(p.id)))
	const save = dialog.getByTestId('add-widget-save')
	await expect(save).toBeEnabled({ timeout: 5_000 })
	const [response] = await Promise.all([
		page.waitForResponse(
			(r) =>
				r.request().method() === 'POST'
				&& /\/api\/(v1\/)?dashboard\/\d+\/widgets$/.test(new URL(r.url()).pathname),
			{ timeout: 15_000 },
		),
		save.click(),
	])
	await expect(dialog).toBeHidden({ timeout: 10_000 })
	let id = 0
	await expect
		.poll(async () => {
			const added = (await storedPlacements(api, dashboardId)).find(
				(p) => !before.has(Number(p.id)) && p.widgetId === 'text',
			)
			id = added ? Number(added.id) : 0
			return id
		}, { timeout: 10_000 })
		.toBeGreaterThan(0)
	return { id, status: response.status() }
}

/**
 * Press the Edit dialog's Save button and wait for the placement update.
 *
 * @param page the page.
 * @param dialog the Edit Widget dialog.
 * @param placementId the placement being edited.
 * @return the PUT request's JSON body and its HTTP status.
 */
export async function submitEdit(
	page: Page,
	dialog: Locator,
	placementId: number,
): Promise<{ body: Record<string, any>; status: number }> {
	const save = dialog.getByTestId('add-widget-save')
	await expect(save).toBeEnabled({ timeout: 5_000 })
	const path = new RegExp(`/api/(v1/)?widgets/${placementId}$`)
	const [response] = await Promise.all([
		page.waitForResponse(
			(r) =>
				r.request().method() === 'PUT'
				&& path.test(new URL(r.url()).pathname)
				&& (r.request().postData() ?? '').includes('"content"'),
			{ timeout: 15_000 },
		),
		save.click(),
	])
	await expect(dialog).toBeHidden({ timeout: 10_000 })
	return { body: response.request().postDataJSON() ?? {}, status: response.status() }
}

/**
 * Switch the text sub-form's "Content type" to Table.
 *
 * @param form the text sub-form.
 * @return the table editor.
 */
export async function switchToTable(form: Locator): Promise<Locator> {
	await chooseOption(ncSelect(form, 'Content type'), 'Table')
	const editor = form.locator('.cn-text-table-editor')
	await expect(editor).toBeVisible({ timeout: 5_000 })
	return editor
}

/**
 * A table-editor cell input, by its 1-indexed aria-label coordinate.
 *
 * @param editor the table editor.
 * @param row 1-indexed row.
 * @param col 1-indexed column.
 * @return the input.
 */
export function cellInput(editor: Locator, row: number, col: number): Locator {
	return editor.getByLabel(`Row ${row}, column ${col}`, { exact: true })
}

/**
 * A table-editor toolbar button by its exact label.
 *
 * @param editor the table editor.
 * @param name e.g. "Add row below".
 * @return the button.
 */
export function editorButton(editor: Locator, name: string): Locator {
	return editor.getByRole('button', { name, exact: true })
}

/**
 * Grow a fresh 1×1 editor table to `rows × cols` through the toolbar, then
 * type `r{row}c{col}` (0-indexed) into every cell unless told not to.
 *
 * @param editor the table editor, showing the 1×1 default.
 * @param rows target row count.
 * @param cols target column count.
 * @param fill whether to label every cell.
 * @return nothing.
 */
export async function buildTable(
	editor: Locator,
	rows: number,
	cols: number,
	fill = true,
): Promise<void> {
	await cellInput(editor, 1, 1).click()
	for (let c = 1; c < cols; c++) {
		await editorButton(editor, 'Add column right').click()
	}
	for (let r = 1; r < rows; r++) {
		await editorButton(editor, 'Add row below').click()
	}
	if (fill) {
		for (let r = 0; r < rows; r++) {
			for (let c = 0; c < cols; c++) {
				await cellInput(editor, r + 1, c + 1).fill(`r${r}c${c}`)
			}
		}
	}
	await expect.poll(async () => (await editorGrid(editor)).length).toBe(rows)
}

/**
 * The table editor's grid as data, straight from the DOM.
 *
 * @param editor the table editor.
 * @return rows of cells.
 */
export async function editorGrid(editor: Locator): Promise<EditorCell[][]> {
	return editor.locator('.cn-text-table-editor__grid').evaluate((table) =>
		Array.from(table.querySelectorAll('tr')).map((tr) =>
			Array.from(tr.querySelectorAll('td')).map((td) => ({
				text: td.querySelector('input')?.value ?? '',
				visible: getComputedStyle(td).display !== 'none',
				colSpan: td.colSpan,
				rowSpan: td.rowSpan,
				align: td.style.textAlign,
			})),
		),
	)
}

/**
 * The rendered table of a text widget as data, straight from the DOM.
 *
 * @param widget the `.cn-text-widget` root.
 * @return rows of cells.
 */
export async function renderedTable(widget: Locator): Promise<RenderedCell[][]> {
	return widget.locator('table.cn-text-widget__table').evaluate((table) =>
		Array.from(table.querySelectorAll('tr')).map((tr) =>
			Array.from(tr.querySelectorAll('th, td')).map((cell) => {
				const el = cell as HTMLTableCellElement
				return {
					tag: el.tagName.toLowerCase(),
					text: (el.textContent ?? '').trim(),
					visible: getComputedStyle(el).display !== 'none',
					colSpan: el.colSpan,
					rowSpan: el.rowSpan,
					align: el.style.textAlign,
					placeholder: el.querySelector('.cn-text-widget__cell-placeholder') !== null,
				}
			}),
		),
	)
}

/**
 * Collect every `window.confirm` the page raises, answering each one.
 *
 * @param page the page.
 * @param accept whether to accept (true) or cancel (false).
 * @return the messages, filled as dialogs arrive.
 */
export function answerConfirms(page: Page, accept: boolean): string[] {
	const messages: string[] = []
	page.on('dialog', async (dialog) => {
		messages.push(dialog.message())
		if (accept) {
			await dialog.accept()
		} else {
			await dialog.dismiss()
		}
	})
	return messages
}
