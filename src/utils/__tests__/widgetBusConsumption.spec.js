/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * widgetBusConsumption: a placement's bus declarations and the data-source
 * filter the bus's clauses make for it (drill-down-cross-widget-filter
 * tasks 8 and 10; REQ-CWF-002, REQ-CWF-008).
 */

import { describe, expect, it } from 'vitest'
import { busDeclarationsOf, consumedFilter, publishedClauses, withBusFilter } from '../widgetBusConsumption.js'

describe('busDeclarationsOf', () => {
	it('reads the declarations from the content, else from content.props, strings as dimensions', () => {
		expect(busDeclarationsOf({ consumes: ['gemeenteCode', { dimension: 'wijkCode', field: 'wijk' }, '', 4] })).toEqual({
			publishes: [],
			consumes: [{ dimension: 'gemeenteCode' }, { dimension: 'wijkCode', field: 'wijk' }],
		})
		expect(busDeclarationsOf({ props: { publishes: [{ dimension: 'gemeenteCode', fromField: 'gemeente' }] } })).toEqual({
			publishes: [{ dimension: 'gemeenteCode', fromField: 'gemeente' }],
			consumes: [],
		})
		expect(busDeclarationsOf(null)).toEqual({ publishes: [], consumes: [] })
	})
})

describe('consumedFilter', () => {
	const clauses = [
		{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' },
		{ dimension: 'status', operator: 'in', value: ['open', 'paused'] },
		{ dimension: 'period.month', operator: 'between', value: ['2026-01', '2026-03'] },
		{ dimension: 'wijkCode', operator: 'eq', value: 'WK035501' },
	]

	it('filters on the consumed dimensions, on their declared field', () => {
		expect(consumedFilter(clauses, [{ dimension: 'gemeenteCode', field: 'gemeente' }, { dimension: 'status' }])).toEqual({
			gemeente: 'GM0355',
			status: ['open', 'paused'],
		})
	})

	it('leaves out dimensions it does not consume and operators a data source cannot take', () => {
		expect(consumedFilter(clauses, [{ dimension: 'period.month' }, { dimension: 'buurtCode' }])).toEqual({})
		expect(consumedFilter([{ dimension: 'status', operator: 'in', value: 'open' }], ['status'])).toEqual({})
	})
})

describe('withBusFilter', () => {
	it('adds the bus filter over the declared one', () => {
		const source = { schema: 'zaak', filter: { type: 'bezwaar', gemeente: 'GM0001' }, groupBy: { field: 'status' } }

		expect(withBusFilter(source, { gemeente: 'GM0355' })).toEqual({
			schema: 'zaak',
			filter: { type: 'bezwaar', gemeente: 'GM0355' },
			groupBy: { field: 'status' },
		})
		expect(source.filter).toEqual({ type: 'bezwaar', gemeente: 'GM0001' })
	})

	it('returns the same source when there is nothing to add', () => {
		const source = { schema: 'zaak' }
		expect(withBusFilter(source, {})).toBe(source)
		expect(withBusFilter(null, { a: 1 })).toBeNull()
	})
})

describe('publishedClauses (REQ-CWF-001)', () => {
	it('publishes a widget click for each dimension published from the picked field', () => {
		const publishes = [{ dimension: 'gemeenteCode', fromField: 'gemeente' }, { dimension: 'status', fromField: 'status' }, 'anyField']
		expect(publishedClauses(publishes, { field: 'gemeente', value: 'GM0355' }, '7')).toEqual([
			{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: '7' },
			{ dimension: 'anyField', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: '7' },
		])
	})

	it('publishes nothing for an empty pick', () => {
		const publishes = [{ dimension: 'gemeenteCode' }]
		expect(publishedClauses(publishes, null, '7')).toEqual([])
		expect(publishedClauses(publishes, { field: 'gemeente', value: '' }, '7')).toEqual([])
		expect(publishedClauses(publishes, { field: 'gemeente', value: null }, '7')).toEqual([])
	})
})
