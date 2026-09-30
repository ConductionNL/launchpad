/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * MetadataFieldsSettings (REQ-MDUI-001): an administrator adds a select
 * field with options, and a select field without options shows the
 * service's message (MetadataAdminController::badRequest shape).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MetadataFieldsSettings from '../MetadataFieldsSettings.vue'
import { api } from '../../../services/api.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('../../../services/api.js', () => ({
	api: {
		getMetadataFields: vi.fn(),
		createMetadataField: vi.fn(),
		deleteMetadataField: vi.fn(),
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
			'<input :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
	},
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel'],
		emits: ['update:modelValue'],
		template:
			'<select @change="$emit(\'update:modelValue\', options[$event.target.selectedIndex])"><option v-for="o in options" :key="o.id">{{ o.name }}</option></select>',
	},
}))

beforeEach(() => vi.clearAllMocks())

async function fillSelectField(wrapper, options) {
	await wrapper.find('input[aria-label="Label"]').setValue('Department')
	await wrapper.find('input[aria-label="Key"]').setValue('department')
	const select = wrapper.find('select')
	select.element.selectedIndex = 3
	await select.trigger('change')
	await wrapper
		.find('input[aria-label="Options, separated by commas"]')
		.setValue(options)
	await wrapper.find('[data-testid="metadata-field-add"]').trigger('click')
	await flushPromises()
}

describe('MetadataFieldsSettings', () => {
	it('REQ-MDUI-001: adds a Department select with two options', async () => {
		api.getMetadataFields.mockResolvedValueOnce({
			data: { fields: [], count: 0 },
		})
		api.getMetadataFields.mockResolvedValue({
			data: {
				fields: [
					{
						id: 1,
						key: 'department',
						label: 'Department',
						type: 'select',
						options: ['HR', 'Finance'],
					},
				],
				count: 1,
			},
		})
		api.createMetadataField.mockResolvedValue({ data: {} })
		const wrapper = mount(MetadataFieldsSettings)
		await flushPromises()
		await fillSelectField(wrapper, 'HR, Finance')
		expect(api.createMetadataField).toHaveBeenCalledWith({
			key: 'department',
			label: 'Department',
			type: 'select',
			options: ['HR', 'Finance'],
		})
		expect(wrapper.findAll('[data-testid="metadata-field-row"]')).toHaveLength(1)
	})

	it('REQ-MDUI-001: a select without options shows the service message', async () => {
		api.getMetadataFields.mockResolvedValue({ data: { fields: [], count: 0 } })
		api.createMetadataField.mockRejectedValue(
			Object.assign(new Error('x'), {
				response: {
					status: 400,
					data: {
						error: 'invalid_metadata_field',
						message: 'Select fields require at least one option',
					},
				},
			}),
		)
		const wrapper = mount(MetadataFieldsSettings)
		await flushPromises()
		await fillSelectField(wrapper, '')
		expect(wrapper.find('[data-testid="metadata-field-error"]').text()).toBe(
			'Select fields require at least one option',
		)
	})
})
