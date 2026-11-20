// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The words for calculated-field error codes and the inline hover text
 * (widget-calculated-fields task 49).
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-49
 */

import { describe, expect, it, vi } from 'vitest'
import { calculatedFieldErrorText, calculatedFieldMessage } from '../errorText.js'

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => (key in vars ? vars[key] : `{${key}}`)),
}))

describe('calculatedFieldMessage', () => {
	it('has a sentence for every code the parser, checker, cycle detector and server answer', () => {
		for (const code of ['parseError', 'invalidExpression', 'typeError', 'cycleDetected', 'unknownFunction', 'functionNotAllowed', 'arityMismatch', 'functionSignature', 'duplicateField', 'downgraded_evaluation_mode']) {
			expect(calculatedFieldMessage(code)).toEqual(expect.any(String))
		}
		expect(calculatedFieldMessage('nope')).toBeNull()
	})
})

describe('calculatedFieldErrorText', () => {
	it('names the reason, the formula and the values read', () => {
		expect(calculatedFieldErrorText({ error: 'division by zero', expression: 'a / b', inputs: { a: 4, b: 0, c: null } }))
			.toBe('This value could not be calculated (division by zero).\nFormula: a / b\nValues read: a = 4, b = 0, c = empty')
	})

	it('uses the sentence of a known code', () => {
		expect(calculatedFieldErrorText({ error: 'cycleDetected', expression: 'x', inputs: {} }))
			.toBe('The formula refers back to itself through other fields.\nFormula: x')
	})
})
