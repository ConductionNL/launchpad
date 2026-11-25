/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The picker's assistant line (REQ-WDG-044): shown only when the server has an
 * assistant, the proposal is one of the offered cards, Place it like this
 * places it as Add widget does, and an answer naming no card says so.
 *
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WidgetPickerModal from '../WidgetPickerModal.vue'
import { api } from '../../services/api.js'
import { resetAssistantAvailability } from '../../services/widgetAssistant.js'

vi.mock('../../services/api.js', () => ({
	api: {
		widgetAssistant: vi.fn(),
		proposeWidget: vi.fn(),
	},
}))

const stubs = {
	NcModal: { props: ['name'], emits: ['close'], template: '<div class="nc-modal" :data-name="name"><slot /></div>' },
	NcTextField: {
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input class="nc-text" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcEmptyContent: { props: ['description'], template: '<div class="nc-empty">{{ description }}</div>' },
	NcButton: { props: ['disabled'], emits: ['click'], template: '<button type="button" class="nc-button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
}

/**
 * Mount the picker with one Nextcloud widget, a pie chart and an embedded page
 * the role may not add.
 *
 * @param {object} props Extra props.
 * @return {Promise<object>} The wrapper, after the availability check.
 */
async function mountPicker(props = {}) {
	const wrapper = mount(WidgetPickerModal, {
		props: {
			open: true,
			widgets: [{ id: 'notes', title: 'Notes', itemApiVersions: [] }],
			customTypes: [
				{ type: 'pie-chart', title: 'Pie chart' },
				{ type: 'iframe', title: 'Embedded page' },
			],
			allowedWidgets: ['notes', 'pie-chart'],
			...props,
		},
		global: { stubs, mocks: { t: (_app, text) => text } },
	})
	await flushPromises()
	return wrapper
}

const line = (wrapper) => wrapper.find('[data-testid="widget-picker-assistant"]')

/**
 * Type a need and ask.
 *
 * @param {object} wrapper The picker.
 * @param {string} need The need.
 * @return {Promise<void>}
 */
async function ask(wrapper, need) {
	await line(wrapper).find('input').setValue(need)
	await line(wrapper).trigger('submit')
	await flushPromises()
}

beforeEach(() => {
	vi.clearAllMocks()
	resetAssistantAvailability()
	api.widgetAssistant.mockResolvedValue({ data: { available: true } })
})

describe('WidgetPickerModal assistant line', () => {
	it('is not shown when the server has no assistant', async () => {
		api.widgetAssistant.mockResolvedValue({ data: { available: false } })
		const wrapper = await mountPicker()
		expect(line(wrapper).exists()).toBe(false)
	})

	it('is not shown when the availability read fails', async () => {
		api.widgetAssistant.mockRejectedValue(new Error('offline'))
		const wrapper = await mountPicker()
		expect(line(wrapper).exists()).toBe(false)
	})

	it('sits above the search when the server has an assistant', async () => {
		const wrapper = await mountPicker()
		expect(line(wrapper).exists()).toBe(true)
		const html = wrapper.html()
		expect(html.indexOf('widget-picker__assistant')).toBeLessThan(html.indexOf('widget-picker__search'))
		expect(wrapper.find('[data-testid="widget-picker-assistant-ask"]').attributes('disabled')).toBeDefined()
	})

	it('sends the need and only the cards the reader may add', async () => {
		api.proposeWidget.mockResolvedValue({ data: { proposal: null } })
		const wrapper = await mountPicker()
		await ask(wrapper, '  a chart of open cases  ')

		const request = api.proposeWidget.mock.calls[0][0]
		expect(request.need).toBe('a chart of open cases')
		const keys = request.cards.map((card) => card.key)
		expect(keys).toContain('type:pie-chart')
		expect(keys).toContain('widget:notes')
		expect(keys).not.toContain('type:iframe')
		expect(request.cards.find((card) => card.key === 'type:pie-chart').name).toBe('Pie chart')
	})

	it('shows the proposal and places it with Place it like this', async () => {
		api.proposeWidget.mockResolvedValue({ data: { proposal: { key: 'type:pie-chart', name: 'Pie chart', reason: 'It shows shares at a glance.' } } })
		const wrapper = await mountPicker()
		await ask(wrapper, 'a chart of open cases')

		const proposal = wrapper.find('[data-testid="widget-picker-assistant-proposal"]')
		expect(proposal.text()).toContain('Pie chart')
		expect(proposal.text()).toContain('It shows shares at a glance.')

		await wrapper.find('[data-testid="widget-picker-assistant-place"]').trigger('click')
		expect(wrapper.emitted('addCustom')).toEqual([['pie-chart']])
		expect(wrapper.find('[data-testid="widget-picker-assistant-proposal"]').exists()).toBe(false)
	})

	it('places a Nextcloud widget and closes the picker', async () => {
		api.proposeWidget.mockResolvedValue({ data: { proposal: { key: 'widget:notes', reason: '' } } })
		const wrapper = await mountPicker()
		await ask(wrapper, 'my notes')
		await wrapper.find('[data-testid="widget-picker-assistant-place"]').trigger('click')
		expect(wrapper.emitted('add')).toEqual([['notes']])
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it('says no widget fits when the answer names no offered card', async () => {
		api.proposeWidget.mockResolvedValue({ data: { proposal: { key: 'type:iframe', reason: 'Embed it' } } })
		const wrapper = await mountPicker()
		await ask(wrapper, 'embed a page')
		expect(wrapper.find('[data-testid="widget-picker-assistant-proposal"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="widget-picker-assistant-message"]').text()).toBe('No widget fits; choose one below')
	})

	it('says the assistant could not answer when the request fails', async () => {
		api.proposeWidget.mockRejectedValue(new Error('503'))
		const wrapper = await mountPicker()
		await ask(wrapper, 'anything')
		expect(wrapper.find('[data-testid="widget-picker-assistant-message"]').text()).toBe('The assistant could not answer; choose a widget below')
	})

	it('does not ask for an empty need', async () => {
		const wrapper = await mountPicker()
		await ask(wrapper, '   ')
		expect(api.proposeWidget).not.toHaveBeenCalled()
	})

	it('forgets the need and the proposal when the picker closes', async () => {
		api.proposeWidget.mockResolvedValue({ data: { proposal: { key: 'widget:notes', reason: '' } } })
		const wrapper = await mountPicker()
		await ask(wrapper, 'my notes')
		await wrapper.setProps({ open: false })
		expect(wrapper.vm.assistantNeed).toBe('')
		expect(wrapper.vm.assistantProposal).toBe(null)
		expect(wrapper.vm.assistantMessage).toBe('')
	})

	it('reads the availability once per page', async () => {
		await mountPicker()
		await mountPicker()
		expect(api.widgetAssistant).toHaveBeenCalledTimes(1)
	})
})
