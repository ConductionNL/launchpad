/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * DetailsTab (REQ-MDUI-002): one control per field type, values sent in
 * the shape MetadataValidationService accepts (number, date string,
 * select string, multi-select list, boolean). Definitions are
 * MetadataField::jsonSerialize rows from `/api/metadata-fields`; stored
 * values come back as strings (multi-select JSON-encoded).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DetailsTab from '../DetailsTab.vue'
import { api } from '../../../../services/api.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('../../../../services/api.js', () => ({
	api: {
		getMetadataFieldDefinitions: vi.fn(),
		getDashboardMetadata: vi.fn(),
		updateDashboardMetadata: vi.fn(),
	},
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcButton: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template:
			'<input class="text" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
	},
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel', 'multiple'],
		emits: ['update:modelValue'],
		template: '<div class="select" :data-label="inputLabel" />',
	},
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template:
			'<input type="checkbox" class="bool" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)">',
	},
}))

const fields = [
	{ id: 1, key: 'owner', label: 'Owner', type: 'text', options: [] },
	{ id: 2, key: 'budget', label: 'Budget', type: 'number', options: [] },
	{ id: 3, key: 'review', label: 'Review date', type: 'date', options: [] },
	{
		id: 4,
		key: 'department',
		label: 'Department',
		type: 'select',
		options: ['HR', 'Finance'],
	},
	{
		id: 5,
		key: 'audience',
		label: 'Audience',
		type: 'multi-select',
		options: ['Staff', 'Guests'],
	},
	{ id: 6, key: 'public', label: 'Public', type: 'boolean', options: [] },
]

beforeEach(() => vi.clearAllMocks())

describe('DetailsTab', () => {
	it('REQ-MDUI-002: shows one control per field and saves the values in the service shapes', async () => {
		api.getMetadataFieldDefinitions.mockResolvedValue({ data: { fields } })
		api.getDashboardMetadata.mockResolvedValue({
			data: { department: 'HR', audience: '["Staff"]', public: '1' },
		})
		api.updateDashboardMetadata.mockResolvedValue({ data: {} })
		const wrapper = mount(DetailsTab, { props: { dashboardUuid: 'payroll' } })
		await flushPromises()
		expect(wrapper.find('input.text').exists()).toBe(true)
		expect(wrapper.find('input[type="number"]').exists()).toBe(true)
		expect(wrapper.find('input[type="date"]').exists()).toBe(true)
		expect(
			wrapper.findAll('.select').map((s) => s.attributes('data-label')),
		).toEqual(['Department', 'Audience'])
		expect(wrapper.find('input.bool').element.checked).toBe(true)

		wrapper
			.findAllComponents({ name: 'NcSelect' })[0]
			.vm.$emit('update:modelValue', 'Finance')
		await wrapper.find('input[type="number"]').setValue('1200')
		await wrapper.find('input[type="date"]').setValue('2026-10-01')
		await wrapper.find('[data-testid="details-save"]').trigger('click')
		await flushPromises()
		expect(api.updateDashboardMetadata).toHaveBeenCalledWith('payroll', {
			owner: '',
			budget: 1200,
			review: '2026-10-01',
			department: 'Finance',
			audience: ['Staff'],
			public: true,
		})
		expect(wrapper.find('[data-testid="details-saved"]').exists()).toBe(true)
	})

	it('shows the service message when a value is refused', async () => {
		api.getMetadataFieldDefinitions.mockResolvedValue({
			data: { fields: [fields[3]] },
		})
		api.getDashboardMetadata.mockResolvedValue({ data: {} })
		api.updateDashboardMetadata.mockRejectedValue(
			Object.assign(new Error('x'), {
				response: {
					status: 400,
					data: {
						error: 'invalid_metadata_field',
						message: 'Value is not one of the options',
					},
				},
			}),
		)
		const wrapper = mount(DetailsTab, { props: { dashboardUuid: 'payroll' } })
		await flushPromises()
		await wrapper.find('[data-testid="details-save"]').trigger('click')
		await flushPromises()
		expect(wrapper.find('[data-testid="details-error"]').text()).toBe(
			'Value is not one of the options',
		)
	})
})
