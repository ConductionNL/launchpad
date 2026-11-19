/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Table-mode helpers shared by text-display-widget-table.spec.ts and
 * text-display-widget-table-editor.spec.ts. Built on support/textWidget.ts.
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { Cell, TableData } from './textWidget.ts'

import { expect } from '@playwright/test'
import { gotoLaunchPad } from '../fixtures/widget-flow.ts'
import {
	API,
	editorGrid,
	openAddTextForm,
	openEditTextForm,
	seedText,
	storedContent,
	storedPlacements,
	switchToTable,
	textWidget,
} from './textWidget.ts'

/** The admin context and the dashboard a spec file seeded. */
export interface TableContext {
	api: APIRequestContext
	dashboardId: number
}

/**
 * A full cell (both spans set), so stored data compares exactly.
 *
 * @param text the cell text.
 * @param rowSpan row span.
 * @param colSpan column span.
 * @return the cell.
 */
export function cell(text: string, rowSpan = 1, colSpan = 1): Required<Cell> {
	return { text, rowSpan, colSpan }
}

/**
 * Rows of 1×1 cells from a text matrix.
 *
 * @param texts the cell texts, row by row.
 * @return the rows.
 */
export function grid(texts: string[][]): Array<Array<Required<Cell>>> {
	return texts.map((row) => row.map((t) => cell(t)))
}

/**
 * The `r{r}c{c}` labels buildTable types, as a text matrix.
 *
 * @param rows row count.
 * @param cols column count.
 * @return the texts.
 */
export function labels(rows: number, cols: number): string[][] {
	return Array.from({ length: rows }, (_, r) =>
		Array.from({ length: cols }, (_, c) => `r${r}c${c}`),
	)
}

/**
 * Seed a table-mode placement.
 *
 * @param ctx the spec's context.
 * @param tableData the table.
 * @param extra more content fields.
 * @return the placement id.
 */
export async function seedTable(
	ctx: TableContext,
	tableData: unknown,
	extra: Record<string, unknown> = {},
): Promise<number> {
	return seedText(
		ctx.api,
		ctx.dashboardId,
		{ text: '', tableMode: true, tableData, ...extra },
		{ w: 6, h: 4 },
	)
}

/**
 * Seed a table, open the dashboard and return the rendered widget.
 *
 * @param page the page.
 * @param ctx the spec's context.
 * @param tableData the table.
 * @return the widget root.
 */
export async function renderTable(
	page: Page,
	ctx: TableContext,
	tableData: unknown,
): Promise<Locator> {
	const id = await seedTable(ctx, tableData)
	await gotoLaunchPad(page)
	const widget = textWidget(page, id)
	await expect(widget.locator('table.cn-text-widget__table')).toBeAttached({ timeout: 15_000 })
	return widget
}

/**
 * Open the Add modal on a fresh table.
 *
 * @param page the page.
 * @return the dialog and the table editor.
 */
export async function freshTable(page: Page): Promise<{ dialog: Locator; editor: Locator }> {
	await gotoLaunchPad(page)
	const { dialog, form } = await openAddTextForm(page)
	const editor = await switchToTable(form)
	return { dialog, editor }
}

/**
 * Open the edit modal of a seeded table placement.
 *
 * @param page the page.
 * @param id the placement.
 * @return the dialog and the table editor.
 */
export async function editTable(
	page: Page,
	id: number,
): Promise<{ dialog: Locator; editor: Locator }> {
	await gotoLaunchPad(page)
	const { dialog, form } = await openEditTextForm(page, id)
	const editor = form.locator('.cn-text-table-editor')
	await expect(editor).toBeVisible({ timeout: 5_000 })
	return { dialog, editor }
}

/**
 * The editor's cell texts, row by row (visible and hidden cells alike).
 *
 * @param editor the table editor.
 * @return the texts.
 */
export async function editorTexts(editor: Locator): Promise<string[][]> {
	return (await editorGrid(editor)).map((row) => row.map((c) => c.text))
}

/**
 * The stored tableData of a placement.
 *
 * @param ctx the spec's context.
 * @param id the placement.
 * @return its tableData.
 */
export async function storedTable(ctx: TableContext, id: number): Promise<TableData> {
	return (await storedContent(ctx.api, ctx.dashboardId, id)).tableData as TableData
}

/**
 * Resolve a CSS variable to its computed colour on this page.
 *
 * @param page the page.
 * @param variable e.g. `--color-text-maxcontrast`.
 * @return the computed colour.
 */
export async function resolveColour(page: Page, variable: string): Promise<string> {
	return page.evaluate((v) => {
		const probe = document.createElement('span')
		probe.style.color = `var(${v})`
		document.body.appendChild(probe)
		const resolved = getComputedStyle(probe).color
		probe.remove()
		return resolved
	}, variable)
}

/**
 * POST a table placement straight at the save endpoint and confirm it was
 * refused with 400 and nothing was stored.
 *
 * @param ctx the spec's context.
 * @param tableData the invalid table.
 * @param message the translated refusal the scenario names, if it names one.
 * @return nothing.
 */
export async function expectSaveRefused(
	ctx: TableContext,
	tableData: unknown,
	message?: string,
): Promise<void> {
	const before = (await storedPlacements(ctx.api, ctx.dashboardId)).length
	const res = await ctx.api.post(`${API}/dashboard/${ctx.dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 200,
			gridWidth: 4,
			gridHeight: 2,
			content: { text: '', tableMode: true, tableData },
		},
	})
	const body = await res.text()
	expect(res.status(), `an invalid grid must be refused with 400, got: ${body}`).toBe(400)
	if (message) {
		expect(body).toContain(message)
	}
	expect(
		(await storedPlacements(ctx.api, ctx.dashboardId)).length,
		'nothing may be stored',
	).toBe(before)
}
