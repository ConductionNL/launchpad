// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The parser's case table (widget-calculated-fields task 41): every
 * operator, every literal kind, every built-in function at its minimum
 * arity, every arity bound refused, unknown functions refused, and malformed
 * input refused as a column-positioned parse error. Each case is one test.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-001-formula-parser-produces-deterministic-ast
 */

import { describe, expect, it } from 'vitest'
import { isAstNode } from '../ast.js'
import { FUNCTIONS } from '../functions.js'
import { CalculatedFieldError, parse } from '../parser.js'

const field = (path, source = 'self') => ({ type: 'fieldRef', source, path })

/**
 * The refusal detail a parse throws.
 *
 * @param {string} expression - The expression.
 * @param {object} options - Parse options.
 * @return {object}
 */
function refusal(expression, options = {}) {
	try {
		parse(expression, options)
	} catch (e) {
		expect(e).toBeInstanceOf(CalculatedFieldError)
		return e.detail
	}
	throw new Error(`expected '${expression}' to be refused`)
}

/**
 * A call of a function with n field arguments.
 *
 * @param {string} name - The function.
 * @param {number} n - The argument count.
 * @return {string}
 */
const callOf = (name, n) => `${name}(${Array.from({ length: n }, (_, i) => `f${i}`).join(', ')})`

const BINARY = ['+', '-', '*', '/', '%', '<', '<=', '>', '>=', '==', '!=', 'and', 'or']

describe('binary operators', () => {
	it.each(BINARY)('parses a %s b', (op) => {
		expect(parse(`a ${op} b`)).toEqual({ type: 'binary', op, left: field('a'), right: field('b') })
	})

	it.each(['+', '-', '*', '/', '%', 'and', 'or'])('associates a %s b %s c to the left', (op) => {
		expect(parse(`a ${op} b ${op} c`)).toEqual({
			type: 'binary',
			op,
			left: { type: 'binary', op, left: field('a'), right: field('b') },
			right: field('c'),
		})
	})
})

describe('unary operators', () => {
	it('parses negation', () => {
		expect(parse('-a')).toEqual({ type: 'unary', op: '-', operand: field('a') })
	})

	it('parses not', () => {
		expect(parse('not a')).toEqual({ type: 'unary', op: 'not', operand: field('a') })
	})

	it('parses a double negation', () => {
		expect(parse('--a')).toEqual({ type: 'unary', op: '-', operand: { type: 'unary', op: '-', operand: field('a') } })
	})

	it('binds negation tighter than multiplication', () => {
		expect(parse('-a * b')).toEqual({ type: 'binary', op: '*', left: { type: 'unary', op: '-', operand: field('a') }, right: field('b') })
	})
})

describe('literals', () => {
	it.each([
		['0', 0],
		['42', 42],
		['3.25', 3.25],
		['1e3', 1000],
		['1.5e2', 150],
		['2.5e-1', 0.25],
	])('parses the number %s', (text, value) => {
		expect(parse(text)).toEqual({ type: 'literal', valueType: 'number', value })
	})

	it.each([
		['"plain"', 'plain'],
		["'single'", 'single'],
		['""', ''],
		['"with space"', 'with space'],
		['"esc \\"q\\""', 'esc "q"'],
	])('parses the string %s', (text, value) => {
		expect(parse(text)).toEqual({ type: 'literal', valueType: 'string', value })
	})

	it.each([
		['true', 'boolean', true],
		['false', 'boolean', false],
		['null', 'null', null],
	])('parses the keyword %s', (text, valueType, value) => {
		expect(parse(text)).toEqual({ type: 'literal', valueType, value })
	})
})

describe('references', () => {
	it.each([
		['revenue', field('revenue')],
		['order.total', field('order.total')],
		['a.b.c', field('a.b.c')],
		['widget:w1.count', field('count', 'widget:w1')],
		['widget:self.revenue', field('revenue')],
	])('parses %s', (text, ast) => {
		expect(parse(text)).toEqual(ast)
	})

	it('reads a declared name as a calculated field', () => {
		expect(parse('margin * 2', { calculatedNames: ['margin'] }).left).toEqual({ type: 'calculatedRef', name: 'margin' })
	})
})

describe('built-in functions', () => {
	it.each(Object.keys(FUNCTIONS))('parses %s at its minimum arity into a well-formed call', (name) => {
		const n = name === 'if' ? 3 : FUNCTIONS[name].min
		const ast = parse(callOf(name, n))
		expect(isAstNode(ast)).toBe(true)
		if (name !== 'if') {
			expect(ast).toEqual({ type: 'call', name, args: Array.from({ length: n }, (_, i) => field(`f${i}`)) })
		}
	})

	it.each(Object.keys(FUNCTIONS).filter((name) => Number.isFinite(FUNCTIONS[name].max)))('refuses %s above its maximum arity', (name) => {
		expect(refusal(callOf(name, FUNCTIONS[name].max + 1))).toMatchObject({ error: 'arityMismatch', name })
	})

	it.each(Object.keys(FUNCTIONS).filter((name) => FUNCTIONS[name].min > 0))('refuses %s below its minimum arity', (name) => {
		expect(refusal(callOf(name, FUNCTIONS[name].min - 1))).toMatchObject({ error: 'arityMismatch', name })
	})

	it.each(['eval', 'exec', 'system', 'require', 'fetch', 'Math', 'constructor'])('refuses the unknown function %s', (name) => {
		expect(refusal(`${name}(a)`)).toMatchObject({ error: 'unknownFunction', name })
	})

	it.each(['sum', 'now', 'concat'])('refuses %s when the allowlist leaves it out', (name) => {
		expect(refusal(callOf(name, FUNCTIONS[name].min), { allowedFunctions: ['abs'] })).toMatchObject({ error: 'functionNotAllowed', name })
	})
})

describe('malformed input', () => {
	it.each([
		'',
		'   ',
		'(',
		')',
		'(a',
		'a)',
		'a +',
		'* a',
		'a b',
		'a ,b',
		'"open',
		"'open",
		'a # b',
		'a & b',
		'a | b',
		'a = b',
		'abs(a,',
		'abs(a b)',
		'widget:',
		'widget:abc',
		'other:abc.x',
		'1..2',
	])('refuses %j as a parse error with a column', (text) => {
		const detail = refusal(text)
		expect(detail.error).toBe('parseError')
		expect(detail.message).toMatch(/column \d+$/)
	})
})
