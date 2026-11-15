/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ColleagueActivityWidgetForm: lines to show and days back (REQ-DWMS-007),
 * in the protocol the add-widget modal speaks.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))
vi.mock('@nextcloud/vue', () => ({
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel', 'clearable'],
		emits: ['update:modelValue'],
		template:
			'<div class="select-stub" :data-label="inputLabel" :data-value="modelValue" />',
	},
}))

import ColleagueActivityWidgetForm from '../ColleagueActivityWidgetForm.vue'

describe('ColleagueActivityWidgetForm', () => {
	it('starts at ten lines over seven days, with labelled selects', () => {
		const selects = mount(ColleagueActivityWidgetForm).findAll('.select-stub')

		expect(selects.map((s) => s.attributes('data-label'))).toEqual(['Lines to show', 'Days back'])
		expect(selects.map((s) => s.attributes('data-value'))).toEqual(['10', '7'])
	})

	it('opens on the settings of the widget being edited, and replaces values it does not offer', () => {
		const wrapper = mount(ColleagueActivityWidgetForm, {
			props: { editingWidget: { content: { limit: 20, days: 3 } } },
		})

		expect(wrapper.findAll('.select-stub').map((s) => s.attributes('data-value'))).toEqual(['20', '7'])
	})

	it('emits both settings whenever one changes', async () => {
		const wrapper = mount(ColleagueActivityWidgetForm)
		const selects = wrapper.findAllComponents({ name: 'NcSelect' })

		await selects[1].vm.$emit('update:modelValue', 30)
		await selects[0].vm.$emit('update:modelValue', 999)

		expect(wrapper.emitted('update:content')).toEqual([[{ limit: 10, days: 30 }], [{ limit: 10, days: 30 }]])
		expect(wrapper.vm.validate()).toEqual([])
	})
})
