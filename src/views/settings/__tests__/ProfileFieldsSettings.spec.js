/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ProfileFieldsSettings.vue` (REQ-PEX-002): the
 * person's own fields load from the API, an LDAP field shows read-only, and
 * saving sends only the editable fields.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ProfileFieldsSettings from '../ProfileFieldsSettings.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

const FIELDS = [
	{
		key: 'office',
		label: 'Kantoorlocatie',
		type: 'text',
		source: 'ldap',
		values: ['Stadhuis'],
		readOnly: true,
	},
	{
		key: 'expertise',
		label: 'Expertise',
		type: 'tags',
		source: 'self',
		values: ['subsidies'],
		readOnly: false,
	},
	{
		key: 'room',
		label: 'Kamer',
		type: 'text',
		source: 'self',
		values: [],
		readOnly: false,
	},
]

const stubs = {
	NcSettingsSection: { template: '<section><slot /></section>' },
	NcNoteCard: { template: '<div class="note"><slot /></div>' },
	NcButton: { template: '<button type="submit"><slot /></button>' },
	NcSelect: {
		props: ['modelValue', 'inputLabel'],
		emits: ['update:modelValue'],
		template:
			'<div class="nc-select" :data-label="inputLabel">{{ (modelValue || []).join("|") }}</div>',
	},
	NcTextField: {
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template:
			'<input class="nc-text" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
}

describe('ProfileFieldsSettings', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.put.mockReset()
		axios.get.mockResolvedValue({ data: { fields: FIELDS } })
	})

	it('shows an LDAP field read-only and the own fields as inputs', async () => {
		const wrapper = mount(ProfileFieldsSettings, { global: { stubs } })
		await settle()
		await settle()

		expect(axios.get).toHaveBeenCalledWith(
			'/apps/launchpad/api/profile-fields/me',
		)
		expect(wrapper.text()).toContain('Kantoorlocatie')
		expect(wrapper.text()).toContain('Stadhuis')
		expect(wrapper.find('.nc-select').attributes('data-label')).toBe('Expertise')
		expect(wrapper.find('.nc-select').text()).toBe('subsidies')
		expect(wrapper.find('input.nc-text').attributes('aria-label')).toBe('Kamer')
	})

	it('saves only the editable fields', async () => {
		axios.put.mockResolvedValue({ data: { fields: FIELDS } })
		const wrapper = mount(ProfileFieldsSettings, { global: { stubs } })
		await settle()
		await settle()

		await wrapper.find('input.nc-text').setValue('B-204')
		await wrapper.find('form').trigger('submit')
		await settle()

		expect(axios.put).toHaveBeenCalledWith(
			'/apps/launchpad/api/profile-fields/me',
			{
				values: { expertise: ['subsidies'], room: 'B-204' },
			},
		)
		expect(wrapper.text()).toContain('Your profile fields are saved.')
	})

	it('shows the server message when a save is refused', async () => {
		axios.put.mockRejectedValue({
			response: {
				data: { error: 'A value is longer than 255 characters: room' },
			},
		})
		const wrapper = mount(ProfileFieldsSettings, { global: { stubs } })
		await settle()
		await settle()

		await wrapper.find('form').trigger('submit')
		await settle()

		expect(wrapper.text()).toContain(
			'A value is longer than 255 characters: room',
		)
	})
})
