// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

import { describe, expect, it } from 'vitest'
import { describeNode, isAstNode } from '../ast.js'
import { allowedFunctions, DEFAULT_ALLOWED_FUNCTIONS } from '../config.js'
import { FUNCTIONS } from '../functions.js'
import { parse } from '../parser.js'
import { parseAndValidate } from '../validate.js'

function refusal (widget, options) {
	try {
		parseAndValidate(widget, options)
	} catch (e) {
		return e.detail
	}
	return null
}

describe('parseAndValidate', () => {
	const fieldSchema = { revenue: 'number', cost: 'number', name: 'string' }

	it('compiles every field and types it in dependency order', () => {
		const { fields, order } = parseAndValidate({
			id: 'w1',
			calculatedFields: [
				{ name: 'margin', expression: 'profit / revenue' },
				{ name: 'profit', expression: 'revenue - cost' },
			],
		}, { fieldSchema })
		expect(order).toEqual(['w1.profit', 'w1.margin'])
		expect(fields.map((f) => [f.name, f.returnType])).toEqual([['margin', 'number'], ['profit', 'number']])
		expect(fields[0].compiledExpression).toEqual(parse('profit / revenue', { calculatedNames: ['margin', 'profit'] }))
	})

	it('refuses a parse error, a type error and a cycle, naming the field', () => {
		expect(refusal({ id: 'w1', calculatedFields: [{ name: 'x', expression: 'revenue +' }] }, { fieldSchema })).toMatchObject({ error: 'parseError', field: 'x' })
		expect(refusal({ id: 'w1', calculatedFields: [{ name: 'x', expression: 'revenue + name' }] }, { fieldSchema })).toMatchObject({ error: 'typeError', field: 'x' })
		expect(refusal({ id: 'w1', calculatedFields: [{ name: 'a', expression: 'b + 1' }, { name: 'b', expression: 'a + 1' }] }, { fieldSchema })).toMatchObject({ error: 'cycleDetected' })
	})

	it('refuses a cycle across widgets', () => {
		const other = { id: 'w2', calculatedFields: [{ name: 'y', compiledExpression: parse('widget:w1.x') }] }
		expect(refusal({ id: 'w1', calculatedFields: [{ name: 'x', expression: 'widget:w2.y' }] }, { otherWidgets: [other] })).toMatchObject({ error: 'cycleDetected' })
	})

	it('refuses a function the instance does not allow, and a field declared twice', () => {
		expect(refusal({ id: 'w1', calculatedFields: [{ name: 'x', expression: 'sqrt(revenue)' }] }, { fieldSchema, setting: { allowedFunctions: ['abs'] } })).toMatchObject({ error: 'functionNotAllowed', field: 'x' })
		expect(refusal({ id: 'w1', calculatedFields: [{ name: 'x', expression: '1' }, { name: 'x', expression: '2' }] })).toMatchObject({ error: 'duplicateField', field: 'x' })
	})

	it('accepts a widget without calculated fields', () => {
		expect(parseAndValidate({ id: 7 })).toEqual({ fields: [], order: [] })
	})
})

describe('allowedFunctions (REQ-CALC-009)', () => {
	it('allows every built-in by default', () => {
		expect(allowedFunctions()).toEqual(Object.keys(FUNCTIONS).sort())
		expect(DEFAULT_ALLOWED_FUNCTIONS).toHaveLength(Object.keys(FUNCTIONS).length)
	})

	it('narrows to the listed built-ins and drops unknown names', () => {
		expect(allowedFunctions({ allowedFunctions: ['round', 'eval', ' abs ', 'round'] })).toEqual(['abs', 'round'])
		expect(allowedFunctions({ allowedFunctions: 'upper, lower,system' })).toEqual(['lower', 'upper'])
		expect(allowedFunctions({ allowedFunctions: [] })).toEqual([])
	})
})

describe('isAstNode', () => {
	it('accepts every node the parser produces', () => {
		for (const expression of ['1', '"s"', 'true', 'null', 'a.b', 'widget:w.x', 'c', '-a', 'not a', 'a + b', 'if(a, 1, 2)', 'round(a, 2)']) {
			expect(isAstNode(parse(expression, { calculatedNames: ['c'] })), expression).toBe(true)
		}
	})

	it('refuses malformed nodes', () => {
		expect(isAstNode(null)).toBe(false)
		expect(isAstNode([])).toBe(false)
		expect(isAstNode({ type: 'eval', code: 'x' })).toBe(false)
		expect(isAstNode({ type: 'literal', valueType: 'number', value: '1' })).toBe(false)
		expect(isAstNode({ type: 'literal', valueType: 'number', value: 1, extra: true })).toBe(false)
		expect(isAstNode({ type: 'binary', op: '**', left: parse('1'), right: parse('2') })).toBe(false)
		expect(isAstNode({ type: 'fieldRef', source: 'other', path: 'x' })).toBe(false)
		expect(isAstNode({ type: 'call', name: 'abs', args: [{ type: 'nope' }] })).toBe(false)
	})

	it('names nodes for error messages', () => {
		expect(describeNode(parse('widget:w.x'))).toBe('widget:w.x')
		expect(describeNode(parse('"s"'))).toBe('"s"')
		expect(describeNode(parse('abs(1)'))).toBe('abs(…)')
		expect(describeNode(null)).toBe('?')
	})
})
