/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * AttentionWidgetForm: the one setting, lines to show (REQ-ATT-004), in the
 * protocol the add-widget modal speaks (`editingWidget`, `value`,
 * `update:content`, `validate()`).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))
vi.mock('@nextcloud/vue', () => ({
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel', 'clearable'],
		emits: ['update:modelValue'],
		template: '<div class="select-stub" :data-label="inputLabel" :data-value="modelValue" />',
	},
}))

import AttentionWidgetForm from '../AttentionWidgetForm.vue'

describe('AttentionWidgetForm', () => {
	it('starts at five lines, with a labelled select', () => {
		const wrapper = mount(AttentionWidgetForm)

		const select = wrapper.find('.select-stub')
		expect(select.attributes('data-value')).toBe('5')
		expect(select.attributes('data-label')).toBe('Lines to show')
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('opens on the limit of the widget being edited', () => {
		const wrapper = mount(AttentionWidgetForm, { props: { editingWidget: { content: { limit: 8 } } } })

		expect(wrapper.find('.select-stub').attributes('data-value')).toBe('8')
	})

	it('falls back to five for a limit outside 1 to 10', () => {
		const wrapper = mount(AttentionWidgetForm, { props: { editingWidget: { content: { limit: 40 } } } })

		expect(wrapper.find('.select-stub').attributes('data-value')).toBe('5')
	})

	it('emits the content when the limit changes', async () => {
		const wrapper = mount(AttentionWidgetForm)

		await wrapper.findComponent({ name: 'NcSelect' }).vm.$emit('update:modelValue', 3)

		expect(wrapper.emitted('update:content')).toEqual([[{ limit: 3 }]])
		expect(wrapper.find('.select-stub').attributes('data-value')).toBe('3')
	})
})
