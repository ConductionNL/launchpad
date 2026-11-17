/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * integrations-office-tiles REQ-OFT-001: the add flow offers "Office tiles",
 * one click asks the host to place the pack, and a search for something else
 * leaves it out.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md
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

function mountPicker(props = {}) {
	return mount(WidgetPickerModal, {
		props: { open: true, widgets: [{ id: 'notes', title: 'Notes' }], ...props },
		global: { stubs, mocks: { t: (_app, text) => text } },
	})
}

describe('Office tiles in the picker', () => {
	it('offers the pack and asks the host to place it', async () => {
		const wrapper = mountPicker()
		const pack = wrapper.find('[data-testid="widget-picker-office-pack"]')

		expect(pack.text()).toContain('Office tiles')
		await pack.trigger('click')

		expect(wrapper.emitted('addOfficePack')).toHaveLength(1)
		expect(wrapper.emitted('add')).toBeUndefined()
	})

	it('keeps the pack for a matching search and drops it for another', async () => {
		const wrapper = mountPicker()
		await wrapper.find('input.nc-text').setValue('office')
		expect(wrapper.find('[data-testid="widget-picker-office"]').exists()).toBe(true)

		await wrapper.find('input.nc-text').setValue('notes')
		expect(wrapper.find('[data-testid="widget-picker-office"]').exists()).toBe(false)
	})

	it('shows the empty state only when the pack is filtered out too', async () => {
		const wrapper = mountPicker({ widgets: [] })
		expect(wrapper.find('.nc-empty').exists()).toBe(false)

		await wrapper.find('input.nc-text').setValue('zzz')
		expect(wrapper.find('.nc-empty').exists()).toBe(true)
	})
})
