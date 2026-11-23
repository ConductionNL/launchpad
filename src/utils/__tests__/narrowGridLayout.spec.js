/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { describe, expect, it, vi } from 'vitest'
import {
	DASHBOARD_BREAKPOINTS,
	dashboardColumnLayout,
	dashboardGridColumnOpts,
	readingOrder,
} from '../narrowGridLayout.js'

vi.mock('@conduction/nextcloud-vue', () => ({
	getDashboardColumnOpts: (breakpoints, layout) => ({
		breakpoints: breakpoints.map((point) => ({ ...point })),
		layout,
		breakpointForWindow: true,
	}),
}))

/**
 * The top of board LpWerkplek on a 12-column grid: two tile groups side by
 * side under their headings, a clock on the right and a search bar below.
 *
 * @return {Array<object>}
 */
function workplace() {
	const tile = (id, x, y) => ({ id, x, y, w: 1, h: 2 })
	return [
		{ id: 'clock', x: 8, y: 0, w: 4, h: 3 },
		{ id: 'my-apps', x: 0, y: 0, w: 4, h: 1 },
		{ id: 'collaborate', x: 4, y: 0, w: 4, h: 1 },
		tile('case', 0, 1), tile('mail', 1, 1), tile('topdesk', 2, 1), tile('council', 3, 1),
		tile('talk', 4, 1), tile('calendar', 5, 1),
		tile('land', 0, 3), tile('claims', 1, 3),
		tile('people', 4, 3),
		{ id: 'search', x: 0, y: 5, w: 8, h: 2 },
	]
}

describe('narrowGridLayout', () => {
	it('keeps four columns on a phone', () => {
		expect(DASHBOARD_BREAKPOINTS.map((point) => point.c)).toEqual([12, 8, 4])
		const opts = dashboardGridColumnOpts()
		expect(opts.breakpoints.at(-1)).toEqual({ w: 768, c: 4 })
		expect(opts.layout).toBe(dashboardColumnLayout)
	})

	it('reads each tile group under its own heading', () => {
		expect(readingOrder(workplace(), 12).map((node) => node.id)).toEqual([
			'my-apps', 'case', 'mail', 'topdesk', 'council', 'land', 'claims',
			'collaborate', 'talk', 'calendar', 'people',
			'clock', 'search',
		])
	})

	it('flows the tiles four to a row and gives every other widget a full row', () => {
		const placed = []
		const nodes = workplace()
		dashboardColumnLayout(4, 12, placed, nodes)
		const at = Object.fromEntries(placed.map((node) => [node.id, [node.x, node.y, node.w]]))

		expect(nodes).toEqual([])
		expect(at['my-apps']).toEqual([0, 0, 4])
		expect(at.case).toEqual([0, 1, 1])
		expect(at.council).toEqual([3, 1, 1])
		expect(at.land).toEqual([0, 3, 1])
		expect(at.claims).toEqual([1, 3, 1])
		expect(at.collaborate).toEqual([0, 5, 4])
		expect(at.talk).toEqual([0, 6, 1])
		expect(at.clock).toEqual([0, 8, 4])
		expect(at.search).toEqual([0, 11, 4])
	})

	it('moves and scales on a wider grid', () => {
		const placed = []
		dashboardColumnLayout(8, 12, placed, [{ id: 'a', x: 6, y: 2, w: 6, h: 2 }])
		expect(placed).toEqual([{ id: 'a', x: 4, y: 2, w: 4, h: 2 }])
	})
})
