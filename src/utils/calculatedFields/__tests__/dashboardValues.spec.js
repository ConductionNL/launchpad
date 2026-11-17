// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Dashboard-wide evaluation of calculated fields in dependency order
 * (tasks 13, 17, 18).
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */

import { describe, expect, it } from 'vitest'
import { dependentWidgets, evaluateDashboard } from '../dashboardValues.js'
import { parse } from '../parser.js'

/**
 * A widget with calculated fields parsed from expressions.
 *
 * @param {string} id - The widget id.
 * @param {Record<string, string>} fields - Name => expression.
 * @return {object}
 */
function widget(id, fields) {
	const names = Object.keys(fields)
	return {
		id,
		calculatedFields: names.map((name) => ({
			name,
			expression: fields[name],
			compiledExpression: parse(fields[name], { calculatedNames: names }),
		})),
	}
}

describe('evaluateDashboard', () => {
	it('evaluates each widget\'s fields on its own source data', () => {
		const result = evaluateDashboard(
			[widget('sales', { margin: '(revenue - cost) / revenue * 100' })],
			{ sales: { revenue: 150000, cost: 90000 } },
		)

		expect(result.values).toEqual({ sales: { margin: 40 } })
		expect(result.errors).toEqual({ sales: {} })
	})

	it('resolves a cross-widget reference to the other widget\'s current data', () => {
		const result = evaluateDashboard(
			[widget('a', { vsTarget: 'widget:target.target_revenue / revenue * 100' }), { id: 'target' }],
			{ a: { revenue: 150000 }, target: { target_revenue: 120000 } },
		)

		expect(result.values.a.vsTarget).toBe(80)
	})

	it('evaluates a field read by another widget before the reader, whatever the widget order', () => {
		const widgets = [
			widget('a', { doubled: 'widget:b.net * 2' }),
			widget('b', { net: 'revenue - refunds' }),
		]
		const result = evaluateDashboard(widgets, { b: { revenue: 100, refunds: 30 } })

		expect(result.values.b.net).toBe(70)
		expect(result.values.a.doubled).toBe(140)
	})

	it('chains calculated fields of the same widget', () => {
		const result = evaluateDashboard(
			[widget('w', { total: 'net * 2', net: 'revenue - cost' })],
			{ w: { revenue: 10, cost: 4 } },
		)

		expect(result.values.w).toEqual({ net: 6, total: 12 })
	})

	it('re-evaluates with the new data after a filter changed the source data', () => {
		const widgets = [widget('a', { share: 'part / widget:b.whole * 100' }), { id: 'b' }]

		const before = evaluateDashboard(widgets, { a: { part: 25 }, b: { whole: 100 } })
		const after = evaluateDashboard(widgets, { a: { part: 25 }, b: { whole: 50 } })

		expect(before.values.a.share).toBe(25)
		expect(after.values.a.share).toBe(50)
	})

	it('reports a failing field inline with its expression and keeps the others', () => {
		const result = evaluateDashboard(
			[widget('w', { ratio: 'a / b', sum: 'a + b' }), widget('v', { one: '1 + 1' })],
			{ w: { a: 1, b: 0 } },
		)

		expect(result.values.w.ratio).toBeNull()
		expect(result.errors.w.ratio).toMatchObject({ expression: 'a / b', inputs: { a: 1, b: 0 } })
		expect(typeof result.errors.w.ratio.error).toBe('string')
		expect(result.values.w.sum).toBe(1)
		expect(result.values.v.one).toBe(2)
		expect(result.errors.v).toEqual({})
	})

	it('propagates null to a reader of a failed field', () => {
		const result = evaluateDashboard(
			[widget('w', { ratio: 'a / b' }), widget('v', { scaled: 'widget:w.ratio * 10' })],
			{ w: { a: 1, b: 0 } },
		)

		expect(result.values.v.scaled).toBeNull()
		expect(result.errors.v).toEqual({})
	})

	it('marks every field of the widgets in a cycle and still evaluates the others', () => {
		const result = evaluateDashboard(
			[
				widget('a', { x: 'widget:b.y + 1' }),
				widget('b', { y: 'widget:a.x + 1' }),
				widget('c', { z: '2 * 3' }),
			],
			{},
		)

		expect(result.values.a.x).toBeNull()
		expect(result.values.b.y).toBeNull()
		expect(result.errors.a.x.error).toContain('cycle')
		expect(result.errors.b.y.expression).toBe('widget:a.x + 1')
		expect(result.values.c).toEqual({})
	})

	it('skips fields without a compiled expression and widgets without fields', () => {
		const result = evaluateDashboard(
			[{ id: 'w', calculatedFields: [{ name: 'draft', expression: 'a +' }] }, { id: 7 }, null],
			{},
		)

		expect(result.values).toEqual({ w: {}, 7: {} })
	})

	it('evaluates against a fixed clock when one is given', () => {
		const result = evaluateDashboard([widget('w', { day: 'today()' })], {}, { now: Date.UTC(2026, 4, 22, 15, 0, 0) })

		expect(result.values.w.day).toBe('2026-05-22T00:00:00Z')
	})
})

describe('dependentWidgets', () => {
	it('answers the changed widget and every widget reading it, directly or transitively', () => {
		const widgets = [
			widget('a', { x: 'widget:b.y + 1' }),
			widget('b', { y: 'widget:c.total' }),
			widget('c', { total: 'n' }),
			widget('d', { other: '1' }),
		]

		expect(dependentWidgets(widgets, 'c')).toEqual(['a', 'b', 'c'])
		expect(dependentWidgets(widgets, 'a')).toEqual(['a'])
		expect(dependentWidgets(widgets, 'd')).toEqual(['d'])
	})
})
