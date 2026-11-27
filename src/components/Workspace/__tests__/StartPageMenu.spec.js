/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The More menu off the dashboard view (runtime-shell REQ-SHELL-009,
 * REQ-SHELL-013): the same menu as on the dashboard view, with the
 * dashboards and Settings and no This dashboard group.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, text) => text,
	translate: (_app, text) => text,
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (tpl, vars) => tpl.replace('{appId}', vars?.appId),
}))
vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(async () => ({ data: { value: null } })), put: vi.fn(async () => ({ data: {} })) },
}))
vi.mock('../LaunchpadMoreMenu.vue', () => ({
	default: {
		name: 'LaunchpadMoreMenu',
		props: ['dashboards', 'settings', 'actions'],
		emits: ['open'],
		template: '<div class="menu-stub" />',
	},
}))
vi.mock('../../../services/api.js', () => ({
	api: { getDefaultDashboardPreference: vi.fn() },
}))

import StartPageMenu from '../StartPageMenu.vue'
import { api } from '../../../services/api.js'
import { useDashboardStore } from '../../../stores/dashboard.js'

const ENTRIES = [
	{ id: 'dashboards', label: 'Dashboards', route: 'Workspace' },
	{ id: 'Documentation', label: 'Documentation', href: 'https://launchpad.conduction.nl', section: 'footer' },
	{ id: 'StoreMenu', label: 'Marketplace', route: 'Store', section: 'footer' },
	{ id: 'FlowsMenu', label: 'Flows', route: 'Flows', section: 'settings' },
]

/**
 * Mount the menu.
 *
 * @param {object|null} chrome The chrome decision.
 * @return {object} The wrapper, the menu stub and the router.
 */
function mountMenu(chrome = { railSuppressed: true, entries: ENTRIES, isAdmin: false }) {
	const router = { push: vi.fn() }
	const wrapper = mount(StartPageMenu, {
		global: {
			provide: { launchpadChrome: chrome, cnOpenUserSettings: vi.fn() },
			mocks: { $router: router },
		},
	})
	return { wrapper, menu: wrapper.findComponent({ name: 'LaunchpadMoreMenu' }), router }
}

describe('StartPageMenu', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
		store = useDashboardStore()
		store.loadedOnce = true
		store.dashboards = [
			{ id: 1, uuid: 'u-1', name: 'Vandaag', source: 'user' },
			{ id: 2, uuid: 'u-2', name: 'Team Vergunningen', source: 'group' },
		]
		api.getDefaultDashboardPreference.mockResolvedValue({ data: { uuid: 'u-1' } })
	})

	it('renders nothing while the panel is rendered, or when nothing was provided', () => {
		expect(mountMenu({ railSuppressed: false, entries: ENTRIES }).menu.exists()).toBe(false)
		expect(mountMenu(null).menu.exists()).toBe(false)
	})

	it('offers the dashboards and Settings, and no This dashboard group', () => {
		const { menu } = mountMenu()
		expect(menu.props('dashboards').map((i) => i.id)).toEqual(['dashboard-1', 'dashboard-2', 'all-dashboards'])
		expect(menu.props('dashboards').some((i) => i.checked === true)).toBe(false)
		expect(menu.props('settings').map((i) => i.id)).toEqual(['personal-settings', 'StoreMenu', 'FlowsMenu', 'Documentation'])
		expect(menu.props('actions')).toBeUndefined()
	})

	it('carries the tour target of the More menu', () => {
		const { wrapper } = mountMenu()
		expect(wrapper.attributes('data-walkthrough-id')).toBe('launchpad-more-menu')
	})

	it('opens a chosen dashboard on the dashboard view, and More unfolds the list in place', async () => {
		const { wrapper, menu, router } = mountMenu()
		menu.props('dashboards')[1].onClick()
		expect(router.push).toHaveBeenCalledWith({ name: 'dashboard-detail', params: { id: '2' } })
		menu.props('dashboards').at(-1).onClick()
		await wrapper.vm.$nextTick()
		expect(router.push).toHaveBeenCalledTimes(1)
		expect(menu.props('dashboards').at(-1).label).toBe('Fewer')
	})

	it('reads the default dashboard once, on first open, and marks it', async () => {
		const { menu } = mountMenu()
		menu.vm.$emit('open')
		await flushPromises()
		menu.vm.$emit('open')
		await flushPromises()
		expect(api.getDefaultDashboardPreference).toHaveBeenCalledTimes(1)
		expect(menu.props('dashboards')[0].meta).toBe('default')
	})

	it('reads the dashboards on open when the page has not', async () => {
		store.loadedOnce = false
		const load = vi.spyOn(store, 'loadDashboards').mockResolvedValue()
		const { menu } = mountMenu()
		menu.vm.$emit('open')
		await flushPromises()
		expect(load).toHaveBeenCalledTimes(1)
	})

	it('keeps the menu working when the default cannot be read', async () => {
		api.getDefaultDashboardPreference.mockRejectedValue(new Error('500'))
		const { menu } = mountMenu()
		menu.vm.$emit('open')
		await flushPromises()
		expect(menu.props('dashboards')[0].meta).toBeUndefined()
	})
})
