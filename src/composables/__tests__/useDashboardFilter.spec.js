/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useDashboardFilter: the `?f=` parameter round-trips the filter state, uses
 * the short keys, stays small, and reads a malformed value as no filter
 * (drill-down-cross-widget-filter task 20; REQ-CWF-003).
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { deserializeFilterState, serializeFilterState } from '../useDashboardFilter.js'

const CLAUSES = [
	{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: 'w-chart-gemeenten', pinned: false },
	{ dimension: 'wijkCode', operator: 'in', value: ['WK0355001', 'WK0355002'], source: 'widgetClick', sourceWidgetId: 'w-map-wijken', pinned: false },
	{ dimension: 'period.month', operator: 'between', value: ['2026-01', '2026-03'], source: 'userExplicit', sourceWidgetId: null, pinned: true },
	{ dimension: 'naam', operator: 'contains', value: 'Zeiste ëë', source: 'savedView', sourceWidgetId: null, pinned: false },
	{ dimension: 'status', operator: 'notNull', source: 'userExplicit', sourceWidgetId: null, pinned: false },
]

/**
 * Decode a parameter the way a reader would, to inspect its JSON.
 *
 * @param {string} param The parameter value.
 * @return {unknown}
 */
function payloadOf(param) {
	const base64 = param.replace(/-/g, '+').replace(/_/g, '/')
	const binary = atob(base64 + '='.repeat((4 - (base64.length % 4)) % 4))
	return JSON.parse(new TextDecoder().decode(Uint8Array.from(binary, (char) => char.charCodeAt(0))))
}

let warn

beforeEach(() => {
	warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
})

afterEach(() => {
	warn.mockRestore()
})

describe('useDashboardFilter', () => {
	it('round-trips every operator, value shape and source', () => {
		const param = serializeFilterState(CLAUSES)

		expect(param).toMatch(/^[A-Za-z0-9_-]+$/)
		expect(deserializeFilterState(param)).toEqual({ clauses: CLAUSES, error: null })
	})

	it('writes the short keys and leaves out what is empty', () => {
		expect(payloadOf(serializeFilterState(CLAUSES.slice(2, 3).concat(CLAUSES.slice(4))))).toEqual([
			{ d: 'period.month', o: 'between', v: ['2026-01', '2026-03'], s: 'userExplicit', p: true },
			{ d: 'status', o: 'notNull', s: 'userExplicit' },
		])
	})

	it('keeps ten clauses under 2 KB', () => {
		const ten = Array.from({ length: 10 }, (_, i) => ({
			dimension: `dimension${i}`, operator: 'in', value: [`GM03${i}5`, `GM03${i}6`], source: 'widgetClick', sourceWidgetId: `w-widget-${i}`,
		}))

		expect(serializeFilterState(ten).length).toBeLessThan(2048)
	})

	it('has no parameter for no clauses, and no parameter reads as no clauses', () => {
		expect(serializeFilterState([])).toBe('')
		expect(serializeFilterState(undefined)).toBe('')
		expect(deserializeFilterState('')).toEqual({ clauses: [], error: null })
		expect(deserializeFilterState(null)).toEqual({ clauses: [], error: null })
		expect(warn).not.toHaveBeenCalled()
	})

	it('a clause without a source comes back as URL bootstrap', () => {
		const param = btoa(JSON.stringify([{ d: 'status', o: 'eq', v: 'open' }])).replace(/=+$/, '')

		expect(deserializeFilterState(param).clauses).toEqual([
			{ dimension: 'status', operator: 'eq', value: 'open', source: 'urlBootstrap', sourceWidgetId: null, pinned: false },
		])
	})

	it.each([
		['not base64url', '%%%'],
		['not JSON', btoa('not json')],
		['not a list', btoa(JSON.stringify({ d: 'status' }))],
		['an unknown operator', btoa(JSON.stringify([{ d: 'status', o: 'like', v: 'x' }]))],
		['a clause without a value', btoa(JSON.stringify([{ d: 'status', o: 'eq' }]))],
		['an unknown source', btoa(JSON.stringify([{ d: 'status', o: 'eq', v: 1, s: 'hack' }]))],
	])('reads a parameter that is %s as no filter, with one warning', (_label, param) => {
		expect(deserializeFilterState(param.replace(/=+$/, ''))).toEqual({ clauses: [], error: 'malformed' })
		expect(warn).toHaveBeenCalledTimes(1)
	})
})
