/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * launcher-discover-server-services: the add flow lists the apps this server
 * runs for the user, marks the ones already on the dashboard, and a click
 * asks the host to place one as a tile.
 *
 * @spec openspec/changes/archive/2026-11-19-launcher-discover-server-services/specs/tiles/spec.md
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import WidgetPickerModal from '../WidgetPickerModal.vue'
import { appTileFromDiscoveredApp, discoveredAppIdsOnDashboard } from '../../utils/discoveredTile.js'

const stubs = {
	NcModal: { template: '<div class="nc-modal"><slot /></div>' },
	NcTextField: {
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<input class="nc-text" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcEmptyContent: { template: '<div class="nc-empty"><slot name="icon" /></div>' },
}

const DECK = { appId: 'deck', name: 'Deck', href: '/apps/deck/', icon: '/apps/deck/img/deck.svg' }
const CHAT = { appId: 'context_chat_ui', name: 'Context Chat', href: '/apps/app_api/embedded/context_chat_ui/', icon: '/apps/app_api/img/app.svg' }

function mountPicker(props = {}) {
	return mount(WidgetPickerModal, {
		props: { open: true, widgets: [], discoveredApps: [DECK, CHAT], placedAppIds: ['deck'], ...props },
		global: { stubs, mocks: { t: (_app, text) => text } },
	})
}

describe('App tiles', () => {
	it('lists the discovered apps with their names', () => {
		const wrapper = mountPicker()
		const section = wrapper.find('[data-testid="widget-picker-discovered"]')
		expect(section.exists()).toBe(true)
		expect(section.text()).toContain('App tiles')
		expect(section.findAll('[data-testid^="widget-picker-app-tile-"]').map((row) => row.text())).toEqual([
			expect.stringContaining('Deck'),
			expect.stringContaining('Context Chat'),
		])
		expect(section.find('[data-testid="widget-picker-custom-tile"]').text()).toContain('Custom tile')
	})

	it('marks an app already on the dashboard', () => {
		const rows = mountPicker().findAll('[data-testid="widget-picker-discovered"] .widget-picker__widget')
		expect(rows[0].text()).toContain('On this dashboard')
		expect(rows[1].text()).not.toContain('On this dashboard')
	})

	it('narrows with the search box', async () => {
		const wrapper = mountPicker()
		await wrapper.find('.nc-text').setValue('chat')
		const rows = wrapper.findAll('[data-testid="widget-picker-discovered"] .widget-picker__widget')
		expect(rows).toHaveLength(1)
		expect(rows[0].text()).toContain('Context Chat')
	})

	it('asks the host to place the app on a click, also when it is there already', async () => {
		const wrapper = mountPicker()
		await wrapper.findAll('[data-testid="widget-picker-discovered"] .widget-picker__widget')[0].trigger('click')
		expect(wrapper.emitted('addApp')[0]).toEqual([DECK])
	})

	it('draws each app entry in the colour its tile gets', () => {
		const swatch = mountPicker().get('[data-testid="widget-picker-app-tile-deck"] .widget-picker__tile-swatch')
		expect(swatch.attributes('style')).toContain('--cn-app-tile-deck')
	})

	it('offers a custom tile that opens the tile form', async () => {
		const wrapper = mountPicker({ discoveredApps: [] })
		await wrapper.get('[data-testid="widget-picker-custom-tile"]').trigger('click')
		expect(wrapper.emitted('addCustom')[0]).toEqual(['tile'])
	})

	it('leaves the custom tile out when the search does not match it', async () => {
		const wrapper = mountPicker({ discoveredApps: [] })
		await wrapper.find('.nc-text').setValue('weather')
		expect(wrapper.find('[data-testid="widget-picker-discovered"]').exists()).toBe(false)
	})
})

describe('the tile a discovered app becomes', () => {
	it('is an app tile for that app, in its default colour, with its count', () => {
		expect(appTileFromDiscoveredApp(DECK)).toEqual({
			type: 'app-tile',
			content: { appId: 'deck', colour: '', showCount: true },
		})
	})

	it('names an app without its own page by its menu id too', () => {
		expect(appTileFromDiscoveredApp(CHAT).content.appId).toBe('context_chat_ui')
	})

	it('reads which apps are on the dashboard from its app-tile widgets', () => {
		expect(discoveredAppIdsOnDashboard([
			{ widgetId: 'app-tile', content: { appId: 'deck' } },
			{ widgetId: 'app-tile', content: {} },
		], [DECK, CHAT])).toEqual(['deck'])
	})

	it('reads which apps are on the dashboard from its app tiles', () => {
		expect(discoveredAppIdsOnDashboard([
			{ tileLinkType: 'app', tileLinkValue: 'deck' },
			{ tileLinkType: 'url', tileLinkValue: '/apps/app_api/embedded/context_chat_ui/' },
			{ widgetId: 'weather' },
		], [DECK, CHAT])).toEqual(['deck', 'context_chat_ui'])
	})
})
