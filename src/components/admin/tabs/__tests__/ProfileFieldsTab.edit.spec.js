/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for editing in `ProfileFieldsTab.vue` (REQ-PEX-001):
 * every control writes its own property of the field that is sent on save,
 * the LDAP attribute only shows for an LDAP field, a field can be removed,
 * the ten-field cap holds in code as well as on the button, and load and
 * save failures show their message.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ProfileFieldsTab from '../ProfileFieldsTab.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

const stubs = {
	NcButton: {
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :type="$attrs.type || \'button\'" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}

function field (overrides = {}) {
  return {
	key: 'office',
	label: 'Office',
	type: 'text',
	source: 'self',
	ldapAttribute: '',
	searchable: true,
	shownInWidget: true,
	visibility: 'everyone',
	...overrides,
}
}

async function mountTab(fields) {
	axios.get.mockResolvedValue({ data: { fields } })
	const wrapper = mount(ProfileFieldsTab, { global: { stubs } })
	await flushPromises()
	return wrapper
}

function button (wrapper, label) {
  return wrapper.findAll('button').find((b) => b.text() === label)
}

beforeEach(() => {
	axios.get.mockReset()
	axios.put.mockReset()
	axios.put.mockImplementation((_url, body) => Promise.resolve({ data: { fields: body.fields } }))
})

describe('ProfileFieldsTab editing', () => {
	it('writes every control into the field that is saved', async () => {
		const wrapper = await mountTab([field()])
		const selects = wrapper.findAll('fieldset select')
		const checks = wrapper.findAll('fieldset input[type="checkbox"]')

		await selects[0].setValue('tags')
		await selects[1].setValue('ldap')
		await wrapper.find('input[placeholder="physicalDeliveryOfficeName"]').setValue('roomNumber')
		await wrapper.findAll('fieldset select')[2].setValue('groups')
		await checks[0].setValue(false)
		await checks[1].setValue(false)
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(axios.put).toHaveBeenCalledWith('/apps/launchpad/api/profile-fields/definitions', {
			fields: [
				field({
					type: 'tags',
					source: 'ldap',
					ldapAttribute: 'roomNumber',
					visibility: 'groups',
					searchable: false,
					shownInWidget: false,
				}),
			],
		})
	})

	it('shows the LDAP attribute only for an LDAP field', async () => {
		const wrapper = await mountTab([field()])
		expect(wrapper.find('input[placeholder="physicalDeliveryOfficeName"]').exists()).toBe(false)

		await wrapper.findAll('fieldset select')[1].setValue('ldap')

		expect(wrapper.find('input[placeholder="physicalDeliveryOfficeName"]').exists()).toBe(true)
	})

	it('names an unlabelled field "New field"', async () => {
		const wrapper = await mountTab([field({ label: '' })])

		expect(wrapper.find('legend').text()).toBe('New field')
	})

	it('removes the field whose button is pressed, not the first', async () => {
		const wrapper = await mountTab([field({ key: 'a', label: 'A' }), field({ key: 'b', label: 'B' })])

		await wrapper.findAll('fieldset')[1].findAll('button').find((b) => b.text() === 'Remove field').trigger('click')

		expect(wrapper.findAll('legend').map((l) => l.text())).toEqual(['A'])
		expect(wrapper.vm.fields.map((f) => f.key)).toEqual(['a'])
	})

	it('adds nothing past ten fields even when called directly', async () => {
		const wrapper = await mountTab(Array.from({ length: 10 }, (_, i) => field({ key: 'f' + i })))

		wrapper.vm.add()

		expect(wrapper.vm.fields).toHaveLength(10)
		expect(button(wrapper, 'Add field').attributes('disabled')).toBeDefined()
	})
})

describe('ProfileFieldsTab failures', () => {
	it('says so when the definitions cannot be loaded and still shows the form', async () => {
		axios.get.mockRejectedValue(new Error('down'))
		const wrapper = mount(ProfileFieldsTab, { global: { stubs } })
		await flushPromises()

		expect(wrapper.find('[role="alert"]').text()).toBe('The profile fields could not be loaded.')
		expect(wrapper.find('form').exists()).toBe(true)
		expect(wrapper.vm.fields).toEqual([])
	})

	it('treats a load without a fields list as none', async () => {
		axios.get.mockResolvedValue({ data: {} })
		const wrapper = mount(ProfileFieldsTab, { global: { stubs } })
		await flushPromises()

		expect(wrapper.findAll('fieldset')).toHaveLength(0)
	})

	it('shows the server\'s error for a refused save', async () => {
		const wrapper = await mountTab([field()])
		axios.put.mockRejectedValue({ response: { data: { error: 'Key "office" is used twice.' } } })

		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(wrapper.find('[role="alert"]').text()).toBe('Key "office" is used twice.')
		expect(wrapper.find('[role="status"]').exists()).toBe(false)
		expect(wrapper.vm.saving).toBe(false)
	})

	it('falls back to a generic message when the save fails without one', async () => {
		const wrapper = await mountTab([field()])
		axios.put.mockRejectedValue(new Error('network'))

		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(wrapper.find('[role="alert"]').text()).toBe('The profile fields could not be saved.')
	})

	it('empties the list when a save answers without fields', async () => {
		const wrapper = await mountTab([field()])
		axios.put.mockResolvedValue({ data: {} })

		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(wrapper.vm.fields).toEqual([])
		expect(wrapper.find('[role="status"]').text()).toBe('Profile fields saved.')
	})
})
