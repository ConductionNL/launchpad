/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The start page's one "Meer" menu (LpVandaag): the dashboard switcher, the
 * open dashboard's actions with the cog menu's gates, and, while the
 * navigation panel is left out, the settings and the app's destinations,
 * each offered once.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, text) => text,
}))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (tpl, vars) => tpl.replace('{appId}', vars.appId),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnGroupedMenu: {
		name: 'CnGroupedMenu',
		props: ['columns', 'label'],
		emits: ['open'],
		template: '<div class="menu-stub" @click="$emit(\'open\')" />',
	},
}))

import DashboardMoreMenu, { orderDestinations } from '../DashboardMoreMenu.vue'

const ENTRIES = [
	{ id: 'dashboards', label: 'Dashboards', route: 'Workspace' },
	{ id: 'admin-templates', label: 'Templates', route: 'admin-templates-index', section: 'settings' },
	{ id: 'admin-settings', label: 'Admin settings', route: 'admin-settings', section: 'settings' },
	{ id: 'FlowsMenu', label: 'Flows', route: 'Flows', section: 'settings' },
	{ id: 'Documentation', label: 'Documentation', href: 'https://launchpad.conduction.nl', section: 'footer' },
	{ id: 'StoreMenu', label: 'Store', route: 'Store', section: 'footer' },
	{ id: 'ReportsMenu', label: 'Reports', route: 'Reports', section: 'settings' },
	{ id: 'FeaturesRoadmapMenu', label: 'Features & roadmap', route: 'FeaturesRoadmap', section: 'footer' },
	{ id: 'IntegrationsMenu', label: 'Integrations', route: 'Integrations', section: 'settings' },
]

const TODAY = { id: 1, uuid: 'u-1', name: 'Vandaag', publicationStatus: 'published', isOwner: true }
const TEAM = { id: 2, uuid: 'u-2', name: 'Team Vergunningen' }
const INTRANET = { id: 3, uuid: 'u-3', name: 'Intranet Zuiddrecht' }

/**
 * Mount the menu.
 *
 * @param {object} props Props.
 * @param {object} chrome The chrome decision.
 * @return {object} The wrapper.
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
			canManagePublication: true,
			canViewHistory: true,
			...props,
		},
		global: { provide: { launchpadChrome: chrome, cnOpenUserSettings: openUserSettings } },
	})
	return { wrapper, openUserSettings }
}

/**
 * The menu's columns as `{ groupId: [itemId] }` per column.
 *
 * @param {object} wrapper The wrapper.
 * @return {Array<object>}
 */
function shape(wrapper) {
	return wrapper.findComponent({ name: 'CnGroupedMenu' }).props('columns').map((column) => Object.fromEntries(
		column.filter((group) => group.items.length > 0).map((group) => [group.id, group.items.map((item) => item.id)]),
	))
}

/**
 * One item of the menu.
 *
 * @param {object} wrapper The wrapper.
 * @param {string} id The item id.
 * @return {object}
 */
function item(wrapper, id) {
	return wrapper.findComponent({ name: 'CnGroupedMenu' }).props('columns').flat().flatMap((group) => group.items).find((entry) => entry.id === id)
}

describe('DashboardMoreMenu', () => {
	it('groups everything as the board draws it, each action once', () => {
		const { wrapper } = mountMenu()
		expect(wrapper.findComponent({ name: 'CnGroupedMenu' }).props('label')).toBe('More')
		expect(shape(wrapper)).toEqual([
			{
				dashboards: ['dashboard-1', 'dashboard-2', 'dashboard-3', 'all-dashboards'],
				settings: ['personal-settings', 'admin-settings-link'],
			},
			{
				'this-dashboard': ['edit', 'config', 'add-widget', 'share', 'set-default', 'versions', 'schedule', 'unpublish'],
				delete: ['delete'],
				launchpad: ['StoreMenu', 'admin-templates', 'ReportsMenu', 'FlowsMenu', 'IntegrationsMenu', 'Documentation', 'FeaturesRoadmapMenu'],
			},
		])
	})

	it('marks the open, the default, the shared and the group dashboards', () => {
		const { wrapper } = mountMenu()
		expect(item(wrapper, 'dashboard-1')).toMatchObject({ checked: true, meta: 'default' })
		expect(item(wrapper, 'dashboard-2')).toMatchObject({ checked: false, meta: 'shared' })
		expect(item(wrapper, 'dashboard-3')).toMatchObject({ checked: false, meta: 'group' })
	})

	it('switches dashboards and opens the full switcher', () => {
		const { wrapper } = mountMenu()
		item(wrapper, 'dashboard-3').onClick()
		item(wrapper, 'all-dashboards').onClick()
		expect(wrapper.emitted('switch')[0]).toEqual([3, 'group'])
		expect(wrapper.emitted('openAll')).toHaveLength(1)
	})

	it('emits each dashboard action the cog menu emitted', () => {
		const { wrapper } = mountMenu()
		const events = { edit: 'toggleEdit', config: 'openConfig', 'add-widget': 'addCustomWidget', share: 'share', 'set-default': 'setDefault', versions: 'versionHistory', schedule: 'schedule', unpublish: 'unpublish', delete: 'delete' }
		for (const [id, event] of Object.entries(events)) {
			item(wrapper, id).onClick()
			expect(wrapper.emitted(event)).toHaveLength(1)
		}
		expect(item(wrapper, 'delete').destructive).toBe(true)
	})

	it('keeps the owner-only and permission-gated actions from someone else\'s dashboard', () => {
		const { wrapper } = mountMenu({
			dashboard: { ...TEAM, publicationStatus: 'draft' },
			source: 'shared',
			canEdit: false,
			canShare: false,
			canManagePublication: false,
			canViewHistory: false,
		})
		expect(shape(wrapper)[1]['this-dashboard']).toEqual(['set-default'])
		expect(shape(wrapper)[1].delete).toBeUndefined()
	})

	it('offers Publish for an unpublished dashboard, Save while editing and the read receipts when there are any', () => {
		const { wrapper } = mountMenu({ dashboard: { ...TODAY, publicationStatus: 'draft' }, isEditMode: true, canViewReadReceipts: true })
		expect(item(wrapper, 'publish')).toBeDefined()
		expect(item(wrapper, 'unpublish')).toBeUndefined()
		expect(item(wrapper, 'edit').label).toBe('Save dashboard')
		item(wrapper, 'read-receipts').onClick()
		expect(wrapper.emitted('readReceipts')).toHaveLength(1)
	})

	it('leaves the admin settings out for a member', () => {
		const { wrapper } = mountMenu({}, { railSuppressed: true, entries: ENTRIES.filter((e) => !e.id.startsWith('admin')), isAdmin: false })
		expect(shape(wrapper)[0].settings).toEqual(['personal-settings'])
		expect(shape(wrapper)[1].launchpad).not.toContain('admin-templates')
	})

	it('opens the personal settings dialog and links the admin settings', () => {
		const { wrapper, openUserSettings } = mountMenu()
		item(wrapper, 'personal-settings').onClick()
		expect(openUserSettings).toHaveBeenCalledTimes(1)
		expect(item(wrapper, 'admin-settings-link').href).toBe('/settings/admin/launchpad')
		expect(item(wrapper, 'Documentation')).toMatchObject({ href: 'https://launchpad.conduction.nl', target: '_blank' })
		expect(item(wrapper, 'StoreMenu').to).toEqual({ name: 'Store' })
	})

	it('leaves the settings and the destinations to the navigation panel while it shows', () => {
		const { wrapper } = mountMenu({}, { railSuppressed: false, entries: ENTRIES, isAdmin: true })
		expect(Object.keys(shape(wrapper)[0])).toEqual(['dashboards'])
		expect(Object.keys(shape(wrapper)[1])).toEqual(['this-dashboard', 'delete'])
	})

	it('reports that it opened, so the page can check the version history', async () => {
		const { wrapper } = mountMenu()
		await wrapper.find('.menu-stub').trigger('click')
		expect(wrapper.emitted('menuOpen')).toHaveLength(1)
	})

	it('offers only the switcher without an open dashboard', () => {
		const { wrapper } = mountMenu({ dashboard: null }, { railSuppressed: false, entries: [], isAdmin: false })
		expect(shape(wrapper)).toEqual([{ dashboards: ['dashboard-1', 'dashboard-2', 'dashboard-3', 'all-dashboards'] }, {}])
	})
})

describe('orderDestinations', () => {
	it('puts the app\'s destinations in the board\'s order, unknown ones after in their own order', () => {
		const ids = orderDestinations([{ id: 'x' }, { id: 'Documentation' }, { id: 'y' }, { id: 'StoreMenu' }]).map((i) => i.id)
		expect(ids).toEqual(['StoreMenu', 'Documentation', 'x', 'y'])
	})
})
