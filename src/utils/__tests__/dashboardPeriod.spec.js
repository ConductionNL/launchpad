/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard period: read from and kept in the address, its start per
 * choice, and added to the query of a widget that names a period field
 * (dashboard-period-and-download REQ-DPD-002).
 *
 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */

import { describe, expect, it } from 'vitest'
import { addressWithPeriod, contentWithPeriod, namesPeriod, periodFieldOf, PERIODS, periodStart, readPeriod, withPeriod } from '../dashboardPeriod.js'

const NOW = new Date(2026, 10, 23, 14, 30)

describe('readPeriod and addressWithPeriod', () => {
	it('offers This month, Quarter and Year, This month first', () => {
		expect(PERIODS).toEqual(['month', 'quarter', 'year'])
	})

	it('reads the period from the address, This month otherwise', () => {
		expect(readPeriod('?period=quarter')).toBe('quarter')
		expect(readPeriod('?kiosk=1&period=year')).toBe('year')
		expect(readPeriod('?period=decade')).toBe('month')
		expect(readPeriod('')).toBe('month')
	})

	it('keeps the period in the address with the rest of the query and the hash', () => {
		expect(addressWithPeriod('https://nc.example/apps/launchpad/d/abc?edit=1#w', 'quarter')).toBe('/apps/launchpad/d/abc?edit=1&period=quarter#w')
		expect(addressWithPeriod('https://nc.example/apps/launchpad?period=year', 'month')).toBe('/apps/launchpad?period=month')
		expect(addressWithPeriod('https://nc.example/apps/launchpad', 'nonsense')).toBe('/apps/launchpad?period=month')
	})
})

describe('periodStart', () => {
	it('starts this month, this quarter and this year', () => {
		expect(periodStart('month', NOW)).toEqual(new Date(2026, 10, 1))
		expect(periodStart('quarter', NOW)).toEqual(new Date(2026, 9, 1))
		expect(periodStart('year', NOW)).toEqual(new Date(2026, 0, 1))
		expect(periodStart('quarter', new Date(2026, 2, 31))).toEqual(new Date(2026, 0, 1))
		expect(periodStart('quarter', new Date(2026, 3, 1))).toEqual(new Date(2026, 3, 1))
	})
})

describe('periodFieldOf and namesPeriod', () => {
	it('reads the period field from the source or the chart data source', () => {
		expect(periodFieldOf({ source: { register: 'r', periodField: 'startDate' } })).toBe('startDate')
		expect(periodFieldOf({ dataSource: { periodField: ' decidedOn ' } })).toBe('decidedOn')
		expect(periodFieldOf({ props: { dataSource: { periodField: 'x' } } })).toBe('x')
		expect(periodFieldOf({ source: 'manual' })).toBe('')
		expect(periodFieldOf({ dataSource: { periodField: '' } })).toBe('')
		expect(periodFieldOf(null)).toBe('')
	})

	it('offers the period only when a placement names a period field', () => {
		const dated = { content: { dataSource: { periodField: 'startDate' } } }
		const embed = { content: { url: 'https://app.powerbi.com/x' } }
		expect(namesPeriod([embed, dated])).toBe(true)
		expect(namesPeriod([embed])).toBe(false)
		expect(namesPeriod(null)).toBe(false)
	})
})

describe('withPeriod', () => {
	it('adds the field between the period start and now to the filter, keeping the rest', () => {
		const source = { register: 'zaken', schema: 'zaak', filter: { status: 'open' } }
		expect(withPeriod(source, 'startDate', 'quarter', NOW)).toEqual({
			register: 'zaken',
			schema: 'zaak',
			filter: { status: 'open', 'startDate[gte]': '2026-10-01', 'startDate[lte]': NOW.toISOString() },
		})
		expect(source.filter).toEqual({ status: 'open' })
	})

	it('leaves a source without a period field, and no source, as they are', () => {
		const source = { register: 'zaken' }
		expect(withPeriod(source, '', 'year', NOW)).toBe(source)
		expect(withPeriod(null, 'startDate', 'year', NOW)).toBeNull()
	})
})

describe('contentWithPeriod', () => {
	const range = { 'startDate[gte]': '2026-10-01', 'startDate[lte]': NOW.toISOString() }

	it('adds the period to the stat card\'s source', () => {
		const content = { label: 'Zaken', source: { register: 'zaken', schema: 'zaak', metric: 'count', filter: { status: 'open' }, periodField: 'startDate' } }
		expect(contentWithPeriod(content, 'quarter', NOW).source.filter).toEqual({ status: 'open', ...range })
		expect(content.source.filter).toEqual({ status: 'open' })
	})

	it('adds the period to the stats block\'s and the chart\'s data source', () => {
		const block = { title: 'Open', dataSource: { register: 'zaken', schema: 'zaak', periodField: 'startDate' } }
		expect(contentWithPeriod(block, 'quarter', NOW).dataSource.filter).toEqual(range)

		const nested = { props: { chartKind: 'bar', dataSource: { register: 'zaken', schema: 'zaak', periodField: 'startDate' } } }
		const dated = contentWithPeriod(nested, 'quarter', NOW)
		expect(dated.props.dataSource.filter).toEqual(range)
		expect(dated.props.chartKind).toBe('bar')
	})

	it('adds the period to the table, whose content is its own source', () => {
		const table = { register: 'zaken', schema: 'zaak', filter: { status: 'open' }, limit: 10, periodField: 'startDate' }
		const dated = contentWithPeriod(table, 'quarter', NOW)
		expect(dated.filter).toEqual({ status: 'open', ...range })
		expect(dated.limit).toBe(10)
		expect(periodFieldOf(table)).toBe('startDate')
		expect(namesPeriod([{ content: table }])).toBe(true)
	})

	it('leaves content without a period field, an unknown period and no content as they are', () => {
		const table = { register: 'zaken', schema: 'zaak', filter: {} }
		const embed = { url: 'https://app.powerbi.com/x' }
		expect(contentWithPeriod(table, 'quarter', NOW)).toBe(table)
		expect(contentWithPeriod(embed, 'year', NOW)).toBe(embed)
		expect(contentWithPeriod({ source: { periodField: 'startDate' } }, 'decade', NOW)).toEqual({ source: { periodField: 'startDate' } })
		expect(contentWithPeriod(null, 'year', NOW)).toBeNull()
	})
})
