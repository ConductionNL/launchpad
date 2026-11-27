/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The grid's column-change layout against the fixture the server's twin
 * (lib/Service/FirstPaintLayout.php) is tested on too, so the first-paint
 * shell lays out exactly what the grid lays out after the hand-over
 * (first-paint-skeleton D3).
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */

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
 * Where every node goes at one column count, keyed by id, with the defaults filled in.
 *
 * @param {Array<object>} stored The stored nodes.
 * @param {number} from The dashboard's column count.
 * @param {number} to The column count to lay out for.
 * @return {object}
 */
function layoutFor(stored, from, to) {
	let placed = stored.map((node) => ({ ...node }))
	if (to !== from) {
		const out = []
		dashboardColumnLayout(to, from, out, placed)
		placed = out
	}
	return Object.fromEntries(placed.map((node) => [node.id, { x: node.x ?? 0, y: node.y ?? 0, w: node.w ?? 1, h: node.h ?? 1 }]))
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
