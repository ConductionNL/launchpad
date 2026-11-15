// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The calculated-field parser (widget-calculated-fields tasks 1, 2, 4, 5):
 * every node type, precedence, whitespace-independent ASTs, column-positioned
 * parse errors and the function allowlist.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-001-formula-parser-produces-deterministic-ast
 */

import { describe, expect, it } from 'vitest'
import { FUNCTIONS } from '../functions.js'
import { CalculatedFieldError, parse, referencesOf } from '../parser.js'

const field = (path, source = 'self') => ({ type: 'fieldRef', source, path })
const num = (value) => ({ type: 'literal', valueType: 'number', value })

/**
 * The refusal detail a parse throws.
 *
 * @param {Function} fn - The throwing call.
 * @return {object}
 */
function refusal(fn) {
	try {
		fn()
	} catch (e) {
		expect(e).toBeInstanceOf(CalculatedFieldError)
		return e.detail
	}
	throw new Error('expected a refusal')
}

describe('parse', () => {
	it('parses the spec\'s ratio into a binary * at the root', () => {
		expect(parse('(revenue - refunds) / revenue * 100')).toEqual({
			type: 'binary',
			op: '*',
			left: { type: 'binary', op: '/', left: { type: 'binary', op: '-', left: field('revenue'), right: field('refunds') }, right: field('revenue') },
			right: num(100),
		})
	})

	it('gives the same AST for spellings that differ only in whitespace', () => {
		expect(parse('( a + b )')).toEqual(parse('(a+b)'))
		expect(parse('  sum(a ,b)\n')).toEqual(parse('sum(a,b)'))
	})

	it('builds every literal kind', () => {
		expect(parse('1.5e2')).toEqual(num(150))
		expect(parse('"a \\"b\\""')).toEqual({ type: 'literal', valueType: 'string', value: 'a "b"' })
		expect(parse("'x'")).toEqual({ type: 'literal', valueType: 'string', value: 'x' })
		expect(parse('true')).toEqual({ type: 'literal', valueType: 'boolean', value: true })
		expect(parse('false')).toEqual({ type: 'literal', valueType: 'boolean', value: false })
		expect(parse('null')).toEqual({ type: 'literal', valueType: 'null', value: null })
	})

	it('resolves references to fields, other widgets and calculated fields', () => {
		expect(parse('order.total')).toEqual(field('order.total'))
		expect(parse('widget:abc123.count')).toEqual(field('count', 'widget:abc123'))
		expect(parse('margin', { calculatedNames: ['margin'] })).toEqual({ type: 'calculatedRef', name: 'margin' })
		expect(parse('widget:self.margin', { calculatedNames: ['margin'] })).toEqual({ type: 'calculatedRef', name: 'margin' })
		expect(parse('widget:self.revenue')).toEqual(field('revenue'))
	})

	it('follows the precedence: or < and < not < comparison < + < * < unary', () => {
		expect(parse('a or b and not c > 1 + 2 * -d')).toEqual({
			type: 'binary',
			op: 'or',
			left: field('a'),
			right: {
				type: 'binary',
				op: 'and',
				left: field('b'),
				right: {
					type: 'unary',
					op: 'not',
					operand: {
						type: 'binary',
						op: '>',
						left: field('c'),
						right: { type: 'binary', op: '+', left: num(1), right: { type: 'binary', op: '*', left: num(2), right: { type: 'unary', op: '-', operand: field('d') } } },
					},
				},
			},
		})
		expect(parse('a - b - c')).toEqual({ type: 'binary', op: '-', left: { type: 'binary', op: '-', left: field('a'), right: field('b') }, right: field('c') })
		expect(parse('a % b <= c != d').op).toBe('!=')
	})

	it('builds if and calls, with a variadic sum', () => {
		expect(parse('if(a > 0, "up", "down")')).toEqual({
			type: 'if',
			cond: { type: 'binary', op: '>', left: field('a'), right: num(0) },
			then: { type: 'literal', valueType: 'string', value: 'up' },
			else: { type: 'literal', valueType: 'string', value: 'down' },
		})
		expect(parse('sum(revenue, cost, profit)')).toEqual({ type: 'call', name: 'sum', args: [field('revenue'), field('cost'), field('profit')] })
		expect(parse('now()')).toEqual({ type: 'call', name: 'now', args: [] })
	})

	it('reports a parse error with its column', () => {
		expect(refusal(() => parse('(revenue - refunds'))).toEqual({ error: 'parseError', message: "Expected ')' at column 19", column: 19 })
		expect(refusal(() => parse('a + * b')).column).toBe(5)
		expect(refusal(() => parse('a b')).column).toBe(3)
		expect(refusal(() => parse('"open')).column).toBe(1)
		expect(refusal(() => parse('a # b')).column).toBe(3)
		expect(refusal(() => parse('')).message).toBe('Unexpected end of expression at column 1')
		expect(refusal(() => parse('widget:abc')).error).toBe('parseError')
		expect(refusal(() => parse('other:abc.x')).error).toBe('parseError')
	})

	it('refuses an unknown function, a disallowed one and a wrong argument count', () => {
		expect(refusal(() => parse('eval("revenue * 2")'))).toMatchObject({ error: 'unknownFunction', name: 'eval' })
		expect(refusal(() => parse('now()', { allowedFunctions: ['abs', 'round', 'sum'] }))).toMatchObject({ error: 'functionNotAllowed', name: 'now' })
		expect(parse('abs(x)', { allowedFunctions: ['abs'] }).name).toBe('abs')
		expect(refusal(() => parse('pow(2)'))).toMatchObject({ error: 'arityMismatch', name: 'pow' })
		expect(refusal(() => parse('now(1)'))).toMatchObject({ error: 'arityMismatch', name: 'now' })
		expect(refusal(() => parse('sum()')).message).toBe("'sum' takes at least 1 argument(s), got 0")
		expect(refusal(() => parse('if(a, b)'))).toMatchObject({ error: 'arityMismatch', name: 'if' })
	})

	it('allows exactly the documented built-in set', () => {
		expect(Object.keys(FUNCTIONS)).toHaveLength(39)
		for (const name of ['abs', 'countIf', 'dateDiff', 'durationFormat', 'concat', 'coalesce', 'toDate']) {
			expect(FUNCTIONS[name]).toBeDefined()
		}
	})

	it('lists the references an AST makes', () => {
		expect(referencesOf(parse('if(margin > 0, widget:b.x, revenue)', { calculatedNames: ['margin'] }))).toEqual({
			fields: [{ source: 'widget:b', path: 'x' }, { source: 'self', path: 'revenue' }],
			calculated: ['margin'],
		})
	})
})
