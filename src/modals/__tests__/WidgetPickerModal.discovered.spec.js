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
	NcButton: { props: ['disabled'], emits: ['click'], template: '<button class="nc-button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
}

const DECK = { appId: 'deck', name: 'Deck', href: '/apps/deck/', icon: '/apps/deck/img/deck.svg' }
const CHAT = { appId: 'context_chat_ui', name: 'Context Chat', href: '/apps/app_api/embedded/context_chat_ui/', icon: '/apps/app_api/img/app.svg' }

function mountPicker(props = {}) {
	return mount(WidgetPickerModal, {
		props: { open: true, widgets: [], discoveredApps: [DECK, CHAT], placedAppIds: ['deck'], ...props },
		global: { stubs, mocks: { t: (_app, text) => text } },
	})
}

const SECTION = '[data-testid="widget-picker-section-tiles"]'

// app-link-tile-widget REQ-WDG-042 removed: the picker has no App tiles
// section; the apps are chosen in the App or link tile form.
describe('the picker without App tiles', () => {
	it('lists no discovered app and no Own tile', () => {
		const wrapper = mountPicker()
		expect(wrapper.find(SECTION).exists()).toBe(false)
		expect(wrapper.text()).not.toContain('Deck')
	})
})

describe('the tile a discovered app becomes', () => {
	it('is an App or link tile in app mode for that app, white text, with its count', () => {
		expect(appTileFromDiscoveredApp(DECK)).toEqual({
			type: 'tile',
			content: { mode: 'app', appId: 'deck', colourToken: '', textColour: 'white', showCount: true, pinned: false },
		})
		expect(appTileFromDiscoveredApp(DECK, { colourToken: '--sc-accent', iconOnly: true }).content).toMatchObject({ colourToken: '--sc-accent', iconOnly: true })
	})

	it('names an app without its own page by its menu id too', () => {
		expect(appTileFromDiscoveredApp(CHAT).content.appId).toBe('context_chat_ui')
	})

	it('reads which apps are on the dashboard from its app-tile widgets', () => {
		expect(discoveredAppIdsOnDashboard([
			{ widgetId: 'app-tile', content: { appId: 'deck' } },
			{ widgetId: 'app-tile', content: {} },
			{ widgetId: 'tile', content: { mode: 'app', appId: 'context_chat_ui' } },
		], [DECK, CHAT])).toEqual(['deck', 'context_chat_ui'])
	})

	it('reads which apps are on the dashboard from its app tiles', () => {
		expect(discoveredAppIdsOnDashboard([
			{ tileLinkType: 'app', tileLinkValue: 'deck' },
			{ tileLinkType: 'url', tileLinkValue: '/apps/app_api/embedded/context_chat_ui/' },
			{ widgetId: 'weather' },
		], [DECK, CHAT])).toEqual(['deck', 'context_chat_ui'])
	})
})
