/**
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `CatalogView.vue` (widgets + legacy-widget-bridge
 * specs). Covers:
 *  - widgets are grouped by category (Built-in / Custom Tiles / Bridge)
 *  - the Bridge filter shows only bridge-sourced entries
 *  - group open / closed state persists to localStorage
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => `/index.php${p}` }))

// Keep the registry real but make the bridge deterministic.
vi.mock('../../services/widgetBridge.js', () => ({
	widgetBridge: { getRegisteredWidgetIds: () => ['weather', 'calendar-nc'] },
}))

import CatalogView, { CATALOG_OPEN_GROUPS_KEY } from '../CatalogView.vue'

const stubs = {
	NcEmptyContent: { template: '<div class="empty-stub"><slot /></div>' },
	IconRenderer: { template: '<span class="icon-stub" />' },
}

function mountCatalog(props = {}) {
	return mount(CatalogView, { propsData: props, stubs })
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	localStorage.clear()
})

describe('CatalogView', () => {
	it('groups widgets into Built-in, Custom Tiles, and Bridge', () => {
		const wrapper = mountCatalog({ bridgeIds: ['weather'] })
		expect(wrapper.find('[data-test="catalog-group-built-in"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="catalog-group-custom-tile"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="catalog-group-bridge"]').exists()).toBe(true)
	})

	it('Bridge filter shows only bridge-sourced entries', async () => {
		const wrapper = mountCatalog({ bridgeIds: ['weather', 'calendar-nc'] })
		await wrapper.find('[data-test="catalog-filter-bridge"]').trigger('click')
		// Match only group containers (the `__group` class), not the toggle
		// buttons which also start with `catalog-group-`.
		const groups = wrapper.findAll('.catalog-view__group')
		expect(groups.length).toBe(1)
		expect(wrapper.find('[data-test="catalog-group-bridge"]').exists()).toBe(true)
		// Native built-in group is filtered out.
		expect(wrapper.find('[data-test="catalog-group-built-in"]').exists()).toBe(false)
	})

	it('collapses a group and persists the closed state to localStorage', async () => {
		const wrapper = mountCatalog({ bridgeIds: [] })
		// Built-in starts open → cards present.
		expect(wrapper.find('[data-test="catalog-group-built-in"] [data-test="catalog-card"]').exists()).toBe(true)
		await wrapper.find('[data-test="catalog-group-toggle-built-in"]').trigger('click')
		// After collapse → no cards.
		expect(wrapper.find('[data-test="catalog-group-built-in"] [data-test="catalog-card"]').exists()).toBe(false)
		const stored = JSON.parse(localStorage.getItem(CATALOG_OPEN_GROUPS_KEY))
		expect(stored['built-in']).toBe(false)
	})

	it('restores persisted collapsed state on mount', () => {
		localStorage.setItem(CATALOG_OPEN_GROUPS_KEY, JSON.stringify({ 'built-in': false }))
		const wrapper = mountCatalog({ bridgeIds: [] })
		expect(wrapper.find('[data-test="catalog-group-built-in"] [data-test="catalog-card"]').exists()).toBe(false)
	})

	it('falls back to the runtime bridge adapter when bridgeIds prop is null', () => {
		const wrapper = mountCatalog()
		// The mocked bridge returns two ids → Bridge group present.
		expect(wrapper.find('[data-test="catalog-group-bridge"]').exists()).toBe(true)
	})
})
