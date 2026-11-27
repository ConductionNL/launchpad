/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget picker as board LpWidgetKiezen draws it (widget-picker-board-sync):
 * the dashboard over the title, the kinds with their counts, choosing and
 * placing a card, the grey card for what the role may not add and the label
 * for an older widget type.
 *
 * @spec openspec/specs/widgets/spec.md
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import WidgetPickerModal from '../WidgetPickerModal.vue'

const stubs = {
	NcModal: { props: ['name'], emits: ['close'], template: '<div class="nc-modal" :data-name="name"><slot /></div>' },
	NcTextField: {
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input class="nc-text" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcEmptyContent: { props: ['description'], template: '<div class="nc-empty">{{ description }}</div>' },
	NcButton: { props: ['disabled'], emits: ['click'], template: '<button class="nc-button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
}

/**
 * Mount the picker.
 *
 * @param {object} props Extra props.
 * @return {object} The wrapper.
 */
function mountPicker(props = {}) {
	return mount(WidgetPickerModal, {
		props: {
			open: true,
			dashboardName: 'My workspace',
			widgets: [{ id: 'notes', title: 'Notes', itemApiVersions: [] }],
			customTypes: [
				{ type: 'clock', title: 'Clock' },
				{ type: 'iframe', title: 'Embedded page' },
				{ type: 'pie-chart', title: 'Pie chart' },
				{ type: 'graph', title: 'Graph' },
			],
			catalogueApps: [{ uuid: 'z1', name: 'Case system', description: 'Cases', icon: '', iconType: 'class' }],
			...props,
		},
		global: { stubs, mocks: { t: (_app, text) => text } },
	})
}

const kind = (wrapper, id) => wrapper.find(`[data-testid="widget-picker-kind-${id}"]`)
const titles = (wrapper) => wrapper.findAll('.widget-picker__widget .widget-picker__widget-title').map((node) => node.text())
const add = (wrapper) => wrapper.find('[data-testid="widget-picker-add"]')

describe('WidgetPickerModal as LpWidgetKiezen draws it', () => {
	it('puts the dashboard over "Add widget", then the search, the kinds and the sections', () => {
		const wrapper = mountPicker()
		const heading = wrapper.find('.widget-picker__heading')
		expect(heading.find('.widget-picker__dashboard').text()).toBe('My workspace')
		expect(heading.find('h2').text()).toBe('Add widget')
		const html = wrapper.html()
		const order = ['widget-picker__heading', 'widget-picker__search', 'widget-picker__kinds', 'widget-picker__list', 'widget-picker__actions'].map((name) => html.indexOf(name))
		expect([...order].sort((a, b) => a - b)).toEqual(order)
		expect(wrapper.find('.widget-picker__grid').exists()).toBe(true)
		expect(mountPicker({ dashboardName: '' }).find('.widget-picker__dashboard').exists()).toBe(false)
	})

	it('lists the kinds with their counts in a navigation, All chosen', () => {
		const wrapper = mountPicker()
		expect(wrapper.find('nav.widget-picker__kinds').attributes('aria-label')).toBe('Kinds of widget')
		expect(wrapper.findAll('.widget-picker__kind').map((node) => `${node.find('.widget-picker__kind-label').text()} ${node.find('.widget-picker__kind-count').text()}`)).toEqual([
			'All 6', 'Content 2', 'Tiles and links 0', 'Layout 0', 'People 0', 'Data and charts 2', 'From other apps 1', 'Office 1',
		])
		expect(kind(wrapper, 'all').attributes('aria-pressed')).toBe('true')
	})

	it('shows only the chosen kind, its count equal to its cards, and searches within it', async () => {
		const wrapper = mountPicker()
		await kind(wrapper, 'data').trigger('click')
		expect(kind(wrapper, 'data').attributes('aria-pressed')).toBe('true')
		expect(kind(wrapper, 'all').attributes('aria-pressed')).toBe('false')
		expect(titles(wrapper)).toEqual(['Pie chart', 'Graph'])
		expect(kind(wrapper, 'data').text()).toContain('2')

		await wrapper.find('.nc-text').setValue('graph')
		expect(titles(wrapper)).toEqual(['Graph'])
		await wrapper.find('.nc-text').setValue('clock')
		expect(wrapper.find('.nc-empty').text()).toBe('No widgets found')
	})

	it('chooses a card on a click, places it only with Add widget, and Cancel closes', async () => {
		const wrapper = mountPicker()
		expect(add(wrapper).attributes('disabled')).toBeDefined()

		const clock = wrapper.find('[data-testid="widget-picker-type-clock"]')
		await clock.trigger('click')
		expect(clock.attributes('aria-pressed')).toBe('true')
		expect(clock.classes()).toContain('widget-picker__widget--chosen')
		expect(wrapper.emitted('addCustom')).toBeUndefined()
		expect(add(wrapper).attributes('disabled')).toBeUndefined()

		await add(wrapper).trigger('click')
		expect(wrapper.emitted('addCustom')).toEqual([['clock']])
		expect(add(wrapper).attributes('disabled')).toBeDefined()

		await wrapper.find('[data-testid="widget-picker-cancel"]').trigger('click')
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it('places a card on a double click', async () => {
		const wrapper = mountPicker()
		await wrapper.find('[data-testid="widget-picker-type-clock"]').trigger('dblclick')
		expect(wrapper.emitted('addCustom')[0]).toEqual(['clock'])
	})

	it('forgets a choice the search hides', async () => {
		const wrapper = mountPicker()
		await wrapper.find('[data-testid="widget-picker-type-clock"]').trigger('click')
		await wrapper.find('.nc-text').setValue('graph')
		expect(add(wrapper).attributes('disabled')).toBeDefined()
	})

	it('shows what the role may not add as a grey card that cannot be chosen', async () => {
		const wrapper = mountPicker({ allowedWidgets: ['clock', 'pie-chart', 'graph', 'notes', 'tile'] })
		const card = wrapper.find('[data-testid="widget-picker-type-iframe"]')
		expect(card.classes()).toContain('widget-picker__widget--disabled')
		expect(card.attributes('aria-disabled')).toBe('true')
		expect(card.text()).toContain('Not allowed for your role')
		expect(wrapper.find('[data-testid="widget-picker-type-clock"]').attributes('aria-disabled')).toBe('false')

		await card.trigger('click')
		expect(card.attributes('aria-pressed')).toBe('false')
		expect(add(wrapper).attributes('disabled')).toBeDefined()
		await card.trigger('dblclick')
		expect(wrapper.emitted('addCustom')).toBeUndefined()
	})

	it('labels an older Nextcloud widget type', () => {
		const card = mountPicker().find('[data-testid="widget-picker-widget-notes"]')
		expect(card.text()).toContain('Older widget type')
		expect(card.text()).toContain('A widget from another Nextcloud app.')
	})

	// app-link-tile-widget REQ-WDG-042 removed: no App tiles section, no Own tile.
	it('has no App tiles section and no Own tile', () => {
		const wrapper = mountPicker()
		expect(wrapper.find('[data-testid="widget-picker-section-tiles"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="widget-picker-custom-tile"]').exists()).toBe(false)
	})

	it('starts again on All with no choice after it closes', async () => {
		const wrapper = mountPicker()
		await kind(wrapper, 'data').trigger('click')
		await wrapper.find('[data-testid="widget-picker-type-graph"]').trigger('click')
		await wrapper.setProps({ open: false })
		await wrapper.setProps({ open: true })
		expect(kind(wrapper, 'all').attributes('aria-pressed')).toBe('true')
		expect(add(wrapper).attributes('disabled')).toBeDefined()
	})
})
