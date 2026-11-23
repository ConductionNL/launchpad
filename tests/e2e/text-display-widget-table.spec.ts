/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `text` widget's table mode: the data
 * model, the Text/Table toggle, grid integrity on save, rendering, cell
 * sanitisation and the empty-cell placeholder (REQ-TBLE-001, -002, -008..011
 * of openspec/specs/text-display-widget), written for e2e-coverage-programme.
 * The editor operations (REQ-TBLE-003..007) are in
 * text-display-widget-table-editor.spec.ts.
 *
 * Rendering scenarios seed `tableData` through the API and assert the
 * `<table>` the dashboard draws. Editor scenarios drive CnTextTableEditor in
 * the real Add/Edit Widget modal ("Content type" select, cell inputs labelled
 * "Row r, column c") and read the stored `tableData` back through the API.
 * The grid-integrity refusals (REQ-TBLE-008) are probed at the save endpoint
 * itself, which is where the scenarios put them (HTTP 400).
 *
 * Gate-19 @e2e traceability:
 *   @e2e text-display-widget::minimal-1x1-table
 *   @e2e text-display-widget::multi-row-multi-column-table
 *   @e2e text-display-widget::merged-cells-with-span-metadata
 *   @e2e text-display-widget::column-alignment-array-length-matches-columns
 *   @e2e text-display-widget::switch-from-text-to-table-mode
 *   @e2e text-display-widget::switch-from-table-to-text-mode
 *   @e2e text-display-widget::render-respects-tablemode-flag
 *   @e2e text-display-widget::valid-rectangular-grid-without-merges
 *   @e2e text-display-widget::ragged-rows-rejected
 *   @e2e text-display-widget::cell-span-exceeds-grid-bounds
 *   @e2e text-display-widget::column-alignment-length-mismatch
 *   @e2e text-display-widget::valid-table-with-merged-cells
 *   @e2e text-display-widget::render-basic-2x2-table
 *   @e2e text-display-widget::render-with-header-row
 *   @e2e text-display-widget::render-with-colspan
 *   @e2e text-display-widget::render-with-rowspan
 *   @e2e text-display-widget::alignment-applied-per-column
 *   @e2e text-display-widget::safe-html-tags-preserved
 *   @e2e text-display-widget::script-tag-removed
 *   @e2e text-display-widget::event-handler-stripped
 *   @e2e text-display-widget::javascript-url-stripped
 *   @e2e text-display-widget::empty-1x1-table-shows-placeholder
 *   @e2e text-display-widget::placeholder-disappears-on-user-input
 *   @e2e text-display-widget::table-with-any-non-empty-cell-hides-placeholder
 *
 * @spec openspec/specs/text-display-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { TableContext } from './support/textTable.ts'

import { expect, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import {
	cell,
	editorTexts,
	editTable,
	expectSaveRefused,
	freshTable,
	grid,
	labels,
	renderTable,
	resolveColour,
	seedTable,
	storedTable,
} from './support/textTable.ts'
import {
	adminApi,
	buildTable,
	cellInput,
	chooseOption,
	editorButton,
	editorGrid,
	ncSelect,
	openAddTextForm,
	openEditTextForm,
	renderedTable,
	seedText,
	storedContent,
	submitAdd,
	submitEdit,
	switchToTable,
	textArea,
	textWidget,
} from './support/textWidget.ts'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

let restoreWidgetRestriction: (() => Promise<void>) | null = null

test.beforeAll(async () => {
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E Text Table ${Date.now()}`)
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api?.dispose()
})

test.describe.configure({ timeout: 90_000 })

/**
 * This file's admin context and seeded dashboard.
 *
 * @return the context the table helpers take.
 */
function ctx(): TableContext {
	return { api, dashboardId: dashboard!.id }
}

test.describe('text widget table — data model (REQ-TBLE-001)', () => {
	// @e2e text-display-widget::minimal-1x1-table
	test('switching a new widget to Table seeds exactly the 1x1 empty table', async ({ page }) => {
		const { dialog, editor } = await freshTable(page)
		expect(await editorGrid(editor)).toEqual([
			[{ text: '', visible: true, colSpan: 1, rowSpan: 1, align: 'left' }],
		])
		await expect(cellInput(editor, 1, 1)).toHaveValue('')
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		const stored = await storedContent(api, dashboard!.id, id)
		expect(stored.tableMode).toBe(true)
		expect(stored.tableData).toEqual({
			headerRow: false,
			columnAlignments: ['left'],
			rows: [[{ text: '', rowSpan: 1, colSpan: 1 }]],
		})
	})

	// @e2e text-display-widget::multi-row-multi-column-table
	test('a 2x2 tableData renders A B / C D with column 1 centred', async ({ page }) => {
		const widget = await renderTable(page, ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'center'],
			rows: [[{ text: 'A' }, { text: 'B' }], [{ text: 'C' }, { text: 'D' }]],
		})
		const table = await renderedTable(widget)
		expect(table.map((row) => row.map((c) => c.text))).toEqual([['A', 'B'], ['C', 'D']])
		expect(table.flat().every((c) => c.visible)).toBe(true)
		expect(table.map((row) => row[1].align)).toEqual(['center', 'center'])
		expect(table.map((row) => row[0].align)).toEqual(['left', 'left'])
	})

	// @e2e text-display-widget::merged-cells-with-span-metadata
	test('the editor loads a colSpan 2 cell and lays the next rows out around it', async ({ page }) => {
		const id = await seedTable(ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left', 'left'],
			rows: [
				[cell('Header', 1, 2), cell(''), cell('X')],
				[cell('A'), cell('B'), cell('C')],
			],
		})
		const { editor } = await editTable(page, id)
		const shown = await editorGrid(editor)
		expect(shown[0][0]).toMatchObject({ text: 'Header', colSpan: 2, visible: true })
		expect(shown[0][1].visible, 'the covered cell must not take a column of its own').toBe(false)
		expect(shown[0][2]).toMatchObject({ text: 'X', visible: true })
		expect(shown[1].map((c) => c.visible)).toEqual([true, true, true])
		// The wider first cell pushes X into the third column, above C.
		const x = await cellInput(editor, 1, 3).boundingBox()
		const c = await cellInput(editor, 2, 3).boundingBox()
		expect(x && c, 'both inputs must be laid out').toBeTruthy()
		expect(Math.abs(x!.x - c!.x)).toBeLessThanOrEqual(2)
	})

	// @e2e text-display-widget::column-alignment-array-length-matches-columns
	test('3 alignments for 3 columns render; 2 alignments for 3 columns are refused on save', async ({
		page,
	}) => {
		const widget = await renderTable(page, ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'center', 'right'],
			rows: grid([['a', 'b', 'c']]),
		})
		const table = await renderedTable(widget)
		expect(table[0].map((c) => [c.text, c.align])).toEqual([
			['a', 'left'],
			['b', 'center'],
			['c', 'right'],
		])
		await expectSaveRefused(ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left'],
			rows: grid([['a', 'b', 'c']]),
		})
	})
})

test.describe('text widget table — mode toggle (REQ-TBLE-002)', () => {
	// @e2e text-display-widget::switch-from-text-to-table-mode
	test('Text → Table seeds a 1x1 table, swaps the controls and keeps the text', async ({ page }) => {
		await gotoLaunchPad(page)
		const { dialog, form } = await openAddTextForm(page)
		await textArea(form).fill('Some text')
		const editor = await switchToTable(form)
		await expect(form.locator('textarea')).toHaveCount(0)
		await expect(editorButton(editor, 'Add row below')).toBeVisible()
		expect(await editorTexts(editor)).toEqual([['']])

		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		const stored = await storedContent(api, dashboard!.id, id)
		expect(stored.tableMode).toBe(true)
		expect(stored.text).toBe('Some text')
		expect(stored.tableData).toEqual({
			headerRow: false,
			columnAlignments: ['left'],
			rows: [[{ text: '', rowSpan: 1, colSpan: 1 }]],
		})
	})

	// @e2e text-display-widget::switch-from-table-to-text-mode
	test('Table → Text shows the text controls and keeps tableData', async ({ page }) => {
		const tableData = {
			headerRow: true,
			columnAlignments: ['left', 'right'],
			rows: [[cell('Keep'), cell('Me')]],
		}
		const id = await seedTable(ctx(), tableData, { text: 'Kept text', contentMode: 'html' })
		await gotoLaunchPad(page)
		const { dialog, form } = await openEditTextForm(page, id)
		await expect(form.locator('.cn-text-table-editor')).toBeVisible()

		await chooseOption(ncSelect(form, 'Content type'), 'Text')
		await expect(form.locator('.cn-text-table-editor')).toHaveCount(0)
		await expect(textArea(form)).toHaveValue('Kept text')
		await expect(form.getByLabel('Font size', { exact: true })).toBeVisible()
		await expect(
			form.locator('.cn-text-widget-form__color-label').filter({ hasText: 'Text color' }),
		).toBeVisible()
		await expect(ncSelect(form, 'Alignment')).toBeVisible()

		await submitEdit(page, dialog, id)
		const stored = await storedContent(api, dashboard!.id, id)
		expect(stored.tableMode).toBe(false)
		expect(stored.tableData).toEqual(tableData)
	})

	// @e2e text-display-widget::render-respects-tablemode-flag
	test('with both text and tableData, the flag alone picks what renders', async ({ page }) => {
		const tableData = { headerRow: false, columnAlignments: ['left'], rows: [[cell('Table cell')]] }
		const asText = await seedText(api, dashboard!.id, {
			text: 'Body text',
			contentMode: 'html',
			tableMode: false,
			tableData,
		})
		const asTable = await seedText(api, dashboard!.id, {
			text: 'Body text',
			contentMode: 'html',
			tableMode: true,
			tableData,
		})
		await gotoLaunchPad(page)

		const textOne = textWidget(page, asText)
		await expect(textOne.locator('.cn-text-widget__content')).toHaveText('Body text', { timeout: 15_000 })
		await expect(textOne.locator('table')).toHaveCount(0)
		await expect(textOne).not.toContainText('Table cell')

		const tableOne = textWidget(page, asTable)
		await expect(tableOne.locator('table.cn-text-widget__table td')).toHaveText(['Table cell'])
		await expect(tableOne.locator('.cn-text-widget__content')).toHaveCount(0)
		await expect(tableOne).not.toContainText('Body text')
	})
})

test.describe('text widget table — grid integrity on save (REQ-TBLE-008)', () => {
	// @e2e text-display-widget::valid-rectangular-grid-without-merges
	test('a 3x3 grid built in the editor saves with 201/200 and is stored unchanged', async ({
		page,
	}) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 3, 3)
		const { id, status } = await submitAdd(page, dialog, api, dashboard!.id)
		expect([200, 201]).toContain(status)
		expect(await storedTable(ctx(), id)).toEqual({
			headerRow: false,
			columnAlignments: ['left', 'left', 'left'],
			rows: grid(labels(3, 3)),
		})
	})

	// @e2e text-display-widget::ragged-rows-rejected
	test('a row of 3 cells over a row of 2 is refused with 400 "Grid is not rectangular"', async () => {
		await expectSaveRefused(
			ctx(),
			{
				headerRow: false,
				columnAlignments: ['left', 'left', 'left'],
				rows: [[cell('a'), cell('b'), cell('c')], [cell('d'), cell('e')]],
			},
			'Grid is not rectangular',
		)
	})

	// @e2e text-display-widget::cell-span-exceeds-grid-bounds
	test('a rowSpan 2 on the last row is refused with 400 "Cell span exceeds grid bounds"', async () => {
		await expectSaveRefused(
			ctx(),
			{
				headerRow: false,
				columnAlignments: ['left'],
				rows: [[cell('a')], [cell('b', 2, 1)]],
			},
			'Cell span exceeds grid bounds',
		)
	})

	// @e2e text-display-widget::column-alignment-length-mismatch
	test('3 columns with 2 alignments are refused and nothing is stored', async () => {
		await expectSaveRefused(ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left'],
			rows: grid([['a', 'b', 'c'], ['d', 'e', 'f'], ['g', 'h', 'i']]),
		})
	})

	// @e2e text-display-widget::valid-table-with-merged-cells
	test('a 3x3 grid whose first cell spans 2 columns validates and saves', async ({ page }) => {
		const rows = [
			[cell('Wide', 1, 2), cell('p'), cell('z')],
			[cell('a'), cell('b'), cell('c')],
			[cell('d'), cell('e'), cell('f')],
		]
		const id = await seedTable(ctx(), { headerRow: false, columnAlignments: ['left', 'left', 'left'], rows })
		const { dialog, editor } = await editTable(page, id)
		await cellInput(editor, 3, 3).fill('f2')
		// The modal's validate() passes: Save enables and carries no error title.
		const save = dialog.getByTestId('add-widget-save')
		await expect(save).toBeEnabled({ timeout: 5_000 })
		expect(await save.getAttribute('title')).toBeFalsy()
		const { status } = await submitEdit(page, dialog, id)
		expect([200, 201]).toContain(status)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows[0][0]).toEqual(cell('Wide', 1, 2))
		expect(stored.rows[2][2]).toEqual(cell('f2'))
	})
})

test.describe('text widget table — rendering (REQ-TBLE-009)', () => {
	// @e2e text-display-widget::render-basic-2x2-table
	test('a 2x2 table renders two rows of left-aligned td', async ({ page }) => {
		const widget = await renderTable(page, ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left'],
			rows: [[{ text: 'A' }, { text: 'B' }], [{ text: 'C' }, { text: 'D' }]],
		})
		const table = await renderedTable(widget)
		expect(table.map((row) => row.map((c) => [c.tag, c.text, c.align]))).toEqual([
			[['td', 'A', 'left'], ['td', 'B', 'left']],
			[['td', 'C', 'left'], ['td', 'D', 'left']],
		])
		await expect(widget.locator('th')).toHaveCount(0)
	})

	// @e2e text-display-widget::render-with-header-row
	test('headerRow renders row 0 as th with per-column alignment', async ({ page }) => {
		const widget = await renderTable(page, ctx(), {
			headerRow: true,
			columnAlignments: ['left', 'center'],
			rows: [[{ text: 'Name' }, { text: 'Score' }], [{ text: 'Alice' }, { text: '95' }]],
		})
		const table = await renderedTable(widget)
		expect(table.map((row) => row.map((c) => [c.tag, c.text, c.align]))).toEqual([
			[['th', 'Name', 'left'], ['th', 'Score', 'center']],
			[['td', 'Alice', 'left'], ['td', '95', 'center']],
		])
	})

	// @e2e text-display-widget::render-with-colspan
	test('colSpan 2 renders colspan="2", skips the covered cell and stays rectangular', async ({
		page,
	}) => {
		const widget = await renderTable(page, ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left'],
			rows: [[{ text: 'Header', colSpan: 2 }, { text: '', colSpan: 1 }], [{ text: 'A' }, { text: 'B' }]],
		})
		const table = await renderedTable(widget)
		expect(table[0][0]).toMatchObject({ tag: 'td', text: 'Header', colSpan: 2, visible: true })
		expect(table[0][1].visible, 'the covered cell must not render').toBe(false)
		const tableBox = widget.locator('table.cn-text-widget__table')
		await tableBox.scrollIntoViewIfNeeded()
		const [wide, a, b] = await Promise.all([
			tableBox.locator('tr').nth(0).locator('td').nth(0).boundingBox(),
			tableBox.locator('tr').nth(1).locator('td').nth(0).boundingBox(),
			tableBox.locator('tr').nth(1).locator('td').nth(1).boundingBox(),
		])
		expect(Math.abs(wide!.width - (b!.x + b!.width - a!.x))).toBeLessThanOrEqual(2)
	})

	// @e2e text-display-widget::render-with-rowspan
	test('rowSpan 2 renders rowspan="2" and skips the covered cell', async ({ page }) => {
		const widget = await renderTable(page, ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left'],
			rows: [[{ text: 'Tall', rowSpan: 2 }, { text: 'B' }], [{ text: '' }, { text: 'D' }]],
		})
		const table = await renderedTable(widget)
		expect(table[0][0]).toMatchObject({ text: 'Tall', rowSpan: 2, visible: true })
		expect(table[1][0].visible, 'the covered cell must not render').toBe(false)
		expect(table[1][1]).toMatchObject({ text: 'D', visible: true })
	})

	// @e2e text-display-widget::alignment-applied-per-column
	test('every cell of columns 0, 1, 2 is left, center, right', async ({ page }) => {
		const widget = await renderTable(page, ctx(), {
			headerRow: true,
			columnAlignments: ['left', 'center', 'right'],
			rows: grid([['h0', 'h1', 'h2'], ['a', 'b', 'c'], ['d', 'e', 'f']]),
		})
		const table = await renderedTable(widget)
		expect(table).toHaveLength(3)
		for (const row of table) {
			expect(row.map((c) => c.align)).toEqual(['left', 'center', 'right'])
		}
	})
})

test.describe('text widget table — cell sanitisation (REQ-TBLE-010)', () => {
	/**
	 * Render a one-cell table and return that cell.
	 *
	 * @param page the page.
	 * @param text the cell text.
	 * @return the rendered td.
	 */
	async function oneCell(page: Page, text: string): Promise<Locator> {
		const widget = await renderTable(page, ctx(), {
			headerRow: false,
			columnAlignments: ['left'],
			rows: [[cell(text)]],
		})
		return widget.locator('table.cn-text-widget__table td').first()
	}

	// @e2e text-display-widget::safe-html-tags-preserved
	test('<b> and <i> in a cell survive and render bold and italic', async ({ page }) => {
		const td = await oneCell(page, 'Hello <b>world</b> and <i>thanks</i>')
		const html = await td.innerHTML()
		expect(html).toContain('<b>world</b>')
		expect(html).toContain('<i>thanks</i>')
		expect(await td.locator('b').evaluate((el) => Number(getComputedStyle(el).fontWeight))).toBeGreaterThanOrEqual(600)
		expect(await td.locator('i').evaluate((el) => getComputedStyle(el).fontStyle)).toBe('italic')
	})

	// @e2e text-display-widget::script-tag-removed
	test('a <script> in a cell is stripped with its contents', async ({ page }) => {
		const td = await oneCell(page, '<script>window.__lpCellScript = 1</script> text')
		await expect(td.locator('script')).toHaveCount(0)
		await expect(td).toHaveText('text')
		expect(await page.evaluate(() => (window as any).__lpCellScript)).toBeUndefined()
	})

	// @e2e text-display-widget::event-handler-stripped
	test('an onclick in a cell link is stripped and href="#" kept', async ({ page }) => {
		const td = await oneCell(page, '<a href="#" onclick="alert(1)">link</a>')
		const link = td.locator('a')
		await expect(link).toHaveCount(1)
		expect(await link.getAttribute('onclick')).toBeNull()
		await expect(link).toHaveAttribute('href', '#')
	})

	// @e2e text-display-widget::javascript-url-stripped
	test('a javascript: href in a cell link is stripped', async ({ page }) => {
		const td = await oneCell(page, '<a href="javascript:alert(1)">click</a>')
		const link = td.locator('a')
		await expect(link).toHaveCount(1)
		expect((await link.getAttribute('href')) ?? '').not.toMatch(/^\s*javascript:/i)
	})
})

test.describe('text widget table — empty-cell placeholder (REQ-TBLE-011)', () => {
	// @e2e text-display-widget::empty-1x1-table-shows-placeholder
	test('an empty 1x1 table shows a muted italic placeholder that is never stored', async ({
		page,
	}) => {
		const id = await seedTable(ctx(), { headerRow: false, columnAlignments: ['left'], rows: [[{ text: '' }]] })
		await gotoLaunchPad(page)
		const placeholder = textWidget(page, id).locator('.cn-text-widget__cell-placeholder')
		await expect(placeholder).toBeVisible({ timeout: 15_000 })
		await expect(placeholder).toHaveText(/empty/i)
		const style = await placeholder.evaluate((el) => ({
			color: getComputedStyle(el).color,
			fontStyle: getComputedStyle(el).fontStyle,
		}))
		expect(style.fontStyle).toBe('italic')
		expect(style.color).toBe(await resolveColour(page, '--color-text-maxcontrast'))

		// Save the table through the editor: the cell is still stored empty.
		const { dialog, form } = await openEditTextForm(page, id)
		await form.locator('.cn-text-table-editor__align-select').first().selectOption('center')
		await submitEdit(page, dialog, id)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows[0][0].text).toBe('')
		expect(stored.columnAlignments).toEqual(['center'])
	})

	// @e2e text-display-widget::placeholder-disappears-on-user-input
	test('typing into the empty cell replaces the placeholder with the text', async ({ page }) => {
		const id = await seedTable(ctx(), { headerRow: false, columnAlignments: ['left'], rows: [[cell('')]] })
		await gotoLaunchPad(page)
		await expect(
			textWidget(page, id).locator('.cn-text-widget__cell-placeholder'),
		).toBeVisible({ timeout: 15_000 })

		const { dialog, form } = await openEditTextForm(page, id)
		const editor = form.locator('.cn-text-table-editor')
		const input = cellInput(editor, 1, 1)
		await expect(input).toHaveValue('')
		expect(await input.evaluate((el) => el.matches(':placeholder-shown'))).toBe(true)
		await input.click()
		await input.pressSequentially('Typed')
		await expect(input).toHaveValue('Typed')
		expect(await input.evaluate((el) => el.matches(':placeholder-shown'))).toBe(false)

		await submitEdit(page, dialog, id)
		const rendered = textWidget(page, id).locator('table.cn-text-widget__table td').first()
		await expect(rendered).toHaveText('Typed', { timeout: 10_000 })
		await expect(rendered.locator('.cn-text-widget__cell-placeholder')).toHaveCount(0)
	})

	// @e2e text-display-widget::table-with-any-non-empty-cell-hides-placeholder
	test('placeholders are per cell: one filled cell, three placeholders, no widget-level one', async ({
		page,
	}) => {
		const widget = await renderTable(page, ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left'],
			rows: [[cell('Data'), cell('')], [cell(''), cell('')]],
		})
		const table = await renderedTable(widget)
		expect(table[0][0]).toMatchObject({ text: 'Data', placeholder: false })
		expect([table[0][1], table[1][0], table[1][1]].map((c) => c.placeholder)).toEqual([true, true, true])
		await expect(widget.locator('.cn-text-widget__placeholder')).toHaveCount(0)
	})
})
