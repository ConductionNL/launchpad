/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useFilterUrlSync: a link with `?f=` opens with its filter as urlBootstrap
 * clauses, a change is written back once per burst, an empty bus removes the
 * parameter, and the URL is replaced, never pushed
 * (drill-down-cross-widget-filter task 4; REQ-CWF-003).
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { deserializeFilterState, serializeFilterState } from '../useDashboardFilter.js'
import { useFilterBus } from '../useFilterBus.js'
import { urlWithFilter, useFilterUrlSync } from '../useFilterUrlSync.js'

const ZEIST = { dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: 'w-a', pinned: false }
const OPEN = { dimension: 'status', operator: 'eq', value: 'open', source: 'widgetClick', sourceWidgetId: 'w-b', pinned: false }

/**
 * A location and a history that write into each other, like the browser's.
 *
 * @param {string} href The starting URL.
 * @return {{location: {href: string}, history: {state: unknown, replaceState: Function, pushState: Function}}}
 */
function browserAt(href) {
	const location = { href }
	const history = {
		state: { route: 'dashboard' },
		replaceState: vi.fn((_state, _title, url) => { location.href = url }),
		pushState: vi.fn(),
	}
	return { location, history }
}

/**
 * The clauses the URL's `f` parameter carries.
 *
 * @param {string} href The URL.
 * @return {Array<object>}
 */
function filterIn(href) {
	const param = new URL(href).searchParams.get('f')
	return param === null ? [] : deserializeFilterState(param).clauses
}

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

describe('useFilterUrlSync', () => {
	it('opens a bookmarked link with its clauses as urlBootstrap', () => {
		const { location, history } = browserAt('https://cloud.example/apps/launchpad/?f=' + serializeFilterState([ZEIST]))
		const bus = useFilterBus()

		const sync = useFilterUrlSync({ bus, location, history })

		expect(bus.clauses.value).toHaveLength(1)
		expect(bus.clauses.value[0]).toMatchObject({ dimension: 'gemeenteCode', value: 'GM0355', source: 'urlBootstrap' })
		expect(sync.restored).toHaveLength(1)
		// Reading the URL is not a change of it.
		vi.runAllTimers()
		expect(history.replaceState).not.toHaveBeenCalled()
	})

	it('opens unfiltered on a malformed parameter and leaves the URL alone', () => {
		const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
		const { location, history } = browserAt('https://cloud.example/apps/launchpad/?f=%%%not-base64')
		const bus = useFilterBus()

		useFilterUrlSync({ bus, location, history })

		expect(bus.clauses.value).toEqual([])
		expect(history.replaceState).not.toHaveBeenCalled()
		warn.mockRestore()
	})

	it('with hydrate off, ignores the URL filter and still writes', () => {
		const { location, history } = browserAt('https://cloud.example/apps/launchpad/?f=' + serializeFilterState([ZEIST]))
		const bus = useFilterBus()

		useFilterUrlSync({ bus, location, history, hydrate: false })
		expect(bus.clauses.value).toEqual([])

		bus.publish(OPEN)
		vi.runAllTimers()
		expect(filterIn(location.href).map((clause) => clause.dimension)).toEqual(['status'])
	})

	it('writes a burst of clicks to the URL once, after the debounce', () => {
		const { location, history } = browserAt('https://cloud.example/apps/launchpad/?tab=2')
		const bus = useFilterBus()
		useFilterUrlSync({ bus, location, history, debounceMs: 500 })

		bus.publish(ZEIST)
		bus.publish(OPEN)
		vi.advanceTimersByTime(499)
		expect(history.replaceState).not.toHaveBeenCalled()

		vi.advanceTimersByTime(1)
		expect(history.replaceState).toHaveBeenCalledTimes(1)
		expect(history.pushState).not.toHaveBeenCalled()
		expect(history.replaceState.mock.calls[0][0]).toEqual({ route: 'dashboard' })
		expect(new URL(location.href).searchParams.get('tab')).toBe('2')
		expect(filterIn(location.href).map((clause) => clause.dimension)).toEqual(['gemeenteCode', 'status'])
	})

	it('removes the parameter when the bus is reset', () => {
		const { location, history } = browserAt('https://cloud.example/apps/launchpad/?f=' + serializeFilterState([ZEIST]))
		const bus = useFilterBus()
		useFilterUrlSync({ bus, location, history })

		bus.reset()
		vi.runAllTimers()

		expect(new URL(location.href).searchParams.has('f')).toBe(false)
		expect(history.replaceState).toHaveBeenCalledTimes(1)
	})

	it('writes a waiting change on stop and listens no longer', () => {
		const { location, history } = browserAt('https://cloud.example/apps/launchpad/')
		const bus = useFilterBus()
		const sync = useFilterUrlSync({ bus, location, history })

		bus.publish(ZEIST)
		sync.stop()
		expect(filterIn(location.href)).toHaveLength(1)

		bus.publish(OPEN)
		vi.runAllTimers()
		expect(history.replaceState).toHaveBeenCalledTimes(1)
	})
})

describe('urlWithFilter', () => {
	it('keeps the other parameters and drops f for no clauses', () => {
		const href = urlWithFilter('https://cloud.example/x?a=1&f=abc', [])
		expect(href).toBe('https://cloud.example/x?a=1')
	})
})
