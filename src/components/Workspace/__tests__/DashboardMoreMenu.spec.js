/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard view's More menu (board LpMeer): the dashboards, Settings
 * and This dashboard with each action's permission rule, handed to the one
 * LaunchpadMoreMenu.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const axiosMock = vi.hoisted(() => ({
	get: vi.fn(async () => ({ data: {} })),
	post: vi.fn(async () => ({ data: {} })),
	delete: vi.fn(async () => ({ data: {} })),
}))
vi.mock('@nextcloud/axios', () => ({ default: axiosMock }))
const showError = vi.hoisted(() => vi.fn())
vi.mock('@nextcloud/dialogs', () => ({ showError }))

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, text) => text,
}))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (tpl, vars) => tpl.replace('{appId}', vars?.appId).replace('{uuid}', vars?.uuid),
}))
vi.mock('../LaunchpadMoreMenu.vue', () => ({
	default: {
		name: 'LaunchpadMoreMenu',
		props: ['dashboards', 'settings', 'actions'],
		emits: ['open'],
		template: '<div class="menu-stub" />',
	},
}))

import DashboardMoreMenu from '../DashboardMoreMenu.vue'
import { setFavouriteDashboards } from '../../../services/favouriteDashboards.js'
import { orderDestinations } from '../moreMenuItems.js'

const ENTRIES = [
	{ id: 'dashboards', label: 'Dashboards', route: 'Workspace' },
	{ id: 'admin-templates', label: 'Templates', route: 'admin-templates-index', section: 'settings' },
	{ id: 'admin-settings', label: 'Admin settings', route: 'admin-settings', section: 'settings' },
	{ id: 'FlowsMenu', label: 'Flows', route: 'Flows', section: 'settings' },
	{ id: 'Documentation', label: 'Documentation', href: 'https://launchpad.conduction.nl', section: 'footer' },
	{ id: 'StoreMenu', label: 'Marketplace', route: 'Store', section: 'footer' },
	{ id: 'ReportsMenu', label: 'Reports', route: 'Reports', section: 'settings' },
	{ id: 'FeaturesRoadmapMenu', label: 'Features & roadmap', route: 'FeaturesRoadmap', section: 'footer' },
	{ id: 'IntegrationsMenu', label: 'Integrations', route: 'Integrations', section: 'settings' },
]

const TODAY = { id: 1, uuid: 'u-1', name: 'Vandaag', isOwner: true }
const TEAM = { id: 2, uuid: 'u-2', name: 'Team Vergunningen' }
const INTRANET = { id: 3, uuid: 'u-3', name: 'Intranet Zuiddrecht' }

/**
 * Mount the menu.
 *
 * @param {object} props Props.
 * @param {object} chrome The chrome decision.
 * @return {object} The wrapper and what the menu was handed.
 */
function mountMenu(props = {}, chrome = { railSuppressed: true, entries: ENTRIES, isAdmin: true }) {
	const openUserSettings = vi.fn()
	const wrapper = mount(DashboardMoreMenu, {
		props: {
			dashboard: TODAY,
			source: 'user',
			userDashboards: [TODAY],
			groupDashboards: [TEAM, INTRANET],
			sharedDashboards: [TEAM],
			defaultUuid: 'u-1',
			canEdit: true,
			canShare: true,
			canDuplicate: true,
			...props,
		},
		global: { provide: { launchpadChrome: chrome, cnOpenUserSettings: openUserSettings } },
	})
	const menu = wrapper.findComponent({ name: 'LaunchpadMoreMenu' })
	const ids = (key) => menu.props(key).map((item) => item.id)
	return { wrapper, menu, ids, openUserSettings }
}

describe('DashboardMoreMenu', () => {
	beforeEach(() => {
		setFavouriteDashboards([])
		vi.clearAllMocks()
	})

	it('hands the one menu the board\'s three groups', () => {
		const { ids } = mountMenu()
		expect(ids('dashboards')).toEqual(['dashboard-1', 'favourites-hint', 'all-dashboards'])
		expect(ids('settings')).toEqual([
			'personal-settings',
			'admin-settings-link',
			'StoreMenu',
			'admin-templates',
			'ReportsMenu',
			'FlowsMenu',
			'IntegrationsMenu',
			'Documentation',
			'FeaturesRoadmapMenu',
		])
		expect(ids('actions')).toEqual(['edit', 'add-widget', 'share', 'copy-link', 'duplicate', 'download', 'set-default', 'delete'])
	})

	it('marks the open, the default, the shared and the group dashboards', async () => {
		const { wrapper } = mountMenu()
		wrapper.vm.toggleDashboardsExpanded()
		await wrapper.vm.$nextTick()
		const [today, team, intranet] = wrapper.findComponent({ name: 'LaunchpadMoreMenu' }).props('dashboards')
		expect(today).toMatchObject({ checked: true, pill: { label: 'Default' } })
		expect(team).toMatchObject({ checked: false, meta: 'shared' })
		expect(intranet).toMatchObject({ checked: false, meta: 'group' })
	})

	it('switches dashboards, and More unfolds every dashboard with a star in place', async () => {
		const { wrapper, menu } = mountMenu()
		menu.props('dashboards').at(-1).onClick()
		await wrapper.vm.$nextTick()
		const unfolded = wrapper.findComponent({ name: 'LaunchpadMoreMenu' }).props('dashboards')
		unfolded[1].onClick()
		expect(wrapper.emitted('switch')).toEqual([[2, 'shared']])
		expect(unfolded.at(-1).label).toBe('Less')
		expect(unfolded.slice(0, -1).map((i) => i.favourite)).toEqual([false, false, false])
		expect(wrapper.emitted('openAll')).toBeUndefined()
	})

	it('lists the page\'s favourites folded without a request, and stars through the API', async () => {
		setFavouriteDashboards(['u-3'])
		axiosMock.post.mockResolvedValueOnce({ data: { favourites: ['u-3', 'u-1'] } })
		const { wrapper } = mountMenu()
		const menu = () => wrapper.findComponent({ name: 'LaunchpadMoreMenu' })
		expect(menu().props('dashboards').map((i) => i.id)).toEqual(['dashboard-3', 'all-dashboards'])
		menu().props('dashboards').at(-1).onClick()
		await wrapper.vm.$nextTick()
		expect(axiosMock.get).not.toHaveBeenCalled()
		menu().props('dashboards').find((i) => i.id === 'dashboard-1').onToggleFavourite()
		await flushPromises()
		expect(axiosMock.post).toHaveBeenLastCalledWith('/apps/launchpad/api/dashboards/u-1/favourite')
		expect(menu().props('dashboards').map((i) => i.id).slice(0, 2)).toEqual(['dashboard-3', 'dashboard-1'])
		expect(menu().props('dashboards')[1].favourite).toBe(true)
	})

	it('unstars through DELETE', async () => {
		setFavouriteDashboards(['u-3'])
		const { wrapper } = mountMenu()
		await wrapper.vm.toggleDashboardFavourite('u-3')
		expect(axiosMock.delete).toHaveBeenCalledWith('/apps/launchpad/api/dashboards/u-3/favourite')
		expect(wrapper.vm.favourites).toEqual([])
	})

	it('puts the favourites back with an error toast when storing a star fails', async () => {
		axiosMock.post.mockRejectedValueOnce(new Error('500'))
		const { wrapper } = mountMenu()
		wrapper.vm.toggleDashboardsExpanded()
		await wrapper.vm.toggleDashboardFavourite('u-2')
		expect(wrapper.vm.favourites).toEqual([])
		expect(showError).toHaveBeenCalledWith('Could not add the dashboard to your favourites')
	})

	it('offers New dashboard in the expanded list only when it may create, and reports it', async () => {
		const { wrapper } = mountMenu({ canCreate: true })
		wrapper.vm.toggleDashboardsExpanded()
		await wrapper.vm.$nextTick()
		const items = wrapper.findComponent({ name: 'LaunchpadMoreMenu' }).props('dashboards')
		items.find((i) => i.id === 'new-dashboard').onClick()
		expect(wrapper.emitted('newDashboard')).toHaveLength(1)
		const without = mountMenu()
		without.wrapper.vm.toggleDashboardsExpanded()
		await without.wrapper.vm.$nextTick()
		expect(without.ids('dashboards')).not.toContain('new-dashboard')
	})

	it('folds the column again every time the menu opens', async () => {
		const { wrapper } = mountMenu()
		wrapper.vm.toggleDashboardsExpanded()
		wrapper.findComponent({ name: 'LaunchpadMoreMenu' }).vm.$emit('open')
		expect(wrapper.vm.dashboardsExpanded).toBe(false)
	})

	it('emits each action by its event', () => {
		const { wrapper, menu } = mountMenu()
		for (const item of menu.props('actions')) {
			item.onClick()
		}
		for (const event of ['toggleEdit', 'addWidget', 'share', 'copyLink', 'duplicate', 'download', 'setDefault', 'delete']) {
			expect(wrapper.emitted(event), event).toHaveLength(1)
		}
	})

	it('keeps each action\'s permission rule on someone else\'s dashboard', () => {
		const { ids } = mountMenu({
			dashboard: { ...TEAM, isOwner: false },
			source: 'group',
			canEdit: false,
			canShare: false,
			canDuplicate: false,
		})
		expect(ids('actions')).toEqual(['copy-link', 'download', 'set-default'])
	})

	it('never offers Versions, Settings, Schedule, Unpublish or Create embed token', () => {
		const { ids } = mountMenu()
		for (const gone of ['versions', 'config', 'schedule', 'unpublish', 'publish', 'embed']) {
			expect(ids('actions')).not.toContain(gone)
		}
	})

	it('offers the read receipts before Delete when the dashboard asks for them', () => {
		const { wrapper, menu, ids } = mountMenu({ canViewReadReceipts: true })
		expect(ids('actions').slice(-2)).toEqual(['read-receipts', 'delete'])
		menu.props('actions').find((item) => item.id === 'read-receipts').onClick()
		expect(wrapper.emitted('readReceipts')).toHaveLength(1)
		expect(mountMenu({ canViewReadReceipts: true, source: 'group', dashboard: TEAM }).ids('actions').at(-1)).toBe('read-receipts')
	})

	it('names Save dashboard while editing', () => {
		const { menu } = mountMenu({ isEditMode: true })
		expect(menu.props('actions')[0].label).toBe('Save dashboard')
	})

	it('leaves the admin settings out for a member, and opens the personal settings dialog', () => {
		const { menu, ids, openUserSettings } = mountMenu({}, { railSuppressed: true, entries: ENTRIES, isAdmin: false })
		expect(ids('settings')).not.toContain('admin-settings-link')
		menu.props('settings').find((item) => item.id === 'personal-settings').onClick()
		expect(openUserSettings).toHaveBeenCalled()
	})

	it('leaves Settings to the navigation panel while it shows', () => {
		const { ids } = mountMenu({}, { railSuppressed: false, entries: ENTRIES, isAdmin: true })
		expect(ids('settings')).toEqual([])
	})

	it('offers only the dashboards without an open dashboard', () => {
		const { ids } = mountMenu({ dashboard: null })
		expect(ids('actions')).toEqual([])
		expect(ids('dashboards')).toContain('all-dashboards')
	})
})

describe('orderDestinations', () => {
	it('puts the app\'s destinations in the board\'s order, unknown ones after in their own order', () => {
		const items = ['zeta', 'Documentation', 'alpha', 'StoreMenu', 'FlowsMenu'].map((id) => ({ id }))
		expect(orderDestinations(items).map((item) => item.id)).toEqual([
			'StoreMenu',
			'FlowsMenu',
			'Documentation',
			'zeta',
			'alpha',
		])
	})
})
