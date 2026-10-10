/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The in-widget filter hides rendered rows and nothing else
 * (dashboards-and-who-may-see-them REQ-DWMS-008).
 */

import { describe, expect, it } from 'vitest'
import { applyRowFilter, isRowWidget } from '../rowFilter.js'

function table() {
	const root = document.createElement('div')
	root.innerHTML = `
		<table>
			<thead><tr><th>Zaak</th></tr></thead>
			<tbody>
				<tr><td>Bouwvergunning Kerkstraat</td></tr>
				<tr><td>Kapvergunning Dorpsplein</td></tr>
				<tr><td>Bouwvergunning Molenweg</td></tr>
			</tbody>
		</table>`
	return root
}

function visible(root) {
	return [...root.querySelectorAll('tbody tr')]
		.filter((row) => !row.hasAttribute('data-lp-filtered-out'))
		.map((row) => row.textContent.trim())
}

describe('applyRowFilter', () => {
	it('keeps the rows whose text holds the term, case-insensitively', () => {
		const root = table()

		const counts = applyRowFilter(root, 'bouw')

		expect(counts).toEqual({ total: 3, shown: 2 })
		expect(visible(root)).toEqual(['Bouwvergunning Kerkstraat', 'Bouwvergunning Molenweg'])
		// The header row is not a data row and is never hidden.
		expect(root.querySelector('thead tr').hasAttribute('data-lp-filtered-out')).toBe(false)
	})

	it('shows every row again when the term is cleared', () => {
		const root = table()
		applyRowFilter(root, 'kap')

		expect(applyRowFilter(root, '  ')).toEqual({ total: 3, shown: 3 })
		expect(visible(root)).toHaveLength(3)
	})

	it('filters list items and only the innermost row of a nested list', () => {
		const root = document.createElement('div')
		root.innerHTML = '<ul><li>Team A<ul><li>Anna</li><li>Bram</li></ul></li><li>Carla</li></ul>'

		expect(applyRowFilter(root, 'bram')).toEqual({ total: 3, shown: 1 })
		expect(root.querySelectorAll('[data-lp-filtered-out]')).toHaveLength(2)
	})

	it('touches nothing but the marker attribute', () => {
		const root = table()
		const before = root.innerHTML

		applyRowFilter(root, 'kap')
		applyRowFilter(root, '')

		expect(root.innerHTML).toBe(before)
	})
})

describe('isRowWidget', () => {
	it('offers the filter on widgets that render rows only', () => {
		expect(isRowWidget({ widgetId: 'table' })).toBe(true)
		expect(isRowWidget({ widgetId: 'object-list' })).toBe(true)
		expect(isRowWidget({ widgetId: 'clock' })).toBe(false)
		expect(isRowWidget(null)).toBe(false)
	})
})
