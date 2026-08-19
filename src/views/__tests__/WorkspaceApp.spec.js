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

import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RuntimeShellSearch from '../../components/RuntimeShellSearch.vue'
import WorkspaceApp from '../WorkspaceApp.vue'

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

	describe('tile-quick-search: the shell no longer owns a search bar', () => {
		/*
		 * Quick search moved out of the page chrome and became the `search`
		 * widget type (REQ-QSEARCH-001), so everything this block used to
		 * assert now lives with its new owner:
		 *
		 *   - label resolution, activation, focus-grid and fallback
		 *     → src/composables/__tests__/useTileSearchHost.spec.js
		 *   - dimming, including the launchpad#95 integer-vs-string guard
		 *     → src/stores/__tests__/tileSearch.spec.js
		 *   - prop wiring and fallback layering
		 *     → src/components/Widgets/Renderers/__tests__/SearchWidget.spec.js
		 *
		 * What remains here is the assertion this component is still
		 * responsible for: that it renders no search bar of its own, in
		 * either branch. Without it, reintroducing the strip would go
		 * unnoticed.
		 */
		it('renders no search bar when a dashboard is active', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			expect(wrapper.findComponent(RuntimeShellSearch).exists()).toBe(false)
			expect(wrapper.find('.workspace-shell__search-bar').exists()).toBe(false)
		})

		it('renders no search bar in the empty state', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: '' } })
			expect(wrapper.findComponent(RuntimeShellSearch).exists()).toBe(false)
		})

		it('no longer exposes the removed host wiring', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			for (const name of [
				'tileSearchLabel',
				'onSearchOpen',
				'onSearchFilter',
				'onSearchFallback',
				'onSearchClear',
				'applySearchDimming',
				'activateSearchResult',
				'focusGrid',
			]) {
				expect(
					typeof wrapper.vm[name],
					`${name} must not survive on WorkspaceApp — it moved to useTileSearchHost/SearchWidget`,
				).toBe('undefined')
			}
		})
	})
})
