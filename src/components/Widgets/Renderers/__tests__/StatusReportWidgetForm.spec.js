/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * StatusReportWidgetForm: the body of work a status report is about
 * (REQ-DWMS-004), in the protocol the add-widget modal speaks.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))
vi.mock('@nextcloud/vue', () => ({
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label', 'type', 'helperText'],
		emits: ['update:modelValue'],
		template: '<input class="field-stub" :data-label="label" :value="modelValue">',
	},
}))

import StatusReportWidgetForm from '../StatusReportWidgetForm.vue'

describe('StatusReportWidgetForm', () => {
	it('opens on the widget being edited, closed values joined with commas', () => {
		const wrapper = mount(StatusReportWidgetForm, {
			props: { editingWidget: { content: { register: 'dossiq', schema: 'case', closedValues: ['done', 'closed'] } } },
		})

		expect(wrapper.find('[data-testid="status-report-form-register"]').element.value).toBe('dossiq')
		expect(wrapper.find('[data-testid="status-report-form-closedValues"]').element.value).toBe('done, closed')
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('refuses to save without a register and a schema', () => {
		const wrapper = mount(StatusReportWidgetForm)

		expect(wrapper.vm.validate()).toEqual(['A status report needs the register and the schema of the body of work.'])
	})

	it('emits the whole body of work on every change, closed values as a list', async () => {
		const wrapper = mount(StatusReportWidgetForm, { props: { editingWidget: { content: { register: 'dossiq' } } } })
		const fields = wrapper.findAllComponents({ name: 'NcTextField' })
		const byLabel = (label) => fields.find((field) => field.props('label') === label)

		await byLabel('Schema').vm.$emit('update:modelValue', ' case ')
		await byLabel('Values that mean closed').vm.$emit('update:modelValue', 'done, ,closed')

		expect(wrapper.emitted('update:content').at(-1)[0]).toEqual({
			title: '',
			register: 'dossiq',
			schema: 'case',
			deadline: '',
			statusProperty: '',
			closedValues: ['done', 'closed'],
			dueProperty: '',
		})
		expect(wrapper.vm.validate()).toEqual([])
	})
})
