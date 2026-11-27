/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The items of the one More menu (board LpMeer): the Dashboards group, the
 * order under Settings and the This dashboard group with its permission
 * rules.
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (tpl, vars) => tpl.replace('{appId}', vars?.appId),
}))

import {
	DASHBOARD_LIMIT,
	dashboardActionItems,
	dashboardItems,
	parseFavourites,
	settingsMenuItems,
	startPageSections,
	toggleFavourite,
} from '../moreMenuItems.js'

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

/**
 * The labels under Settings for a viewer.
 *
 * @param {boolean} isAdmin Whether the viewer is an administrator.
 * @return {string[]}
 */
function settingsLabels(isAdmin) {
	const chrome = { railSuppressed: true, entries: ENTRIES, isAdmin, includePersonalSettings: true }
	return settingsMenuItems(startPageSections(chrome, () => {})).map((item) => item.label)
}

describe('dashboardItems', () => {
	const onSwitch = vi.fn()
	const onToggleExpanded = vi.fn()
	const onToggleFavourite = vi.fn()
	const base = {
		userDashboards: [{ id: 1, uuid: 'u-1', name: 'Vandaag' }, { id: 4, uuid: 'u-4', name: 'Mijn werkplek' }],
		groupDashboards: [{ id: 2, uuid: 'u-2', name: 'Team Vergunningen' }, { id: 3, uuid: 'u-3', name: 'Intranet Zuiddrecht' }, { id: 1, name: 'Vandaag' }],
		sharedDashboards: [{ id: 2 }],
		activeId: 4,
		defaultUuid: 'u-1',
		onSwitch,
		onToggleExpanded,
		onToggleFavourite,
	}
	const items = dashboardItems(base)

	it('without favourites lists own, then shared and group dashboards once, ending in More', () => {
		expect(items.map((i) => i.label)).toEqual(['Vandaag', 'Mijn werkplek', 'Team Vergunningen', 'Intranet Zuiddrecht', 'More'])
	})

	it('marks default, shared and group, and checks the open one', () => {
		expect(items.map((i) => i.meta)).toEqual(['default', undefined, 'shared', 'group', undefined])
		expect(items.map((i) => i.checked)).toEqual([false, true, false, false, undefined])
	})

	it('switches with the dashboard\'s source, and More unfolds the column and keeps the menu open', () => {
		items[2].onClick()
		expect(onSwitch).toHaveBeenCalledWith(2, 'shared')
		expect(items.at(-1).keepOpen).toBe(true)
		expect(items.at(-1).expanded).toBe(false)
		items.at(-1).onClick()
		expect(onToggleExpanded).toHaveBeenCalled()
	})

	it('without favourites lists at most the limit before More', () => {
		const many = Array.from({ length: DASHBOARD_LIMIT + 3 }, (_, i) => ({ id: i + 1, name: `D${i}` }))
		const list = dashboardItems({ userDashboards: many, onSwitch })
		expect(list).toHaveLength(DASHBOARD_LIMIT + 1)
	})

	it('folded, lists only the favourites, in the order of the full list, and no stars', () => {
		const list = dashboardItems({ ...base, favourites: ['u-3', 'u-1', 'gone'] })
		expect(list.map((i) => i.label)).toEqual(['Vandaag', 'Intranet Zuiddrecht', 'More'])
		expect(list.some((i) => i.favourite !== undefined)).toBe(false)
	})

	it('unfolded, lists every dashboard beyond the limit, each with its star, ending in Fewer', () => {
		const many = Array.from({ length: DASHBOARD_LIMIT + 3 }, (_, i) => ({ id: i + 1, uuid: `m-${i}`, name: `D${i}` }))
		const list = dashboardItems({ userDashboards: many, favourites: ['m-2'], expanded: true, onSwitch, onToggleFavourite })
		expect(list).toHaveLength(DASHBOARD_LIMIT + 4)
		expect(list.slice(0, -1).map((i) => i.favourite)).toEqual(many.map((d) => d.uuid === 'm-2'))
		expect(list.at(-1).label).toBe('Fewer')
		expect(list.at(-1).expanded).toBe(true)
		list[5].onToggleFavourite()
		expect(onToggleFavourite).toHaveBeenCalledWith('m-5')
	})
})

describe('toggleFavourite and parseFavourites', () => {
	it('adds a uuid at the end and removes it again', () => {
		expect(toggleFavourite(['a'], 'b')).toEqual(['a', 'b'])
		expect(toggleFavourite(['a', 'b'], 'a')).toEqual(['b'])
	})

	it('ignores an empty uuid and drops junk from the list', () => {
		expect(toggleFavourite(['a', '', 3], '')).toEqual(['a'])
		expect(toggleFavourite(null, 'a')).toEqual(['a'])
	})

	it('reads a stored JSON list, and anything else as no favourites', () => {
		expect(parseFavourites('["a","b"]')).toEqual(['a', 'b'])
		expect(parseFavourites('["a",1,""]')).toEqual(['a'])
		expect(parseFavourites('{"a":1}')).toEqual([])
		expect(parseFavourites('not json')).toEqual([])
		expect(parseFavourites(null)).toEqual([])
		expect(parseFavourites('')).toEqual([])
	})
})

describe('settingsMenuItems', () => {
	it('lists, for an administrator, the board\'s nine destinations in its order', () => {
		expect(settingsLabels(true)).toEqual([
			'Personal settings',
			'Admin settings',
			'Marketplace',
			'Templates',
			'Reports',
			'Flows',
			'Integrations',
			'Documentation',
			'Features & roadmap',
		])
	})

	it('leaves Admin settings out for anyone else', () => {
		expect(settingsLabels(false)).not.toContain('Admin settings')
	})

	it('opens an external destination in a new tab', () => {
		const chrome = { railSuppressed: true, entries: ENTRIES, isAdmin: false }
		const docs = settingsMenuItems(startPageSections(chrome, null)).find((i) => i.id === 'Documentation')
		expect(docs.target).toBe('_blank')
		expect(docs.icon).toBeTruthy()
	})
})

describe('dashboardActionItems', () => {
	const dashboard = { id: 1, uuid: 'u-1', name: 'Vandaag' }
	const all = { dashboard, isOwner: true, canEdit: true, canShare: true, canDuplicate: true, emit: vi.fn() }
	const ids = (options) => dashboardActionItems(options).map((i) => i.id)

	it('offers the board\'s eight actions to the owner, Download included,, Delete last and destructive', () => {
		const items = dashboardActionItems(all)
		expect(items.map((i) => i.label)).toEqual([
			'Edit',
			'Add widget',
			'Sharing',
			'Copy link to this dashboard',
			'Download',
			'Duplicate as new dashboard',
			'Set as default',
			'Delete',
		])
		expect(items.at(-1).destructive).toBe(true)
		expect(items.slice(0, -1).some((i) => i.destructive)).toBe(false)
	})

	it('offers a viewer who may not edit, share or copy only the link, Download and the default', () => {
		expect(ids({ dashboard, emit: vi.fn() })).toEqual(['copy-link', 'download', 'set-default'])
	})

	it('keeps Edit and Add widget behind canEdit, Share behind canShare, Delete behind ownership', () => {
		expect(ids({ ...all, canEdit: false })).not.toContain('edit')
		expect(ids({ ...all, canEdit: false })).not.toContain('add-widget')
		expect(ids({ ...all, canShare: false })).not.toContain('share')
		expect(ids({ ...all, canDuplicate: false })).not.toContain('duplicate')
		expect(ids({ ...all, isOwner: false })).not.toContain('delete')
	})

	it('offers nothing without a dashboard, and never Versions or Settings', () => {
		expect(dashboardActionItems({ ...all, dashboard: null })).toEqual([])
		expect(ids(all)).not.toContain('versions')
		expect(ids(all)).not.toContain('config')
	})

	it('names Save dashboard while editing and Set as default also for the default (LpMeer)', () => {
		const items = dashboardActionItems({ ...all, isEditMode: true, isDefault: true })
		expect(items.find((i) => i.id === 'edit').label).toBe('Save dashboard')
		expect(items.find((i) => i.id === 'set-default').label).toBe('Set as default')
	})

	it('reports each chosen action by its event', () => {
		const emit = vi.fn()
		for (const item of dashboardActionItems({ ...all, emit })) {
			item.onClick()
		}
		expect(emit.mock.calls.map(([event]) => event)).toEqual([
			'toggleEdit', 'addWidget', 'share', 'copyLink', 'download', 'duplicate', 'setDefault', 'delete',
		])
	})
})

describe('dashboardIcon', () => {
	it('draws the dashboard\'s own icon by name, else the house or the generic dashboard', async () => {
		const { dashboardIcon } = await import('../moreMenuItems.js')
		expect(dashboardIcon({ icon: 'Bullhorn' }, false).name).toBe('DashboardMenuIcon')
		expect(dashboardIcon({ icon: '' }, true).name).toMatch(/Home/)
		expect(dashboardIcon({}, false).name).toMatch(/ViewDashboard/)
	})
})
