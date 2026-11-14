// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The client-side calculated-field evaluator (REQ-CALC-003, REQ-CALC-005,
 * REQ-CALC-008): an AST evaluated on a row, deterministically. The PHP
 * evaluator mirrors it; the shared suite in
 * `src/utils/calculatedFields/shared-evaluator-test-suite.json` pins both.
 *
 * Values: numbers (IEEE 754), strings, booleans, null, lists (array fields),
 * dates (UTC ISO strings `YYYY-MM-DDTHH:MM:SSZ`) and durations (seconds).
 * Null propagates: an operator or function given null answers null, except
 * coalesce, ifNull, case and the list aggregates, which handle null
 * themselves; nulls inside a list are skipped. A runtime failure (division by
 * zero, an invalid date, a value of the wrong kind) throws an
 * `EvaluationError`; `evaluateField` turns it into the inline error the widget
 * shows, with the expression and the inputs it read.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
 */

import { referencesOf } from './parser.js'

/**
 * A calculated field that failed at run time.
 */
export class EvaluationError extends Error {
	/**
	 * @param {string} message What failed, e.g. `division by zero`.
	 */
	constructor(message) {
		super(message)
		this.name = 'EvaluationError'
	}
}

const DAY = 86400
const UNIT_SECONDS = { seconds: 1, minutes: 60, hours: 3600, days: DAY, weeks: 7 * DAY }

/**
 * Read a dotted path from a value; through a list it reads every element, so
 * `lines.amount` on rows with a `lines` array is the list of amounts.
 *
 * @param {unknown} value The row, or the list of rows.
 * @param {string} path The dotted path.
 * @return {unknown} The value, a list, or null when absent.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
export function readPath(value, path) {
	let current = value
	for (const part of String(path).split('.')) {
		if (Array.isArray(current)) {
			current = current.flatMap((item) => {
				const next = item === null || typeof item !== 'object' ? null : item[part]
				return next === undefined ? [null] : (Array.isArray(next) ? next : [next])
			})
		} else if (current !== null && typeof current === 'object') {
			current = Object.hasOwn(current, part) ? current[part] : null
		} else {
			return null
		}
	}
	return current === undefined ? null : current
}

/**
 * A number, or a runtime error naming the operation.
 *
 * @param {unknown} value The value.
 * @param {string} what The operation, for the message.
 * @return {number} The number.
 */
function num(value, what) {
	if (typeof value !== 'number' || !Number.isFinite(value)) {
		throw new EvaluationError(`${what} needs a number`)
	}
	return value
}

/**
 * A string, or a runtime error naming the operation.
 *
 * @param {unknown} value The value.
 * @param {string} what The operation.
 * @return {string} The string.
 */
function str(value, what) {
	if (typeof value !== 'string') {
		throw new EvaluationError(`${what} needs a string`)
	}
	return value
}

/**
 * A list, or a runtime error naming the operation.
 *
 * @param {unknown} value The value.
 * @param {string} what The operation.
 * @return {Array} The list.
 */
function list(value, what) {
	if (!Array.isArray(value)) {
		throw new EvaluationError(`${what} needs a list`)
	}
	return value
}

/**
 * A finite number result, or a runtime error.
 *
 * @param {number} value The result.
 * @return {number} The result.
 */
function finite(value) {
	if (!Number.isFinite(value)) {
		throw new EvaluationError('numeric overflow')
	}
	return value
}

/**
 * Epoch seconds of a date value.
 *
 * @param {unknown} value An ISO date string or epoch milliseconds.
 * @return {number} Epoch seconds.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
 */
export function dateSeconds(value) {
	const ms = typeof value === 'number' ? value : (typeof value === 'string' ? Date.parse(value) : NaN)
	if (!Number.isFinite(ms)) {
		throw new EvaluationError('invalid date')
	}
	return Math.floor(ms / 1000)
}

/**
 * The canonical date value of epoch seconds: `YYYY-MM-DDTHH:MM:SSZ`.
 *
 * @param {number} seconds Epoch seconds.
 * @return {string} The date.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
 */
export function isoDate(seconds) {
	return new Date(seconds * 1000).toISOString().replace(/\.\d{3}Z$/, 'Z')
}

/**
 * The text of a value, as concat and toString give it.
 *
 * @param {unknown} value The value.
 * @return {string} The text.
 */
function text(value) {
	if (typeof value === 'string') return value
	if (typeof value === 'number') return String(num(value, 'toString'))
	if (typeof value === 'boolean') return value ? 'true' : 'false'
	throw new EvaluationError('toString needs a scalar')
}

/**
 * Round half away from zero to `digits` decimals, as PHP's round does: the
 * product is first trimmed to 15 significant digits so 1.005 rounds to 1.01.
 *
 * @param {number} value The number.
 * @param {number} digits The decimals.
 * @return {number} The rounded number.
 */
function roundHalfAway(value, digits) {
	const factor = 10 ** Math.trunc(digits)
	const scaled = Number((Math.abs(value) * factor).toPrecision(15))
	const rounded = Math.round(scaled) / factor
	return finite(value < 0 ? -rounded : rounded)
}

/**
 * The numbers of the arguments of sum/avg/min/max, lists flattened and the
 * nulls inside them skipped.
 *
 * @param {Array} args The arguments.
 * @param {string} what The function.
 * @return {number[]} The numbers.
 */
function numbersOf(args, what) {
	return args.flatMap((arg) => (Array.isArray(arg) ? arg.filter((v) => v !== null) : [arg])).map((v) => num(v, what))
}

/**
 * Add calendar months to epoch seconds, clamping the day to the month's end.
 *
 * @param {number} seconds Epoch seconds.
 * @param {number} months Months to add (whole).
 * @return {number} Epoch seconds.
 */
function addMonths(seconds, months) {
	const d = new Date(seconds * 1000)
	const year = d.getUTCFullYear()
	const month = d.getUTCMonth() + Math.trunc(months)
	const lastDay = new Date(Date.UTC(year, month + 1, 0)).getUTCDate()
	const day = Math.min(d.getUTCDate(), lastDay)
	return Date.UTC(year, month, day, d.getUTCHours(), d.getUTCMinutes(), d.getUTCSeconds()) / 1000
}

/**
 * Whole calendar months from `start` to `end` (negative when end is earlier).
 *
 * @param {number} end Epoch seconds.
 * @param {number} start Epoch seconds.
 * @return {number} Months.
 */
function monthsBetween(end, start) {
	if (end < start) return -monthsBetween(start, end)
	const a = new Date(end * 1000)
	const b = new Date(start * 1000)
	let months = (a.getUTCFullYear() - b.getUTCFullYear()) * 12 + (a.getUTCMonth() - b.getUTCMonth())
	if (addMonths(start, months) > end) months -= 1
	return months
}

/**
 * A unit name of dateAdd / dateDiff, or a runtime error.
 *
 * @param {string} unit The unit.
 * @return {string} The unit.
 */
function unitOf(unit) {
	if (!Object.hasOwn(UNIT_SECONDS, unit) && unit !== 'months' && unit !== 'years') {
		throw new EvaluationError(`unknown unit '${unit}'`)
	}
	return unit
}

const PATTERN_TOKENS = /yyyy|MM|dd|HH|mm|ss/g

/**
 * Format epoch seconds with yyyy, MM, dd, HH, mm and ss (UTC).
 *
 * @param {number} seconds Epoch seconds.
 * @param {string} pattern The pattern.
 * @return {string} The formatted date.
 */
function formatPattern(seconds, pattern) {
	const iso = isoDate(seconds)
	const parts = { yyyy: iso.slice(0, 4), MM: iso.slice(5, 7), dd: iso.slice(8, 10), HH: iso.slice(11, 13), mm: iso.slice(14, 16), ss: iso.slice(17, 19) }
	return pattern.replace(PATTERN_TOKENS, (token) => parts[token])
}

/**
 * Parse a date written in a yyyy/MM/dd/HH/mm/ss pattern (UTC).
 *
 * @param {string} value The text.
 * @param {string} pattern The pattern.
 * @return {number} Epoch seconds.
 */
function parsePattern(value, pattern) {
	const order = []
	const source = pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(PATTERN_TOKENS, (token) => {
		order.push(token)
		return token === 'yyyy' ? '(\\d{4})' : '(\\d{2})'
	})
	const match = new RegExp(`^${source}$`).exec(value)
	if (!match) throw new EvaluationError('invalid date')
	const p = { yyyy: 1970, MM: 1, dd: 1, HH: 0, mm: 0, ss: 0 }
	order.forEach((token, i) => { p[token] = Number(match[i + 1]) })
	const ms = Date.UTC(p.yyyy, p.MM - 1, p.dd, p.HH, p.mm, p.ss)
	if (new Date(ms).getUTCDate() !== p.dd || new Date(ms).getUTCMonth() !== p.MM - 1) throw new EvaluationError('invalid date')
	return ms / 1000
}

/**
 * Format a duration in seconds with hh (total hours), mm and ss.
 *
 * @param {number} seconds The duration.
 * @param {string} pattern The pattern.
 * @return {string} The text, `-` first for a negative duration.
 */
function formatDuration(seconds, pattern) {
	const total = Math.round(Math.abs(seconds))
	const parts = {
		hh: String(Math.floor(total / 3600)).padStart(2, '0'),
		mm: String(Math.floor((total % 3600) / 60)).padStart(2, '0'),
		ss: String(total % 60).padStart(2, '0'),
	}
	return (seconds < 0 ? '-' : '') + pattern.replace(/hh|mm|ss/g, (token) => parts[token])
}

/**
 * The functions that handle null arguments themselves.
 */
const NULL_AWARE = new Set(['coalesce', 'ifNull', 'case', 'count', 'countIf', 'sumIf', 'avgIf'])

/**
 * Every built-in function on its evaluated arguments.
 */
const IMPLEMENTATIONS = {
	abs: ([x]) => Math.abs(num(x, 'abs')),
	round: ([x, d = 0]) => roundHalfAway(num(x, 'round'), num(d, 'round')),
	ceil: ([x]) => Math.ceil(num(x, 'ceil')),
	floor: ([x]) => Math.floor(num(x, 'floor')),
	min: (args) => { const n = numbersOf(args, 'min'); return n.length ? Math.min(...n) : null },
	max: (args) => { const n = numbersOf(args, 'max'); return n.length ? Math.max(...n) : null },
	pow: ([x, y]) => finite(num(x, 'pow') ** num(y, 'pow')),
	sqrt: ([x]) => {
		if (num(x, 'sqrt') < 0) throw new EvaluationError('square root of a negative number')
		return Math.sqrt(x)
	},
	log: ([x, base]) => {
		if (num(x, 'log') <= 0) throw new EvaluationError('logarithm of a number that is not positive')
		if (base === undefined) return Math.log(x)
		if (num(base, 'log') <= 0 || base === 1) throw new EvaluationError('invalid logarithm base')
		return Math.log(x) / Math.log(base)
	},
	sum: (args) => finite(numbersOf(args, 'sum').reduce((a, b) => a + b, 0)),
	avg: (args) => { const n = numbersOf(args, 'avg'); return n.length ? finite(n.reduce((a, b) => a + b, 0) / n.length) : null },
	count: ([items]) => (items === null ? 0 : list(items, 'count').filter((v) => v !== null).length),
	countIf: ([items, value]) => (items === null ? 0 : list(items, 'countIf').filter((v) => v !== null && v === value).length),
	sumIf: ([values, keys, value]) => matching(values, keys, value, 'sumIf').reduce((a, b) => a + b, 0),
	avgIf: ([values, keys, value]) => {
		const picked = matching(values, keys, value, 'avgIf')
		return picked.length ? finite(picked.reduce((a, b) => a + b, 0) / picked.length) : null
	},
	now: (_, ctx) => isoDate(Math.floor(ctx.now / 1000)),
	today: (_, ctx) => isoDate(Math.floor(ctx.now / 1000 / DAY) * DAY),
	dateDiff: ([end, start, unit = 'days']) => {
		const a = dateSeconds(end)
		const b = dateSeconds(start)
		const u = unitOf(str(unit, 'dateDiff'))
		if (u === 'months') return monthsBetween(a, b)
		if (u === 'years') return Math.trunc(monthsBetween(a, b) / 12)
		return Math.trunc((a - b) / UNIT_SECONDS[u])
	},
	dateAdd: ([date, amount, unit]) => {
		const s = dateSeconds(date)
		const n = Math.trunc(num(amount, 'dateAdd'))
		const u = unitOf(str(unit, 'dateAdd'))
		if (u === 'months') return isoDate(addMonths(s, n))
		if (u === 'years') return isoDate(addMonths(s, n * 12))
		return isoDate(s + n * UNIT_SECONDS[u])
	},
	formatDate: ([date, pattern]) => formatPattern(dateSeconds(date), str(pattern, 'formatDate')),
	parseDate: ([value, pattern]) => isoDate(pattern === undefined ? dateSeconds(str(value, 'parseDate')) : parsePattern(str(value, 'parseDate'), str(pattern, 'parseDate'))),
	durationBetween: ([end, start]) => dateSeconds(end) - dateSeconds(start),
	durationFormat: ([seconds, pattern = 'hh:mm:ss']) => formatDuration(num(seconds, 'durationFormat'), str(pattern, 'durationFormat')),
	concat: (args) => args.map(text).join(''),
	upper: ([s]) => str(s, 'upper').toUpperCase(),
	lower: ([s]) => str(s, 'lower').toLowerCase(),
	trim: ([s]) => str(s, 'trim').trim(),
	contains: ([s, part]) => str(s, 'contains').includes(str(part, 'contains')),
	startsWith: ([s, part]) => str(s, 'startsWith').startsWith(str(part, 'startsWith')),
	endsWith: ([s, part]) => str(s, 'endsWith').endsWith(str(part, 'endsWith')),
	replace: ([s, search, replacement]) => {
		if (str(search, 'replace') === '') return str(s, 'replace')
		return str(s, 'replace').split(search).join(str(replacement, 'replace'))
	},
	format: ([template, ...args]) => str(template, 'format').replace(/\{(\d+)\}/g, (whole, i) => (Number(i) < args.length ? text(args[Number(i)]) : whole)),
	coalesce: (args) => args.find((v) => v !== null) ?? null,
	ifNull: ([value, fallback]) => (value === null ? fallback : value),
	case: (args) => {
		for (let i = 0; i + 1 < args.length; i += 2) {
			if (args[i] === true) return args[i + 1]
		}
		return args.length % 2 === 1 ? args[args.length - 1] : null
	},
	toNumber: ([v]) => {
		if (typeof v === 'number') return num(v, 'toNumber')
		if (typeof v === 'boolean') return v ? 1 : 0
		if (typeof v === 'string' && v.trim() !== '' && Number.isFinite(Number(v.trim()))) return Number(v.trim())
		throw new EvaluationError('cannot convert to a number')
	},
	toString: ([v]) => text(v),
	toBool: ([v]) => {
		if (typeof v === 'boolean') return v
		if (typeof v === 'number') return v !== 0
		const word = typeof v === 'string' ? v.trim().toLowerCase() : null
		if (['true', '1', 'yes'].includes(word)) return true
		if (['false', '0', 'no', ''].includes(word)) return false
		throw new EvaluationError('cannot convert to a boolean')
	},
	toDate: ([v]) => isoDate(dateSeconds(v)),
}

/**
 * The numbers of `values` whose key in `keys` equals `value`, nulls skipped.
 *
 * @param {Array|null} values The values.
 * @param {Array|null} keys The keys, position by position.
 * @param {unknown} value The key to match.
 * @param {string} what The function.
 * @return {number[]} The matching numbers.
 */
function matching(values, keys, value, what) {
	if (values === null || keys === null) return []
	const v = list(values, what)
	const k = list(keys, what)
	const picked = []
	for (let i = 0; i < Math.min(v.length, k.length); i++) {
		if (k[i] !== null && k[i] === value && v[i] !== null) picked.push(num(v[i], what))
	}
	return picked
}

/**
 * Apply a binary operator to two evaluated, non-null operands.
 *
 * @param {string} op The operator.
 * @param {unknown} a The left value.
 * @param {unknown} b The right value.
 * @return {unknown} The result.
 */
function applyBinary(op, a, b) {
	switch (op) {
	case '+': return finite(num(a, '+') + num(b, '+'))
	case '-': return finite(num(a, '-') - num(b, '-'))
	case '*': return finite(num(a, '*') * num(b, '*'))
	case '/':
		if (num(b, '/') === 0) throw new EvaluationError('division by zero')
		return finite(num(a, '/') / b)
	case '%':
		if (num(b, '%') === 0) throw new EvaluationError('division by zero')
		return num(a, '%') % b
	case '==': return a === b
	case '!=': return a !== b
	default: {
		if (typeof a !== typeof b || (typeof a !== 'number' && typeof a !== 'string')) {
			throw new EvaluationError(`'${op}' needs two numbers, two strings or two dates`)
		}
		return { '<': a < b, '<=': a <= b, '>': a > b, '>=': a >= b }[op]
	}
	}
}

/**
 * A boolean operand, or a runtime error.
 *
 * @param {unknown} value The value.
 * @param {string} what The operator.
 * @return {boolean} The boolean.
 */
function bool(value, what) {
	if (typeof value !== 'boolean') throw new EvaluationError(`'${what}' needs a boolean`)
	return value
}

/**
 * Evaluate an AST on a row.
 *
 * @param {object} ast The compiled expression.
 * @param {{row?: object, calculated?: object, widgets?: object, widgetCalculated?: object}} [context] The row; this widget's calculated values; other widgets' data and calculated values by id.
 * @param {{now?: number}} [options] The current time in epoch ms (for now() and today()).
 * @return {unknown} The value.
 * @throws {EvaluationError} When the evaluation fails.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
 */
export function evaluate(ast, context = {}, options = {}) {
	const ctx = { now: Number.isFinite(options.now) ? options.now : Date.now() }
	const row = context.row ?? {}
	const calculated = context.calculated ?? {}
	const widgets = context.widgets ?? {}
	const widgetCalculated = context.widgetCalculated ?? {}

	const run = (node) => {
		switch (node.type) {
		case 'literal':
			return node.value
		case 'fieldRef': {
			if (node.source === 'self') return readPath(row, node.path)
			const id = node.source.slice('widget:'.length)
			if (widgetCalculated[id] && Object.hasOwn(widgetCalculated[id], node.path)) return widgetCalculated[id][node.path]
			return readPath(widgets[id] ?? null, node.path)
		}
		case 'calculatedRef':
			return Object.hasOwn(calculated, node.name) ? calculated[node.name] : null
		case 'unary': {
			const v = run(node.operand)
			if (v === null) return null
			return node.op === 'not' ? !bool(v, 'not') : -num(v, '-')
		}
		case 'binary': {
			const a = run(node.left)
			const b = run(node.right)
			if (a === null || b === null) return null
			if (node.op === 'and') return bool(a, 'and') && bool(b, 'and')
			if (node.op === 'or') return bool(a, 'or') || bool(b, 'or')
			return applyBinary(node.op, a, b)
		}
		case 'if': {
			const cond = run(node.cond)
			if (cond === null) return null
			return bool(cond, 'if') ? run(node.then) : run(node.else)
		}
		case 'call': {
			const impl = IMPLEMENTATIONS[node.name]
			if (!impl) throw new EvaluationError(`unknown function '${node.name}'`)
			const args = node.args.map(run)
			if (!NULL_AWARE.has(node.name) && args.some((v) => v === null)) return null
			const result = impl(args, ctx)
			return result === undefined ? null : result
		}
		default:
			throw new EvaluationError(`unknown node '${node.type}'`)
		}
	}
	return run(ast)
}

/**
 * Evaluate one calculated field for the widget, never throwing: a failure
 * becomes `{ error, expression, inputs }` so the cell shows an inline error
 * with the expression and the values it read, and every other field and
 * widget renders as usual (REQ-CALC-008).
 *
 * @param {{expression?: string, compiledExpression: object}} field The calculated field.
 * @param {object} [context] As for {@link evaluate}.
 * @param {object} [options] As for {@link evaluate}.
 * @return {{value: *}|{error: string, expression: string, inputs: object}} The value, or the error.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-008-inline-error-display-preserves-dashboard-usability
 */
export function evaluateField(field, context = {}, options = {}) {
	try {
		return { value: evaluate(field.compiledExpression, context, options) }
	} catch (e) {
		const inputs = {}
		const refs = field.compiledExpression ? referencesOf(field.compiledExpression) : { fields: [], calculated: [] }
		for (const ref of refs.fields) {
			const key = ref.source === 'self' ? ref.path : `${ref.source}.${ref.path}`
			try {
				inputs[key] = evaluate({ type: 'fieldRef', source: ref.source, path: ref.path }, context, options)
			} catch {
				inputs[key] = null
			}
		}
		for (const name of refs.calculated) {
			inputs[name] = Object.hasOwn(context.calculated ?? {}, name) ? context.calculated[name] : null
		}
		return {
			error: e instanceof EvaluationError ? e.message : 'evaluation failed',
			expression: field.expression ?? '',
			inputs,
		}
	}
}
