/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The picker the Meer menu's "Add widget" opens is the one place to add
 * anything: the app catalogue, Launchpad's own widget types, the Nextcloud
 * widgets, the office tile pack and the apps discovered on this server.
 *
 * @spec openspec/changes/archive/2026-11-19-launcher-discover-server-services/specs/tiles/spec.md
 * @spec openspec/changes/archive/2026-11-19-launcher-app-catalogue/specs/app-catalogue/spec.md
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md
 * @spec openspec/specs/widgets/spec.md#requirement-widget-picker-req-wdg-010
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import WidgetPickerModal from '../WidgetPickerModal.vue'

const stubs = {
	NcModal: { template: '<div class="nc-modal"><slot /></div>' },
	NcTextField: {
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<input class="nc-text" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcEmptyContent: { template: '<div class="nc-empty"><slot name="icon" /></div>' },
	NcButton: { props: ['disabled'], emits: ['click'], template: '<button class="nc-button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
}

/**
 * Choose a card and press Add widget.
 *
 * @param {object} wrapper the mounted picker.
 * @param {object} card the card.
 * @return {Promise<void>}
 */
async function place(wrapper, card) {
	await card.trigger('click')
	await wrapper.find('[data-testid="widget-picker-add"]').trigger('click')
}


const FILES = { appId: 'files', name: 'Files', href: '/apps/files', icon: '' }
const MAIL = { uuid: 'c-1', name: 'Mail', icon: '', iconType: 'url' }

function mountPicker(props = {}) {
	return mount(WidgetPickerModal, {
		props: {
			open: true,
			widgets: [{ id: 'notes', title: 'Notes' }],
			discoveredApps: [FILES],
			catalogueApps: [MAIL],
			customTypes: [{ type: 'text', title: 'Text' }, { type: 'image', title: 'Image' }],
			...props,
		},
		global: { stubs, mocks: { t: (_app, text) => text } },
	})
}

describe('WidgetPickerModal sections', () => {
	it('shows the Launchpad widgets and the other apps\' widgets, in that order, without App tiles', () => {
		const wrapper = mountPicker()
		const ids = wrapper
			.findAll('section[data-testid^="widget-picker-section-"]')
			.map((section) => section.attributes('data-testid'))

		// app-link-tile-widget REQ-WDG-042 removed: the apps are in the tile form.
		expect(ids).toEqual([
			'widget-picker-section-launchpad',
			'widget-picker-section-apps',
		])
		expect(wrapper.findAll('section h3').map((h) => h.text())).toEqual(['Widgets from Launchpad', 'From other Nextcloud apps'])
		expect(wrapper.find('[data-testid="widget-picker-section-launchpad"] [data-testid="widget-picker-office-pack"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="widget-picker-section-apps"]').text()).toContain('Notes')
		expect(wrapper.find('.nc-empty').exists()).toBe(false)
	})

	it('places what each section offers through its own event', async () => {
		const wrapper = mountPicker()

		await place(wrapper, wrapper.find('[data-testid="widget-picker-office-pack"]'))

		expect(wrapper.emitted('addOfficePack')).toHaveLength(1)
		expect(wrapper.find('[data-testid="widget-picker-app-tile-files"]').exists()).toBe(false)
	})

	it('lists each Launchpad type and asks for its form when one is picked', async () => {
		const wrapper = mountPicker()

		expect(wrapper.findAll('[data-testid^="widget-picker-type-"] .widget-picker__widget-title').map((row) => row.text())).toEqual(['Text', 'Image'])
		await place(wrapper, wrapper.find('[data-testid="widget-picker-type-image"]'))

		expect(wrapper.emitted('addCustom')).toEqual([['image']])
		expect(wrapper.emitted('add')).toBeUndefined()
	})

	it('searches the Launchpad types by title and leaves the section out without a match', async () => {
		const wrapper = mountPicker({ discoveredApps: [], catalogueApps: [] })

		await wrapper.find('.nc-text').setValue('IMA')
		expect(wrapper.findAll('[data-testid^="widget-picker-type-"] .widget-picker__widget-title').map((row) => row.text())).toEqual(['Image'])

		await wrapper.find('.nc-text').setValue('nothing like it')
		expect(wrapper.find('[data-testid="widget-picker-section-launchpad"]').exists()).toBe(false)
	})

	it('renders nothing while closed', () => {
		const wrapper = mountPicker({ open: false })
		expect(wrapper.find('.nc-modal').exists()).toBe(false)
	})
})
