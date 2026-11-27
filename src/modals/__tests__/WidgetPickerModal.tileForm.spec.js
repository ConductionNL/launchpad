/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The App or link tile's form opens inside Add widget
 * (add-widget-dialog-with-miniatures REQ-WDG-040, app-link-tile-widget
 * REQ-APPTILE-004): placing its card swaps the cards for the form; the
 * form's results go to the host; Cancel goes back to the cards.
 *
 * @spec openspec/changes/archive/2026-11-27-add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-the-widget-picker-as-lpwidgetkiezen-draws-it-req-wdg-040
 */
import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import WidgetPickerModal from '../WidgetPickerModal.vue'

const stubs = {
	NcModal: { name: 'NcModal', template: '<div class="nc-modal"><slot /></div>' },
	NcTextField: { name: 'NcTextField', props: ['modelValue'], template: '<input class="nc-text" />' },
	NcButton: { name: 'NcButton', props: ['disabled'], emits: ['click'], template: '<button class="nc-button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
	NcEmptyContent: { template: '<div class="nc-empty"><slot /></div>' },
	WidgetMiniature: { template: '<div class="widget-miniature" />' },
	TileEditor: {
		name: 'TileEditor',
		props: { embedded: Boolean, open: Boolean, tile: Object, dashboardName: String, catalogueApps: Array, discoveredApps: Array, foundServices: Array, officeTiles: Array, placements: Array, requestAppFn: Function },
		emits: ['close', 'save', 'addApps', 'importBookmarks'],
		template: '<div class="tile-editor-stub" />',
	},
}

const SERVICES = [{ name: 'Grafana', url: 'http://localhost:3000' }]
const OFFICE = [{ title: 'Word', linkType: 'create', linkValue: 'doc' }]
const PLACEMENTS = [{ id: 1, widgetId: 'clock' }]

function mountPicker(props = {}) {
	return mount(WidgetPickerModal, {
		props: {
			open: true,
			dashboardName: 'My workspace',
			widgets: [],
			customTypes: [{ type: 'tile', title: 'App or link tile' }, { type: 'clock', title: 'Clock' }],
			foundServices: SERVICES,
			officeTiles: OFFICE,
			placements: PLACEMENTS,
			...props,
		},
		global: { stubs, mocks: { t: (_app, text) => text } },
	})
}

async function placeTile(wrapper) {
	await wrapper.find('[data-testid="widget-picker-type-tile"]').trigger('click')
	await wrapper.find('[data-testid="widget-picker-add"]').trigger('click')
	await flushPromises()
}

describe('the App or link tile form inside Add widget', () => {
	it('swaps the cards for the embedded form and asks the host for its lists', async () => {
		const wrapper = mountPicker()
		await placeTile(wrapper)

		const form = wrapper.findComponent({ name: 'TileEditor' })
		expect(form.exists()).toBe(true)
		expect(form.props()).toMatchObject({ embedded: true, tile: null, dashboardName: 'My workspace', foundServices: SERVICES, officeTiles: OFFICE, placements: PLACEMENTS })
		expect(wrapper.find('.widget-picker__widget').exists()).toBe(false)
		expect(wrapper.emitted('tileForm')).toHaveLength(1)
		expect(wrapper.emitted('addCustom')).toBeUndefined()
	})

	it('hands the form\'s results to the host', async () => {
		const wrapper = mountPicker()
		await placeTile(wrapper)
		const form = wrapper.findComponent({ name: 'TileEditor' })
		const file = { name: 'bookmarks.html' }

		form.vm.$emit('save', { title: 'Intranet' })
		form.vm.$emit('addApps', { choices: [], appearance: {} })
		form.vm.$emit('importBookmarks', file)

		expect(wrapper.emitted('tileSave')[0]).toEqual([{ title: 'Intranet' }])
		expect(wrapper.emitted('addApps')[0]).toEqual([{ choices: [], appearance: {} }])
		expect(wrapper.emitted('importBookmarks')[0]).toEqual([file])
	})

	it('goes back to the cards on Cancel, and opens on the cards next time', async () => {
		const wrapper = mountPicker()
		await placeTile(wrapper)
		wrapper.findComponent({ name: 'TileEditor' }).vm.$emit('close')
		await flushPromises()
		expect(wrapper.find('.widget-picker__widget').exists()).toBe(true)

		await placeTile(wrapper)
		await wrapper.setProps({ open: false })
		await wrapper.setProps({ open: true })
		await flushPromises()
		expect(wrapper.findComponent({ name: 'TileEditor' }).exists()).toBe(false)
	})

	it('places any other card as before', async () => {
		const wrapper = mountPicker()
		await wrapper.find('[data-testid="widget-picker-type-clock"]').trigger('click')
		await wrapper.find('[data-testid="widget-picker-add"]').trigger('click')
		expect(wrapper.emitted('addCustom')[0]).toEqual(['clock'])
		expect(wrapper.emitted('tileForm')).toBeUndefined()
	})
})
