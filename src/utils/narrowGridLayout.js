/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { getDashboardColumnOpts } from '@conduction/nextcloud-vue'

/** Grids this narrow or narrower lay the widgets out in reading order. */
export const NARROW_COLUMNS = 4

/**
 * The breakpoints of the dashboard grid: nextcloud-vue's default table
 * (1400, 1100, 768) without its one-column phone step, so a phone keeps four
 * columns and app tiles sit four to a row (board LpMobiel) instead of one tile
 * per screen-wide row.
 *
 * @type {ReadonlyArray<{w: number, c: number}>}
 */
export const DASHBOARD_BREAKPOINTS = Object.freeze([
	{ w: 1400, c: 12 },
	{ w: 1100, c: 8 },
	{ w: 768, c: NARROW_COLUMNS },
])

/**
 * Whether a node is one cell wide in the layout it comes from: an app tile.
 *
 * @param {{w?: number}} node The GridStack node.
 * @param {number} fromColumns The column count of that layout.
 * @return {boolean}
 * @spec openspec/specs/grid-layout/spec.md#req-grid-007
 */
function isCell(node, fromColumns) {
	return (node.w ?? 1) * 12 <= fromColumns
}

/**
 * Whether a node is a one-row heading (the section heading widget).
 *
 * @param {{h?: number}} node The GridStack node.
 * @return {boolean}
 * @spec openspec/specs/grid-layout/spec.md#req-grid-007
 */
function isHeading(node) {
	return (node.h ?? 1) === 1
}

/**
 * Reading order on a narrow grid. Every widget is read top to bottom, left to
 * right, except that a tile belongs to the nearest heading above it whose
 * columns it falls in, so two tile groups side by side ("My apps" and
 * "Collaborate") each stay under their own heading.
 *
 * @param {Array<{x?: number, y?: number, w?: number, h?: number}>} nodes The nodes in the wider layout.
 * @param {number} fromColumns The column count of that layout.
 * @return {Array<object>} The nodes in reading order.
 * @spec openspec/specs/grid-layout/spec.md#req-grid-007
 */
export function readingOrder(nodes, fromColumns) {
	const headings = nodes.filter((node) => isHeading(node) && !isCell(node, fromColumns))
	const keyOf = (node) => {
		const y = node.y ?? 0
		const x = node.x ?? 0
		if (isCell(node, fromColumns)) {
			const owner = headings
				.filter((h) => (h.y ?? 0) < y && x >= (h.x ?? 0) && x < (h.x ?? 0) + (h.w ?? 1))
				.sort((a, b) => (b.y ?? 0) - (a.y ?? 0))[0]
			if (owner) {
				return [owner.y ?? 0, owner.x ?? 0, y, x]
			}
		}
		return [y, x, -1, -1]
	}
	const compare = (a, b) => {
		const at = a.findIndex((value, i) => value !== b[i])
		return at === -1 ? 0 : a[at] - b[at]
	}
	return nodes
		.map((node) => ({ node, key: keyOf(node) }))
		.sort((a, b) => compare(a.key, b.key))
		.map((entry) => entry.node)
}

/**
 * Place nodes on a narrow grid in reading order: tiles flow one cell each,
 * as many to a row as fit, and every other widget takes a full row.
 *
 * @param {Array<object>} nodes The nodes, already in reading order.
 * @param {number} column The narrow column count.
 * @param {number} fromColumns The column count the nodes come from.
 * @return {void}
 * @spec openspec/specs/grid-layout/spec.md#req-grid-007
 */
export function flowNarrow(nodes, column, fromColumns) {
	let x = 0
	let y = 0
	let rowHeight = 0
	const newRow = () => {
		if (x > 0) {
			y += rowHeight
		}
		x = 0
		rowHeight = 0
	}
	for (const node of nodes) {
		if (isCell(node, fromColumns)) {
			if (x + 1 > column) {
				newRow()
			}
			node.x = x
			node.y = y
			node.w = 1
			x += 1
			rowHeight = Math.max(rowHeight, node.h ?? 1)
			continue
		}
		newRow()
		node.x = 0
		node.y = y
		node.w = column
		y += node.h ?? 1
	}
}

/**
 * GridStack column-change layout: reading-order flow on a narrow grid, the
 * library's move-and-scale everywhere else.
 *
 * @param {number} column The new column count.
 * @param {number} oldColumn The previous column count.
 * @param {Array<object>} newNodes Where placed nodes go.
 * @param {Array<object>} nodes The nodes to place.
 * @return {void}
 * @spec openspec/specs/grid-layout/spec.md#req-grid-007
 */
export function dashboardColumnLayout(column, oldColumn, newNodes, nodes) {
	if (column <= NARROW_COLUMNS && oldColumn > column) {
		const ordered = readingOrder(nodes, oldColumn)
		flowNarrow(ordered, column, oldColumn)
		newNodes.push(...ordered)
		nodes.length = 0
		return
	}
	const ratio = column / oldColumn
	for (const node of nodes) {
		node.x = Math.min(Math.round((node.x ?? 0) * ratio), column - 1)
		node.w = Math.min(Math.round((node.w ?? 1) * ratio) || 1, column)
		newNodes.push(node)
	}
	nodes.length = 0
}

/**
 * The `columnOpts` of the dashboard grid, for a dashboard of `columns`
 * columns.
 *
 * Above the widest breakpoint GridStack widens to `columnMax`, and each
 * breakpoint sets its own count, so both are capped at the dashboard's own
 * column count: a dashboard set to 6 or 8 columns stays at 6 or 8 on a wide
 * screen instead of being widened to the table's 12, and a narrower screen
 * only ever takes columns away. A step that would not narrow the grid any
 * further is left out.
 *
 * @param {number} [columns] The dashboard's column count (default 12).
 * @return {object}
 * @spec openspec/specs/grid-layout/spec.md#req-grid-007
 * @spec openspec/specs/dashboards/spec.md#req-dash-038
 */
export function dashboardGridColumnOpts(columns = 12) {
	const max = Number.isFinite(columns) && columns > 0 ? columns : 12
	const breakpoints = []
	for (const point of DASHBOARD_BREAKPOINTS) {
		const c = Math.min(point.c, max)
		if (breakpoints.length === 0 || c < breakpoints.at(-1).c) {
			breakpoints.push({ ...point, c })
		}
	}
	return {
		...getDashboardColumnOpts(breakpoints, dashboardColumnLayout),
		columnMax: max,
	}
}
