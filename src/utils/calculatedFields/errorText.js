// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The words a person reads for a calculated field's error codes and
 * warnings (REQ-CALC-008). The parser, type checker, cycle detector and the
 * server answer a code; the evaluator answers a short technical reason. This
 * turns either into a translated sentence.
 */

import { translate as t } from '@nextcloud/l10n'

/**
 * The translated sentence per error or warning code.
 *
 * @return {{[code: string]: string}}
 */
function messages() {
	return {
		parseError: t('launchpad', 'The formula cannot be read.'),
		invalidExpression: t('launchpad', 'The formula cannot be read.'),
		typeError: t('launchpad', 'The formula mixes values that do not go together, such as text and a number.'),
		cycleDetected: t('launchpad', 'The formula refers back to itself through other fields.'),
		unknownFunction: t('launchpad', 'The formula uses a function that does not exist.'),
		functionNotAllowed: t('launchpad', 'The formula uses a function this instance does not allow.'),
		arityMismatch: t('launchpad', 'A function in the formula has the wrong number of values.'),
		functionSignature: t('launchpad', 'A function in the formula is given the wrong kind of value.'),
		duplicateField: t('launchpad', 'Two calculated fields on this widget have the same name.'),
		downgraded_evaluation_mode: t('launchpad', 'This field is calculated in the browser only: it cannot be stored in the register.'),
	}
}

/**
 * The sentence for an error or warning code, or null for a code it does not know.
 *
 * @param {string} code The code.
 * @return {string|null}
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-49
 */
export function calculatedFieldMessage(code) {
	return messages()[code] ?? null
}

/**
 * The hover text of a field that could not be calculated: what went wrong,
 * the formula, and the values it read.
 *
 * @param {{error?: string, expression?: string, inputs?: object}} failure The evaluator's error envelope.
 * @return {string}
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-49
 */
export function calculatedFieldErrorText(failure) {
	const reason = calculatedFieldMessage(failure?.error) ?? t('launchpad', 'This value could not be calculated ({reason}).', { reason: failure?.error || '?' })
	const inputs = Object.entries(failure?.inputs || {})
		.map(([name, value]) => `${name} = ${value === null || value === undefined ? t('launchpad', 'empty') : JSON.stringify(value)}`)
		.join(', ')
	const lines = [reason, t('launchpad', 'Formula: {expression}', { expression: failure?.expression || '' })]
	if (inputs !== '') {
		lines.push(t('launchpad', 'Values read: {inputs}', { inputs }))
	}
	return lines.join('\n')
}
