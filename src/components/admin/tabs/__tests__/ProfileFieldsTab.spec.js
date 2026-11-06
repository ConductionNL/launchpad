/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ProfileFieldsTab.vue` (REQ-PEX-001): the
 * administrator's field definitions load, a field is added with safe
 * defaults, at most ten are allowed, and saving sends the list.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ProfileFieldsTab from '../ProfileFieldsTab.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

const settle = () => new Promise((resolve) => setTimeout(resolve, 0))
const stubs = {
	NcButton: {
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :type="$attrs.type || \'button\'" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}
const OFFICE = {
	key: 'office',
	label: 'Kantoorlocatie',
	type: 'text',
	source: 'ldap',
	ldapAttribute: 'physicalDeliveryOfficeName',
	searchable: false,
	shownInWidget: true,
	visibility: 'everyone',
}

describe('ProfileFieldsTab', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.put.mockReset()
	})

	it('loads the definitions and shows the LDAP attribute of an LDAP field', async () => {
		axios.get.mockResolvedValue({ data: { fields: [OFFICE] } })
		const wrapper = mount(ProfileFieldsTab, { global: { stubs } })
		await settle()
		await settle()

		expect(axios.get).toHaveBeenCalledWith(
			'/apps/launchpad/api/profile-fields/definitions',
		)
		expect(wrapper.find('legend').text()).toBe('Kantoorlocatie')
		expect(
			wrapper.find('input[placeholder="physicalDeliveryOfficeName"]').element
				.value,
		).toBe('physicalDeliveryOfficeName')
	})

	it('adds a searchable text field for everyone and saves the list', async () => {
		axios.get.mockResolvedValue({ data: { fields: [] } })
		axios.put.mockImplementation((url, body) =>
			Promise.resolve({ data: { fields: body.fields } }),
		)
		const wrapper = mount(ProfileFieldsTab, { global: { stubs } })
		await settle()
		await settle()

		const add = wrapper
			.findAll('button')
			.find((button) => button.text() === 'Add field')
		await add.trigger('click')
		const inputs = wrapper.findAll('fieldset input[type="text"]')
		await inputs[0].setValue('Expertise')
		await inputs[1].setValue('expertise')
		await wrapper.find('form').trigger('submit')
		await settle()

		expect(axios.put).toHaveBeenCalledWith(
			'/apps/launchpad/api/profile-fields/definitions',
			{
				fields: [
					{
						key: 'expertise',
						label: 'Expertise',
						type: 'text',
						source: 'self',
						ldapAttribute: '',
						searchable: true,
						shownInWidget: true,
						visibility: 'everyone',
					},
				],
			},
		)
		expect(wrapper.text()).toContain('Profile fields saved.')
	})

	it('stops at ten fields', async () => {
		axios.get.mockResolvedValue({
			data: {
				fields: Array.from({ length: 10 }, (_, i) => ({
					...OFFICE,
					key: 'f' + i,
					source: 'self',
				})),
			},
		})
		const wrapper = mount(ProfileFieldsTab, { global: { stubs } })
		await settle()
		await settle()

		const add = wrapper
			.findAll('button')
			.find((button) => button.text() === 'Add field')
		expect(add.attributes('disabled')).toBeDefined()
	})
})
