/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `WorkspaceApp.vue`. Covers the runtime-shell:
 *  - REQ-SHELL-002 (canEdit gate),
 *  - the in-page edit toolbar was removed — editing actions now live in
 *    the per-dashboard cog menu (DashboardRowActions), so the page chrome
 *    stays clean (regression-guarded below),
 *  - REQ-SHELL-004 (hamburger as `NcButton type="tertiary"` + active-dashboard label),
 *  - REQ-SHELL-005 (empty-state branch on `allowUserDashboards`),
 *  - REQ-SHELL-006 (SidebarBackdrop rendered when sidebarOpen).
 *
 * The embedded Views.vue child is stubbed because it depends on Pinia
 * stores + GridStack — neither is in scope for runtime-shell unit tests.
 *
 * @spec openspec/changes/runtime-shell/tasks.md#task-11
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'

import WorkspaceApp from '../WorkspaceApp.vue'
import RuntimeShellSearch from '../../components/RuntimeShellSearch.vue'
import { useDashboardStore } from '../../stores/dashboard.js'
import { useWidgetStore } from '../../stores/widgets.js'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

/**
 * Mount helper supplying the inject defaults that mirror the typed
 * initial-state contract (REQ-INIT-002). Each call accepts an `inject`
 * override so individual tests target a single key.
 *
 * @param {object} options mount overrides
 * @param {object} [options.inject] inject overrides
 * @return {import('@vue/test-utils').Wrapper}
 */
function mountShell(options = {}) {
	const inject = {
		isAdmin: false,
		dashboardSource: 'group',
		activeDashboardId: '',
		allowUserDashboards: false,
		layout: [],
		widgets: [],
		userDashboards: [],
		groupDashboards: [],
		...(options.inject || {}),
	}
	return mount(WorkspaceApp, {
		pinia: options.pinia || createPinia(),
		provide: inject,
		stubs: {
			Views: {
				name: 'Views',
				template: '<div class="views-stub" />',
				methods: {
					openCustomWidgetModal: vi.fn(),
				},
			},
			SidebarBackdrop: true,
			DashboardSwitcherSidebar: true,
			// Stub `NcButton` to a transparent `<button>` so the test
			// can drive `.workspace-shell__hamburger` clicks without
			// mounting the full Nextcloud Vue button.
			// `emits: ['click']` is load-bearing under Vue 3: listeners now
			// live in `$attrs` as `onClick`, so `v-bind="$attrs"` would bind
			// the parent's handler natively *and* `$emit('click')` would
			// fall through to it again — firing `toggleSidebar` twice.
			// Declaring the event removes `onClick` from `$attrs`, leaving
			// `$emit` as the single path. Vue 2 kept listeners in the
			// separate `$listeners` object, so the pattern was safe there.
			NcButton: {
				name: 'NcButton',
				props: ['type', 'disabled'],
				emits: ['click'],
				template:
					'<button v-bind="$attrs" :data-nc-button-type="type" :disabled="disabled" @click="$emit(\'click\', $event)"><slot name="icon" /><slot /></button>',
				inheritAttrs: false,
			},
			MenuIcon: true,
		},
	})
}

describe('WorkspaceApp', () => {
	describe('REQ-SHELL-002: canEdit permission rule', () => {
		it('canEdit true for admin on group dashboard', () => {
			const wrapper = mountShell({
				inject: {
					isAdmin: true,
					dashboardSource: 'group',
					activeDashboardId: 'd1',
				},
			})
			expect(wrapper.vm.canEdit).toBe(true)
		})

		it('canEdit true for owner of personal dashboard', () => {
			const wrapper = mountShell({
				inject: {
					isAdmin: false,
					dashboardSource: 'user',
					activeDashboardId: 'd1',
				},
			})
			expect(wrapper.vm.canEdit).toBe(true)
		})

		it('canEdit false for non-admin viewing group-shared dashboard', () => {
			const wrapper = mountShell({
				inject: {
					isAdmin: false,
					dashboardSource: 'group',
					activeDashboardId: 'd1',
				},
			})
			expect(wrapper.vm.canEdit).toBe(false)
		})
	})

	describe('edit toolbar removed (actions live in the dashboard cog menu)', () => {
		it('does not render an in-page edit toolbar even when canEdit=true', () => {
			const wrapper = mountShell({
				inject: {
					isAdmin: true,
					dashboardSource: 'group',
					activeDashboardId: 'd1',
				},
			})
			// The Region-3 toolbar (Add Widget + Save Layout) was removed;
			// editing actions now live in the per-dashboard cog menu
			// (DashboardRowActions), so the page chrome stays clean.
			expect(wrapper.find('.workspace-shell__toolbar').exists()).toBe(false)
			expect(wrapper.find('.workspace-shell__save-button').exists()).toBe(
				false,
			)
			expect(
				wrapper.find('[data-test="add-widget-toolbar-button"]').exists(),
			).toBe(false)
		})
	})

	describe('REQ-SHELL-004: hamburger and active-dashboard label', () => {
		it('title strip is hidden when sidebar is closed', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			expect(wrapper.vm.sidebarOpen).toBe(false)
			expect(wrapper.find('.workspace-shell__strip').exists()).toBe(false)
			expect(wrapper.find('.workspace-shell__hamburger').exists()).toBe(false)
			expect(wrapper.find('.workspace-shell__title').exists()).toBe(false)
		})

		it('title strip with hamburger appears when sidebar is open', async () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			wrapper.vm.sidebarOpen = true
			await wrapper.vm.$nextTick()
			const strip = wrapper.find('.workspace-shell__strip')
			expect(strip.exists()).toBe(true)
			const hamburger = wrapper.find('.workspace-shell__hamburger')
			expect(hamburger.exists()).toBe(true)
			// Stubbed NcButton mirrors the `type` prop onto a data attribute.
			expect(hamburger.attributes('data-nc-button-type')).toBe('tertiary')
			// Clicking the in-strip hamburger closes the sidebar.
			await hamburger.trigger('click')
			expect(wrapper.vm.sidebarOpen).toBe(false)
		})

		it('title strip shows dashboard name as an H1 heading, NOT as a select', async () => {
			const wrapper = mountShell({
				inject: {
					activeDashboardId: 'd1',
					userDashboards: [{ id: 'd1', name: 'Marketing Overview' }],
				},
			})
			wrapper.vm.sidebarOpen = true
			await wrapper.vm.$nextTick()
			const title = wrapper.find('.workspace-shell__title')
			expect(title.exists()).toBe(true)
			expect(title.element.tagName).toBe('H1')
			expect(title.text()).toBe('Marketing Overview')
			// No <select> control should appear in the title strip.
			expect(wrapper.find('.workspace-shell__strip select').exists()).toBe(
				false,
			)
		})
	})

	describe('REQ-SHELL-005: empty state', () => {
		it('empty state with allowUserDashboards renders Create CTA', () => {
			const wrapper = mountShell({
				inject: { activeDashboardId: '', allowUserDashboards: true },
			})
			const cta = wrapper.find('.workspace-shell__empty-cta')
			expect(cta.exists()).toBe(true)
			expect(cta.text()).toBe('Create your first dashboard')
		})

		it('empty state without allowUserDashboards hides Create CTA', () => {
			const wrapper = mountShell({
				inject: { activeDashboardId: '', allowUserDashboards: false },
			})
			expect(wrapper.find('.workspace-shell__empty-cta').exists()).toBe(false)
			expect(wrapper.find('.workspace-shell__empty-hint').text()).toBe(
				'Contact your administrator',
			)
		})
	})

	describe('REQ-SHELL-006: sidebar backdrop', () => {
		it('SidebarBackdrop is rendered when sidebarOpen is true', async () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			// Initially no backdrop.
			expect(wrapper.findComponent({ name: 'SidebarBackdrop' }).exists()).toBe(
				false,
			)
			wrapper.vm.sidebarOpen = true
			await wrapper.vm.$nextTick()
			expect(wrapper.findComponent({ name: 'SidebarBackdrop' }).exists()).toBe(
				true,
			)
		})

		it('closeSidebar sets sidebarOpen to false', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			wrapper.vm.sidebarOpen = true
			wrapper.vm.closeSidebar()
			expect(wrapper.vm.sidebarOpen).toBe(false)
		})
	})

	describe('tile-quick-search: RuntimeShellSearch wiring', () => {
		/**
		 * Build a pinia instance pre-populated with dashboard-store /
		 * widget-store state so `searchableTiles` has something to derive.
		 *
		 * @param {object} [options] store overrides.
		 * @param {Array} [options.widgetPlacements] placements to seed.
		 * @param {Array} [options.availableWidgets] widget definitions to seed.
		 * @return {import('pinia').Pinia}
		 */
		function buildPinia({ widgetPlacements = [], availableWidgets = [] } = {}) {
			const pinia = createPinia()
			useDashboardStore(pinia).widgetPlacements = widgetPlacements
			useWidgetStore(pinia).availableWidgets = availableWidgets
			return pinia
		}

		it('renders the search bar above the grid when a dashboard is active', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			expect(wrapper.findComponent(RuntimeShellSearch).exists()).toBe(true)
		})

		it('does not render the search bar in the empty state', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: '' } })
			expect(wrapper.findComponent(RuntimeShellSearch).exists()).toBe(false)
		})

		it('searchableTiles derives a tile placement label from tileTitle', () => {
			const pinia = buildPinia({
				widgetPlacements: [
					{ id: 'p1', tileType: 'custom', tileTitle: 'Zaaksysteem' },
				],
			})
			const wrapper = mountShell({
				inject: { activeDashboardId: 'd1' },
				pinia,
			})
			expect(wrapper.vm.searchableTiles).toEqual([
				{
					id: 'p1',
					label: 'Zaaksysteem',
					placement: wrapper.vm.widgetPlacements[0],
				},
			])
		})

		it('searchableTiles derives a widget placement label from customTitle, then widget.title, then a fallback', () => {
			const pinia = buildPinia({
				widgetPlacements: [
					{ id: 'p1', widgetId: 'w1', customTitle: 'My custom title' },
					{ id: 'p2', widgetId: 'w1' },
					{ id: 'p3', widgetId: 'unknown' },
				],
				availableWidgets: [{ id: 'w1', title: 'Weather' }],
			})
			const wrapper = mountShell({
				inject: { activeDashboardId: 'd1' },
				pinia,
			})
			const labels = wrapper.vm.searchableTiles.map((t) => t.label)
			expect(labels).toEqual(['My custom title', 'Weather', 'Widget'])
		})

		it('passes the searchableTiles + fallbackTarget through as props', () => {
			const pinia = buildPinia({
				widgetPlacements: [
					{ id: 'p1', tileType: 'custom', tileTitle: 'Zaaksysteem' },
				],
			})
			const wrapper = mountShell({
				inject: {
					activeDashboardId: 'd1',
					quicksearchFallbackTarget: 'unified-search',
				},
				pinia,
			})
			const search = wrapper.findComponent(RuntimeShellSearch)
			expect(search.props('items')).toEqual(wrapper.vm.searchableTiles)
			expect(search.props('fallbackTarget')).toBe('unified-search')
		})

		it('defaults fallbackTarget to "none" when the initial-state key is absent', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			expect(wrapper.vm.quicksearchFallbackTarget).toBe('none')
		})

		it('onSearchFilter dims non-matching grid items and leaves matches undimmed', async () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			const grid = wrapper.find('.workspace-shell__grid').element
			const matchEl = document.createElement('div')
			matchEl.className = 'launchpad-grid-item'
			matchEl.setAttribute('data-placement-id', 'match')
			const otherEl = document.createElement('div')
			otherEl.className = 'launchpad-grid-item'
			otherEl.setAttribute('data-placement-id', 'other')
			grid.appendChild(matchEl)
			grid.appendChild(otherEl)

			wrapper.vm.onSearchFilter(['match'])
			expect(matchEl.classList.contains('launchpad-grid-item--dimmed')).toBe(
				false,
			)
			expect(otherEl.classList.contains('launchpad-grid-item--dimmed')).toBe(
				true,
			)

			// null (query cleared) undims everything.
			wrapper.vm.onSearchFilter(null)
			expect(matchEl.classList.contains('launchpad-grid-item--dimmed')).toBe(
				false,
			)
			expect(otherEl.classList.contains('launchpad-grid-item--dimmed')).toBe(
				false,
			)
		})

		it('onSearchClear undims every tile and moves focus to the grid', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			const grid = wrapper.find('.workspace-shell__grid').element
			const el = document.createElement('div')
			el.className = 'launchpad-grid-item launchpad-grid-item--dimmed'
			el.setAttribute('data-placement-id', 'p1')
			grid.appendChild(el)

			const focusSpy = vi.spyOn(grid, 'focus')
			wrapper.vm.onSearchClear()
			expect(el.classList.contains('launchpad-grid-item--dimmed')).toBe(false)
			expect(focusSpy).toHaveBeenCalled()
		})

		it("onSearchOpen scrolls to and clicks the matched tile's rendered link", () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			const grid = wrapper.find('.workspace-shell__grid').element
			const el = document.createElement('div')
			el.className = 'launchpad-grid-item'
			el.setAttribute('data-placement-id', 'p1')
			// A hash-only href avoids jsdom's "navigation not implemented"
			// console noise when `.click()` fires below, while still
			// exercising the real `<a href>` activation path.
			const link = document.createElement('a')
			link.setAttribute('href', '#deck')
			el.appendChild(link)
			grid.appendChild(el)

			const scrollSpy = vi.fn()
			el.scrollIntoView = scrollSpy
			const clickSpy = vi.spyOn(link, 'click')

			wrapper.vm.onSearchOpen({
				id: 'p1',
				label: 'Deck',
				placement: { id: 'p1' },
			})
			expect(scrollSpy).toHaveBeenCalled()
			expect(clickSpy).toHaveBeenCalled()
		})

		/*
		 * launchpad#95 — TWO DEFECTS, ONE ROOT CAUSE, AND A FIXTURE THAT HID
		 * BOTH.
		 *
		 * Every test above seeds a placement id as a STRING (`'p1'`,
		 * `'match'`). `WidgetPlacement` rows arrive from
		 * `GET /api/dashboard/{id}` with an INTEGER `id` — the column is an
		 * auto-increment primary key — so no fixture in this file had ever
		 * exercised the type the product actually handles, and both defects
		 * below were invisible to a green suite:
		 *
		 *   1. `applySearchDimming()` compared `getAttribute()` (always a
		 *      string) against the raw ids with `Array.includes`, which does
		 *      not coerce. With integer ids nothing ever matched, so EVERY
		 *      tile was dimmed — including the ones the user searched for.
		 *   2. `activateSearchResult()` called `.replace()` on the id.
		 *      `Number.prototype.replace` does not exist, so Enter threw a
		 *      `TypeError` inside a Vue event handler and silently did
		 *      nothing.
		 *
		 * These two tests are the regression guard, and they are written
		 * against the production shape on purpose. Both are RED on the code
		 * as it stood before the fix: the first because `matchEl` is dimmed,
		 * the second because the call throws before reaching the link.
		 */
		it('onSearchFilter leaves an INTEGER-id match undimmed (launchpad#95)', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			const grid = wrapper.find('.workspace-shell__grid').element
			const matchEl = document.createElement('div')
			matchEl.className = 'launchpad-grid-item'
			matchEl.setAttribute('data-placement-id', '7')
			const otherEl = document.createElement('div')
			otherEl.className = 'launchpad-grid-item'
			otherEl.setAttribute('data-placement-id', '8')
			grid.appendChild(matchEl)
			grid.appendChild(otherEl)

			// The ids are numbers, exactly as `searchableTiles()` copies them
			// off the API row — NOT the strings the older fixtures used.
			wrapper.vm.onSearchFilter([7])

			expect(
				matchEl.classList.contains('launchpad-grid-item--dimmed'),
				'a matching tile must not be de-emphasised',
			).toBe(false)
			expect(
				otherEl.classList.contains('launchpad-grid-item--dimmed'),
				'CONTROL: a non-matching tile must still be de-emphasised, or the assertion above is satisfied by "nothing is ever dimmed"',
			).toBe(true)
		})

		it('onSearchOpen activates a tile whose placement id is an INTEGER (launchpad#95)', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			const grid = wrapper.find('.workspace-shell__grid').element
			const el = document.createElement('div')
			el.className = 'launchpad-grid-item'
			el.setAttribute('data-placement-id', '7')
			const link = document.createElement('a')
			link.setAttribute('href', '#deck')
			el.appendChild(link)
			grid.appendChild(el)

			const scrollSpy = vi.fn()
			el.scrollIntoView = scrollSpy
			const clickSpy = vi.spyOn(link, 'click')

			wrapper.vm.onSearchOpen({ id: 7, label: 'Deck', placement: { id: 7 } })

			expect(
				scrollSpy,
				'the matched tile must be scrolled into view',
			).toHaveBeenCalled()
			expect(
				clickSpy,
				"Enter must activate the tile's rendered link",
			).toHaveBeenCalled()
		})

		it('onSearchOpen ignores a placement with no id rather than looking for the string "null"', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			const grid = wrapper.find('.workspace-shell__grid').element
			const el = document.createElement('div')
			el.className = 'launchpad-grid-item'
			// A cell literally labelled "null" — the shape a bare String()
			// cast would go looking for, and find.
			el.setAttribute('data-placement-id', 'null')
			const link = document.createElement('a')
			link.setAttribute('href', '#deck')
			el.appendChild(link)
			grid.appendChild(el)
			const clickSpy = vi.spyOn(link, 'click')

			wrapper.vm.onSearchOpen({
				id: null,
				label: 'Deck',
				placement: { id: null },
			})

			expect(
				clickSpy,
				'a null id must not activate the tile that happens to be called "null"',
			).not.toHaveBeenCalled()
		})

		it('onSearchFallback opens a web-search URL in a new tab', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			const openSpy = vi.spyOn(window, 'open').mockImplementation(() => {})
			wrapper.vm.onSearchFallback({
				type: 'web-search',
				url: 'https://example.org/search?q=x',
			})
			expect(openSpy).toHaveBeenCalledWith(
				'https://example.org/search?q=x',
				'_blank',
				'noopener,noreferrer',
			)
			openSpy.mockRestore()
		})

		it('onSearchFallback dispatches a unified-search CustomEvent without navigating', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			const openSpy = vi.spyOn(window, 'open').mockImplementation(() => {})
			const dispatchSpy = vi.spyOn(window, 'dispatchEvent')
			wrapper.vm.onSearchFallback({
				type: 'unified-search',
				query: 'invoices',
			})
			expect(openSpy).not.toHaveBeenCalled()
			const dispatched = dispatchSpy.mock.calls.find(
				([e]) => e.type === 'nextcloud:unified-search.search',
			)
			expect(dispatched).toBeTruthy()
			expect(dispatched[0].detail).toEqual({ query: 'invoices' })
			openSpy.mockRestore()
			dispatchSpy.mockRestore()
		})

		it('onSearchFallback does nothing observable for a "none" action', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			const openSpy = vi.spyOn(window, 'open').mockImplementation(() => {})
			expect(() => wrapper.vm.onSearchFallback({ type: 'none' })).not.toThrow()
			expect(openSpy).not.toHaveBeenCalled()
			openSpy.mockRestore()
		})
	})
})
