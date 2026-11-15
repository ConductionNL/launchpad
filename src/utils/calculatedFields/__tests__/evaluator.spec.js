// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

import { describe, expect, it } from 'vitest'
import suite from '../../../../tests/fixtures/calculated-fields/shared-evaluator-test-suite.json'
import { evaluate, evaluateField, EvaluationError, readPath } from '../evaluator.js'
import { parse } from '../parser.js'

/**
 * Deep equality with numbers equal to within 1e-9 (REQ-CALC-005).
 *
 * @param {*} actual The value.
 * @param {*} expected The expected value.
 */
function expectClose(actual, expected) {
	if (typeof expected === 'number') {
		expect(typeof actual).toBe('number')
		expect(Math.abs(actual - expected)).toBeLessThanOrEqual(1e-9)
		return
	}
	expect(actual).toEqual(expected)
}

describe('the shared evaluator suite (REQ-CALC-005)', () => {
	it('holds at least 50 cases, errors included', () => {
		expect(suite.cases.length).toBeGreaterThanOrEqual(50)
		expect(suite.cases.some((c) => 'error' in c)).toBe(true)
	})

	for (const c of suite.cases) {
		it(c.name, () => {
			const names = Object.keys(c.context.calculated ?? {})
			expect(parse(c.expression, { calculatedNames: names })).toEqual(c.ast)
			if ('error' in c) {
				expect(() => evaluate(c.ast, c.context, { now: c.now })).toThrow(new EvaluationError(c.error))
				return
			}
			expectClose(evaluate(c.ast, c.context, { now: c.now }), c.expected)
		})
	}
})

describe('evaluate', () => {
	it('recomputes from new inputs without anything cached (REQ-CALC-003)', () => {
		const ast = parse('revenue - cost')
		expect(evaluate(ast, { row: { revenue: 100000, cost: 60000 } })).toBe(40000)
		expect(evaluate(ast, { row: { revenue: 110000, cost: 70000 } })).toBe(40000)
		expect(evaluate(ast, { row: { revenue: 120000, cost: 70000 } })).toBe(50000)
	})

	it('reads a cross-widget calculated value before the widget\'s data', () => {
		const ast = parse('widget:w2.total')
		expect(evaluate(ast, { widgets: { w2: { total: 1 } }, widgetCalculated: { w2: { total: 2 } } })).toBe(2)
		expect(evaluate(ast, { widgets: {} })).toBeNull()
	})

	it('refuses a value of the wrong kind at run time', () => {
		expect(() => evaluate(parse('a + 1'), { row: { a: 'x' } })).toThrow('+ needs a number')
		expect(() => evaluate(parse('a and true'), { row: { a: 1 } })).toThrow("'and' needs a boolean")
		expect(() => evaluate(parse('a < b'), { row: { a: 1, b: 'x' } })).toThrow("'<' needs two numbers, two strings or two dates")
		expect(() => evaluate({ type: 'call', name: 'nope', args: [] })).toThrow("unknown function 'nope'")
	})

	it('uses the current time when none is given', () => {
		const before = Date.now()
		const now = Date.parse(evaluate(parse('now()')))
		expect(now).toBeGreaterThanOrEqual(Math.floor(before / 1000) * 1000)
	})
})

describe('readPath', () => {
	it('reads through objects and lists, null when absent', () => {
		expect(readPath({ a: { b: 1 } }, 'a.b')).toBe(1)
		expect(readPath({ a: [{ b: 1 }, { c: 2 }, { b: [3, 4] }] }, 'a.b')).toEqual([1, null, 3, 4])
		expect(readPath({ a: 1 }, 'a.b')).toBeNull()
		expect(readPath(null, 'a')).toBeNull()
	})
})

describe('evaluateField (REQ-CALC-008)', () => {
	it('returns the value of a field that evaluates', () => {
		const field = { expression: 'a / b', compiledExpression: parse('a / b') }
		expect(evaluateField(field, { row: { a: 1, b: 4 } })).toEqual({ value: 0.25 })
	})

	it('turns a failure into an inline error with the expression and the inputs it read', () => {
		const field = { expression: 'a / b + widget:w2.x + rate', compiledExpression: parse('a / b + widget:w2.x + rate', { calculatedNames: ['rate'] }) }
		expect(evaluateField(field, { row: { a: 1, b: 0 }, widgets: { w2: { x: 3 } }, calculated: { rate: 2 } })).toEqual({
			error: 'division by zero',
			expression: 'a / b + widget:w2.x + rate',
			inputs: { a: 1, b: 0, 'widget:w2.x': 3, rate: 2 },
		})
	})

	it('reports a failure that is not an evaluation error without its message', () => {
		const field = { expression: 'x', compiledExpression: { type: 'bogus' } }
		expect(evaluateField(field, {})).toEqual({ error: 'unknown node \'bogus\'', expression: 'x', inputs: {} })
		expect(evaluateField({ compiledExpression: null }, {})).toEqual({ error: 'evaluation failed', expression: '', inputs: {} })
	})
})
