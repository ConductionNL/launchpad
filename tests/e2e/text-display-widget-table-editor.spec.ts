/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `text` widget's table editor: add and
 * delete rows and columns, merge and split cells, column alignment and the
 * header row (REQ-TBLE-003..007 of openspec/specs/text-display-widget),
 * written for e2e-coverage-programme.
 *
 * Every scenario drives CnTextTableEditor inside the real Add/Edit Widget
 * modal: the toolbar buttons, the per-column alignment selects, the Header row
 * checkbox and the cell inputs (labelled "Row r, column c"), with selection by
 * click and Shift+click as the editor documents it. Delete confirmations are
 * the editor's own `window.confirm`, answered through Playwright's dialog
 * event. Results are read from the editor's DOM, from the stored `tableData`
 * (API read-back) and, where the scenario names rendering, from the
 * dashboard's `<table>` after a reload. Tables that must start with a merged
 * cell are seeded through the API and opened with edit mode → right-click →
 * Edit.
 *
 * Gate-19 @e2e traceability:
 *   @e2e text-display-widget::add-row-below-current-row
 *   @e2e text-display-widget::add-row-above-current-row
 *   @e2e text-display-widget::add-row-with-default-alignment
 *   @e2e text-display-widget::add-row-into-merged-cell-context
 *   @e2e text-display-widget::add-column-right-of-current-column
 *   @e2e text-display-widget::add-column-left-of-current-column
 *   @e2e text-display-widget::add-column-with-cell-initialization
 *   @e2e text-display-widget::delete-empty-row
 *   @e2e text-display-widget::delete-row-with-text-confirmation-required
 *   @e2e text-display-widget::delete-column-with-confirmation
 *   @e2e text-display-widget::cancel-deletion
 *   @e2e text-display-widget::merge-2-horizontal-cells
 *   @e2e text-display-widget::merge-2-vertical-cells
 *   @e2e text-display-widget::merge-2x2-block
 *   @e2e text-display-widget::split-merged-cell
 *   @e2e text-display-widget::split-without-merge-idempotent
 *   @e2e text-display-widget::set-column-alignment-to-center
 *   @e2e text-display-widget::set-multiple-columns-to-different-alignments
 *   @e2e text-display-widget::toggle-header-row-flag
 *   @e2e text-display-widget::header-row-toggle-is-independent-of-content
 *
 * @spec openspec/specs/text-display-widget/spec.md
 */

import type { APIRequestContext, Locator } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { TableContext } from './support/textTable.ts'

import { expect, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import {
	cell,
	editorTexts,
	editTable,
	freshTable,
	grid,
	labels,
	seedTable,
	storedTable,
} from './support/textTable.ts'
import {
	adminApi,
	answerConfirms,
	buildTable,
	cellInput,
	editorButton,
	editorGrid,
	renderedTable,
	submitAdd,
	submitEdit,
	textWidget,
} from './support/textWidget.ts'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E Text Table Editor ${Date.now()}`)
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

test.describe('text widget table — add row (REQ-TBLE-003)', () => {
	// @e2e text-display-widget::add-row-below-current-row
	test('Add row below on row 0 inserts three empty 1x1 cells at index 1', async ({ page }) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 2, 3)
		await cellInput(editor, 1, 2).click()
		await editorButton(editor, 'Add row below').click()
		expect(await editorTexts(editor)).toEqual([
			['r0c0', 'r0c1', 'r0c2'],
			['', '', ''],
			['r1c0', 'r1c1', 'r1c2'],
		])
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows).toHaveLength(3)
		expect(stored.rows[1]).toEqual([cell(''), cell(''), cell('')])
	})

	// @e2e text-display-widget::add-row-above-current-row
	test('Add row above on row 1 inserts at index 1 and shifts the old row to 2', async ({ page }) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 2, 3)
		await cellInput(editor, 2, 1).click()
		await editorButton(editor, 'Add row above').click()
		expect(await editorTexts(editor)).toEqual([
			['r0c0', 'r0c1', 'r0c2'],
			['', '', ''],
			['r1c0', 'r1c1', 'r1c2'],
		])
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows).toHaveLength(3)
		expect(stored.rows[2].map((c) => c.text)).toEqual(['r1c0', 'r1c1', 'r1c2'])
	})

	// @e2e text-display-widget::add-row-with-default-alignment
	test('a new row gets one cell per column and follows the column alignments', async ({ page }) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 1, 3)
		const aligns = editor.locator('.cn-text-table-editor__align-select')
		await aligns.nth(1).selectOption('center')
		await aligns.nth(2).selectOption('right')
		await cellInput(editor, 1, 1).click()
		await editorButton(editor, 'Add row below').click()
		const shown = await editorGrid(editor)
		expect(shown).toHaveLength(2)
		expect(shown[1].map((c) => c.align)).toEqual(['left', 'center', 'right'])
		await cellInput(editor, 2, 1).fill('n0')
		await cellInput(editor, 2, 2).fill('n1')
		await cellInput(editor, 2, 3).fill('n2')

		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		const stored = await storedTable(ctx(), id)
		expect(stored.columnAlignments).toEqual(['left', 'center', 'right'])
		expect(stored.rows[1]).toHaveLength(3)
		await page.reload()
		const table = await renderedTable(textWidget(page, id))
		expect(table[1].map((c) => [c.text, c.align])).toEqual([
			['n0', 'left'],
			['n1', 'center'],
			['n2', 'right'],
		])
	})

	// @e2e text-display-widget::add-row-into-merged-cell-context
	test('a row added below a colSpan 2 cell gets its own cells under columns 0 and 1', async ({
		page,
	}) => {
		const id = await seedTable(ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left', 'left'],
			rows: [
				[cell('Merged', 1, 2), cell(''), cell('X')],
				[cell('A'), cell('B'), cell('C')],
			],
		})
		const { dialog, editor } = await editTable(page, id)
		await cellInput(editor, 1, 1).click()
		await editorButton(editor, 'Add row below').click()
		const shown = await editorGrid(editor)
		expect(shown).toHaveLength(3)
		// Columns 0 and 1 of the new row are two separate, visible 1x1 cells.
		expect(shown[1].slice(0, 2).map((c) => [c.visible, c.colSpan, c.rowSpan])).toEqual([
			[true, 1, 1],
			[true, 1, 1],
		])
		await cellInput(editor, 2, 1).fill('n0')
		await cellInput(editor, 2, 2).fill('n1')

		await submitEdit(page, dialog, id)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows[0][0]).toEqual(cell('Merged', 1, 2))
		expect(stored.rows[1].slice(0, 2)).toEqual([cell('n0'), cell('n1')])
		expect(stored.rows.every((row) => row.length === stored.columnAlignments.length)).toBe(true)
	})
})

test.describe('text widget table — add column (REQ-TBLE-004)', () => {
	// @e2e text-display-widget::add-column-right-of-current-column
	test('Add column right on column 0 inserts an empty, left-aligned column at index 1', async ({
		page,
	}) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 2, 2)
		await editor.locator('.cn-text-table-editor__align-select').nth(1).selectOption('right')
		await cellInput(editor, 1, 1).click()
		await editorButton(editor, 'Add column right').click()
		expect(await editorTexts(editor)).toEqual([
			['r0c0', '', 'r0c1'],
			['r1c0', '', 'r1c1'],
		])
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		const stored = await storedTable(ctx(), id)
		expect(stored.columnAlignments).toEqual(['left', 'left', 'right'])
		expect(stored.rows.map((row) => row[1])).toEqual([cell(''), cell('')])
	})

	// @e2e text-display-widget::add-column-left-of-current-column
	test('Add column left on column 1 inserts at index 1 and shifts the old column to 2', async ({
		page,
	}) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 2, 2)
		await cellInput(editor, 1, 2).click()
		await editorButton(editor, 'Add column left').click()
		expect(await editorTexts(editor)).toEqual([
			['r0c0', '', 'r0c1'],
			['r1c0', '', 'r1c1'],
		])
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		const stored = await storedTable(ctx(), id)
		expect(stored.columnAlignments).toHaveLength(3)
		expect(stored.rows.every((row) => row.length === 3)).toBe(true)
	})

	// @e2e text-display-widget::add-column-with-cell-initialization
	test('every one of N rows receives a fresh {text: "", rowSpan: 1, colSpan: 1}', async ({
		page,
	}) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 3, 1)
		await cellInput(editor, 1, 1).click()
		await editorButton(editor, 'Add column right').click()
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows).toHaveLength(3)
		expect(stored.rows.map((row) => row[1])).toEqual([cell(''), cell(''), cell('')])
	})
})

test.describe('text widget table — delete (REQ-TBLE-005)', () => {
	// @e2e text-display-widget::delete-empty-row
	test('an empty row is deleted without a confirmation prompt', async ({ page }) => {
		const confirms = answerConfirms(page, true)
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 3, 2, false)
		await cellInput(editor, 1, 1).fill('top')
		await cellInput(editor, 3, 1).fill('bottom')
		await cellInput(editor, 2, 1).click()
		await editorButton(editor, 'Delete row').click()
		expect(await editorTexts(editor)).toEqual([['top', ''], ['bottom', '']])
		expect(confirms, 'an empty row must not ask for confirmation').toEqual([])
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		expect((await storedTable(ctx(), id)).rows).toHaveLength(2)
	})

	// @e2e text-display-widget::delete-row-with-text-confirmation-required
	test('a row with text asks "This row contains text. Delete?" and goes on confirm', async ({
		page,
	}) => {
		const confirms = answerConfirms(page, true)
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 3, 2)
		await cellInput(editor, 2, 1).click()
		await editorButton(editor, 'Delete row').click()
		await expect.poll(() => confirms.length).toBe(1)
		expect(confirms[0]).toBe('This row contains text. Delete?')
		expect(await editorTexts(editor)).toEqual([['r0c0', 'r0c1'], ['r2c0', 'r2c1']])
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		expect((await storedTable(ctx(), id)).rows.map((row) => row.map((c) => c.text))).toEqual([
			['r0c0', 'r0c1'],
			['r2c0', 'r2c1'],
		])
	})

	// @e2e text-display-widget::delete-column-with-confirmation
	test('a column with text asks first, then leaves every row and the alignments', async ({
		page,
	}) => {
		const confirms = answerConfirms(page, true)
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 2, 3)
		const aligns = editor.locator('.cn-text-table-editor__align-select')
		await aligns.nth(1).selectOption('center')
		await aligns.nth(2).selectOption('right')
		await cellInput(editor, 1, 2).click()
		await editorButton(editor, 'Delete column').click()
		await expect.poll(() => confirms.length).toBe(1)
		expect(await editorTexts(editor)).toEqual([['r0c0', 'r0c2'], ['r1c0', 'r1c2']])
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		const stored = await storedTable(ctx(), id)
		expect(stored.columnAlignments).toEqual(['left', 'right'])
		expect(stored.rows.map((row) => row.map((c) => c.text))).toEqual([
			['r0c0', 'r0c2'],
			['r1c0', 'r1c2'],
		])
	})

	// @e2e text-display-widget::cancel-deletion
	test('cancelling the confirmation leaves the row in place', async ({ page }) => {
		const confirms = answerConfirms(page, false)
		const { editor } = await freshTable(page)
		await buildTable(editor, 3, 2)
		const before = await editorTexts(editor)
		await cellInput(editor, 2, 1).click()
		await editorButton(editor, 'Delete row').click()
		await expect.poll(() => confirms.length).toBe(1)
		expect(await editorTexts(editor)).toEqual(before)
		expect(before).toHaveLength(3)
	})
})

test.describe('text widget table — merge and split (REQ-TBLE-006)', () => {
	/**
	 * Select a rectangle the way the editor documents it: click one corner,
	 * Shift+click the other.
	 *
	 * @param editor the table editor.
	 * @param from 1-indexed [row, col] of the first corner.
	 * @param to 1-indexed [row, col] of the second corner.
	 * @return nothing.
	 */
	async function selectRange(
		editor: Locator,
		from: [number, number],
		to: [number, number],
	): Promise<void> {
		await cellInput(editor, from[0], from[1]).click()
		await cellInput(editor, to[0], to[1]).click({ modifiers: ['Shift'] })
		await expect(
			editorButton(editor, 'Merge cells'),
			'a two-corner selection must enable Merge cells',
		).toBeEnabled({ timeout: 5_000 })
	}

	// @e2e text-display-widget::merge-2-horizontal-cells
	test('merging columns 0 and 1 of row 0 gives colSpan 2 and an emptied placeholder', async ({
		page,
	}) => {
		const id = await seedTable(ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left', 'left', 'left'],
			rows: grid([['a', 'b', 'c', 'd']]),
		})
		const { dialog, editor } = await editTable(page, id)
		await selectRange(editor, [1, 1], [1, 2])
		await editorButton(editor, 'Merge cells').click()
		const shown = await editorGrid(editor)
		expect(shown[0][0]).toMatchObject({ colSpan: 2, visible: true })
		expect(shown[0][1]).toMatchObject({ text: '', visible: false })

		await submitEdit(page, dialog, id)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows[0][0].colSpan).toBe(2)
		expect(stored.rows[0][1]).toEqual(cell(''))
		expect(stored.rows[0]).toHaveLength(4)
		await page.reload()
		const table = await renderedTable(textWidget(page, id))
		expect(table[0][0]).toMatchObject({ colSpan: 2, visible: true })
		expect(table[0][1].visible, 'the swallowed cell must not render').toBe(false)
	})

	// @e2e text-display-widget::merge-2-vertical-cells
	test('merging rows 0 and 1 of column 0 gives rowSpan 2 and an emptied placeholder', async ({
		page,
	}) => {
		const id = await seedTable(ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left'],
			rows: grid([['a', 'b'], ['c', 'd'], ['e', 'f']]),
		})
		const { dialog, editor } = await editTable(page, id)
		await selectRange(editor, [1, 1], [2, 1])
		await editorButton(editor, 'Merge cells').click()
		await submitEdit(page, dialog, id)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows[0][0].rowSpan).toBe(2)
		expect(stored.rows[1][0]).toEqual(cell(''))
	})

	// @e2e text-display-widget::merge-2x2-block
	test('merging a 2x2 block gives rowSpan 2, colSpan 2 and three emptied placeholders', async ({
		page,
	}) => {
		const id = await seedTable(ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left', 'left'],
			rows: grid(labels(3, 3)),
		})
		const { dialog, editor } = await editTable(page, id)
		await selectRange(editor, [1, 1], [2, 2])
		await editorButton(editor, 'Merge cells').click()
		await submitEdit(page, dialog, id)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows[0][0]).toMatchObject({ rowSpan: 2, colSpan: 2 })
		expect([stored.rows[0][1], stored.rows[1][0], stored.rows[1][1]]).toEqual([
			cell(''),
			cell(''),
			cell(''),
		])
	})

	// @e2e text-display-widget::split-merged-cell
	test('Split cell resets a colSpan 2 cell and its placeholder to 1x1 each', async ({ page }) => {
		const id = await seedTable(ctx(), {
			headerRow: false,
			columnAlignments: ['left', 'left'],
			rows: [[cell('Wide', 1, 2), cell('')], [cell('A'), cell('B')]],
		})
		const { dialog, editor } = await editTable(page, id)
		await cellInput(editor, 1, 1).click()
		await expect(editorButton(editor, 'Split cell')).toBeEnabled()
		await editorButton(editor, 'Split cell').click()
		const shown = await editorGrid(editor)
		expect(shown[0].map((c) => [c.visible, c.colSpan, c.rowSpan])).toEqual([
			[true, 1, 1],
			[true, 1, 1],
		])

		await submitEdit(page, dialog, id)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows[0][0]).toMatchObject({ colSpan: 1, rowSpan: 1 })
		expect(stored.rows[0][1]).toMatchObject({ colSpan: 1, rowSpan: 1 })
		await page.reload()
		const table = await renderedTable(textWidget(page, id))
		expect(table[0].map((c) => [c.visible, c.colSpan])).toEqual([
			[true, 1],
			[true, 1],
		])
	})

	// @e2e text-display-widget::split-without-merge-idempotent
	test('Split cell on an unmerged cell changes nothing', async ({ page }) => {
		const { editor } = await freshTable(page)
		await buildTable(editor, 2, 2)
		await cellInput(editor, 1, 1).click()
		const before = await editorGrid(editor)
		const split = editorButton(editor, 'Split cell')
		await expect(split).toBeDisabled()
		await split.click({ force: true })
		expect(await editorGrid(editor)).toEqual(before)
	})
})

test.describe('text widget table — alignment and header row (REQ-TBLE-007)', () => {
	// @e2e text-display-widget::set-column-alignment-to-center
	test('setting column 1 to center stores it and centres every cell in that column', async ({
		page,
	}) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 2, 3)
		await editor.locator('.cn-text-table-editor__align-select').nth(1).selectOption('center')
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		expect((await storedTable(ctx(), id)).columnAlignments).toEqual(['left', 'center', 'left'])
		await page.reload()
		const table = await renderedTable(textWidget(page, id))
		expect(table.map((row) => row[1].align)).toEqual(['center', 'center'])
		expect(table.map((row) => row[0].align)).toEqual(['left', 'left'])
	})

	// @e2e text-display-widget::set-multiple-columns-to-different-alignments
	test('left / center / right on three columns are stored and rendered per column', async ({
		page,
	}) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 2, 3)
		const aligns = editor.locator('.cn-text-table-editor__align-select')
		await aligns.nth(0).selectOption('left')
		await aligns.nth(1).selectOption('center')
		await aligns.nth(2).selectOption('right')
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		expect((await storedTable(ctx(), id)).columnAlignments).toEqual(['left', 'center', 'right'])
		await page.reload()
		const table = await renderedTable(textWidget(page, id))
		for (const row of table) {
			expect(row.map((c) => c.align)).toEqual(['left', 'center', 'right'])
		}
	})

	// @e2e text-display-widget::toggle-header-row-flag
	test('ticking Header row stores headerRow true and renders row 0 as th', async ({ page }) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 2, 2)
		const header = editor.getByLabel('Header row')
		await expect(header).not.toBeChecked()
		await header.check()
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		expect((await storedTable(ctx(), id)).headerRow).toBe(true)
		await page.reload()
		const table = await renderedTable(textWidget(page, id))
		expect(table[0].map((c) => c.tag)).toEqual(['th', 'th'])
		expect(table[1].map((c) => c.tag)).toEqual(['td', 'td'])
	})

	// @e2e text-display-widget::header-row-toggle-is-independent-of-content
	test('toggling Header row on and off never touches the cell text', async ({ page }) => {
		const { dialog, editor } = await freshTable(page)
		await buildTable(editor, 2, 2)
		const texts = await editorTexts(editor)
		const header = editor.getByLabel('Header row')
		await header.check()
		expect(await editorTexts(editor)).toEqual(texts)
		await header.uncheck()
		expect(await editorTexts(editor)).toEqual(texts)
		await header.check()
		expect(await editorTexts(editor)).toEqual(texts)

		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		const stored = await storedTable(ctx(), id)
		expect(stored.rows.map((row) => row.map((c) => c.text))).toEqual(texts)
		await page.reload()
		const table = await renderedTable(textWidget(page, id))
		expect(table.map((row) => row.map((c) => c.text))).toEqual(texts)
		expect(table[0].map((c) => c.tag)).toEqual(['th', 'th'])
	})
})
