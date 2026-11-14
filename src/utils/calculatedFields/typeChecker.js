// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Type checking of a calculated field at save time (REQ-CALC-002): the type of
 * every sub-expression is inferred from the source fields' schema, and an
 * operator or function given arguments it does not take is refused with a
 * `CalculatedFieldError` (`typeError` naming the operands, or
 * `functionSignature` naming the function and the argument).
 *
 * Types: number, string, boolean, date, duration, null, list (an array field)
 * and any (a field whose type the schema does not say; checked at run time).
 * `null` fits every parameter: it propagates to a null result when evaluated.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
 */

import { describeNode } from './ast.js'
import { CalculatedFieldError } from './parser.js'

const NUM = ['number']
const STR = ['string']
const DATE = ['date']
const LIST = ['list']
const ANY = ['*']
const NUM_OR_LIST = ['number', 'list']

/**
 * Each function's parameter types (`params`, then `rest` for every further
 * argument) and its return type, or `unify` when it returns the common type
 * of the arguments `unify` picks.
 */
const SIGNATURES = Object.freeze({
	abs: { params: [NUM], returns: 'number' },
	round: { params: [NUM, NUM], returns: 'number' },
	ceil: { params: [NUM], returns: 'number' },
	floor: { params: [NUM], returns: 'number' },
	min: { params: [], rest: NUM_OR_LIST, returns: 'number' },
	max: { params: [], rest: NUM_OR_LIST, returns: 'number' },
	pow: { params: [NUM, NUM], returns: 'number' },
	sqrt: { params: [NUM], returns: 'number' },
	log: { params: [NUM, NUM], returns: 'number' },
	sum: { params: [], rest: NUM_OR_LIST, returns: 'number' },
	avg: { params: [], rest: NUM_OR_LIST, returns: 'number' },
	count: { params: [LIST], returns: 'number' },
	countIf: { params: [LIST, ANY], returns: 'number' },
	sumIf: { params: [LIST, LIST, ANY], returns: 'number' },
	avgIf: { params: [LIST, LIST, ANY], returns: 'number' },
	now: { params: [], returns: 'date' },
	today: { params: [], returns: 'date' },
	dateDiff: { params: [DATE, DATE, STR], returns: 'number' },
	dateAdd: { params: [DATE, NUM, STR], returns: 'date' },
	formatDate: { params: [DATE, STR], returns: 'string' },
	parseDate: { params: [STR, STR], returns: 'date' },
	durationBetween: { params: [DATE, DATE], returns: 'duration' },
	durationFormat: { params: [['duration', 'number'], STR], returns: 'string' },
	concat: { params: [], rest: ANY, returns: 'string' },
	upper: { params: [STR], returns: 'string' },
	lower: { params: [STR], returns: 'string' },
	trim: { params: [STR], returns: 'string' },
	contains: { params: [STR, STR], returns: 'boolean' },
	startsWith: { params: [STR, STR], returns: 'boolean' },
	endsWith: { params: [STR, STR], returns: 'boolean' },
	replace: { params: [STR, STR, STR], returns: 'string' },
	format: { params: [STR], rest: ANY, returns: 'string' },
	coalesce: { params: [], rest: ANY, unify: (types) => types },
	ifNull: { params: [ANY, ANY], unify: (types) => types },
	case: { params: [], rest: ANY, unify: caseValueTypes },
	toNumber: { params: [ANY], returns: 'number' },
	toString: { params: [ANY], returns: 'string' },
	toBool: { params: [ANY], returns: 'boolean' },
	toDate: { params: [ANY], returns: 'date' },
})

const ARITHMETIC = ['+', '-', '*', '/', '%']
const ORDERING = ['<', '<=', '>', '>=']
const ORDERABLE = ['number', 'string', 'date', 'duration']

/**
 * Whether an argument of `actual` type fits a parameter that takes `allowed`.
 *
 * @param {string} actual The argument's type.
 * @param {string[]} allowed The parameter's types (`*` for any).
 * @return {boolean} True when it fits.
 */
function fits(actual, allowed) {
	return actual === 'any' || actual === 'null' || allowed.includes('*') || allowed.includes(actual)
}

/**
 * The common type of several values (branches, coalesce arguments): `null`
 * when all are null, `any` when one is, else the one type they share.
 *
 * @param {string[]} types The types.
 * @param {AstNode[]} nodes The nodes, for the error message.
 * @return {string} The common type.
 * @throws {CalculatedFieldError} `typeError` when they differ.
 */
function unify(types, nodes) {
	const known = types.filter((type) => type !== 'null')
	if (known.length === 0) return 'null'
	if (known.includes('any')) return 'any'
	const first = known[0]
	const other = known.findIndex((type) => type !== first)
	if (other === -1) return first
	const names = nodes.filter((_, i) => types[i] !== 'null').map(describeNode)
	throw new CalculatedFieldError({
		error: 'typeError',
		message: `The values ${names[0]} (${first}) and ${names[other]} (${known[other]}) do not have the same type`,
		operands: [names[0], names[other]],
	})
}

/**
 * The value types of a `case(cond1, value1, cond2, value2, …, default?)`: every
 * second argument, plus a trailing default. The conditions must be boolean.
 *
 * @param {string[]} types The argument types.
 * @param {AstNode[]} args The arguments.
 * @return {{types: string[], nodes: AstNode[]}} The value types and nodes.
 * @throws {CalculatedFieldError} `functionSignature` for a non-boolean condition.
 */
function caseValueTypes(types, args) {
	const values = []
	const nodes = []
	for (let i = 0; i < types.length; i += 2) {
		if (i + 1 >= types.length) {
			values.push(types[i])
			nodes.push(args[i])
			break
		}
		if (!fits(types[i], ['boolean'])) {
			throw signatureError('case', i, args[i], types[i], ['boolean'])
		}
		values.push(types[i + 1])
		nodes.push(args[i + 1])
	}
	return { types: values, nodes }
}

/**
 * A refused function argument.
 *
 * @param {string} name The function.
 * @param {number} index The argument's position (0-based).
 * @param {AstNode} node The argument.
 * @param {string} actual Its type.
 * @param {string[]} allowed What the parameter takes.
 * @return {CalculatedFieldError} The error.
 */
function signatureError(name, index, node, actual, allowed) {
	return new CalculatedFieldError({
		error: 'functionSignature',
		name,
		argument: index + 1,
		message: `'${name}' argument ${index + 1} must be ${allowed.join(' or ')}, got ${describeNode(node)} (${actual})`,
	})
}

/**
 * The type of a schema property: number, string, boolean, date, duration,
 * list, or any when the schema does not say.
 *
 * @param {unknown} property A JSON-schema property, or a type name.
 * @return {string} The calculated-field type.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
 */
export function typeOfProperty(property) {
	const spec = typeof property === 'string' ? { type: property } : (property || {})
	const type = Array.isArray(spec.type) ? spec.type.find((t) => t !== 'null') : spec.type
	if (type === 'number' || type === 'integer') return 'number'
	if (type === 'boolean') return 'boolean'
	if (type === 'array') return 'list'
	if (type === 'date' || type === 'duration') return type
	if (type === 'string') {
		if (spec.format === 'date' || spec.format === 'date-time') return 'date'
		if (spec.format === 'duration') return 'duration'
		return 'string'
	}
	return 'any'
}

/**
 * The type of a dotted path in a field schema, which is either a flat map
 * (`{ revenue: 'number' }`) or a JSON schema with nested `properties`.
 *
 * @param {object|null} schema The field schema.
 * @param {string} path The dotted path.
 * @return {string} The type, or any when the schema does not know the path.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
 */
export function typeOfPath(schema, path) {
	if (!schema || typeof schema !== 'object') return 'any'
	if (!schema.properties && Object.hasOwn(schema, path)) {
		return typeOfProperty(schema[path])
	}
	let node = schema
	for (const part of path.split('.')) {
		const next = node?.properties?.[part] ?? (node?.type === 'array' ? node.items?.properties?.[part] : undefined)
		if (!next) return 'any'
		node = next
	}
	return typeOfProperty(node)
}

/**
 * The type of a binary expression.
 *
 * @param {object} node The binary node.
 * @param {string} left The left operand's type.
 * @param {string} right The right operand's type.
 * @return {string} The result type.
 * @throws {CalculatedFieldError} `typeError` naming both operands.
 */
function binaryType(node, left, right) {
	const refuse = () => new CalculatedFieldError({
		error: 'typeError',
		message: `Cannot apply '${node.op}' to ${describeNode(node.left)} (${left}) and ${describeNode(node.right)} (${right})`,
		operands: [describeNode(node.left), describeNode(node.right)],
	})
	const known = [left, right].filter((type) => type !== 'any' && type !== 'null')
	if (ARITHMETIC.includes(node.op)) {
		if (known.every((type) => type === 'number')) return 'number'
		throw refuse()
	}
	if (node.op === 'and' || node.op === 'or') {
		if (known.every((type) => type === 'boolean')) return 'boolean'
		throw refuse()
	}
	if (ORDERING.includes(node.op) && known.some((type) => !ORDERABLE.includes(type))) {
		throw refuse()
	}
	if (known.length === 2 && known[0] !== known[1]) throw refuse()
	return 'boolean'
}

/**
 * The type of a call, checking every argument against the signature.
 *
 * @param {object} node The call node.
 * @param {string[]} types The argument types.
 * @return {string} The return type.
 * @throws {CalculatedFieldError} `functionSignature` for an argument it does not take.
 */
function callType(node, types) {
	const signature = SIGNATURES[node.name]
	if (!signature) {
		throw new CalculatedFieldError({ error: 'unknownFunction', name: node.name, message: `Unknown function '${node.name}'` })
	}
	types.forEach((type, i) => {
		const allowed = i < signature.params.length ? signature.params[i] : signature.rest
		if (allowed && !fits(type, allowed)) {
			throw signatureError(node.name, i, node.args[i], type, allowed)
		}
	})
	if (!signature.unify) return signature.returns
	const picked = signature.unify(types, node.args)
	return Array.isArray(picked) ? unify(picked, node.args) : unify(picked.types, picked.nodes)
}

/**
 * Infer the type of an expression, refusing one whose operators or functions
 * get arguments they do not take.
 *
 * @param {AstNode} ast The parsed expression.
 * @param {object|null} fieldSchema The widget's source fields: a flat map or a JSON schema.
 * @param {{calculatedTypes?: object, widgetSchemas?: object}} [options] Types of this widget's other calculated fields, and the field schemas of other widgets by id.
 * @return {string} The expression's type.
 * @throws {CalculatedFieldError} `typeError`, `functionSignature` or `unknownFunction`.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
 */
export function inferType(ast, fieldSchema, options = {}) {
	const calculatedTypes = options.calculatedTypes || {}
	const widgetSchemas = options.widgetSchemas || {}
	const infer = (node) => {
		switch (node.type) {
		case 'literal':
			return node.valueType
		case 'fieldRef':
			return node.source === 'self'
				? typeOfPath(fieldSchema, node.path)
				: typeOfPath(widgetSchemas[node.source.slice('widget:'.length)], node.path)
		case 'calculatedRef':
			return calculatedTypes[node.name] || 'any'
		case 'unary': {
			const type = infer(node.operand)
			const allowed = node.op === 'not' ? ['boolean'] : ['number', 'duration']
			if (fits(type, allowed)) return node.op === 'not' ? 'boolean' : (type === 'duration' ? 'duration' : 'number')
			throw new CalculatedFieldError({
				error: 'typeError',
				message: `Cannot apply '${node.op}' to ${describeNode(node.operand)} (${type})`,
				operands: [describeNode(node.operand)],
			})
		}
		case 'binary':
			return binaryType(node, infer(node.left), infer(node.right))
		case 'if': {
			const cond = infer(node.cond)
			if (!fits(cond, ['boolean'])) {
				throw new CalculatedFieldError({
					error: 'typeError',
					message: `The condition ${describeNode(node.cond)} must be boolean, got ${cond}`,
					operands: [describeNode(node.cond)],
				})
			}
			return unify([infer(node.then), infer(node.else)], [node.then, node.else])
		}
		case 'call':
			return callType(node, node.args.map(infer))
		default:
			throw new CalculatedFieldError({ error: 'typeError', message: `Unknown node '${node.type}'` })
		}
	}
	return infer(ast)
}
