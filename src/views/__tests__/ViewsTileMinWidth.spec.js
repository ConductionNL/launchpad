/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A tile is a widget of one cell (LpWerkplek): the grid gets a one-column
 * minimum for every tile placement, so it does not widen a tile to the
 * grid's two-column minimum and push its neighbours out of place.
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('../../constants/widgetRegistry.js', async (importOriginal) => {
	const actual = await importOriginal()
	return {
		...actual,
		getWidgetTypeEntry: (type) => (type === 'divider' ? { minHeight: 1 } : actual.getWidgetTypeEntry(type)),
	}
})

const { isTilePlacement, withTypeMinHeight } = await import('../Views.vue')

describe('Views: one-cell tiles', () => {
	it('knows launcher, inline and app tiles', () => {
		expect(isTilePlacement({ widgetId: 'tile' })).toBe(true)
		expect(isTilePlacement({ widgetId: 'tile-12' })).toBe(true)
		expect(isTilePlacement({ widgetId: 'mydash-tile', tileType: 'custom' })).toBe(true)
		expect(isTilePlacement({ widgetId: 'app-tile' })).toBe(true)
		expect(isTilePlacement({ widgetId: 'calendar' })).toBe(false)
	})

	it('gives a tile a one-column minimum and leaves other widgets at the grid minimum', () => {
		expect(withTypeMinHeight({ id: 1, widgetId: 'tile' }).minWidth).toBe(1)
		expect(withTypeMinHeight({ id: 2, widgetId: 'calendar' }).minWidth).toBeUndefined()
	})

	it('keeps a divider\'s one-row minimum and a minimum the placement sets itself', () => {
		expect(withTypeMinHeight({ id: 3, widgetId: 'divider' }).minHeight).toBe(1)
		expect(withTypeMinHeight({ id: 4, widgetId: 'tile', minWidth: 3 }).minWidth).toBe(3)
	})
})
