/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * CommunitiesWidgetForm: the title and "Show only pinned"
 * (openspec/changes/engagement-communities REQ-COMM-003).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CommunitiesWidgetForm from '../CommunitiesWidgetForm.vue'

const stubs = {
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input :value="modelValue" />',
	},
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue', 'type'],
		emits: ['update:modelValue'],
		template: '<label><slot /></label>',
	},
}

describe('CommunitiesWidgetForm', () => {
	it('starts from the placement being edited', () => {
		const wrapper = mount(CommunitiesWidgetForm, {
			props: { editingWidget: { content: { title: 'Praat mee', onlyPinned: true } } },
			global: { stubs },
		})

		expect(wrapper.findComponent({ name: 'NcTextField' }).props('modelValue')).toBe('Praat mee')
		expect(wrapper.findComponent({ name: 'NcCheckboxRadioSwitch' }).props('modelValue')).toBe(true)
	})

	it('emits the whole content on each change', async () => {
		const wrapper = mount(CommunitiesWidgetForm, { global: { stubs } })

		wrapper.findComponent({ name: 'NcTextField' }).vm.$emit('update:modelValue', 'Communities')
		wrapper.findComponent({ name: 'NcCheckboxRadioSwitch' }).vm.$emit('update:modelValue', true)

		expect(wrapper.emitted('update:content')).toEqual([
			[{ title: 'Communities', onlyPinned: false }],
			[{ title: 'Communities', onlyPinned: true }],
		])
		expect(wrapper.vm.validate()).toEqual([])
	})
})
