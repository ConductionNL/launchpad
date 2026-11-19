/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ActivityReportWidgetForm: the period the report opens on (REQ-DWMS-006),
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

import ActivityReportWidgetForm from '../ActivityReportWidgetForm.vue'

describe('ActivityReportWidgetForm', () => {
	it('starts at thirty days, with a labelled select', () => {
		const selects = mount(ActivityReportWidgetForm).findAll('.select-stub')

		expect(selects.map((s) => s.attributes('data-label'))).toEqual(['Days shown at first'])
		expect(selects.map((s) => s.attributes('data-value'))).toEqual(['30'])
	})

	it('opens on the widget being edited, and replaces a value it does not offer', () => {
		expect(mount(ActivityReportWidgetForm, { props: { editingWidget: { content: { days: 90 } } } })
			.find('.select-stub').attributes('data-value')).toBe('90')
		expect(mount(ActivityReportWidgetForm, { props: { editingWidget: { content: { days: 12 } } } })
			.find('.select-stub').attributes('data-value')).toBe('30')
	})

	it('emits the chosen period, falling back for one it does not offer', async () => {
		const wrapper = mount(ActivityReportWidgetForm)
		const select = wrapper.findComponent({ name: 'NcSelect' })

		await select.vm.$emit('update:modelValue', 365)
		await select.vm.$emit('update:modelValue', 999)

		expect(wrapper.emitted('update:content')).toEqual([[{ days: 365 }], [{ days: 30 }]])
		expect(wrapper.vm.validate()).toEqual([])
	})
})
