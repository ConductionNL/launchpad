/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Preloading a dashboard's widget renderers: once per type, only for types
 * with a lazily loaded renderer.
 */

import { describe, expect, it, vi } from 'vitest'
import { preloadWidgetRenderers } from '../preloadWidgetRenderers.js'

/**
 * A registry entry whose renderer loads lazily.
 *
 * @return {{entry: object, load: Function}} The entry and its loader.
 */
function lazyEntry() {
	const load = vi.fn(() => Promise.resolve({}))
	return { entry: { renderer: { __cnLazy: { load } } }, load }
}

describe('preloadWidgetRenderers', () => {
	it('asks each type on the dashboard for its renderer once', () => {
		const weather = lazyEntry()
		const cases = lazyEntry()
		const entries = { weather: weather.entry, 'assigned-cases': cases.entry }

		const asked = preloadWidgetRenderers(
			[{ widgetId: 'weather' }, { widgetId: 'assigned-cases' }, { widgetId: 'weather' }],
			(type) => entries[type] ?? null,
		)

		expect(asked).toBe(2)
		expect(weather.load).toHaveBeenCalledTimes(1)
		expect(cases.load).toHaveBeenCalledTimes(1)
	})

	it('skips placements without a type, types it does not know and renderers that are not lazy', () => {
		const entries = { 'nc-widget': { renderer: {} } }

		const asked = preloadWidgetRenderers(
			[{ widgetId: '' }, { tileId: 4 }, null, { widgetId: 'unknown' }, { widgetId: 'nc-widget' }],
			(type) => entries[type] ?? null,
		)

		expect(asked).toBe(0)
	})

	it('asks nothing for a dashboard without placements', () => {
		expect(preloadWidgetRenderers(undefined, () => null)).toBe(0)
	})
})
