/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A placed widget shows its calculated fields beside its own data: the
 * value formatted for display only, or an error icon whose hover text names
 * what went wrong, the formula and the values it read (REQ-CALC-008,
 * REQ-CALC-010).
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-19
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import CalculatedValues from '../CalculatedValues.vue'
import { useCalculatedFieldsStore } from '../../../../stores/calculatedFields.js'
import { parse } from '../../../../utils/calculatedFields/parser.js'

vi.mock('@nextcloud/l10n', () => ({
	getLanguage: () => 'nl-NL',
	translate: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => (key in vars ? vars[key] : `{${key}}`)),
}))

/**
 * A placement with calculated fields compiled from their expressions.
 *
 * @param {string} id The placement id.
 * @param {Array<object>} fields The fields: `{name, expression, label?, returnType?, formatOptions?}`.
 * @return {object}
 */
function placement(id, fields) {
	const names = fields.map((field) => field.name)
	return {
		id,
		calculatedFields: fields.map((field) => ({ ...field, compiledExpression: parse(field.expression, { calculatedNames: names }) })),
	}
}

beforeEach(() => {
	setActivePinia(createPinia())
})

describe('CalculatedValues', () => {
	it('shows each field with its value formatted at display time, and keeps the native value', async () => {
		const place = placement('7', [{ name: 'share', label: 'Share closed', expression: 'closed / total', returnType: 'number', formatOptions: { style: 'percent', maximumFractionDigits: 0 } }])
		const store = useCalculatedFieldsStore()
		store.setWidgets([place])
		store.setWidgetData('7', { closed: 3, total: 4 })
		const wrapper = mount(CalculatedValues, { props: { placement: place } })
		await nextTick()

		expect(wrapper.find('dt').text()).toBe('Share closed')
		expect(wrapper.find('[data-testid="calculated-value"]').text()).toBe('75%')
		expect(store.valuesOf('7').share).toBe(0.75)
	})

	it('shows an error icon naming the formula and the values read, and the other fields still render', async () => {
		const place = placement('8', [
			{ name: 'ratio', expression: 'a / b' },
			{ name: 'sum', expression: 'a + b' },
		])
		const store = useCalculatedFieldsStore()
		store.setWidgets([place])
		store.setWidgetData('8', { a: 4, b: 0 })
		const wrapper = mount(CalculatedValues, { props: { placement: place } })
		await nextTick()

		const error = wrapper.find('[data-field="ratio"] [data-testid="calculated-value-error"]')
		expect(error.exists()).toBe(true)
		expect(error.attributes('title')).toContain('Formula: a / b')
		expect(error.attributes('title')).toContain('b = 0')
		expect(wrapper.find('[data-field="sum"] [data-testid="calculated-value"]').text()).toBe('4')
	})

	it('renders nothing for a widget without calculated fields', () => {
		const wrapper = mount(CalculatedValues, { props: { placement: { id: '9' } } })
		expect(wrapper.find('[data-testid="calculated-values"]').exists()).toBe(false)
	})
})
