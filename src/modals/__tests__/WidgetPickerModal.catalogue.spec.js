/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * launcher-app-catalogue: the add flow lists the catalogue entries the
 * server returned for the user, narrows them with the search box, asks the
 * host to place one as a tile, and offers "Can't find your app?" to ask IT
 * for a missing one.
 *
 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md
 */

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import WidgetPickerModal from '../WidgetPickerModal.vue'
import { tileFromCatalogueApp } from '../../utils/discoveredTile.js'

const stubs = {
	NcModal: { template: '<div class="nc-modal"><slot /></div>' },
	NcTextField: {
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input class="nc-text" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcButton: {
		props: ['disabled', 'type', 'variant'],
		emits: ['click'],
		template: '<button class="nc-button" :type="type || \'button\'" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcEmptyContent: { template: '<div class="nc-empty"><slot name="icon" /></div>' },
}

const ZAAK = { uuid: 'z1', name: 'Zaaksysteem', description: 'Cases and permits', icon: 'icon-folder', iconType: 'class', linkType: 'url', linkValue: 'https://zaken.example.nl' }
const AGENDA = { uuid: 'a1', name: 'Agenda', description: 'Your calendar', icon: '/apps/calendar/img/calendar.svg', iconType: 'url', linkType: 'app', linkValue: 'calendar' }

/**
 * Mount the picker.
 *
 * @param {object} props Extra props.
 * @return {object} The wrapper.
 */
function mountPicker(props = {}) {
	return mount(WidgetPickerModal, {
		props: { open: true, widgets: [], catalogueApps: [ZAAK, AGENDA], ...props },
		global: { stubs, mocks: { t: (_app, text) => text } },
	})
}

const rows = (wrapper) => wrapper.findAll('[data-testid="widget-picker-catalogue"] .widget-picker__widget')

describe('From the app catalogue', () => {
	it('lists the entries the server returned, with their descriptions', () => {
		const wrapper = mountPicker()
		const section = wrapper.find('[data-testid="widget-picker-catalogue"]')
		expect(section.find('h3').text()).toBe('From the app catalogue')
		expect(rows(wrapper).map((row) => row.text())).toEqual([
			expect.stringContaining('Zaaksysteem'),
			expect.stringContaining('Agenda'),
		])
		expect(rows(wrapper)[0].text()).toContain('Cases and permits')
		expect(rows(wrapper)[0].find('span.icon-folder').exists()).toBe(true)
		expect(rows(wrapper)[1].find('img').attributes('src')).toBe('/apps/calendar/img/calendar.svg')
	})

	it('narrows with the same search box', async () => {
		const wrapper = mountPicker()
		await wrapper.find('.nc-text').setValue('zaak')
		expect(rows(wrapper)).toHaveLength(1)
		expect(rows(wrapper)[0].text()).toContain('Zaaksysteem')
		await wrapper.find('.nc-text').setValue('calendar')
		expect(rows(wrapper)[0].text()).toContain('Agenda')
	})

	it('asks the host to place the picked entry', async () => {
		const wrapper = mountPicker()
		await rows(wrapper)[0].trigger('click')
		expect(wrapper.emitted('addCatalogueApp')[0]).toEqual([ZAAK])
	})

	it('is left out when the catalogue has nothing for the user', () => {
		expect(mountPicker({ catalogueApps: [] }).find('[data-testid="widget-picker-catalogue"]').exists()).toBe(false)
	})

	it('turns an entry into an ordinary tile', () => {
		expect(tileFromCatalogueApp(ZAAK)).toEqual({ title: 'Zaaksysteem', icon: 'icon-folder', iconType: 'class', linkType: 'url', linkValue: 'https://zaken.example.nl' })
		expect(tileFromCatalogueApp(AGENDA)).toEqual({ title: 'Agenda', icon: '/apps/calendar/img/calendar.svg', iconType: 'url', linkType: 'app', linkValue: 'calendar' })
		expect(tileFromCatalogueApp({ name: 'X', linkType: 'odd', linkValue: 'https://x.nl' })).toEqual({ title: 'X', icon: '', iconType: 'class', linkType: 'url', linkValue: 'https://x.nl' })
	})
})

describe("Can't find your app?", () => {
	const openForm = async (wrapper) => {
		await wrapper.find('[data-testid="widget-picker-request"] .nc-button').trigger('click')
	}

	it('is offered only when the host can send a request', () => {
		expect(mountPicker().find('[data-testid="widget-picker-request"]').exists()).toBe(false)
		expect(mountPicker({ requestAppFn: vi.fn() }).find('[data-testid="widget-picker-request"]').text()).toContain("Can't find your app?")
	})

	it('sends the name and purpose and says the request was sent', async () => {
		const requestAppFn = vi.fn().mockResolvedValue({})
		const wrapper = mountPicker({ requestAppFn })
		await wrapper.find('.nc-text').setValue('kaart')
		await openForm(wrapper)
		const fields = wrapper.findAll('form .nc-text')
		expect(fields[0].element.value).toBe('kaart')
		await fields[0].setValue('Kaartviewer')
		await fields[1].setValue(' Ik moet percelen bekijken bij vergunningen ')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(requestAppFn).toHaveBeenCalledWith({ appName: 'Kaartviewer', purpose: 'Ik moet percelen bekijken bij vergunningen' })
		expect(wrapper.find('[role="status"]').text()).toBe('Your request was sent to IT')
		expect(wrapper.find('[role="status"]').classes()).not.toContain('widget-picker__request-message--error')
	})

	it('names the open-request limit when the server refuses', async () => {
		const requestAppFn = vi.fn().mockRejectedValue({ response: { status: 429, data: { code: 'request_limit' } } })
		const wrapper = mountPicker({ requestAppFn })
		await openForm(wrapper)
		await wrapper.findAll('form .nc-text')[0].setValue('Kaartviewer')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(wrapper.find('[role="status"]').text()).toBe('You have 10 open requests. Wait for an answer first.')
		expect(wrapper.find('[role="status"]').classes()).toContain('widget-picker__request-message--error')
	})

	it('says when a request could not be sent, and sends nothing without a name', async () => {
		const requestAppFn = vi.fn().mockRejectedValue(new Error('offline'))
		const wrapper = mountPicker({ requestAppFn })
		await openForm(wrapper)
		await wrapper.find('form').trigger('submit')
		expect(requestAppFn).not.toHaveBeenCalled()

		await wrapper.findAll('form .nc-text')[0].setValue('Kaartviewer')
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(wrapper.find('[role="status"]').text()).toBe('Your request could not be sent. Try again later.')
	})

	it('closes and forgets the form when the picker closes', async () => {
		const wrapper = mountPicker({ requestAppFn: vi.fn() })
		await openForm(wrapper)
		await wrapper.findAll('form .nc-text')[0].setValue('Kaartviewer')
		await wrapper.setProps({ open: false })
		await wrapper.setProps({ open: true })
		expect(wrapper.find('form').exists()).toBe(false)
		expect(wrapper.vm.requestAppName).toBe('')
	})
})
