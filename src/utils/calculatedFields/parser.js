// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The calculated-field formula parser (REQ-CALC-001, REQ-CALC-009): an
 * expression becomes a canonical, JSON-serialisable AST, the same for any
 * spelling that differs only in whitespace. Refusals are thrown as a
 * `CalculatedFieldError` whose `detail` is the API's 400 body.
 *
 * Grammar, loosest first: `or`, `and`, `not`, comparison (`< <= > >= == !=`),
 * `+ -`, `* / %`, unary `-`, then a primary: a number, a quoted string,
 * `true`, `false`, `null`, a parenthesised expression, `if(cond, then, else)`,
 * a call `name(args…)`, or a reference. A reference is a dotted path
 * (`revenue`, `order.total`) on the widget itself, `widget:<id>.<path>` on
 * another widget, or the name of one of this widget's calculated fields.
 *
 * AST nodes: `literal {valueType, value}`, `fieldRef {source, path}`,
 * `calculatedRef {name}`, `binary {op, left, right}`, `unary {op, operand}`,
 * `if {cond, then, else}`, `call {name, args}`.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-001-formula-parser-produces-deterministic-ast
 */

import { FUNCTIONS, isBuiltIn } from './functions.js'

/**
 * A refused expression; `detail` is the 400 body (`error`, `message`, …).
 */
export class CalculatedFieldError extends Error {
	/**
	 * @param {object} detail - The refusal: `error` and `message`, plus `column`, `name` or `cycle`.
	 */
	constructor(detail) {
		super(detail.message)
		this.name = 'CalculatedFieldError'
		this.detail = detail
	}
}

const COMPARISON = ['<=', '>=', '==', '!=', '<', '>']
const KEYWORDS = { and: 'and', or: 'or', not: 'not', true: 'true', false: 'false', null: 'null', if: 'if' }

/**
 * Split an expression into tokens, each with its 1-based column.
 *
 * @param {string} source - The expression.
 * @return {Array<{kind: string, value: *, column: number}>}
 */
function tokenize(source) {
	const tokens = []
	let i = 0
	while (i < source.length) {
		const ch = source[i]
		const column = i + 1
		if (/\s/.test(ch)) {
			i++
			continue
		}
		const two = source.slice(i, i + 2)
		if (COMPARISON.includes(two)) {
			tokens.push({ kind: 'op', value: two, column })
			i += 2
			continue
		}
		if ('+-*/%<>(),'.includes(ch)) {
			tokens.push({ kind: ch === '(' || ch === ')' || ch === ',' ? ch : 'op', value: ch, column })
			i++
			continue
		}
		if (ch === '"' || ch === "'") {
			let j = i + 1
			let text = ''
			while (j < source.length && source[j] !== ch) {
				if (source[j] === '\\' && j + 1 < source.length) {
					j++
				}
				text += source[j]
				j++
			}
			if (j >= source.length) {
				throw parseError('Unterminated string', column)
			}
			tokens.push({ kind: 'string', value: text, column })
			i = j + 1
			continue
		}
		const number = /^(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/.exec(source.slice(i))
		if (number) {
			tokens.push({ kind: 'number', value: Number(number[0]), column })
			i += number[0].length
			continue
		}
		const word = /^[A-Za-z_][A-Za-z0-9_]*(?::[A-Za-z0-9_-]+)?(?:\.[A-Za-z_][A-Za-z0-9_]*)*/.exec(source.slice(i))
		if (word) {
			const keyword = KEYWORDS[word[0]]
			tokens.push(keyword ? { kind: keyword, value: word[0], column } : { kind: 'name', value: word[0], column })
			i += word[0].length
			continue
		}
		throw parseError(`Unexpected character '${ch}'`, column)
	}
	tokens.push({ kind: 'end', value: null, column: source.length + 1 })
	return tokens
}

/**
 * A parse refusal at a column.
 *
 * @param {string} message - What is wrong.
 * @param {number} column - The 1-based column.
 * @return {CalculatedFieldError}
 */
function parseError(message, column) {
	return new CalculatedFieldError({ error: 'parseError', message: `${message} at column ${column}`, column })
}

/**
 * Parse an expression into its AST.
 *
 * @param {string} expression - The formula.
 * @param {object} [options] - Parse options.
 * @param {string[]} [options.calculatedNames] - This widget's calculated field names (become `calculatedRef`).
 * @param {string[]|null} [options.allowedFunctions] - This instance's allowlist; null allows every built-in.
 * @return {object} The AST.
 * @throws {CalculatedFieldError} On a syntax error, an unknown or disallowed function, or a wrong number of arguments.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-001-formula-parser-produces-deterministic-ast
 */
export function parse(expression, options = {}) {
	const calculatedNames = new Set(options.calculatedNames || [])
	const allowed = Array.isArray(options.allowedFunctions) ? new Set(options.allowedFunctions) : null
	const tokens = tokenize(String(expression ?? ''))
	let pos = 0

	const peek = () => tokens[pos]
	const next = () => tokens[pos++]
	const expect = (kind, what) => {
		const token = peek()
		if (token.kind !== kind) {
			throw parseError(`Expected ${what}`, token.column)
		}
		return next()
	}
	const isOp = (...ops) => peek().kind === 'op' && ops.includes(peek().value)

	const binaryLevel = (operand, matches) => () => {
		let left = operand()
		while (matches()) {
			const op = next().value
			left = { type: 'binary', op, left, right: operand() }
		}
		return left
	}

	const reference = (token) => {
		const text = token.value
		if (text.startsWith('widget:')) {
			const dot = text.indexOf('.')
			if (dot === -1) {
				throw parseError('A widget reference needs a field, e.g. widget:<id>.<field>', token.column)
			}
			const source = text.slice(0, dot)
			const path = text.slice(dot + 1)
			if (source === 'widget:self') {
				return calculatedNames.has(path) ? { type: 'calculatedRef', name: path } : { type: 'fieldRef', source: 'self', path }
			}
			return { type: 'fieldRef', source, path }
		}
		if (text.includes(':')) {
			throw parseError(`Unknown reference '${text}'`, token.column)
		}
		return calculatedNames.has(text) ? { type: 'calculatedRef', name: text } : { type: 'fieldRef', source: 'self', path: text }
	}

	const args = () => {
		expect('(', "'('")
		const list = []
		if (peek().kind !== ')') {
			list.push(orExpr())
			while (peek().kind === ',') {
				next()
				list.push(orExpr())
			}
		}
		expect(')', "')'")
		return list
	}

	const call = (token) => {
		const name = token.value
		if (!isBuiltIn(name)) {
			throw new CalculatedFieldError({ error: 'unknownFunction', name, message: `Unknown function '${name}'`, column: token.column })
		}
		if (allowed !== null && !allowed.has(name)) {
			throw new CalculatedFieldError({ error: 'functionNotAllowed', name, message: `The function '${name}' is not allowed on this instance`, column: token.column })
		}
		const list = args()
		const { min, max } = FUNCTIONS[name]
		if (list.length < min || list.length > max) {
			throw new CalculatedFieldError({
				error: 'arityMismatch',
				name,
				message: `'${name}' takes ${max === Infinity ? `at least ${min}` : (min === max ? min : `${min} to ${max}`)} argument(s), got ${list.length}`,
				column: token.column,
			})
		}
		return { type: 'call', name, args: list }
	}

	const primary = () => {
		const token = next()
		switch (token.kind) {
		case 'number':
			return { type: 'literal', valueType: 'number', value: token.value }
		case 'string':
			return { type: 'literal', valueType: 'string', value: token.value }
		case 'true':
		case 'false':
			return { type: 'literal', valueType: 'boolean', value: token.kind === 'true' }
		case 'null':
			return { type: 'literal', valueType: 'null', value: null }
		case '(': {
			const inner = orExpr()
			expect(')', "')'")
			return inner
		}
		case 'if': {
			const list = args()
			if (list.length !== 3) {
				throw new CalculatedFieldError({ error: 'arityMismatch', name: 'if', message: `'if' takes 3 arguments, got ${list.length}`, column: token.column })
			}
			return { type: 'if', cond: list[0], then: list[1], else: list[2] }
		}
		case 'name':
			return peek().kind === '(' ? call(token) : reference(token)
		default:
			throw parseError(token.kind === 'end' ? 'Unexpected end of expression' : `Unexpected '${token.value}'`, token.column)
		}
	}

	const unary = () => {
		if (isOp('-')) {
			next()
			return { type: 'unary', op: '-', operand: unary() }
		}
		return primary()
	}
	const multiplicative = binaryLevel(unary, () => isOp('*', '/', '%'))
	const additive = binaryLevel(multiplicative, () => isOp('+', '-'))
	const comparison = binaryLevel(additive, () => isOp(...COMPARISON))
	const notExpr = () => {
		if (peek().kind === 'not') {
			next()
			return { type: 'unary', op: 'not', operand: notExpr() }
		}
		return comparison()
	}
	const andExpr = () => {
		let left = notExpr()
		while (peek().kind === 'and') {
			next()
			left = { type: 'binary', op: 'and', left, right: notExpr() }
		}
		return left
	}
	/**
	 * The loosest level, `or`; every sub-expression starts here.
	 *
	 * @return {object} The AST.
	 */
	function orExpr() {
		let left = andExpr()
		while (peek().kind === 'or') {
			next()
			left = { type: 'binary', op: 'or', left, right: andExpr() }
		}
		return left
	}

	const ast = orExpr()
	if (peek().kind !== 'end') {
		throw parseError(`Unexpected '${peek().value}'`, peek().column)
	}
	return ast
}

/**
 * Every reference an AST makes: widget-relative field refs and calculated refs.
 *
 * @param {object} ast - The AST.
 * @return {{fields: Array<{source: string, path: string}>, calculated: string[]}}
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
export function referencesOf(ast) {
	const fields = []
	const calculated = []
	const walk = (node) => {
		if (!node || typeof node !== 'object') {
			return
		}
		if (node.type === 'fieldRef') {
			fields.push({ source: node.source, path: node.path })
		} else if (node.type === 'calculatedRef') {
			calculated.push(node.name)
		}
		for (const child of [node.left, node.right, node.operand, node.cond, node.then, node.else, ...(node.args || [])]) {
			walk(child)
		}
	}
	walk(ast)
	return { fields, calculated }
}
