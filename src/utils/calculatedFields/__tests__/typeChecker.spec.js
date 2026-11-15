// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

import { describe, expect, it } from 'vitest'
import { parse } from '../parser.js'
import { inferType, typeOfPath, typeOfProperty } from '../typeChecker.js'

const schema = {
	revenue: 'number',
	cost: 'integer',
	customerName: 'string',
	active: 'boolean',
	opened: { type: 'string', format: 'date-time' },
	closed: { type: 'string', format: 'date' },
	lines: 'array',
	notes: 'object',
}

const typeOf = (expression, options) => inferType(parse(expression, options), schema, options)
function refusal (expression, options) {
	try {
		typeOf(expression, options)
	} catch (e) {
		return e.detail
	}
	return null
}

describe('inferType (REQ-CALC-002)', () => {
	it.each([
		['revenue - cost', 'number'],
		['coalesce(null, revenue, 0)', 'number'],
		['revenue > cost', 'boolean'],
		['active and revenue > 0', 'boolean'],
		['not active', 'boolean'],
		['-revenue', 'number'],
		['if(revenue > 100, "High", "Low")', 'string'],
		['if(active, null, 1)', 'number'],
		['concat(customerName, revenue)', 'string'],
		['dateDiff(closed, opened, "days")', 'number'],
		['dateAdd(opened, 1, "days")', 'date'],
		['durationBetween(closed, opened)', 'duration'],
		['-durationBetween(closed, opened)', 'duration'],
		['durationFormat(durationBetween(closed, opened))', 'string'],
		['sum(lines)', 'number'],
		['count(lines)', 'number'],
		['case(revenue > 10, "a", "b")', 'string'],
		['ifNull(notes, 1)', 'any'],
		['null', 'null'],
		['coalesce(null, null)', 'null'],
		['toDate(customerName) > opened', 'boolean'],
		['notes + 1', 'number'],
		['unknownField * 2', 'number'],
	])('%s is %s', (expression, type) => {
		expect(typeOf(expression)).toBe(type)
	})

	it('refuses a binary operator on mismatched operands, naming both', () => {
		expect(refusal('revenue + customerName')).toEqual({
			error: 'typeError',
			message: "Cannot apply '+' to revenue (number) and customerName (string)",
			operands: ['revenue', 'customerName'],
		})
		expect(refusal('active and revenue').error).toBe('typeError')
		expect(refusal('customerName == revenue').operands).toEqual(['customerName', 'revenue'])
		expect(refusal('active < active').error).toBe('typeError')
		expect(refusal('-customerName').operands).toEqual(['customerName'])
		expect(refusal('not revenue').error).toBe('typeError')
	})

	it('refuses a function argument the signature does not take', () => {
		expect(refusal('round(revenue, "two")')).toEqual({
			error: 'functionSignature',
			name: 'round',
			argument: 2,
			message: "'round' argument 2 must be number, got \"two\" (string)",
		})
		expect(refusal('abs("hello")').name).toBe('abs')
		expect(refusal('upper(revenue)').argument).toBe(1)
		expect(refusal('count(revenue)').error).toBe('functionSignature')
		expect(refusal('case(revenue, 1, 2)').name).toBe('case')
		expect(refusal('sum(revenue, customerName)').argument).toBe(2)
	})

	it('refuses branches and coalesce values of different types', () => {
		expect(refusal('if(active, 1, "one")').error).toBe('typeError')
		expect(refusal('coalesce(revenue, customerName)').operands).toEqual(['revenue', 'customerName'])
		expect(refusal('if(revenue, 1, 2)').error).toBe('typeError')
	})

	it('uses the types of other calculated fields and other widgets', () => {
		const options = { calculatedNames: ['profit'], calculatedTypes: { profit: 'string' }, widgetSchemas: { w2: { total: 'number' } } }
		expect(refusal('profit + 1', options).operands).toEqual(['profit', '1'])
		expect(typeOf('widget:w2.total * 2', options)).toBe('number')
		expect(refusal('widget:w2.total + "x"', options).operands).toEqual(['widget:w2.total', '"x"'])
		expect(typeOf('widget:w3.anything', options)).toBe('any')
	})

	it('refuses a function it has no signature for', () => {
		expect(() => inferType({ type: 'call', name: 'nope', args: [] }, schema)).toThrow("Unknown function 'nope'")
	})
})

describe('schema types', () => {
	it('maps JSON-schema properties to calculated-field types', () => {
		expect(typeOfProperty({ type: ['null', 'integer'] })).toBe('number')
		expect(typeOfProperty({ type: 'string', format: 'duration' })).toBe('duration')
		expect(typeOfProperty('date')).toBe('date')
		expect(typeOfProperty(null)).toBe('any')
	})

	it('reads dotted paths in a nested schema, through arrays', () => {
		const nested = { properties: { order: { type: 'object', properties: { total: { type: 'number' } } }, lines: { type: 'array', items: { properties: { kind: { type: 'string' } } } } } }
		expect(typeOfPath(nested, 'order.total')).toBe('number')
		expect(typeOfPath(nested, 'lines.kind')).toBe('string')
		expect(typeOfPath(nested, 'order.missing')).toBe('any')
		expect(typeOfPath(null, 'x')).toBe('any')
	})
})
