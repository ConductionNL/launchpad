/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useFilterBus: conflict resolution, toggling, reset and hierarchy resolution
 * (drill-down-cross-widget-filter tasks 18 and 19; REQ-CWF-001, -002, -004,
 * -005, -006).
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { resolveHierarchy, useFilterBus } from '../useFilterBus.js'

const NL_ADMINISTRATIVE = {
	id: 'nl-administrative',
	levels: [
		{ dimension: 'provincieCode', label: 'hierarchy.level.province', parentDimension: null },
		{ dimension: 'gemeenteCode', label: 'hierarchy.level.municipality', parentDimension: 'provincieCode' },
		{ dimension: 'wijkCode', label: 'hierarchy.level.district', parentDimension: 'gemeenteCode' },
		{ dimension: 'buurtCode', label: 'hierarchy.level.neighbourhood', parentDimension: 'wijkCode' },
	],
}

/**
 * A widget-click clause.
 *
 * @param {string} dimension The dimension.
 * @param {unknown} value The value.
 * @param {string} widget The publishing widget.
 * @return {object}
 */
function click(dimension, value, widget = 'A') {
	return { dimension, operator: 'eq', value, source: 'widgetClick', sourceWidgetId: widget }
}

/**
 * The bus state as dimension => [value, source].
 *
 * @param {object} bus The bus.
 * @return {object}
 */
function state(bus) {
	return Object.fromEntries(bus.clauses.value.map((clause) => [clause.dimension, [clause.value, clause.source]]))
}

let warn

beforeEach(() => {
	warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
})

afterEach(() => {
	warn.mockRestore()
})

describe('useFilterBus conflict resolution', () => {
	it('a widget click adds a clause naming its widget, and subscribers hear it', () => {
		const bus = useFilterBus()
		const heard = []
		bus.subscribe((clauses) => heard.push(clauses.map((clause) => clause.value)))

		expect(bus.publish(click('gemeenteCode', 'GM0355'))).toEqual({ accepted: true, toggled: false, audit: null })
		expect(bus.clauses.value).toEqual([
			{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: 'A', pinned: false },
		])
		expect(heard).toEqual([['GM0355']])
	})

	it('a widget click replaces another widget click for the same dimension, with a toast', () => {
		const bus = useFilterBus()
		bus.publish(click('gemeenteCode', 'GM0355', 'A'))

		const result = bus.publish(click('gemeenteCode', 'GM0344', 'B'))

		expect(result.audit).toEqual({ dimension: 'gemeenteCode', from: 'GM0355', to: 'GM0344', fromSource: 'widgetClick', toSource: 'widgetClick', silent: false })
		expect(state(bus)).toEqual({ gemeenteCode: ['GM0344', 'widgetClick'] })
		expect(bus.clauses.value[0].sourceWidgetId).toBe('B')
	})

	it('a widget click replaces a URL-bootstrap clause silently', () => {
		const bus = useFilterBus()
		bus.hydrate([{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' }])

		const result = bus.publish(click('gemeenteCode', 'GM0344'))

		expect(result.audit.silent).toBe(true)
		expect(state(bus)).toEqual({ gemeenteCode: ['GM0344', 'widgetClick'] })
	})

	it('a pinned clause is never replaced, and the drop is logged once', () => {
		const bus = useFilterBus()
		bus.publish({ dimension: 'status', operator: 'eq', value: 'open', source: 'userExplicit', pinned: true })
		const heard = vi.fn()
		bus.subscribe(heard)

		expect(bus.publish(click('status', 'closed'))).toEqual({ accepted: false, reason: 'pinned' })
		expect(state(bus)).toEqual({ status: ['open', 'userExplicit'] })
		expect(heard).not.toHaveBeenCalled()
		expect(warn).toHaveBeenCalledTimes(1)
	})

	it('the same clause clicked twice on the same widget toggles it off', () => {
		const bus = useFilterBus()
		bus.publish(click('gemeenteCode', 'GM0355'))

		expect(bus.publish(click('gemeenteCode', 'GM0355')).toggled).toBe(true)
		expect(bus.clauses.value).toEqual([])
	})

	it('the same value from another widget is a replacement, not a toggle', () => {
		const bus = useFilterBus()
		bus.publish(click('gemeenteCode', 'GM0355', 'A'))

		const result = bus.publish(click('gemeenteCode', 'GM0355', 'B'))
		expect(result.toggled).toBe(false)
		expect(result.audit.silent).toBe(true)
		expect(bus.clauses.value[0].sourceWidgetId).toBe('B')
	})

	it('refuses a clause the user may not see and leaves the bus untouched', () => {
		const bus = useFilterBus({ canAccess: (clause) => clause.value !== 'GM0344' })
		bus.publish(click('gemeenteCode', 'GM0355'))

		expect(bus.publish(click('gemeenteCode', 'GM0344'))).toEqual({ accepted: false, reason: 'forbidden' })
		expect(state(bus)).toEqual({ gemeenteCode: ['GM0355', 'widgetClick'] })
	})

	it('reset clears every non-pinned clause, and on an empty bus does nothing at all', () => {
		const bus = useFilterBus()
		bus.publish({ dimension: 'year', operator: 'eq', value: 2026, source: 'userExplicit', pinned: true })
		bus.publish(click('gemeenteCode', 'GM0355'))
		bus.publish(click('status', 'open', 'C'))
		const heard = vi.fn()
		bus.subscribe(heard)

		expect(bus.reset()).toBe(true)
		expect(state(bus)).toEqual({ year: [2026, 'userExplicit'] })
		expect(heard).toHaveBeenCalledTimes(1)

		expect(bus.reset()).toBe(false)
		expect(heard).toHaveBeenCalledTimes(1)
	})

	it('removes one non-pinned clause, never a pinned one', () => {
		const bus = useFilterBus()
		bus.publish({ dimension: 'year', operator: 'eq', value: 2026, pinned: true })
		bus.publish(click('status', 'open'))

		expect(bus.remove('year')).toBe(false)
		expect(bus.remove('status')).toBe(true)
		expect(bus.remove('status')).toBe(false)
		expect(Object.keys(state(bus))).toEqual(['year'])
	})

	it('gives a widget only the clauses it consumes, also when it mounts later', () => {
		const bus = useFilterBus()
		bus.publish(click('gemeenteCode', 'GM0355'))
		bus.publish(click('status', 'open', 'C'))

		expect(bus.clausesFor(['gemeenteCode']).map((clause) => clause.value)).toEqual(['GM0355'])
		expect(bus.clausesFor(['period.month'])).toEqual([])
		expect(bus.clausesFor(undefined)).toEqual([])
	})

	it('hydrates restored clauses as URL bootstrap and keeps a pinned clause', () => {
		const bus = useFilterBus()
		bus.publish({ dimension: 'year', operator: 'eq', value: 2026, pinned: true })

		bus.hydrate([
			{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: 'A' },
			{ dimension: 'year', operator: 'eq', value: 2020 },
		])

		expect(state(bus)).toEqual({ year: [2026, 'userExplicit'], gemeenteCode: ['GM0355', 'urlBootstrap'] })
	})

	it('ignores a clause without a dimension', () => {
		const bus = useFilterBus()
		expect(bus.publish({ value: 1 })).toEqual({ accepted: false, reason: 'invalid' })
		expect(bus.publish(null)).toEqual({ accepted: false, reason: 'invalid' })
	})
})

describe('useFilterBus hierarchy resolution', () => {
	const row = { wijkCode: 'WK0355001', gemeenteCode: 'GM0355', provincieCode: 'PV26' }

	it('drilling down to a wijk infers the gemeente and provincie as parent scope', () => {
		const bus = useFilterBus({ hierarchies: [NL_ADMINISTRATIVE] })

		bus.publish(click('wijkCode', 'WK0355001'), { row })

		expect(state(bus)).toEqual({
			provincieCode: ['PV26', 'parentScope'],
			gemeenteCode: ['GM0355', 'parentScope'],
			wijkCode: ['WK0355001', 'widgetClick'],
		})
		expect(bus.breadcrumb('nl-administrative')).toEqual([
			{ dimension: 'provincieCode', label: 'hierarchy.level.province', value: 'PV26' },
			{ dimension: 'gemeenteCode', label: 'hierarchy.level.municipality', value: 'GM0355' },
			{ dimension: 'wijkCode', label: 'hierarchy.level.district', value: 'WK0355001' },
		])
	})

	it('drilling up to a provincie clears the gemeente and wijk below it', () => {
		const bus = useFilterBus({ hierarchies: [NL_ADMINISTRATIVE] })
		bus.publish(click('gemeenteCode', 'GM0355'))
		bus.publish(click('wijkCode', 'WK0355001', 'B'), { row })

		bus.publish(click('provincieCode', 'PV09', 'P'))

		expect(state(bus)).toEqual({ provincieCode: ['PV09', 'widgetClick'] })
		expect(bus.breadcrumb('nl-administrative').map((step) => step.value)).toEqual(['PV09'])
	})

	it('a wijk in another gemeente moves the inferred ancestors with it', () => {
		const bus = useFilterBus({ hierarchies: [NL_ADMINISTRATIVE] })
		bus.publish(click('wijkCode', 'WK0355001'), { row })

		bus.publish(click('wijkCode', 'WK0344002'), { row: { gemeenteCode: 'GM0344', provincieCode: 'PV26' } })

		expect(state(bus)).toEqual({
			provincieCode: ['PV26', 'parentScope'],
			gemeenteCode: ['GM0344', 'parentScope'],
			wijkCode: ['WK0344002', 'widgetClick'],
		})
	})

	it('a wijk under a gemeente pinned to another value is refused', () => {
		const bus = useFilterBus({ hierarchies: [NL_ADMINISTRATIVE] })
		bus.publish({ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0344', pinned: true })

		expect(bus.publish(click('wijkCode', 'WK0355001'), { row })).toEqual({ accepted: false, reason: 'pinned' })
		expect(Object.keys(state(bus))).toEqual(['gemeenteCode'])
	})

	it('toggling the wijk off takes its inferred ancestors with it', () => {
		const bus = useFilterBus({ hierarchies: [NL_ADMINISTRATIVE] })
		bus.publish(click('wijkCode', 'WK0355001'), { row })

		bus.publish(click('wijkCode', 'WK0355001'), { row })

		expect(bus.clauses.value).toEqual([])
	})

	it('an ancestor missing from the row is skipped with a warning', () => {
		const bus = useFilterBus({ hierarchies: [NL_ADMINISTRATIVE] })

		bus.publish(click('wijkCode', 'WK0355001'), { row: { gemeenteCode: 'GM0355' } })

		expect(Object.keys(state(bus))).toEqual(['gemeenteCode', 'wijkCode'])
		expect(warn).toHaveBeenCalledTimes(1)
	})

	it('resolveHierarchy leaves a dimension outside every hierarchy alone', () => {
		expect(resolveHierarchy({ dimension: 'status' }, [NL_ADMINISTRATIVE], row)).toEqual({ descendants: [], ancestors: [] })
		expect(resolveHierarchy({ dimension: 'gemeenteCode' }, [NL_ADMINISTRATIVE], row).descendants).toEqual(['wijkCode', 'buurtCode'])
	})
})
