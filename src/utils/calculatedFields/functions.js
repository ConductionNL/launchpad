// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The built-in functions a calculated field may call, with their arity
 * (REQ-CALC-009). The parser refuses any other name, and a call with too few
 * or too many arguments. `max: Infinity` marks a variadic function.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-009-allowlisted-function-set-with-named-signatures
 */

/**
 * Every built-in function, by name: its group and how many arguments it takes.
 *
 * @type {Readonly<Record<string, {group: string, min: number, max: number}>>}
 */
export const FUNCTIONS = Object.freeze({
	abs: { group: 'math', min: 1, max: 1 },
	round: { group: 'math', min: 1, max: 2 },
	ceil: { group: 'math', min: 1, max: 1 },
	floor: { group: 'math', min: 1, max: 1 },
	min: { group: 'math', min: 1, max: Infinity },
	max: { group: 'math', min: 1, max: Infinity },
	pow: { group: 'math', min: 2, max: 2 },
	sqrt: { group: 'math', min: 1, max: 1 },
	log: { group: 'math', min: 1, max: 2 },
	sum: { group: 'math', min: 1, max: Infinity },
	avg: { group: 'math', min: 1, max: Infinity },
	count: { group: 'aggregate', min: 1, max: 1 },
	countIf: { group: 'aggregate', min: 2, max: 2 },
	sumIf: { group: 'aggregate', min: 3, max: 3 },
	avgIf: { group: 'aggregate', min: 3, max: 3 },
	now: { group: 'date', min: 0, max: 0 },
	today: { group: 'date', min: 0, max: 0 },
	dateDiff: { group: 'date', min: 2, max: 3 },
	dateAdd: { group: 'date', min: 3, max: 3 },
	formatDate: { group: 'date', min: 2, max: 2 },
	parseDate: { group: 'date', min: 1, max: 2 },
	durationBetween: { group: 'duration', min: 2, max: 2 },
	durationFormat: { group: 'duration', min: 1, max: 2 },
	concat: { group: 'string', min: 1, max: Infinity },
	upper: { group: 'string', min: 1, max: 1 },
	lower: { group: 'string', min: 1, max: 1 },
	trim: { group: 'string', min: 1, max: 1 },
	contains: { group: 'string', min: 2, max: 2 },
	startsWith: { group: 'string', min: 2, max: 2 },
	endsWith: { group: 'string', min: 2, max: 2 },
	replace: { group: 'string', min: 3, max: 3 },
	format: { group: 'string', min: 1, max: Infinity },
	coalesce: { group: 'conditional', min: 1, max: Infinity },
	ifNull: { group: 'conditional', min: 2, max: 2 },
	case: { group: 'conditional', min: 2, max: Infinity },
	toNumber: { group: 'coercion', min: 1, max: 1 },
	toString: { group: 'coercion', min: 1, max: 1 },
	toBool: { group: 'coercion', min: 1, max: 1 },
	toDate: { group: 'coercion', min: 1, max: 1 },
})

/**
 * Whether a name is a built-in function at all.
 *
 * @param {string} name - The name.
 * @return {boolean}
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-009-allowlisted-function-set-with-named-signatures
 */
export function isBuiltIn(name) {
	return Object.hasOwn(FUNCTIONS, name)
}
