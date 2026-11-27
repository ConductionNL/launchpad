/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The items of launchpad's one More menu (board LpMeer), built the same way
 * on every page: the Dashboards group, the items under Settings and the
 * This dashboard group. LaunchpadMoreMenu draws them.
 */

import { CnDashboardIcon } from '@conduction/nextcloud-vue'
import { n, t } from '@nextcloud/l10n'
import { h, markRaw } from 'vue'
import AccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import BookOpenVariantOutline from 'vue-material-design-icons/BookOpenVariantOutline.vue'
import ChartBoxOutline from 'vue-material-design-icons/ChartBoxOutline.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import Download from 'vue-material-design-icons/Download.vue'
import FileReplaceOutline from 'vue-material-design-icons/FileReplaceOutline.vue'
import Home from 'vue-material-design-icons/Home.vue'
import LinkVariant from 'vue-material-design-icons/LinkVariant.vue'
import MapMarkerPath from 'vue-material-design-icons/MapMarkerPath.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import PowerPlugOutline from 'vue-material-design-icons/PowerPlugOutline.vue'
import ShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import ShieldAccountOutline from 'vue-material-design-icons/ShieldAccountOutline.vue'
import Sitemap from 'vue-material-design-icons/Sitemap.vue'
import Star from 'vue-material-design-icons/Star.vue'
import StarCheck from 'vue-material-design-icons/StarCheck.vue'
import StoreOutline from 'vue-material-design-icons/StoreOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import ViewGridOutline from 'vue-material-design-icons/ViewGridOutline.vue'
import { adminPageUrl } from '../../utils/adminPage.js'

/**
 * The sections of the navigation panel, in the panel's order.
 */
const SECTIONS = ['main', 'footer', 'settings']

/** The items of the Settings group, in the board's order. */
const SETTINGS_ITEMS = ['personal-settings', 'admin-settings-link']

/**
 * The order of the app's own destinations, as the boards list them. An
 * entry not named here follows, in the panel's order.
 */
const LAUNCHPAD_ORDER = [
	'StoreMenu',
	'admin-templates',
	'ReportsMenu',
	'FlowsMenu',
	'IntegrationsMenu',
	'Documentation',
	'FeaturesRoadmapMenu',
]

/**
 * Panel entries the Launchpad group leaves out because another item already
 * offers them: the dashboards (the Dashboards group) and the in-app admin
 * settings route (the Settings group links to the admin settings).
 */
const OFFERED_ELSEWHERE = ['dashboards', 'admin-settings']

/**
 * The app's own destinations in the board's order.
 *
 * @param {object[]} items The resolved panel items.
 * @return {object[]} The items, ordered.
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
 */
export function orderDestinations(items) {
	const rank = (item) => {
		const at = LAUNCHPAD_ORDER.indexOf(item.id)
		return at === -1 ? LAUNCHPAD_ORDER.length : at
	}
	return items
		.map((item, index) => ({ item, index }))
		.sort((a, b) => rank(a.item) - rank(b.item) || a.index - b.index)
		.map(({ item }) => item)
}

/**
 * The Settings group's items: the personal settings and, for an
 * administrator, the admin settings link.
 *
 * @param {Record<string, object[]>} sections The menu items by section.
 * @return {object[]} The items.
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
 */
export function settingsItems(sections) {
	return SETTINGS_ITEMS.map((id) =>
		sections.settings.find((item) => item.id === id),
	).filter(Boolean)
}

/**
 * The Launchpad group's items: the app's own destinations in the board's
 * order, without the ones another group already offers.
 *
 * @param {Record<string, object[]>} sections The menu items by section.
 * @return {object[]} The items.
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
 */
export function launchpadItems(sections) {
	return orderDestinations(
		[...sections.main, ...sections.footer, ...sections.settings].filter(
			(item) =>
				!OFFERED_ELSEWHERE.includes(item.id)
				&& !SETTINGS_ITEMS.includes(item.id),
		),
	)
}

/**
 * Whether a destination leaves the instance (scheme-prefixed), which is
 * the same test the panel's `NcAppNavigationItem` applies before it opens
 * a new tab.
 *
 * @param {string} href The destination.
 * @return {boolean} True for a `scheme://` URL.
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
 */
export function isExternal(href) {
	return /^(https?:)?\/\//.test(String(href ?? ''))
}

/**
 * Resolve one panel entry to a menu item: a link for `href`, a router
 * target for `route`, a button for `action: "user-settings"`. The same
 * precedence as the panel (`CnAppNav.itemTo` / `itemHref`): an action
 * never carries a link.
 *
 * @param {object} item The manifest menu entry.
 * @param {(() => void)|null} openUserSettings The injected personal-settings opener.
 * @return {object|null} The menu item, or null when the entry has no destination.
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
 */
export function resolveMenuItem(item, openUserSettings) {
	const label = t('launchpad', String(item.label ?? item.id ?? ''))
	if (item.action === 'user-settings') {
		if (typeof openUserSettings !== 'function') {
			return null
		}
		return { id: item.id, label, onClick: openUserSettings }
	}
	if (item.href) {
		return {
			id: item.id,
			label,
			href: item.href,
			external: isExternal(item.href),
		}
	}
	if (item.route) {
		return {
			id: item.id,
			label,
			to: item.query
				? { name: item.route, query: item.query }
				: { name: item.route },
		}
	}
	return null
}

/**
 * The panel's destinations by section (`main`, `footer`, `settings`) for
 * the chrome decision `App.vue` provides. The settings section opens with
 * the personal settings and, for an administrator, the link to the admin
 * settings, as the panel's settings foldout does.
 *
 * @param {{ entries: object[], isAdmin: boolean, includePersonalSettings: boolean }} chrome The chrome decision.
 * @param {(() => void)|null} openUserSettings The injected personal-settings opener.
 * @return {Record<string, object[]>} The menu items by section.
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */
export function startPageSections(chrome, openUserSettings) {
	const bySection = Object.fromEntries(SECTIONS.map((s) => [s, []]))
	for (const entry of chrome.entries) {
		const section = entry.section ?? 'main'
		if (!(section in bySection)) {
			continue
		}
		const item = resolveMenuItem(entry, openUserSettings)
		if (item) {
			bySection[section].push(item)
		}
	}
	const settings = []
	if (chrome.includePersonalSettings && typeof openUserSettings === 'function') {
		settings.push({
			id: 'personal-settings',
			label: t('launchpad', 'Personal settings'),
			onClick: openUserSettings,
		})
	}
	if (chrome.isAdmin) {
		settings.push({
			id: 'admin-settings-link',
			label: t('launchpad', 'Admin settings'),
			// The administration, a Launchpad page (admin-settings REQ-ADM-030).
			href: adminPageUrl(),
			external: false,
		})
	}
	bySection.settings = [...settings, ...bySection.settings]
	return bySection
}

/**
 * Read the chrome decision `App.vue` provides, unwrapped, with defaults
 * that keep the panel.
 *
 * @param {object|null} provided The injected `launchpadChrome` (a ref or a plain value).
 * @return {{ railSuppressed: boolean, entries: object[], isAdmin: boolean, includePersonalSettings: boolean }}
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
 */
export function readChrome(provided) {
	const value = provided?.value ?? provided
	return {
		railSuppressed: value?.railSuppressed === true,
		entries: Array.isArray(value?.entries) ? value.entries : [],
		isAdmin: value?.isAdmin === true,
		includePersonalSettings: value?.includePersonalSettings !== false,
	}
}

/** Icons of the destinations under Settings, by menu entry id. */
const DESTINATION_ICONS = {
	StoreMenu: StoreOutline,
	'admin-templates': FileReplaceOutline,
	ReportsMenu: ChartBoxOutline,
	FlowsMenu: Sitemap,
	IntegrationsMenu: PowerPlugOutline,
	Documentation: BookOpenVariantOutline,
	FeaturesRoadmapMenu: MapMarkerPath,
	'personal-settings': AccountOutline,
	'admin-settings-link': ShieldAccountOutline,
}

/**
 * The note beside a dashboard: default, shared or group.
 *
 * @param {boolean} isDefault Whether it is the person's default dashboard.
 * @param {string} source The dashboard's section.
 * @return {string|undefined}
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
 */
export function dashboardMeta(isDefault, source) {
	if (isDefault) {
		return t('launchpad', 'default')
	}
	if (source === 'shared') {
		return t('launchpad', 'shared')
	}
	if (source === 'group') {
		return t('launchpad', 'group')
	}
	return undefined
}

/**
 * The glyph a dashboard row shows: the dashboard's own icon (LpMeer draws
 * Today's house, a chart for management information, a megaphone for the
 * intranet), else the default dashboard's house or the generic dashboard.
 *
 * @param {object} dashboard The dashboard.
 * @param {boolean} isDefault Whether it opens first.
 * @return {object} A component that takes `size`.
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
 */
export function dashboardIcon(dashboard, isDefault) {
	const name = typeof dashboard?.icon === 'string' ? dashboard.icon.trim() : ''
	if (name === '' && isDefault) {
		return markRaw(Home)
	}
	// No icon: the shared renderer draws its default (dashboard-icons), the
	// same component the removed sidebar showed.
	return markRaw({
		name: 'DashboardMenuIcon',
		props: { size: { type: Number, default: 18 } },
		/**
		 * @return {object} The dashboard icon at the menu's size.
		 */
		render() {
			return h(CnDashboardIcon, { name, size: this.size })
		},
	})
}

/**
 * The dashboard that opens automatically (REQ-EDM-001..005), which carries the
 * Default pill (REQ-SHELL-018): the person's pin, else the resolver's group
 * fallback (steps 2-5): the flagged primary-group dashboard, the flagged
 * default-group one, the first primary-group one, the first default-group
 * one. A personal dashboard is never marked without a pin.
 *
 * @param {string} pinned The uuid of the person's pinned default, or ''.
 * @param {object[]} groupDashboards The group, default and shared dashboards.
 * @return {string} The uuid, or ''.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-15
 */
export function effectiveDefaultUuid(pinned, groupDashboards = []) {
	if (pinned) {
		return pinned
	}
	const primary = groupDashboards.filter((d) => d.source !== 'default' && d.source !== 'shared')
	const fallback = groupDashboards.filter((d) => d.source === 'default')
	const flagged = (list) => list.find((d) => Number(d?.isDefault) === 1)
	const found = [flagged(primary), flagged(fallback), primary[0], fallback[0]].find((d) => Boolean(d?.uuid))
	return found?.uuid ?? ''
}

/**
 * The Default pill on the dashboard that opens automatically (REQ-SHELL-018):
 * a badge, not a star; the star is the favourite.
 *
 * @return {{label: string, title: string, ariaLabel: string}} The pill.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-15
 */
function DEFAULT_PILL () {
  return {
	label: t('launchpad', 'Default'),
	title: t('launchpad', 'Opens automatically'),
	ariaLabel: t('launchpad', 'Default dashboard'),
}
}

/** What {@link dashboardItems} assumes for an option it is not given. */
const DASHBOARD_ITEM_DEFAULTS = {
	userDashboards: [],
	groupDashboards: [],
	sharedDashboards: [],
	activeId: null,
	defaultUuid: '',
	favourites: [],
	expanded: false,
	onToggleExpanded: () => {},
	onToggleFavourite: () => {},
	onNewDashboard: null,
	openTrees: [],
	onToggleTree: () => {},
}

/**
 * The Dashboards group (Ruben's review, 27 Nov 2026, items 5 and 6).
 *
 * Folded, it is a short list: the person's favourite dashboards, in the
 * order they starred them. A person without favourites sees the open
 * dashboard and a line saying that starring a dashboard puts it here
 * (Ruben's decision, 27 Nov 2026). The open dashboard is checked. "More" closes the list and unfolds the column in
 * place into every dashboard the person can open, favourites first, with
 * "New dashboard" below it; "Less" folds it again. Every row carries a star
 * that marks or unmarks it as a favourite (REQ-SHELL-014..016).
 *
 * @param {object} options The dashboards and what choosing one does.
 * @param {object[]} options.userDashboards The person's own dashboards.
 * @param {object[]} options.groupDashboards The group, default and shared dashboards.
 * @param {object[]} options.sharedDashboards Which of those came through a share.
 * @param {string|number|null} options.activeId The open dashboard's id.
 * @param {string} options.defaultUuid The uuid of the person's default dashboard.
 * @param {string[]} options.favourites The uuids of the person's favourite dashboards.
 * @param {boolean} options.expanded Whether the column lists every dashboard.
 * @param {(id: string|number, source: string) => void} options.onSwitch Open a dashboard.
 * @param {() => void} options.onToggleExpanded Unfold or fold the column.
 * @param {(uuid: string) => void} options.onToggleFavourite Mark or unmark a favourite.
 * @param {(() => void)|null} options.onNewDashboard Create and open a personal dashboard; null when
 *   personal dashboards are off or the quota is reached, which leaves New dashboard out.
 * @return {object[]} The items.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-3
 */
export function dashboardItems(options) {
	const o = { ...DASHBOARD_ITEM_DEFAULTS, ...options }
	const all = dashboardRows(o.userDashboards, o.groupDashboards, o.sharedDashboards)
	const { rows, starred, showHint, hidden } = columnRows(all, o)
	const defaultUuid = effectiveDefaultUuid(o.defaultUuid, o.groupDashboards)
	const context = { activeId: o.activeId, defaultUuid, starred, onSwitch: o.onSwitch, onToggleFavourite: o.onToggleFavourite, onToggleTree: o.onToggleTree }
	return [
		...rows.map((row) => dashboardRowItem(row, context)),
		...hintItems(showHint),
		...columnFoot(hidden, o.expanded, o.onToggleExpanded, o.onNewDashboard),
	]
}

/**
 * The rows of the expanded list as a tree (REQ-TREEUI-001, REQ-SHELL-019):
 * every dashboard whose parent is listed goes under that parent, depth-first,
 * and a parent's children show only while it is open. A parent outside the
 * list, or a dashboard naming itself, counts as no parent.
 *
 * @param {object[]} rows The `{d, source}` rows, in list order.
 * @param {Set<string>} open The uuids of the open parents.
 * @return {object[]} The rows with `depth`, `hasChildren` and `open`.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-16
 */
function treeRows(rows, open) {
	const byUuid = new Map(rows.filter(({ d }) => Boolean(d.uuid)).map((row) => [row.d.uuid, row]))
	const children = new Map()
	const roots = []
	for (const row of rows) {
		const parent = row.d.parentUuid
		const nested = Boolean(parent) && parent !== row.d.uuid && byUuid.has(parent)
		if (nested) {
			children.set(parent, [...(children.get(parent) ?? []), row])
		} else {
			roots.push(row)
		}
	}
	const out = []
	const seen = new Set()
	const walk = (row, depth) => {
		const key = row.d.uuid ?? row.d.id
		if (seen.has(key)) {
			return
		}
		seen.add(key)
		const kids = children.get(row.d.uuid) ?? []
		const isOpen = open.has(row.d.uuid)
		out.push({ ...row, depth, hasChildren: kids.length > 0, open: isOpen })
		kids.filter(() => isOpen).forEach((kid) => walk(kid, depth + 1))
	}
	roots.forEach((root) => walk(root, 0))
	return out
}

/**
 * The tree fields of a row item: its depth and, for a parent, its expand
 * control. Folded rows carry none.
 *
 * @param {object} row The row, with `depth`, `hasChildren` and `open` in the expanded list.
 * @param {(uuid: string) => void} onToggleTree Show or hide a parent's children.
 * @return {object} The fields.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-16
 */
function treeFields(row, onToggleTree) {
	if (row.depth === undefined) {
		return {}
	}
	const fields = { depth: row.depth }
	if (row.hasChildren) {
		fields.tree = { uuid: row.d.uuid, open: row.open, onToggle: () => onToggleTree(row.d.uuid) }
	}
	return fields
}

/**
 * The line that a starred dashboard appears here, before anything is
 * starred (Ruben, 27 Nov 2026).
 *
 * @param {boolean} show Whether the column shows it.
 * @return {object[]} The line, or nothing.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-3
 */
function hintItems(show) {
	if (!show) {
		return []
	}
	return [{ id: 'favourites-hint', hint: true, label: t('launchpad', 'Star a dashboard to keep it here') }]
}

/**
 * The rows the Dashboards column shows. Folded: the favourites in starring
 * order, or, before anything is starred, the open dashboard (Ruben, 27 Nov
 * 2026). Expanded: the favourites first, then every other dashboard.
 *
 * @param {object[]} all Every `{d, source}` row.
 * @param {object} options The column's options (see {@link dashboardItems}).
 * @param {string[]} options.favourites The favourite uuids in starring order.
 * @param {boolean} options.expanded Whether the column lists every dashboard.
 * @param {string|number|null} options.activeId The open dashboard's id.
 * @param {string[]} options.openTrees The uuids of the parents whose children show (expanded list).
 * @return {{rows: object[], starred: Set<string>, showHint: boolean, hidden: number}} The rows,
 *   and how many dashboards the folded column leaves out.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-3
 */
function columnRows(all, { favourites, expanded, activeId, openTrees = [] }) {
	const byUuid = new Map(all.filter(({ d }) => Boolean(d.uuid)).map((row) => [row.d.uuid, row]))
	const favouriteRows = favourites.map((uuid) => byUuid.get(uuid)).filter(Boolean)
	const starred = new Set(favouriteRows.map(({ d }) => d.uuid))
	if (expanded) {
		const others = all.filter(({ d }) => !starred.has(d.uuid))
		return { rows: [...favouriteRows, ...treeRows(others, new Set(openTrees))], starred, showHint: false, hidden: all.length }
	}
	if (favouriteRows.length > 0) {
		return { rows: favouriteRows, starred, showHint: false, hidden: all.length - favouriteRows.length }
	}
	const open = all.filter(({ d }) => d.id === activeId)
	return { rows: open, starred, showHint: true, hidden: all.length - open.length }
}

/**
 * One dashboard row: radio item, icon, marker and, with a uuid, its star
 * (REQ-SHELL-016: every row, short or expanded).
 *
 * @param {{d: object, source: string, depth?: number}} row The dashboard, its section and, expanded, its tree place.
 * @param {object} context What the row needs to know and do.
 * @param {string|number|null} context.activeId The open dashboard's id.
 * @param {string} context.defaultUuid The uuid of the person's default dashboard.
 * @param {Set<string>} context.starred The favourite uuids.
 * @param {(id: string|number, source: string) => void} context.onSwitch Open a dashboard.
 * @param {(uuid: string) => void} context.onToggleFavourite Mark or unmark a favourite.
 * @param {(uuid: string) => void} context.onToggleTree Show or hide a parent's children.
 * @return {object} The item.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-4
 */
function dashboardRowItem(row, { activeId, defaultUuid, starred, onSwitch, onToggleFavourite, onToggleTree }) {
	const { d, source } = row
	const isDefault = Boolean(d.uuid) && d.uuid === defaultUuid
	const item = {
		id: `dashboard-${d.id}`,
		label: d.name || t('launchpad', 'Dashboard'),
		icon: dashboardIcon(d, isDefault),
		meta: dashboardMeta(false, source),
		pill: isDefault ? DEFAULT_PILL() : undefined,
		checked: d.id === activeId,
		onClick: () => onSwitch(d.id, source),
	}
	if (d.uuid) {
		item.favourite = starred.has(d.uuid)
		item.onToggleFavourite = () => onToggleFavourite(d.uuid)
	}
	return { ...item, ...treeFields(row, onToggleTree) }
}

/**
 * The foot of the Dashboards column: New dashboard (expanded, when creating
 * is allowed) and More / Less (REQ-SHELL-015).
 *
 * @param {number} hidden How many dashboards the folded column leaves out.
 * @param {boolean} expanded Whether the column lists every dashboard.
 * @param {() => void} onToggleExpanded Unfold or fold the column.
 * @param {(() => void)|null} onNewDashboard Create a dashboard, or null.
 * @return {object[]} The items.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-3
 */
function columnFoot(hidden, expanded, onToggleExpanded, onNewDashboard) {
	const foot = []
	if (expanded && typeof onNewDashboard === 'function') {
		foot.push({
			id: 'new-dashboard',
			label: t('launchpad', 'New dashboard'),
			icon: markRaw(Plus),
			onClick: () => onNewDashboard(),
		})
	}
	foot.push({
		id: 'all-dashboards',
		label: expanded ? t('launchpad', 'Less') : t('launchpad', 'More'),
		meta: expanded
			? t('launchpad', 'favourites only')
			: n('launchpad', '%n more dashboard', '%n more dashboards', Math.max(hidden, 0)),
		icon: markRaw(expanded ? ChevronUp : ViewGridOutline),
		keepOpen: true,
		expanded,
		onClick: () => onToggleExpanded(),
	})
	return foot
}

/**
 * The favourites after the person starred or unstarred one dashboard.
 *
 * @param {string[]} favourites The uuids that are favourites now.
 * @param {string} uuid The dashboard the star belongs to.
 * @return {string[]} The new list: the uuid added at the end, or removed.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-4
 */
export function toggleFavourite(favourites, uuid) {
	const list = Array.isArray(favourites) ? favourites.filter((u) => typeof u === 'string' && u !== '') : []
	if (typeof uuid !== 'string' || uuid === '') {
		return list
	}
	return list.includes(uuid) ? list.filter((u) => u !== uuid) : [...list, uuid]
}

/**
 * The favourites read from the stored preference: a JSON list of uuids.
 * Anything else reads as no favourites.
 *
 * @param {string|null} stored The stored value.
 * @return {string[]} The uuids.
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-4
 */
export function parseFavourites(stored) {
	if (typeof stored !== 'string' || stored === '') {
		return []
	}
	try {
		const value = JSON.parse(stored)
		return Array.isArray(value) ? value.filter((u) => typeof u === 'string' && u !== '') : []
	} catch {
		return []
	}
}

/**
 * The dashboards the Dashboards group lists, each once with its section:
 * the person's own first, then the shared and group ones.
 *
 * @param {object[]} userDashboards The person's own dashboards.
 * @param {object[]} groupDashboards The group, default and shared dashboards.
 * @param {object[]} sharedDashboards Which of those came through a share.
 * @return {Array<{d: object, source: string}>} The rows.
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
 */
function dashboardRows(userDashboards, groupDashboards, sharedDashboards) {
	const shared = new Set(sharedDashboards.map((d) => d?.id))
	const seen = new Set()
	return [
		...userDashboards.map((d) => ({ d, source: 'user' })),
		...groupDashboards.map((d) => ({ d, source: shared.has(d?.id) ? 'shared' : 'group' })),
	]
		.filter(({ d }) => {
			const known = d && d.id !== undefined && d.id !== null && !seen.has(d.id)
			if (known) {
				seen.add(d.id)
			}
			return known
		})
}

/**
 * A destination as a menu item: its link or router target, its action and
 * its icon.
 *
 * @param {object} item The resolved destination.
 * @return {object} The menu item.
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
 */
export function destinationItem(item) {
	return {
		id: item.id,
		label: item.label,
		icon: DESTINATION_ICONS[item.id] ? markRaw(DESTINATION_ICONS[item.id]) : undefined,
		href: item.href,
		target: item.href && item.external ? '_blank' : undefined,
		to: item.to,
		onClick: item.onClick,
	}
}

/**
 * The items under Settings, in the board's order: Personal settings, Admin
 * settings (administrators only), then the app's own destinations
 * (Marketplace, Templates, Reports, Flows, Integrations, Documentation,
 * Features and roadmap).
 *
 * @param {Record<string, object[]>} sections The destinations by section.
 * @return {object[]} The items.
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
 */
export function settingsMenuItems(sections) {
	return [...settingsItems(sections), ...launchpadItems(sections)].map(destinationItem)
}

/**
 * The This dashboard group, with what the person may do with the open
 * dashboard: Edit, Add widget, Share, Copy link to this dashboard, Duplicate
 * as new dashboard, Set as default, and Delete last.
 *
 * @param {object} options The dashboard, the permissions and the handlers.
 * @param {object|null} options.dashboard The open dashboard.
 * @param {boolean} options.isOwner Whether it is the person's own.
 * @param {boolean} options.isDefault Whether it is the person's default.
 * @param {boolean} options.isEditMode Whether it is being edited.
 * @param {boolean} options.canEdit Whether the person may edit it.
 * @param {boolean} options.canShare Whether the person may share it.
 * @param {boolean} options.canDuplicate Whether the person may make a personal copy.
 * @param {(event: string) => void} options.emit Report the chosen action.
 * @return {object[]} The items.
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
 */
export function dashboardActionItems(options) {
	if (!options.dashboard) {
		return []
	}
	return actionRows(options)
		.filter((row) => row.offered === true)
		.map(({ id, label, icon, event, destructive }) => ({
			id,
			label,
			icon: markRaw(icon),
			onClick: () => options.emit(event),
			...(destructive ? { destructive } : {}),
		}))
}

/**
 * Every This dashboard action with whether this person is offered it.
 *
 * @param {object} options The options of {@link dashboardActionItems}.
 * @return {object[]} The rows, in the board's order.
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
 */
function actionRows(options) {
	const editing = options.isEditMode === true
	const isDefault = options.isDefault === true
	return [
		{ offered: options.canEdit, id: 'edit', label: editing ? t('launchpad', 'Save dashboard') : t('launchpad', 'Edit'), icon: editing ? ContentSave : Pencil, event: 'toggleEdit' },
		{ offered: options.canEdit, id: 'add-widget', label: t('launchpad', 'Add widget'), icon: Plus, event: 'addWidget' },
		{ offered: options.canShare, id: 'share', label: t('launchpad', 'Sharing'), icon: ShareVariant, event: 'share' },
		{ offered: true, id: 'copy-link', label: t('launchpad', 'Copy link to this dashboard'), icon: LinkVariant, event: 'copyLink' },
		{ offered: options.canDuplicate, id: 'duplicate', label: t('launchpad', 'Duplicate as new dashboard'), icon: ContentCopy, event: 'duplicate' },
		// REQ-DPD-003: Download sits between Duplicate and Set as default (board LpMeer).
		{ offered: options.canDownload !== false, id: 'download', label: t('launchpad', 'Download'), icon: Download, event: 'download' },
		{ offered: true, id: 'set-default', label: t('launchpad', 'Set as default'), icon: isDefault ? StarCheck : Star, event: 'setDefault' },
		{ offered: options.isOwner, id: 'delete', label: t('launchpad', 'Delete'), icon: TrashCanOutline, event: 'delete', destructive: true },
	]
}
