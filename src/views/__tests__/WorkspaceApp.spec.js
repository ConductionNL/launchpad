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
import Vue from 'vue'
import { PiniaVuePlugin, createPinia } from 'pinia'

import WorkspaceApp from '../WorkspaceApp.vue'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

Vue.use(PiniaVuePlugin)

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
		pinia: createPinia(),
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
			NcButton: {
				name: 'NcButton',
				props: ['type', 'disabled'],
				template: '<button v-bind="$attrs" :data-nc-button-type="type" :disabled="disabled" @click="$emit(\'click\', $event)"><slot name="icon" /><slot /></button>',
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
				inject: { isAdmin: true, dashboardSource: 'group', activeDashboardId: 'd1' },
			})
			expect(wrapper.vm.canEdit).toBe(true)
		})

		it('canEdit true for owner of personal dashboard', () => {
			const wrapper = mountShell({
				inject: { isAdmin: false, dashboardSource: 'user', activeDashboardId: 'd1' },
			})
			expect(wrapper.vm.canEdit).toBe(true)
		})

		it('canEdit false for non-admin viewing group-shared dashboard', () => {
			const wrapper = mountShell({
				inject: { isAdmin: false, dashboardSource: 'group', activeDashboardId: 'd1' },
			})
			expect(wrapper.vm.canEdit).toBe(false)
		})
	})

	describe('edit toolbar removed (actions live in the dashboard cog menu)', () => {
		it('does not render an in-page edit toolbar even when canEdit=true', () => {
			const wrapper = mountShell({
				inject: { isAdmin: true, dashboardSource: 'group', activeDashboardId: 'd1' },
			})
			// The Region-3 toolbar (Add Widget + Save Layout) was removed;
			// editing actions now live in the per-dashboard cog menu
			// (DashboardRowActions), so the page chrome stays clean.
			expect(wrapper.find('.workspace-shell__toolbar').exists()).toBe(false)
			expect(wrapper.find('.workspace-shell__save-button').exists()).toBe(false)
			expect(wrapper.find('[data-test="add-widget-toolbar-button"]').exists()).toBe(false)
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
			expect(wrapper.find('.workspace-shell__strip select').exists()).toBe(false)
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
			expect(wrapper.find('.workspace-shell__empty-hint').text())
				.toBe('Contact your administrator')
		})
	})

	describe('REQ-SHELL-006: sidebar backdrop', () => {
		it('SidebarBackdrop is rendered when sidebarOpen is true', async () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			// Initially no backdrop.
			expect(wrapper.findComponent({ name: 'SidebarBackdrop' }).exists()).toBe(false)
			wrapper.vm.sidebarOpen = true
			await wrapper.vm.$nextTick()
			expect(wrapper.findComponent({ name: 'SidebarBackdrop' }).exists()).toBe(true)
		})

		it('closeSidebar sets sidebarOpen to false', () => {
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			wrapper.vm.sidebarOpen = true
			wrapper.vm.closeSidebar()
			expect(wrapper.vm.sidebarOpen).toBe(false)
		})
	})
})
