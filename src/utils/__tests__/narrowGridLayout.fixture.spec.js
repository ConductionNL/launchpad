/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The grid's column-change layout against the fixture the server's twin
 * (lib/Service/FirstPaintLayout.php) is tested on too, so the first-paint
 * shell lays out exactly what the grid lays out after the hand-over
 * (first-paint-skeleton D3). The layout runs through GridStack's own engine
 * with the dashboard's column-change callback, so the fixture holds what the
 * grid really draws, including its collision push and top-gravity pack after
 * a move-and-scale.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */

import { GridStackEngine } from 'gridstack/dist/gridstack-engine.js'
import { describe, expect, it } from 'vitest'
import fixture from '../../../tests/fixtures/first-paint-layouts.json'
import { DASHBOARD_BREAKPOINTS, dashboardColumnLayout } from '../narrowGridLayout.js'

/**
 * The column counts the grid shows for a dashboard, widest first: its own,
 * then each breakpoint capped at it that narrows it further, as
 * dashboardGridColumnOpts() builds them.
 *
 * @param {number} columns The dashboard's column count.
 * @return {Array<number>}
 */
function columnCounts(columns) {
	const counts = [columns]
	for (const point of DASHBOARD_BREAKPOINTS) {
		const c = Math.min(point.c, columns)
		if (c < counts.at(-1)) {
			counts.push(c)
		}
	}
	return counts
}

/**
 * Where every node goes at one column count, keyed by id, with the defaults
 * filled in: the grid takes the stored nodes in at the dashboard's columns
 * (row order, then packed), then changes to `to` columns with
 * dashboardColumnLayout as CnDashboardGrid's columnOpts do.
 *
 * @param {Array<object>} stored The stored nodes.
 * @param {number} from The dashboard's column count.
 * @param {number} to The column count to lay out for.
 * @return {object}
 */
function layoutFor(stored, from, to) {
	const engine = new GridStackEngine({ column: from, mode: 'top' })
	const nodes = stored
		.map((node) => ({ x: 0, y: 0, w: 1, h: 1, ...node }))
		.sort((a, b) => (a.y - b.y) || (a.x - b.x))
	engine.batchUpdate()
	for (const node of nodes) {
		engine.addNode(node)
	}
	engine.batchUpdate(false)
	if (to !== from) {
		engine.columnChanged(from, to, dashboardColumnLayout)
	}
	return Object.fromEntries(engine.nodes.map((node) => [node.id, { x: node.x, y: node.y, w: node.w, h: node.h }]))
}

describe('narrowGridLayout against the first-paint fixture', () => {
	for (const testCase of fixture.cases) {
		it(testCase.name, () => {
			const counts = columnCounts(testCase.columns)
			expect(counts.map(String)).toEqual(Object.keys(testCase.expected).sort((a, b) => Number(b) - Number(a)))
			for (const count of counts) {
				expect(layoutFor(testCase.nodes, testCase.columns, count), `${count} columns`).toEqual(testCase.expected[String(count)])
			}
		})
	}
})
