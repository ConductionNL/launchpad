/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard view's More menu (board LpMeer): the dashboards, Settings
 * and This dashboard with each action's permission rule, handed to the one
 * LaunchpadMoreMenu.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, text) => text,
}))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (tpl, vars) => tpl.replace('{appId}', vars?.appId),
}))
vi.mock('../LaunchpadMoreMenu.vue', () => ({
	default: {
		name: 'LaunchpadMoreMenu',
		props: ['dashboards', 'settings', 'actions'],
		template: '<div class="menu-stub" />',
	},
}))

import DashboardMoreMenu from '../DashboardMoreMenu.vue'
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
	it('hands the one menu the board\'s three groups', () => {
		const { ids } = mountMenu()
		expect(ids('dashboards')).toEqual(['dashboard-1', 'dashboard-2', 'dashboard-3', 'all-dashboards'])
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
		expect(ids('actions')).toEqual(['edit', 'add-widget', 'share', 'copy-link', 'duplicate', 'set-default', 'delete'])
	})

	it('marks the open, the default, the shared and the group dashboards', () => {
		const { menu } = mountMenu()
		const [today, team, intranet] = menu.props('dashboards')
		expect(today).toMatchObject({ checked: true, meta: 'default' })
		expect(team).toMatchObject({ checked: false, meta: 'shared' })
		expect(intranet).toMatchObject({ checked: false, meta: 'group' })
	})

	it('switches dashboards and opens the full switcher', () => {
		const { wrapper, menu } = mountMenu()
		menu.props('dashboards')[1].onClick()
		menu.props('dashboards').at(-1).onClick()
		expect(wrapper.emitted('switch')).toEqual([[2, 'shared']])
		expect(wrapper.emitted('openAll')).toHaveLength(1)
	})

	it('emits each action by its event', () => {
		const { wrapper, menu } = mountMenu()
		for (const item of menu.props('actions')) {
			item.onClick()
		}
		for (const event of ['toggleEdit', 'addWidget', 'share', 'copyLink', 'duplicate', 'setDefault', 'delete']) {
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
		expect(ids('actions')).toEqual(['copy-link', 'set-default'])
	})

	it('never offers Versions, Settings, Schedule, Unpublish or Create embed token', () => {
		const { ids } = mountMenu()
		for (const gone of ['versions', 'config', 'schedule', 'unpublish', 'publish', 'embed']) {
			expect(ids('actions')).not.toContain(gone)
		}
	})

	it('offers no read receipts: they are read on the Announcements tab (REQ-ACK-008)', () => {
		const { ids } = mountMenu({ canViewReadReceipts: true })
		expect(ids('actions')).not.toContain('read-receipts')
		expect(ids('actions').at(-1)).toBe('delete')
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
