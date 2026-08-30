/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `SearchWidget.vue` (tile-quick-search
 * REQ-QSEARCH-001/002/004) — the host that replaced `WorkspaceApp.vue`'s
 * search wiring when quick search became a placeable widget.
 *
 * Covers what this component itself decides: which items are searchable,
 * how the two fallback layers resolve, and that filtering writes store state
 * rather than touching tiles.
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RuntimeShellSearch from '../../../RuntimeShellSearch.vue'
import SearchWidget from '../SearchWidget.vue'
import { useDashboardStore } from '../../../../stores/dashboard.js'
import { useTileSearchStore } from '../../../../stores/tileSearch.js'
import { useWidgetStore } from '../../../../stores/widgets.js'

let pinia

beforeEach(() => {
	globalThis.t = (_app, key) => key
	pinia = createPinia()
	// Both are needed: `setActivePinia` for the direct `useXStore()` calls in
	// this file and in the component's methods, and the plugin install below
	// so the component's `mapState` resolves through `$pinia` the way it does
	// in the real app rather than through the active-instance fallback.
	setActivePinia(pinia)
})

/**
 * @param {object} [options] mount options.
 * @param {object} [options.content] the widget's persisted content.
 * @param {string} [options.adminFallback] the injected admin setting.
 * @param {Array} [options.widgetPlacements] placements to seed.
 * @param {Array} [options.availableWidgets] widget catalog to seed.
 * @return {import('@vue/test-utils').VueWrapper} the mounted widget.
 */
function mountWidget({
	content = {},
	adminFallback,
	widgetPlacements = [],
	availableWidgets = [],
} = {}) {
	useDashboardStore().widgetPlacements = widgetPlacements
	useWidgetStore().availableWidgets = availableWidgets
	return mount(SearchWidget, {
		propsData: { content, placement: { id: 1 } },
		global: {
			plugins: [pinia],
			provide:
				adminFallback === undefined
					? {}
					: { quicksearchFallbackTarget: adminFallback },
			stubs: { RuntimeShellSearch: true },
		},
	})
}

describe('SearchWidget — searchable items (REQ-QSEARCH-002)', () => {
	it('derives items from the LIVE store, not an initial-state snapshot', async () => {
		const wrapper = mountWidget({
			widgetPlacements: [
				{ id: 7, tileType: 'custom', tileTitle: 'Zaaksysteem' },
			],
		})
		expect(wrapper.vm.searchableTiles).toEqual([
			{
				id: 7,
				label: 'Zaaksysteem',
				placement: wrapper.vm.widgetPlacements[0],
			},
		])

		// A placement added after mount must show up without a reload.
		useDashboardStore().widgetPlacements = [
			{ id: 7, tileType: 'custom', tileTitle: 'Zaaksysteem' },
			{ id: 8, tileType: 'custom', tileTitle: 'Deck' },
		]
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.searchableTiles.map((i) => i.label)).toEqual([
			'Zaaksysteem',
			'Deck',
		])
	})

	it('passes items and the resolved placeholder through to the bar', () => {
		const wrapper = mountWidget({
			content: { placeholder: 'Find an application…' },
			widgetPlacements: [{ id: 7, tileType: 'custom', tileTitle: 'Deck' }],
		})
		const bar = wrapper.findComponent(RuntimeShellSearch)
		expect(bar.props('items')).toEqual(wrapper.vm.searchableTiles)
		expect(bar.props('placeholder')).toBe('Find an application…')
	})
})

describe('SearchWidget — fallback layering (REQ-QSEARCH-004)', () => {
	it('inherits the admin setting when the widget sets no override', () => {
		const wrapper = mountWidget({
			content: { fallbackTarget: '' },
			adminFallback: 'unified-search',
		})
		expect(wrapper.vm.effectiveFallbackTarget).toBe('unified-search')
		expect(
			wrapper.findComponent(RuntimeShellSearch).props('fallbackTarget'),
		).toBe('unified-search')
	})

	it("lets the widget's own override win over the admin setting", () => {
		const wrapper = mountWidget({
			content: { fallbackTarget: 'none' },
			adminFallback: 'unified-search',
		})
		expect(
			wrapper.vm.effectiveFallbackTarget,
			'a widget set to "none" must not inherit the admin unified-search',
		).toBe('none')
	})

	it('defaults to "none" when neither layer is configured', () => {
		const wrapper = mountWidget()
		expect(wrapper.vm.effectiveFallbackTarget).toBe('none')
	})
})

describe('SearchWidget — dimming is store state (REQ-QSEARCH-002)', () => {
	it('writes the match set to the store on filter', () => {
		const wrapper = mountWidget()
		wrapper.vm.onSearchFilter([7])
		expect(useTileSearchStore().isDimmed(7)).toBe(false)
		expect(useTileSearchStore().isDimmed(8)).toBe(true)
	})

	it('clears the store and returns focus to the grid on clear', () => {
		const grid = document.createElement('div')
		grid.id = 'launchpad-main-content'
		grid.tabIndex = -1
		document.body.appendChild(grid)
		const focusSpy = vi.spyOn(grid, 'focus')

		const wrapper = mountWidget()
		wrapper.vm.onSearchFilter([7])
		wrapper.vm.onSearchClear()

		expect(useTileSearchStore().hasActiveQuery).toBe(false)
		expect(focusSpy).toHaveBeenCalled()
		document.body.innerHTML = ''
	})

	it('clears the store when the widget is removed from the dashboard', () => {
		const wrapper = mountWidget()
		wrapper.vm.onSearchFilter([7])
		expect(useTileSearchStore().hasActiveQuery).toBe(true)

		wrapper.unmount()
		expect(
			useTileSearchStore().hasActiveQuery,
			'a deleted search widget must not leave tiles dimmed behind it',
		).toBe(false)
	})
})
