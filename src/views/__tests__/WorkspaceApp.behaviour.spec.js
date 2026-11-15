/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the behaviour of `WorkspaceApp.vue` beyond its
 * chrome: the `/dashboards/:id` route parameter selecting a dashboard, Escape
 * and the backdrop closing the sidebar, creating a first dashboard from the
 * empty state (and logging a failure), which footer the active dashboard
 * resolves to, its name in the title strip, and the org navigation rail.
 *
 * The two stores and the logger are doubled; child components are stubbed.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import WorkspaceApp from '../WorkspaceApp.vue'

const dashboardStore = {
	activeDashboardId: null,
	switchDashboard: vi.fn(),
	createDashboard: vi.fn(),
}
const orgNavStore = { shouldRender: false, position: 'top' }
const logged = []

vi.mock('../../stores/dashboard.js', () => ({
	useDashboardStore: () => dashboardStore,
}))
vi.mock('../../stores/orgNavigation.js', () => ({
	useOrgNavigationStore: () => orgNavStore,
}))
vi.mock('../../utils/logger.js', () => ({
	logger: { error: (...args) => logged.push(args) },
}))

/**
 * Mount the shell.
 *
 * @param {object} options The options
 * @param {object} [options.provide] Injected initial state
 * @param {object} [options.route] The current route
 * @return {object} The wrapper
 */
function mountShell({ provide = {}, route = { params: {} } } = {}) {
	return mount(WorkspaceApp, {
		global: {
			provide,
			mocks: { $route: route },
			stubs: {
				Views: { name: 'Views', render: () => h('div', { class: 'views-stub' }) },
				DashboardFooter: { name: 'DashboardFooter', props: ['footer', 'locale'], render: () => null },
				OrgNavigationPanel: { name: 'OrgNavigationPanel', render: () => h('nav', { class: 'org-nav-stub' }) },
				SidebarBackdrop: { name: 'SidebarBackdrop', emits: ['close'], render: () => h('div', { class: 'backdrop-stub' }) },
				StartPageMenu: true,
				NcButton: {
					name: 'NcButton',
					emits: ['click'],
					render() {
						return h('button', { class: this.$attrs.class, onClick: () => this.$emit('click') })
					},
				},
				MenuIcon: true,
			},
		},
	})
}

const footerOf = (wrapper) => wrapper.findComponent({ name: 'DashboardFooter' }).props('footer')

beforeEach(() => {
	vi.clearAllMocks()
	logged.length = 0
	dashboardStore.activeDashboardId = null
	orgNavStore.shouldRender = false
	orgNavStore.position = 'top'
})

describe('WorkspaceApp route parameter', () => {
	it('switches to the dashboard the route names', () => {
		dashboardStore.activeDashboardId = 'a'
		mountShell({ route: { params: { id: 'b' } } })

		expect(dashboardStore.switchDashboard).toHaveBeenCalledWith('b')
	})

	it('does not switch to the dashboard that is already active, compared as text', () => {
		dashboardStore.activeDashboardId = 7
		mountShell({ route: { params: { id: '7' } } })

		expect(dashboardStore.switchDashboard).not.toHaveBeenCalled()
	})

	it('does nothing without a dashboard in the route', () => {
		for (const id of [undefined, null, '']) {
			mountShell({ route: { params: { id } } }).unmount()
		}

		expect(dashboardStore.switchDashboard).not.toHaveBeenCalled()
	})
})

describe('WorkspaceApp sidebar', () => {
	it('closes an open sidebar on Escape only', async () => {
		const wrapper = mountShell()
		await wrapper.setData({ sidebarOpen: true })

		document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }))
		expect(wrapper.vm.sidebarOpen).toBe(true)

		document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
		expect(wrapper.vm.sidebarOpen).toBe(false)
		wrapper.unmount()
	})

	it('stops listening for Escape once unmounted', () => {
		const removed = vi.spyOn(document, 'removeEventListener')
		const wrapper = mountShell()
		const handler = wrapper.vm.onDocumentKeydown

		wrapper.unmount()

		expect(removed).toHaveBeenCalledWith('keydown', handler)
		removed.mockRestore()
	})

	it('closes when the backdrop asks, and the hamburger toggles it', async () => {
		const wrapper = mountShell()
		await wrapper.setData({ sidebarOpen: true })

		await wrapper.findComponent({ name: 'SidebarBackdrop' }).vm.$emit('close')
		expect(wrapper.vm.sidebarOpen).toBe(false)
		expect(wrapper.findComponent({ name: 'SidebarBackdrop' }).exists()).toBe(false)

		await wrapper.setData({ sidebarOpen: true })
		await wrapper.find('.workspace-shell__hamburger').trigger('click')
		expect(wrapper.vm.sidebarOpen).toBe(false)
		wrapper.unmount()
	})
})

describe('WorkspaceApp first dashboard', () => {
	it('creates a first dashboard from the empty state', async () => {
		dashboardStore.createDashboard.mockResolvedValue({})
		const wrapper = mountShell({ provide: { allowUserDashboards: true } })

		await wrapper.find('.workspace-shell__empty-cta').trigger('click')

		expect(dashboardStore.createDashboard).toHaveBeenCalledWith({ name: 'My dashboard' })
		expect(logged).toEqual([])
		wrapper.unmount()
	})

	it('logs a first dashboard that cannot be created', async () => {
		const failure = new Error('quota')
		dashboardStore.createDashboard.mockRejectedValue(failure)
		const wrapper = mountShell({ provide: { allowUserDashboards: true } })

		await wrapper.vm.onCreateFirstDashboard()

		expect(logged).toEqual([['[WorkspaceApp] Failed to create first dashboard:', failure]])
		wrapper.unmount()
	})
})

describe('WorkspaceApp active dashboard', () => {
	const instanceFooter = { text: 'Instance footer' }

	it('uses the instance footer without an active dashboard', () => {
		const wrapper = mountShell({ provide: { effectiveFooter: instanceFooter } })

		expect(footerOf(wrapper)).toEqual(instanceFooter)
		expect(wrapper.findComponent({ name: 'DashboardFooter' }).props('locale')).toBe('en')
		wrapper.unmount()
	})

	it('uses the active dashboard\'s own footer, even when it hides the footer', () => {
		const own = { text: 'Team footer' }
		const withOwn = mountShell({
			provide: { activeDashboardId: 'u1', effectiveFooter: instanceFooter, userDashboards: [{ id: 'u1', name: 'Mine', effectiveFooter: own }] },
		})
		expect(footerOf(withOwn)).toEqual(own)
		withOwn.unmount()

		const hidden = mountShell({
			provide: { activeDashboardId: 'g1', effectiveFooter: instanceFooter, groupDashboards: [null, { id: 'g1', name: 'Team', effectiveFooter: null }] },
		})
		expect(footerOf(hidden)).toBeNull()
		hidden.unmount()
	})

	it('falls back to the instance footer when the dashboard has none or is unknown', () => {
		for (const dashboards of [[{ id: 'u1', name: 'Mine' }], []]) {
			const wrapper = mountShell({
				provide: { activeDashboardId: 'u1', effectiveFooter: instanceFooter, userDashboards: dashboards },
			})
			expect(footerOf(wrapper)).toEqual(instanceFooter)
			wrapper.unmount()
		}
	})

	it('titles the strip with the active dashboard\'s name, or nothing when unknown', async () => {
		const known = mountShell({ provide: { activeDashboardId: 'g1', groupDashboards: [{ id: 'g1', name: 'Team' }] } })
		await known.setData({ sidebarOpen: true })
		expect(known.find('.workspace-shell__title').text()).toBe('Team')
		expect(known.find('.views-stub').exists()).toBe(true)
		known.unmount()

		const unknown = mountShell({ provide: { activeDashboardId: 'zz', groupDashboards: [{ id: 'g1' }] } })
		await unknown.setData({ sidebarOpen: true })
		expect(unknown.find('.workspace-shell__title').text()).toBe('')
		unknown.unmount()

		const nameless = mountShell({ provide: { activeDashboardId: 'g1', groupDashboards: [{ id: 'g1' }] } })
		await nameless.setData({ sidebarOpen: true })
		expect(nameless.find('.workspace-shell__title').text()).toBe('')
		nameless.unmount()
	})
})

describe('WorkspaceApp org navigation', () => {
	it('renders the rail with a class for its position when it should render', () => {
		orgNavStore.shouldRender = true
		orgNavStore.position = 'left'
		const wrapper = mountShell()

		expect(wrapper.find('.org-nav-stub').exists()).toBe(true)
		expect(wrapper.classes()).toContain('workspace-shell--org-nav-left')
		wrapper.unmount()
	})

	it('names an unset position hidden', () => {
		orgNavStore.shouldRender = true
		orgNavStore.position = ''
		const wrapper = mountShell()

		expect(wrapper.classes()).toContain('workspace-shell--org-nav-hidden')
		wrapper.unmount()
	})

	it('adds no rail and no class when it should not render', () => {
		const wrapper = mountShell()

		expect(wrapper.find('.org-nav-stub').exists()).toBe(false)
		expect(wrapper.classes()).toEqual(['workspace-shell'])
		wrapper.unmount()
	})
})
