/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * launcher-discover-server-services: the add flow lists the apps this server
 * runs for the user, marks the ones already on the dashboard, and a click
 * asks the host to place one as a tile.
 *
 * @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import WidgetPickerModal from '../WidgetPickerModal.vue'
import { discoveredAppIdsOnDashboard, tileFromDiscoveredApp } from '../../utils/discoveredTile.js'

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

describe('Discovered on this server', () => {
	it('lists the discovered apps with their names', () => {
		const wrapper = mountPicker()
		const section = wrapper.find('[data-testid="widget-picker-discovered"]')
		expect(section.exists()).toBe(true)
		expect(section.text()).toContain('Discovered on this server')
		expect(section.findAll('.widget-picker__widget').map((row) => row.text())).toEqual([
			expect.stringContaining('Deck'),
			expect.stringContaining('Context Chat'),
		])
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

	it('draws no section when nothing was discovered', () => {
		expect(mountPicker({ discoveredApps: [] }).find('[data-testid="widget-picker-discovered"]').exists()).toBe(false)
	})
})

describe('the tile a discovered app becomes', () => {
	it('opens a Nextcloud app by its id', () => {
		expect(tileFromDiscoveredApp(DECK)).toEqual({
			title: 'Deck',
			icon: '/apps/deck/img/deck.svg',
			iconType: 'url',
			linkType: 'app',
			linkValue: 'deck',
		})
	})

	it('opens another page by its address', () => {
		expect(tileFromDiscoveredApp(CHAT)).toMatchObject({ linkType: 'url', linkValue: '/apps/app_api/embedded/context_chat_ui/' })
	})

	it('reads which apps are on the dashboard from its app tiles', () => {
		expect(discoveredAppIdsOnDashboard([
			{ tileLinkType: 'app', tileLinkValue: 'deck' },
			{ tileLinkType: 'url', tileLinkValue: '/apps/app_api/embedded/context_chat_ui/' },
			{ widgetId: 'weather' },
		], [DECK, CHAT])).toEqual(['deck', 'context_chat_ui'])
	})
})
