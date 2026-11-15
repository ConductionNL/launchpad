// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Cycle detection across calculated fields and widgets (tasks 7, 9).
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */

import { describe, expect, it } from 'vitest'
import { assertAcyclic } from '../cycleDetector.js'
import { CalculatedFieldError, parse } from '../parser.js'

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
		calculatedFields: names.map((name) => ({ name, compiledExpression: parse(fields[name], { calculatedNames: names }) })),
	}
}

/**
 * The cycleDetected detail.
 *
 * @param {Array} widgets - The widgets.
 * @return {object}
 */
function cycle(widgets) {
	try {
		assertAcyclic(widgets)
	} catch (e) {
		expect(e).toBeInstanceOf(CalculatedFieldError)
		return e.detail
	}
	throw new Error('expected a cycle')
}

describe('assertAcyclic', () => {
	it('rejects a self-reference', () => {
		expect(cycle([widget('A', { x: 'widget:self.x + 1' })])).toMatchObject({ error: 'cycleDetected', cycle: ['A.x', 'A.x'], widgets: ['A'] })
	})

	it('rejects mutual and longer cycles inside a widget', () => {
		expect(cycle([widget('A', { x: 'y * 2', y: 'x / 2' })]).cycle).toEqual(['A.x', 'A.y', 'A.x'])
		expect(cycle([widget('A', { p: 'q', q: 'r', r: 'p + 1', s: 'revenue' })]).cycle).toEqual(['A.p', 'A.q', 'A.r', 'A.p'])
	})

	it('rejects two widgets that read each other, naming both', () => {
		const detail = cycle([widget('A', { a: 'widget:B.x' }), widget('B', { b: 'widget:A.y' })])

		expect(detail.error).toBe('cycleDetected')
		expect(detail.widgets).toEqual(['A', 'B'])
		expect(detail.message).toBe('Calculated fields depend on each other in a cycle: A -> B -> A')
	})

	it('accepts an acyclic graph and orders dependencies first', () => {
		const order = assertAcyclic([
			widget('A', { share: 'widget:B.total / revenue', pct: 'share * 100' }),
			widget('B', { total: 'sum(x, y)' }),
		])

		expect(order).toEqual(['B.total', 'A.share', 'A.pct'])
		expect(assertAcyclic([])).toEqual([])
		expect(assertAcyclic([widget('C', { z: 'widget:unknown.v' })])).toEqual(['C.z'])
	})
})
