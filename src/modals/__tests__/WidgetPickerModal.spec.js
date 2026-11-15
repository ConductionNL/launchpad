/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the widget list in `WidgetPickerModal.vue`: order
 * (unplaced first, then by `order`), the placed badge, search, the empty
 * state, the add event and resetting the search on close. The discovered
 * apps section has its own spec.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import WidgetPickerModal from '../WidgetPickerModal.vue'

const stubs = {
	NcModal: { emits: ['close'], template: '<div class="nc-modal"><slot /></div>' },
	NcTextField: {
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<input class="nc-text" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcEmptyContent: { props: ['description'], template: '<div class="nc-empty">{{ description }}</div>' },
}

const WIDGETS = [
	{ id: 'notes', title: 'Notes', order: 3 },
	{ id: 'mail', title: 'Mail', order: 1, iconUrl: '/mail.svg' },
	{ id: 'deck', title: 'Deck', order: 2, iconClass: 'icon-deck' },
	{ id: 'talk', title: 'Talk' },
]

function mountPicker(props = {}) {
	return mount(WidgetPickerModal, {
		props: { open: true, widgets: WIDGETS, placedWidgetIds: ['mail'], ...props },
		global: { stubs, mocks: { t: (_app, text) => text } },
	})
}

/**
 * The widget rows outside the discovered-apps section, as their titles.
 *
 * @param {object} wrapper the mounted picker.
 * @return {string[]} the titles in display order.
 */
function titles(wrapper) {
	return wrapper.findAll('.widget-picker__list > .widget-picker__widget')
		.map((row) => row.find('.widget-picker__widget-title').text())
}

describe('WidgetPickerModal widget list', () => {
	it('renders nothing while closed', () => {
		expect(mountPicker({ open: false }).find('.nc-modal').exists()).toBe(false)
	})

	it('lists unplaced widgets by order first, placed widgets last', () => {
		expect(titles(mountPicker())).toEqual(['Talk', 'Deck', 'Notes', 'Mail'])
	})

	it('badges a placed widget and shows its icon', () => {
		const rows = mountPicker().findAll('.widget-picker__list > .widget-picker__widget')
		const mail = rows[3]

		expect(mail.classes()).toContain('widget-picker__widget--placed')
		expect(mail.find('.widget-picker__widget-badge').text()).toBe('Already added')
		expect(mail.find('img').attributes('src')).toBe('/mail.svg')
		expect(rows[1].find('span.icon-deck').exists()).toBe(true)
		expect(rows[0].find('.widget-picker__widget-badge').exists()).toBe(false)
	})

	it('filters by title, case-insensitively', async () => {
		const wrapper = mountPicker()
		await wrapper.find('.nc-text').setValue('DE')
		expect(titles(wrapper)).toEqual(['Deck'])
	})

	it('shows the empty state when nothing matches', async () => {
		const wrapper = mountPicker()
		expect(wrapper.find('.nc-empty').exists()).toBe(false)

		await wrapper.find('.nc-text').setValue('zzz')

		expect(titles(wrapper)).toEqual([])
		expect(wrapper.find('.nc-empty').text()).toBe('No widgets found')
	})

	it('emits add with the widget id on a click, placed or not', async () => {
		const wrapper = mountPicker()
		const rows = wrapper.findAll('.widget-picker__list > .widget-picker__widget')
		await rows[0].trigger('click')
		await rows[3].trigger('click')
		expect(wrapper.emitted('add')).toEqual([['talk'], ['mail']])
	})

	it('clears the search when closed', async () => {
		const wrapper = mountPicker()
		await wrapper.find('.nc-text').setValue('deck')

		await wrapper.setProps({ open: false })
		expect(wrapper.vm.searchQuery).toBe('')

		await wrapper.setProps({ open: true })
		expect(titles(wrapper)).toHaveLength(4)
	})

	it('keeps the search while the modal stays open', async () => {
		const wrapper = mountPicker()
		await wrapper.find('.nc-text').setValue('deck')
		await wrapper.setProps({ widgets: [...WIDGETS] })
		expect(wrapper.vm.searchQuery).toBe('deck')
	})
})
