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
			customTypeTitles: ['Text', 'Image'],
			...props,
		},
		global: { stubs, mocks: { t: (_app, text) => text } },
	})
}

describe('WidgetPickerModal sections', () => {
	it('shows the catalogue, the custom widget, the office pack and the discovered apps, in that order', () => {
		const wrapper = mountPicker()
		const ids = wrapper
			.findAll('section[data-testid]')
			.map((section) => section.attributes('data-testid'))

		expect(ids).toEqual([
			'widget-picker-catalogue',
			'widget-picker-custom',
			'widget-picker-office',
			'widget-picker-discovered',
		])
		expect(wrapper.find('[data-testid="widget-picker-catalogue"]').text()).toContain('Mail')
		expect(wrapper.find('[data-testid="widget-picker-discovered"]').text()).toContain('Files')
		expect(wrapper.find('.nc-empty').exists()).toBe(false)
	})

	it('places what each section offers through its own event', async () => {
		const wrapper = mountPicker()

		await wrapper.find('[data-testid="widget-picker-catalogue"] button').trigger('click')
		await wrapper.find('[data-testid="widget-picker-office-pack"]').trigger('click')
		await wrapper.find('[data-testid="widget-picker-discovered"] button').trigger('click')

		expect(wrapper.emitted('addCatalogueApp')).toEqual([[MAIL]])
		expect(wrapper.emitted('addOfficePack')).toHaveLength(1)
		expect(wrapper.emitted('addApp')).toEqual([[FILES]])
	})

	it('asks for the custom widget form when "Custom widget" is picked', async () => {
		const wrapper = mountPicker()

		await wrapper.find('[data-testid="widget-picker-custom-widget"]').trigger('click')

		expect(wrapper.emitted('addCustom')).toEqual([[]])
		expect(wrapper.emitted('add')).toBeUndefined()
	})

	it('finds "Custom widget" by its name or a type it builds, and leaves it out otherwise', async () => {
		const wrapper = mountPicker({ discoveredApps: [], catalogueApps: [] })
		const custom = () => wrapper.find('[data-testid="widget-picker-custom-widget"]')

		await wrapper.find('.nc-text').setValue('CUSTOM')
		expect(custom().exists()).toBe(true)
		await wrapper.find('.nc-text').setValue('ima')
		expect(custom().exists()).toBe(true)
		await wrapper.find('.nc-text').setValue('nothing like it')
		expect(custom().exists()).toBe(false)
	})

	it('renders nothing while closed', () => {
		const wrapper = mountPicker({ open: false })
		expect(wrapper.find('.nc-modal').exists()).toBe(false)
	})
})
