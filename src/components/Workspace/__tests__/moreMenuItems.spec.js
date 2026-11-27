/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The items of the one More menu (board LpMeer): the Dashboards group, the
 * order under Settings and the This dashboard group with its permission
 * rules.
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars) => text.replace(/{(\w+)}/g, (m, k) => vars?.[k] ?? m),
	n: (_app, one, many, count) => (count === 1 ? one : many).replace('%n', String(count)),
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (tpl, vars) => tpl.replace('{appId}', vars?.appId),
}))

import {
	dashboardActionItems,
	dashboardItems,
	effectiveDefaultUuid,
	parseFavourites,
	settingsMenuItems,
	startPageSections,
	toggleFavourite,
} from '../moreMenuItems.js'

const ENTRIES = [
	{ id: 'dashboards', label: 'Dashboards', route: 'Workspace' },
	{ id: 'admin-templates', label: 'Templates', href: '/index.php/settings/admin/launchpad#templates', section: 'settings' },
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

	it('without favourites lists the open dashboard, a line about starring, and More', () => {
		expect(items.map((i) => i.id)).toEqual(['dashboard-4', 'favourites-hint', 'all-dashboards'])
		expect(items[1]).toMatchObject({ hint: true, label: 'Star a dashboard to keep it here' })
		expect(items[0].checked).toBe(true)
		// REQ-SHELL-016: the open dashboard's row carries its (empty) star.
		expect(items[0].favourite).toBe(false)
		// Four dashboards, one shown: "3 more dashboards".
		expect(items.at(-1).meta).toBe('3 more dashboards')
	})

	it('without favourites and nothing open, lists only the line and More', () => {
		const list = dashboardItems({ ...base, activeId: null })
		expect(list.map((i) => i.id)).toEqual(['favourites-hint', 'all-dashboards'])
	})

	it('unfolded, lists own, then shared and group dashboards once, marked, the open one checked', () => {
		const all = dashboardItems({ ...base, expanded: true })
		expect(all.map((i) => i.label)).toEqual(['Vandaag', 'Mijn werkplek', 'Team Vergunningen', 'Intranet Zuiddrecht', 'Less'])
		expect(all.map((i) => i.meta)).toEqual([undefined, undefined, 'shared', 'group', 'favourites only'])
		// REQ-SHELL-018: the default carries the Default pill, no other row does.
		expect(all.map((i) => i.pill?.label)).toEqual(['Default', undefined, undefined, undefined, undefined])
		expect(all[0].pill).toEqual({ label: 'Default', title: 'Opens automatically', ariaLabel: 'Default dashboard' })
		expect(all.map((i) => i.checked)).toEqual([false, true, false, false, undefined])
	})

	it('switches with the dashboard\'s source, and More unfolds the column and keeps the menu open', () => {
		const all = dashboardItems({ ...base, expanded: true })
		all[2].onClick()
		expect(onSwitch).toHaveBeenCalledWith(2, 'shared')
		expect(items.at(-1).keepOpen).toBe(true)
		expect(items.at(-1).expanded).toBe(false)
		items.at(-1).onClick()
		expect(onToggleExpanded).toHaveBeenCalled()
	})

	it('folded, lists only the favourites, in starring order, each with a filled star', () => {
		const list = dashboardItems({ ...base, favourites: ['u-3', 'u-1', 'gone'] })
		expect(list.map((i) => i.label)).toEqual(['Intranet Zuiddrecht', 'Vandaag', 'More'])
		expect(list.slice(0, -1).map((i) => i.favourite)).toEqual([true, true])
		expect(list.at(-1).meta).toBe('2 more dashboards')
	})

	it('unfolded, lists the favourites first in starring order, then the others', () => {
		const list = dashboardItems({ ...base, favourites: ['u-3', 'u-1'], expanded: true })
		expect(list.map((i) => i.label)).toEqual(['Intranet Zuiddrecht', 'Vandaag', 'Mijn werkplek', 'Team Vergunningen', 'Less'])
		expect(list.map((i) => i.favourite)).toEqual([true, true, false, false, undefined])
	})

	it('unfolded, offers New dashboard above Less only when creating is allowed', () => {
		const onNewDashboard = vi.fn()
		const withCreate = dashboardItems({ ...base, expanded: true, onNewDashboard })
		expect(withCreate.slice(-2).map((i) => i.id)).toEqual(['new-dashboard', 'all-dashboards'])
		expect(withCreate.at(-2).label).toBe('New dashboard')
		withCreate.at(-2).onClick()
		expect(onNewDashboard).toHaveBeenCalled()
		expect(dashboardItems({ ...base, expanded: true }).some((i) => i.id === 'new-dashboard')).toBe(false)
		expect(dashboardItems({ ...base, onNewDashboard }).some((i) => i.id === 'new-dashboard')).toBe(false)
	})

	it('unfolded, lists every dashboard, each with its star, ending in Less', () => {
		const many = Array.from({ length: 11 }, (_, i) => ({ id: i + 1, uuid: `m-${i}`, name: `D${i}` }))
		const list = dashboardItems({ userDashboards: many, favourites: ['m-2'], expanded: true, onSwitch, onToggleFavourite })
		expect(list).toHaveLength(12)
		expect(list.slice(0, -1).map((i) => i.favourite)).toEqual([true, ...Array(10).fill(false)])
		expect(list.at(-1).label).toBe('Less')
		expect(list.at(-1).expanded).toBe(true)
		list[5].onToggleFavourite()
		expect(onToggleFavourite).toHaveBeenCalledWith('m-5')
	})
})

describe('dashboardItems tree (REQ-SHELL-019, REQ-TREEUI-001)', () => {
	const tree = {
		userDashboards: [
			{ id: 1, uuid: 'hr', name: 'HR' },
			{ id: 2, uuid: 'on', name: 'Onboarding', parentUuid: 'hr' },
			{ id: 3, uuid: 'va', name: 'Vacation rules', parentUuid: 'hr' },
			{ id: 4, uuid: 'x', name: 'Loose', parentUuid: 'gone' },
		],
		expanded: true,
		onSwitch: vi.fn(),
	}
	const rows = (list) => list.filter((i) => i.id.startsWith('dashboard-'))

	it('closed, a parent carries an expand control and its children are not listed', () => {
		const onToggleTree = vi.fn()
		const list = rows(dashboardItems({ ...tree, onToggleTree }))
		expect(list.map((i) => i.label)).toEqual(['HR', 'Loose'])
		expect(list[0].tree).toMatchObject({ uuid: 'hr', open: false })
		expect(list[1].tree).toBeUndefined()
		list[0].tree.onToggle()
		expect(onToggleTree).toHaveBeenCalledWith('hr')
	})

	it('open, the children follow their parent one level deeper', () => {
		const list = rows(dashboardItems({ ...tree, openTrees: ['hr'] }))
		expect(list.map((i) => [i.label, i.depth])).toEqual([['HR', 0], ['Onboarding', 1], ['Vacation rules', 1], ['Loose', 0]])
		expect(list[0].tree.open).toBe(true)
	})

	it('the folded column has no tree', () => {
		const list = rows(dashboardItems({ ...tree, expanded: false, favourites: ['on'] }))
		expect(list.map((i) => i.label)).toEqual(['Onboarding'])
		expect(list[0].depth).toBeUndefined()
	})
})

describe('effectiveDefaultUuid', () => {
	const groups = [
		{ uuid: 'g-1', source: 'group' },
		{ uuid: 'g-2', source: 'group', isDefault: 1 },
		{ uuid: 'd-1', source: 'default' },
		{ uuid: 'd-2', source: 'default', isDefault: 1 },
		{ uuid: 's-1', source: 'shared', isDefault: 1 },
	]

	it('is the pin when there is one', () => {
		expect(effectiveDefaultUuid('u-9', groups)).toBe('u-9')
	})

	it('falls back to the flagged primary-group, then the flagged default-group dashboard', () => {
		expect(effectiveDefaultUuid('', groups)).toBe('g-2')
		expect(effectiveDefaultUuid('', groups.filter((d) => d.uuid !== 'g-2'))).toBe('d-2')
	})

	it('then to the first primary-group and the first default-group dashboard, never a shared one', () => {
		expect(effectiveDefaultUuid('', [{ uuid: 'g-1', source: 'group' }, { uuid: 'd-1', source: 'default' }])).toBe('g-1')
		expect(effectiveDefaultUuid('', [{ uuid: 's-1', source: 'shared' }, { uuid: 'd-1', source: 'default' }])).toBe('d-1')
		expect(effectiveDefaultUuid('', [{ uuid: 's-1', source: 'shared', isDefault: 1 }])).toBe('')
		expect(effectiveDefaultUuid('', [])).toBe('')
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

	// more-menu-favourites task 10: Announcements after Admin settings for
	// whoever may manage announcements.
	it('offers Announcements after Admin settings to whoever may manage announcements', () => {
		const items = (isAdmin) => settingsMenuItems(startPageSections(
			{ railSuppressed: true, entries: ENTRIES, isAdmin, canManageAnnouncements: true, includePersonalSettings: true },
			() => {},
		))
		const admin = items(true)
		expect(admin.map((i) => i.label).slice(0, 3)).toEqual(['Personal settings', 'Admin settings', 'Announcements'])
		expect(admin[2].href).toMatch(/\/settings\/admin\/launchpad#announcements$/)
		// A delegated manager who is not an administrator gets it too.
		expect(items(false).map((i) => i.label).slice(0, 2)).toEqual(['Personal settings', 'Announcements'])
	})

	it('leaves Announcements out for anyone who may not manage them', () => {
		expect(settingsLabels(true)).not.toContain('Announcements')
		expect(settingsLabels(false)).not.toContain('Announcements')
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

	it('offers the board\'s eight actions to the owner, Download between Duplicate and Set as default, Delete last and destructive', () => {
		const items = dashboardActionItems(all)
		expect(items.map((i) => i.label)).toEqual([
			'Edit',
			'Add widget',
			'Sharing',
			'Copy link to this dashboard',
			'Duplicate as new dashboard',
			'Download',
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
			'toggleEdit', 'addWidget', 'share', 'copyLink', 'duplicate', 'download', 'setDefault', 'delete',
		])
	})
})

describe('dashboardIcon', () => {
	it('draws the dashboard\'s own icon by name, the house for the default, else the renderer\'s default', async () => {
		const { dashboardIcon } = await import('../moreMenuItems.js')
		expect(dashboardIcon({ icon: 'Bullhorn' }, false).name).toBe('DashboardMenuIcon')
		expect(dashboardIcon({ icon: '' }, true).name).toMatch(/Home/)
		// dashboard-icons: no icon goes through the shared renderer, which draws its default.
		expect(dashboardIcon({}, false).name).toBe('DashboardMenuIcon')
		expect(dashboardIcon({ icon: '   ' }, false).name).toBe('DashboardMenuIcon')
	})
})
