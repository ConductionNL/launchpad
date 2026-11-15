/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * filterRows: the pure filter behind a widget's row filter (REQ-DWMS-008).
 */

import { describe, expect, it } from 'vitest'
import { filterRows } from '../rowFilter.js'

const ROWS = [
	{ title: 'Bouwvergunning Dorpsstraat', reason: '3 open' },
	{ title: 'Kapvergunning', reason: 'Wacht op advies' },
	{ title: 'Melding', reason: null },
]

describe('filterRows', () => {
	it('keeps every row for an empty or blank term, as a new array', () => {
		const all = filterRows(ROWS, '   ', ['title'])
		expect(all).toEqual(ROWS)
		expect(all).not.toBe(ROWS)
	})

	it('matches case-insensitively on any named field, in the original order', () => {
		expect(filterRows(ROWS, 'VERGUNNING', ['title']).map((r) => r.title)).toEqual([
			'Bouwvergunning Dorpsstraat',
			'Kapvergunning',
		])
		expect(filterRows(ROWS, 'advies', ['title', 'reason']).map((r) => r.title)).toEqual(['Kapvergunning'])
	})

	it('skips missing values and never mutates the rows', () => {
		const before = JSON.stringify(ROWS)
		expect(filterRows(ROWS, 'null', ['reason'])).toEqual([])
		expect(JSON.stringify(ROWS)).toBe(before)
	})

	it('treats a missing list as empty', () => {
		expect(filterRows(undefined, 'x', ['title'])).toEqual([])
	})
})
