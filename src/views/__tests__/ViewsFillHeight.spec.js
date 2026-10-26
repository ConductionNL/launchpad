/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A dashboard whose placement asks for it (`content.fillHeight`) fills the
 * height below the top bar (LpVandaag): the page hands the grid
 * `fillHeight`, and outside edit mode marks the placements whose rows size
 * to their content (`content.fitRows`).
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))

let Views

beforeAll(async () => {
	Views = (await import('../Views.vue')).default
})

/**
 * A host with the page's own computed properties.
 *
 * @param {object[]} placements The shown placements.
 * @param {boolean} isEditMode Whether the page is in edit mode.
 * @return {object}
 */
function makeHost(placements, isEditMode = false) {
	const host = { shownPlacements: placements, isEditMode }
	Object.defineProperty(host, 'fillsHeight', { get: () => Views.computed.fillsHeight.call(host) })
	Object.defineProperty(host, 'gridLayout', { get: () => Views.computed.gridLayout.call(host) })
	return host
}

const header = { id: 1, widgetId: 'header', content: { fillHeight: true, fitRows: true } }
const counter = { id: 2, widgetId: 'stat', content: { fitRows: true } }
const tiles = { id: 3, widgetId: 'app-tiles', content: {} }

describe('Views: a dashboard that fills the height', () => {
	it('fills when a placement asks for it, and marks the rows that fit their content', () => {
		const host = makeHost([header, counter, tiles])
		expect(host.fillsHeight).toBe(true)
		expect(host.gridLayout.map((p) => p.fitRows === true)).toEqual([true, true, false])
		expect(host.gridLayout[2]).toBe(tiles)
	})

	it('hands the placements over unchanged while editing', () => {
		const host = makeHost([header, counter, tiles], true)
		expect(host.gridLayout).toBe(host.shownPlacements)
		expect(host.gridLayout[0].fitRows).toBeUndefined()
	})

	it('does not fill a dashboard that does not ask', () => {
		const host = makeHost([counter, tiles])
		expect(host.fillsHeight).toBe(false)
		expect(host.gridLayout).toBe(host.shownPlacements)
	})
})
