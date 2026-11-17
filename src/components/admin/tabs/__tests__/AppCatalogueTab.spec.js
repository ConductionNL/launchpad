/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `AppCatalogueTab.vue` (REQ-ACAT-001, REQ-ACAT-004):
 * the administrator's catalogue and open requests load, an entry is added
 * for a group, approving opens the form prefilled with the requested name
 * and saves through the approve call, declining needs a note, and the
 * manager group is stored.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AppCatalogueTab from '../AppCatalogueTab.vue'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
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
	NcTextField: {
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template:
			'<label>{{ label }}<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
	},
	NcSelect: {
		props: ['modelValue', 'inputLabel', 'options'],
		emits: ['update:modelValue'],
		template: '<div class="nc-select" :data-label="inputLabel" />',
	},
}

const ZAAK = {
	uuid: 'app-1',
	name: 'Zaaksysteem',
	description: '',
	icon: '',
	iconType: 'class',
	linkType: 'url',
	linkValue: 'https://zaken.example.nl',
	targetGroups: ['burgerzaken'],
}
const KAART = {
	uuid: 'req-1',
	requestedBy: 'pieter',
	appName: 'Kaartviewer',
	purpose: 'Ik moet percelen bekijken bij vergunningen',
	status: 'open',
}
const GROUPS = [
	{ id: 'burgerzaken', displayName: 'Burgerzaken' },
	{ id: 'admin', displayName: 'admin' },
]

/**
 * Mount the tab on a loaded catalogue.
 *
 * @param {object} data The admin index response.
 * @return {Promise<object>} The wrapper.
 */
async function mountTab(data) {
	axios.get.mockResolvedValue({ data })
	const wrapper = mount(AppCatalogueTab, {
		props: { groups: GROUPS },
		global: { stubs },
	})
	await settle()
	await settle()
	return wrapper
}

function button (wrapper, text) {
  return wrapper.findAll('button').find((candidate) => candidate.text() === text)
}
function field (wrapper, label) {
  return wrapper.findAll('label').find((candidate) => candidate.text() === label).find('input')
}

describe('AppCatalogueTab', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.post.mockReset()
		axios.put.mockReset()
		axios.delete.mockReset()
	})

	it('lists the entries with their group names and the open requests', async () => {
		const wrapper = await mountTab({
			apps: [ZAAK, { ...ZAAK, uuid: 'app-2', name: 'Agenda', targetGroups: [] }],
			requests: [KAART],
			managerGroup: '',
		})

		expect(axios.get).toHaveBeenCalledWith('/apps/launchpad/api/admin/catalogue')
		const entries = wrapper.findAll('[data-testid="app-catalogue-entry"]')
		expect(entries).toHaveLength(2)
		expect(entries[0].text()).toContain('Zaaksysteem')
		expect(entries[0].find('[data-testid="app-catalogue-entry-groups"]').text()).toBe('Burgerzaken')
		expect(entries[1].find('[data-testid="app-catalogue-entry-groups"]').text()).toBe('Everyone')
		const request = wrapper.find('[data-testid="app-catalogue-request"]')
		expect(request.text()).toContain('Kaartviewer')
		expect(request.text()).toContain('Asked by pieter')
	})

	it('labels every group picker', async () => {
		const wrapper = await mountTab({ apps: [], requests: [], managerGroup: '' })
		await button(wrapper, 'Add app').trigger('click')

		const labels = wrapper.findAll('.nc-select').map((select) => select.attributes('data-label'))
		expect(labels).toEqual(['Address type', 'Visible to groups', 'Catalogue manager group'])
	})

	it('adds an app for one group and lists it', async () => {
		const wrapper = await mountTab({ apps: [], requests: [], managerGroup: '' })
		axios.post.mockImplementation((url, body) =>
			Promise.resolve({ data: { app: { ...body, uuid: 'new-1' } } }),
		)

		await button(wrapper, 'Add app').trigger('click')
		await field(wrapper, 'Name').setValue('Zaaksysteem')
		await field(wrapper, 'Web address').setValue('https://zaken.example.nl')
		wrapper.vm.form.targetGroups = ['burgerzaken']
		await wrapper.find('[data-testid="app-catalogue-form"]').trigger('submit')
		await settle()

		expect(axios.post).toHaveBeenCalledWith('/apps/launchpad/api/admin/catalogue', {
			name: 'Zaaksysteem',
			description: '',
			icon: '',
			iconType: 'class',
			linkType: 'url',
			linkValue: 'https://zaken.example.nl',
			targetGroups: ['burgerzaken'],
		})
		expect(wrapper.find('[data-testid="app-catalogue-form"]').exists()).toBe(false)
		const entry = wrapper.find('[data-testid="app-catalogue-entry"]')
		expect(entry.find('[data-testid="app-catalogue-entry-groups"]').text()).toBe('Burgerzaken')
	})

	it('edits an entry through the update call', async () => {
		const wrapper = await mountTab({ apps: [ZAAK], requests: [], managerGroup: '' })
		axios.put.mockImplementation((url, body) =>
			Promise.resolve({ data: { app: { ...ZAAK, ...body } } }),
		)

		await button(wrapper, 'Edit').trigger('click')
		expect(field(wrapper, 'Name').element.value).toBe('Zaaksysteem')
		await field(wrapper, 'Name').setValue('Zaken')
		await wrapper.find('[data-testid="app-catalogue-form"]').trigger('submit')
		await settle()

		expect(axios.put.mock.calls[0][0]).toBe('/apps/launchpad/api/admin/catalogue/app-1')
		expect(axios.put.mock.calls[0][1].name).toBe('Zaken')
		expect(wrapper.find('[data-testid="app-catalogue-entry"]').text()).toContain('Zaken')
	})

	it('removes an entry', async () => {
		const wrapper = await mountTab({ apps: [ZAAK], requests: [], managerGroup: '' })
		axios.delete.mockResolvedValue({ data: { deleted: true } })

		await button(wrapper, 'Remove').trigger('click')
		await settle()

		expect(axios.delete).toHaveBeenCalledWith('/apps/launchpad/api/admin/catalogue/app-1')
		expect(wrapper.findAll('[data-testid="app-catalogue-entry"]')).toHaveLength(0)
	})

	it('approves a request through the prefilled form and adds the entry', async () => {
		const wrapper = await mountTab({ apps: [], requests: [KAART], managerGroup: '' })
		axios.post.mockImplementation((url, body) =>
			Promise.resolve({ data: { request: { ...KAART, status: 'approved' }, app: { ...body, uuid: 'new-2' } } }),
		)

		await button(wrapper, 'Approve').trigger('click')
		expect(wrapper.find('[data-testid="app-catalogue-form"] h4').text()).toBe('Approve request')
		expect(field(wrapper, 'Name').element.value).toBe('Kaartviewer')
		await field(wrapper, 'Web address').setValue('https://kaart.example.nl')
		await wrapper.find('[data-testid="app-catalogue-form"]').trigger('submit')
		await settle()

		expect(axios.post.mock.calls[0][0]).toBe(
			'/apps/launchpad/api/admin/catalogue/requests/req-1/approve',
		)
		expect(axios.post.mock.calls[0][1]).toMatchObject({
			name: 'Kaartviewer',
			linkValue: 'https://kaart.example.nl',
		})
		expect(wrapper.findAll('[data-testid="app-catalogue-request"]')).toHaveLength(0)
		expect(wrapper.find('[data-testid="app-catalogue-entry"]').text()).toContain('Kaartviewer')
		expect(wrapper.find('[data-testid="app-catalogue-notice"]').text()).toBe(
			'Kaartviewer is now in the app catalogue',
		)
	})

	it('declines a request only with a note', async () => {
		const wrapper = await mountTab({ apps: [], requests: [KAART], managerGroup: '' })
		axios.post.mockResolvedValue({ data: { request: { ...KAART, status: 'declined' } } })

		await button(wrapper, 'Decline').trigger('click')
		expect(button(wrapper, 'Decline request').attributes('disabled')).toBeDefined()
		await field(wrapper, 'Why is it declined?').setValue('Gebruik de kaartlaag in het zaaksysteem')
		expect(button(wrapper, 'Decline request').attributes('disabled')).toBeUndefined()
		await wrapper.find('.app-catalogue-tab__decline').trigger('submit')
		await settle()

		expect(axios.post).toHaveBeenCalledWith(
			'/apps/launchpad/api/admin/catalogue/requests/req-1/decline',
			{ note: 'Gebruik de kaartlaag in het zaaksysteem' },
		)
		expect(wrapper.findAll('[data-testid="app-catalogue-request"]')).toHaveLength(0)
		expect(wrapper.find('[data-testid="app-catalogue-notice"]').text()).toBe('The request was declined.')
	})

	it('says when someone else already decided on the request', async () => {
		const wrapper = await mountTab({ apps: [], requests: [KAART], managerGroup: '' })
		axios.post.mockRejectedValue({ response: { status: 409, data: { code: 'request_not_open' } } })

		await button(wrapper, 'Decline').trigger('click')
		await field(wrapper, 'Why is it declined?').setValue('Nee')
		await wrapper.find('.app-catalogue-tab__decline').trigger('submit')
		await settle()

		expect(wrapper.find('[data-testid="app-catalogue-error"]').text()).toBe(
			'Someone already decided on this request.',
		)
		expect(wrapper.findAll('[data-testid="app-catalogue-request"]')).toHaveLength(1)
	})

	it('stores the manager group, and none when cleared', async () => {
		const wrapper = await mountTab({ apps: [], requests: [], managerGroup: '' })
		axios.put.mockImplementation((url, body) => Promise.resolve({ data: { managerGroup: body.managerGroup } }))

		await wrapper.vm.saveManagerGroup('burgerzaken')
		expect(axios.put).toHaveBeenLastCalledWith('/apps/launchpad/api/admin/catalogue/settings', {
			managerGroup: 'burgerzaken',
		})
		expect(wrapper.vm.managerGroup).toBe('burgerzaken')

		await wrapper.vm.saveManagerGroup(null)
		expect(axios.put).toHaveBeenLastCalledWith('/apps/launchpad/api/admin/catalogue/settings', {
			managerGroup: '',
		})
		expect(wrapper.vm.managerGroup).toBeNull()
	})

	it('shows an error when the catalogue cannot be loaded', async () => {
		axios.get.mockRejectedValue(new Error('down'))
		const wrapper = mount(AppCatalogueTab, { props: { groups: GROUPS }, global: { stubs } })
		await settle()
		await settle()

		expect(wrapper.find('[data-testid="app-catalogue-error"]').text()).toBe(
			'The app catalogue could not be loaded.',
		)
	})
})
