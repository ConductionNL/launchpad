/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the widget list in `WidgetPickerModal.vue`: order
 * (unplaced first, then by `order`), the placed badge, search, the empty
 * state, the add event and resetting the search on close. The discovered
 * apps section has its own spec. Since widget-picker-board-sync the Nextcloud
 * widgets stand as cards under "From other Nextcloud apps", and a card is
 * placed with Add widget after it is chosen.
 *
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-widget-picker-as-lpwidgetkiezen-draws-it-req-wdg-040
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
	NcButton: { props: ['disabled'], emits: ['click'], template: '<button class="nc-button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
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
	return rowsOf(wrapper).map((row) => row.find('.widget-picker__widget-title').text())
}

/**
 * The Nextcloud widget cards.
 *
 * @param {object} wrapper the mounted picker.
 * @return {Array<object>} the cards.
 */
function rowsOf(wrapper) {
	return wrapper.findAll('[data-testid="widget-picker-section-apps"] .widget-picker__widget')
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

describe('WidgetPickerModal widget list', () => {
	it('renders nothing while closed', () => {
		expect(mountPicker({ open: false }).find('.nc-modal').exists()).toBe(false)
	})

	it('lists unplaced widgets by order first, placed widgets last', () => {
		expect(titles(mountPicker())).toEqual(['Talk', 'Deck', 'Notes', 'Mail'])
	})

	it('badges a placed widget and shows its icon', () => {
		const rows = rowsOf(mountPicker())
		const mail = rows[3]

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

	it('emits add with the widget id once chosen and placed, placed or not, and closes', async () => {
		const wrapper = mountPicker()
		await rowsOf(wrapper)[0].trigger('click')
		expect(wrapper.emitted('add')).toBeUndefined()

		await place(wrapper, rowsOf(wrapper)[0])
		await place(wrapper, rowsOf(wrapper)[3])
		expect(wrapper.emitted('add')).toEqual([['talk'], ['mail']])
		expect(wrapper.emitted('close')).toHaveLength(2)
	})

	it('clears the search when closed', async () => {
		const wrapper = mountPicker()
		await wrapper.find('.nc-text').setValue('deck')

		await wrapper.setProps({ open: false })
		expect(wrapper.vm.searchQuery).toBe('')

		await wrapper.setProps({ open: true })
		expect(titles(wrapper)).toHaveLength(4)
		expect(wrapper.vm.chosenKind).toBe('all')
	})

	it('keeps the search while the modal stays open', async () => {
		const wrapper = mountPicker()
		await wrapper.find('.nc-text').setValue('deck')
		await wrapper.setProps({ widgets: [...WIDGETS] })
		expect(wrapper.vm.searchQuery).toBe('deck')
	})
})
