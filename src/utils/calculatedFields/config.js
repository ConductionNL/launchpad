// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Which built-in functions this instance lets a calculated field call
 * (REQ-CALC-009). By default every built-in; an administrator may narrow it.
 * Names that are not built-ins are dropped, so a stale setting can never
 * allow an unknown function. The parser refuses a call outside the list with
 * `unknownFunction` at parse time.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-009-allowlisted-function-set-with-named-signatures
 */

import { FUNCTIONS, isBuiltIn } from './functions.js'

/**
 * Every built-in function name, in a stable order.
 *
 * @type {readonly string[]}
 */
export const DEFAULT_ALLOWED_FUNCTIONS = Object.freeze(Object.keys(FUNCTIONS).sort())

/**
 * The functions an instance allows, from its setting.
 *
 * @param {{allowedFunctions?: string[]|string|null}|null} [setting] The instance setting: a list, a comma-separated string, or absent for all.
 * @return {string[]} The allowed function names.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-009-allowlisted-function-set-with-named-signatures
 */
export function allowedFunctions(setting = null) {
	const raw = setting?.allowedFunctions
	if (raw === undefined || raw === null) {
		return [...DEFAULT_ALLOWED_FUNCTIONS]
	}
	const names = Array.isArray(raw) ? raw : String(raw).split(',')
	return [...new Set(names.map((name) => String(name).trim()).filter(isBuiltIn))].sort()
}
