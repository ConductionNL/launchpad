/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the behaviour of `WorkspaceApp.vue` beyond its
 * chrome: the `/dashboards/:id` route parameter selecting a dashboard,
 * creating a first dashboard from the empty state (and logging a failure),
 * which footer the active dashboard resolves to, and the org navigation rail.
 *
 * The two stores and the logger are doubled; child components are stubbed.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import WorkspaceApp from '../WorkspaceApp.vue'

const dashboardStore = {
	activeDashboardId: null,
	openDashboard: vi.fn(),
	createDashboard: vi.fn(),
}
const orgNavStore = { shouldRender: false, position: 'top', loadFirst: vi.fn() }
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
				StartPageMenu: true,
				TileSidePanel: true,
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

		expect(dashboardStore.openDashboard).toHaveBeenCalledWith('b')
	})

	it('does not switch to the dashboard that is already active, compared as text', () => {
		dashboardStore.activeDashboardId = 7
		mountShell({ route: { params: { id: '7' } } })

		expect(dashboardStore.openDashboard).not.toHaveBeenCalled()
	})

	it('does nothing without a dashboard in the route', () => {
		for (const id of [undefined, null, '']) {
			mountShell({ route: { params: { id } } }).unmount()
		}

		expect(dashboardStore.openDashboard).not.toHaveBeenCalled()
	})
})

describe('WorkspaceApp first dashboard', () => {
	it('creates a first dashboard from the empty state', async () => {
		dashboardStore.createDashboard.mockResolvedValue({})
		const wrapper = mountShell({ provide: { allowUserDashboards: true } })

		await wrapper.find('.workspace-shell__empty-cta').trigger('click')

		expect(dashboardStore.createDashboard).toHaveBeenCalledWith({ name: 'My workspace', widgets: 'starters' })
		expect(logged).toEqual([])
		wrapper.unmount()
	})

	it('creates an empty first dashboard from Start empty', async () => {
		dashboardStore.createDashboard.mockResolvedValue({})
		const wrapper = mountShell({ provide: { allowUserDashboards: true } })

		await wrapper.find('.workspace-shell__empty-blank').trigger('click')

		expect(dashboardStore.createDashboard).toHaveBeenCalledWith({ name: 'My workspace', widgets: 'none' })
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

	it('loads the position and the tree itself, because the rail mounts only once they are known', () => {
		const wrapper = mountShell()

		expect(orgNavStore.loadFirst).toHaveBeenCalledTimes(1)
		wrapper.unmount()
	})

	it('adds no rail and no class when it should not render', () => {
		const wrapper = mountShell()

		expect(wrapper.find('.org-nav-stub').exists()).toBe(false)
		expect(wrapper.classes()).toEqual(['workspace-shell'])
		wrapper.unmount()
	})
})
