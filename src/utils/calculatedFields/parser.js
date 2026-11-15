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
 * The token readers, tried in this order at each position.
 */
const READERS = [readWhitespace, readComparison, readPunctuation, readString, readNumber, readWord]

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
		const read = readAt(source, i)
		if (read.token) {
			tokens.push(read.token)
		}
		i = read.next
	}
	tokens.push({ kind: 'end', value: null, column: source.length + 1 })
	return tokens
}

/**
 * The token that starts at a position, tried reader by reader in order.
 *
 * @param {string} source - The expression.
 * @param {number} i - The 0-based position.
 * @return {{token: object|null, next: number}} The token (null for whitespace) and where the next one starts.
 * @throws {CalculatedFieldError} When no reader accepts the character.
 */
function readAt(source, i) {
	for (const reader of READERS) {
		const read = reader(source, i)
		if (read) {
			return read
		}
	}
	throw parseError(`Unexpected character '${source[i]}'`, i + 1)
}

/**
 * Whitespace: skipped.
 *
 * @param {string} source - The expression.
 * @param {number} i - The position.
 * @return {object|null}
 */
function readWhitespace(source, i) {
	return /\s/.test(source[i]) ? { token: null, next: i + 1 } : null
}

/**
 * A two-character comparison operator.
 *
 * @param {string} source - The expression.
 * @param {number} i - The position.
 * @return {object|null}
 */
function readComparison(source, i) {
	const two = source.slice(i, i + 2)
	return COMPARISON.includes(two) ? { token: { kind: 'op', value: two, column: i + 1 }, next: i + 2 } : null
}

/**
 * A one-character operator, parenthesis or comma.
 *
 * @param {string} source - The expression.
 * @param {number} i - The position.
 * @return {object|null}
 */
function readPunctuation(source, i) {
	const ch = source[i]
	if (!'+-*/%<>(),'.includes(ch)) {
		return null
	}
	return { token: { kind: '(),'.includes(ch) ? ch : 'op', value: ch, column: i + 1 }, next: i + 1 }
}

/**
 * A quoted string; a backslash takes the next character literally.
 *
 * @param {string} source - The expression.
 * @param {number} i - The position.
 * @return {object|null}
 * @throws {CalculatedFieldError} When the string is not closed.
 */
function readString(source, i) {
	const quote = source[i]
	if (quote !== '"' && quote !== "'") {
		return null
	}
	const { text, end } = scanString(source, i + 1, quote)
	if (end >= source.length) {
		throw parseError('Unterminated string', i + 1)
	}
	return { token: { kind: 'string', value: text, column: i + 1 }, next: end + 1 }
}

/**
 * The text of a string up to its closing quote.
 *
 * @param {string} source - The expression.
 * @param {number} start - The position after the opening quote.
 * @param {string} quote - The quote character.
 * @return {{text: string, end: number}} The text and the closing quote's position (source.length when unclosed).
 */
function scanString(source, start, quote) {
	let j = start
	let text = ''
	while (j < source.length && source[j] !== quote) {
		const escaped = source[j] === '\\' && j + 1 < source.length
		j += escaped ? 1 : 0
		text += source[j]
		j++
	}
	return { text, end: j }
}

/**
 * A number literal.
 *
 * @param {string} source - The expression.
 * @param {number} i - The position.
 * @return {object|null}
 */
function readNumber(source, i) {
	const number = /^(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/.exec(source.slice(i))
	return number ? { token: { kind: 'number', value: Number(number[0]), column: i + 1 }, next: i + number[0].length } : null
}

/**
 * A keyword or a name: letters, digits and underscores, an optional
 * `:source` part, and dotted parts.
 */
const WORD_PATTERN = new RegExp(String.raw`^[A-Za-z_][A-Za-z0-9_]*(?::[A-Za-z0-9_-]+)?(?:\.[A-Za-z_][A-Za-z0-9_]*)*`)

/**
 * A keyword or a (dotted, optionally source-prefixed) name.
 *
 * @param {string} source - The expression.
 * @param {number} i - The position.
 * @return {object|null}
 */
function readWord(source, i) {
	const word = WORD_PATTERN.exec(source.slice(i))
	if (!word) {
		return null
	}
	return { token: { kind: Object.hasOwn(KEYWORDS, word[0]) ? KEYWORDS[word[0]] : 'name', value: word[0], column: i + 1 }, next: i + word[0].length }
}

/**
 * Refuse a call to an unknown function or one this instance does not allow.
 *
 * @param {{value: string, column: number}} token - The function name token.
 * @param {Set<string>|null} allowed - The allowlist; null allows every built-in.
 * @return {void}
 * @throws {CalculatedFieldError} `unknownFunction` or `functionNotAllowed`.
 */
function assertCallable(token, allowed) {
	const name = token.value
	if (!isBuiltIn(name)) {
		throw new CalculatedFieldError({ error: 'unknownFunction', name, message: `Unknown function '${name}'`, column: token.column })
	}
	if (allowed !== null && !allowed.has(name)) {
		throw new CalculatedFieldError({ error: 'functionNotAllowed', name, message: `The function '${name}' is not allowed on this instance`, column: token.column })
	}
}

/**
 * Refuse a call with the wrong number of arguments.
 *
 * @param {{value: string, column: number}} token - The function name token.
 * @param {number} count - The number of arguments given.
 * @return {void}
 * @throws {CalculatedFieldError} `arityMismatch`.
 */
function assertArity(token, count) {
	const name = token.value
	const { min, max } = FUNCTIONS[name]
	if (count >= min && count <= max) {
		return
	}
	throw new CalculatedFieldError({
		error: 'arityMismatch',
		name,
		message: `'${name}' takes ${arityText(min, max)} argument(s), got ${count}`,
		column: token.column,
	})
}

/**
 * How many arguments a function takes, in words.
 *
 * @param {number} min - The fewest.
 * @param {number} max - The most; Infinity for no limit.
 * @return {string|number}
 */
function arityText(min, max) {
	if (max === Infinity) {
		return `at least ${min}`
	}
	return min === max ? min : `${min} to ${max}`
}

/**
 * Literal builders by token kind.
 */
const LITERALS = {
	number: (token) => ({ type: 'literal', valueType: 'number', value: token.value }),
	string: (token) => ({ type: 'literal', valueType: 'string', value: token.value }),
	true: () => ({ type: 'literal', valueType: 'boolean', value: true }),
	false: () => ({ type: 'literal', valueType: 'boolean', value: false }),
	null: () => ({ type: 'literal', valueType: 'null', value: null }),
}

/**
 * The literal a token stands for, or null when it is not a literal.
 *
 * @param {{kind: string, value: (string|number|null)}} token - The token.
 * @return {object|null}
 */
function literalOf(token) {
	return Object.hasOwn(LITERALS, token.kind) ? LITERALS[token.kind](token) : null
}

/**
 * The refusal for a token that cannot start an operand.
 *
 * @param {{kind: string, value: (string|number|null), column: number}} token - The token.
 * @return {CalculatedFieldError}
 */
function unexpected(token) {
	return parseError(token.kind === 'end' ? 'Unexpected end of expression' : `Unexpected '${token.value}'`, token.column)
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
 * The reference a dotted name stands for on the widget itself.
 *
 * @param {string} path - The name.
 * @param {Set<string>} calculatedNames - This widget's calculated field names.
 * @return {object} A `calculatedRef` or a `fieldRef` on `self`.
 */
function selfReference(path, calculatedNames) {
	return calculatedNames.has(path) ? { type: 'calculatedRef', name: path } : { type: 'fieldRef', source: 'self', path }
}

/**
 * The reference a `widget:<id>.<field>` name stands for.
 *
 * @param {{value: string, column: number}} token - The name token.
 * @param {Set<string>} calculatedNames - This widget's calculated field names.
 * @return {object} The reference.
 * @throws {CalculatedFieldError} When the field is missing.
 */
function widgetReference(token, calculatedNames) {
	const text = token.value
	const dot = text.indexOf('.')
	if (dot === -1) {
		throw parseError('A widget reference needs a field, e.g. widget:<id>.<field>', token.column)
	}
	const source = text.slice(0, dot)
	const path = text.slice(dot + 1)
	return source === 'widget:self' ? selfReference(path, calculatedNames) : { type: 'fieldRef', source, path }
}

/**
 * The reference a name token stands for.
 *
 * @param {{value: string, column: number}} token - The name token.
 * @param {Set<string>} calculatedNames - This widget's calculated field names.
 * @return {object} The reference.
 * @throws {CalculatedFieldError} On an unknown reference source.
 */
function referenceOf(token, calculatedNames) {
	const text = token.value
	if (text.startsWith('widget:')) {
		return widgetReference(token, calculatedNames)
	}
	if (text.includes(':')) {
		throw parseError(`Unknown reference '${text}'`, token.column)
	}
	return selfReference(text, calculatedNames)
}

/**
 * The operators of the tighter binary levels.
 */
const MULTIPLICATIVE = ['*', '/', '%']
const ADDITIVE = ['+', '-']

/**
 * A recursive-descent parser over one token list.
 */
class Parser {
	/**
	 * @param {Array<object>} tokens - The tokens, ending with `end`.
	 * @param {Set<string>} calculatedNames - This widget's calculated field names.
	 * @param {Set<string>|null} allowed - The function allowlist; null allows every built-in.
	 */
	constructor(tokens, calculatedNames, allowed) {
		this.tokens = tokens
		this.pos = 0
		this.calculatedNames = calculatedNames
		this.allowed = allowed
	}

	/**
	 * @return {object} The current token.
	 */
	peek() {
		return this.tokens[this.pos]
	}

	/**
	 * @return {object} The current token, consumed.
	 */
	next() {
		return this.tokens[this.pos++]
	}

	/**
	 * Consume a token of a kind, or refuse.
	 *
	 * @param {string} kind - The token kind.
	 * @param {string} what - How to name it in the refusal.
	 * @return {object} The token.
	 * @throws {CalculatedFieldError} When the current token is of another kind.
	 */
	expect(kind, what) {
		const token = this.peek()
		if (token.kind !== kind) {
			throw parseError(`Expected ${what}`, token.column)
		}
		return this.next()
	}

	/**
	 * @param {string[]} ops - Operators.
	 * @return {boolean} Whether the current token is one of them.
	 */
	isOp(ops) {
		const token = this.peek()
		return token.kind === 'op' && ops.includes(token.value)
	}

	/**
	 * Parse the whole token list.
	 *
	 * @return {object} The AST.
	 * @throws {CalculatedFieldError} When tokens remain after the expression.
	 */
	parseAll() {
		const ast = this.orExpr()
		const token = this.peek()
		if (token.kind !== 'end') {
			throw parseError(`Unexpected '${token.value}'`, token.column)
		}
		return ast
	}

	/**
	 * A left-associative chain of keyword operators (`and`, `or`).
	 *
	 * @param {string} kind - The keyword's token kind.
	 * @param {function(): object} operand - Parses one operand.
	 * @return {object} The AST.
	 */
	keywordChain(kind, operand) {
		let left = operand()
		while (this.peek().kind === kind) {
			this.next()
			left = { type: 'binary', op: kind, left, right: operand() }
		}
		return left
	}

	/**
	 * A left-associative chain of symbol operators.
	 *
	 * @param {string[]} ops - The operators of this level.
	 * @param {function(): object} operand - Parses one operand.
	 * @return {object} The AST.
	 */
	operatorChain(ops, operand) {
		let left = operand()
		while (this.isOp(ops)) {
			const op = this.next().value
			left = { type: 'binary', op, left, right: operand() }
		}
		return left
	}

	/**
	 * @return {object} An `or` expression; every sub-expression starts here.
	 */
	orExpr() {
		return this.keywordChain('or', () => this.andExpr())
	}

	/**
	 * @return {object} An `and` expression.
	 */
	andExpr() {
		return this.keywordChain('and', () => this.notExpr())
	}

	/**
	 * @return {object} A `not` expression.
	 */
	notExpr() {
		if (this.peek().kind !== 'not') {
			return this.comparison()
		}
		this.next()
		return { type: 'unary', op: 'not', operand: this.notExpr() }
	}

	/**
	 * @return {object} A comparison.
	 */
	comparison() {
		return this.operatorChain(COMPARISON, () => this.additive())
	}

	/**
	 * @return {object} A sum or difference.
	 */
	additive() {
		return this.operatorChain(ADDITIVE, () => this.multiplicative())
	}

	/**
	 * @return {object} A product, quotient or remainder.
	 */
	multiplicative() {
		return this.operatorChain(MULTIPLICATIVE, () => this.unary())
	}

	/**
	 * @return {object} A negation or a primary.
	 */
	unary() {
		if (!this.isOp(['-'])) {
			return this.primary()
		}
		this.next()
		return { type: 'unary', op: '-', operand: this.unary() }
	}

	/**
	 * @return {object} A literal, a parenthesised expression, an `if`, a call or a reference.
	 * @throws {CalculatedFieldError} When the token cannot start an operand.
	 */
	primary() {
		const token = this.next()
		return literalOf(token) ?? this.nonLiteral(token)
	}

	/**
	 * A primary that is not a literal.
	 *
	 * @param {object} token - Its first token, consumed.
	 * @return {object} The AST.
	 * @throws {CalculatedFieldError} When the token cannot start an operand.
	 */
	nonLiteral(token) {
		switch (token.kind) {
		case '(':
			return this.parenthesised()
		case 'if':
			return this.ifCall(token)
		case 'name':
			return this.peek().kind === '(' ? this.call(token) : referenceOf(token, this.calculatedNames)
		default:
			throw unexpected(token)
		}
	}

	/**
	 * @return {object} The expression inside parentheses, the `(` already consumed.
	 */
	parenthesised() {
		const inner = this.orExpr()
		this.expect(')', "')'")
		return inner
	}

	/**
	 * @return {Array<object>} A parenthesised, comma-separated argument list.
	 */
	args() {
		this.expect('(', "'('")
		const list = this.peek().kind === ')' ? [] : this.argList()
		this.expect(')', "')'")
		return list
	}

	/**
	 * @return {Array<object>} One or more comma-separated arguments.
	 */
	argList() {
		const list = [this.orExpr()]
		while (this.peek().kind === ',') {
			this.next()
			list.push(this.orExpr())
		}
		return list
	}

	/**
	 * @param {object} token - The function name token.
	 * @return {object} A `call` node.
	 * @throws {CalculatedFieldError} On an unknown or disallowed function or a wrong argument count.
	 */
	call(token) {
		assertCallable(token, this.allowed)
		const list = this.args()
		assertArity(token, list.length)
		return { type: 'call', name: token.value, args: list }
	}

	/**
	 * @param {object} token - The `if` token.
	 * @return {object} An `if` node.
	 * @throws {CalculatedFieldError} Unless it has exactly 3 arguments.
	 */
	ifCall(token) {
		const list = this.args()
		if (list.length !== 3) {
			throw new CalculatedFieldError({ error: 'arityMismatch', name: 'if', message: `'if' takes 3 arguments, got ${list.length}`, column: token.column })
		}
		return { type: 'if', cond: list[0], then: list[1], else: list[2] }
	}
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
	return new Parser(tokenize(String(expression ?? '')), calculatedNames, allowed).parseAll()
}

/**
 * The child nodes of an AST node.
 *
 * @param {object} node - The node.
 * @return {Array<object|undefined>} Its children, possibly undefined.
 */
function childrenOf(node) {
	return [node.left, node.right, node.operand, node.cond, node.then, node.else, ...(node.args || [])]
}

/**
 * Collect a node's references, then its children's.
 *
 * @param {object|undefined} node - The node.
 * @param {{fields: Array<object>, calculated: string[]}} found - Extended here.
 * @return {void}
 */
function collectReferences(node, found) {
	if (!node || typeof node !== 'object') {
		return
	}
	if (node.type === 'fieldRef') {
		found.fields.push({ source: node.source, path: node.path })
	} else if (node.type === 'calculatedRef') {
		found.calculated.push(node.name)
	}
	childrenOf(node).forEach((child) => collectReferences(child, found))
}

/**
 * Every reference an AST makes: widget-relative field refs and calculated refs.
 *
 * @param {object} ast - The AST.
 * @return {{fields: Array<{source: string, path: string}>, calculated: string[]}}
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
export function referencesOf(ast) {
	const found = { fields: [], calculated: [] }
	collectReferences(ast, found)
	return found
}
