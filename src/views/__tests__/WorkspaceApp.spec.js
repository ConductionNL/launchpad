/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `WorkspaceApp.vue`. Covers REQ-SHELL-001..007:
 *  - REQ-SHELL-002 (canEdit gate),
 *  - REQ-SHELL-003 (toolbar present when canEdit, Save button disabled in-flight),
 *  - REQ-SHELL-004 (hamburger as `NcButton type="tertiary"` + active-dashboard label),
 *  - REQ-SHELL-005 (empty-state branch on `allowUserDashboards`),
 *  - REQ-SHELL-006 (SidebarBackdrop rendered when sidebarOpen),
 *  - REQ-SHELL-007 (lifecycle cleanup of the `document.click` listener).
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

	describe('REQ-SHELL-003: toolbar contents and visibility', () => {
		it('toolbar is present in DOM for admin on group dashboard (canEdit=true)', () => {
			const wrapper = mountShell({
				inject: { isAdmin: true, dashboardSource: 'group', activeDashboardId: 'd1' },
			})
			expect(wrapper.find('.workspace-shell__toolbar').exists()).toBe(true)
		})

		it('toolbar is present in DOM for non-admin on personal dashboard (canEdit=true)', () => {
			const wrapper = mountShell({
				inject: { isAdmin: false, dashboardSource: 'user', activeDashboardId: 'd1' },
			})
			expect(wrapper.find('.workspace-shell__toolbar').exists()).toBe(true)
		})

		it('toolbar is NOT in DOM for non-admin on group dashboard (canEdit=false)', () => {
			const wrapper = mountShell({
				inject: { isAdmin: false, dashboardSource: 'group', activeDashboardId: 'd1' },
			})
			// v-if removes from DOM entirely — not v-show
			expect(wrapper.find('.workspace-shell__toolbar').exists()).toBe(false)
		})

		it('toolbar is NOT in DOM when no active dashboard (empty state)', () => {
			const wrapper = mountShell({
				inject: { isAdmin: true, dashboardSource: 'group', activeDashboardId: '' },
			})
			expect(wrapper.find('.workspace-shell__toolbar').exists()).toBe(false)
		})

		it('Save Layout button exists with correct test attribute when canEdit', () => {
			const wrapper = mountShell({
				inject: { isAdmin: true, dashboardSource: 'group', activeDashboardId: 'd1' },
			})
			expect(wrapper.find('.workspace-shell__save-button').exists()).toBe(true)
		})

		it('Add Widget button exists with correct data-test attribute when canEdit', () => {
			const wrapper = mountShell({
				inject: { isAdmin: true, dashboardSource: 'group', activeDashboardId: 'd1' },
			})
			expect(wrapper.find('[data-test="add-widget-toolbar-button"]').exists()).toBe(true)
		})

		it('Save button is disabled while saving is true', async () => {
			const wrapper = mountShell({
				inject: { isAdmin: true, dashboardSource: 'group', activeDashboardId: 'd1' },
			})
			wrapper.vm.saving = true
			await wrapper.vm.$nextTick()
			const saveBtn = wrapper.find('.workspace-shell__save-button')
			expect(saveBtn.attributes('disabled')).toBeDefined()
		})

		it('saving state begins as false (no in-flight request on mount)', () => {
			const wrapper = mountShell({
				inject: { isAdmin: true, dashboardSource: 'user', activeDashboardId: 'd1' },
			})
			expect(wrapper.vm.saving).toBe(false)
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

	describe('REQ-SHELL-007: lifecycle hooks', () => {
		it('registers document click listener on mount, removes on unmount', async () => {
			const addSpy = vi.spyOn(document, 'addEventListener')
			const removeSpy = vi.spyOn(document, 'removeEventListener')
			const wrapper = mountShell({ inject: { activeDashboardId: 'd1' } })
			await wrapper.vm.$nextTick()
			const addedHandler = addSpy.mock.calls.find(c => c[0] === 'click')?.[1]
			expect(typeof addedHandler).toBe('function')

			wrapper.destroy()
			const removedHandler = removeSpy.mock.calls.find(c => c[0] === 'click')?.[1]
			expect(removedHandler).toBe(addedHandler)
			addSpy.mockRestore()
			removeSpy.mockRestore()
		})
	})
})
