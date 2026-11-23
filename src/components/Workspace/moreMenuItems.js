/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The items of launchpad's one More menu (board LpMeer), built the same way
 * on every page: the Dashboards group, the items under Settings and the
 * This dashboard group. LaunchpadMoreMenu draws them.
 */

import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { markRaw } from 'vue'
import AccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import BookOpenVariantOutline from 'vue-material-design-icons/BookOpenVariantOutline.vue'
import ChartBoxOutline from 'vue-material-design-icons/ChartBoxOutline.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
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
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import ViewGridOutline from 'vue-material-design-icons/ViewGridOutline.vue'

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
			href: generateUrl('/settings/admin/{appId}', {
				appId: 'launchpad',
			}),
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

/** How many dashboards the menu lists before "All dashboards". */
export const DASHBOARD_LIMIT = 8

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
 * The Dashboards group: the person's own dashboards, then the shared and
 * group ones, at most DASHBOARD_LIMIT, the open one checked, and "All
 * dashboards" last.
 *
 * @param {object} options The dashboards and what choosing one does.
 * @param {object[]} options.userDashboards The person's own dashboards.
 * @param {object[]} options.groupDashboards The group, default and shared dashboards.
 * @param {object[]} options.sharedDashboards Which of those came through a share.
 * @param {string|number|null} options.activeId The open dashboard's id.
 * @param {string} options.defaultUuid The uuid of the person's default dashboard.
 * @param {(id: string|number, source: string) => void} options.onSwitch Open a dashboard.
 * @param {() => void} options.onOpenAll Open the full list.
 * @return {object[]} The items.
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
 */
export function dashboardItems({
	userDashboards = [],
	groupDashboards = [],
	sharedDashboards = [],
	activeId = null,
	defaultUuid = '',
	onSwitch,
	onOpenAll,
}) {
	const shared = new Set(sharedDashboards.map((d) => d?.id))
	const rows = [
		...userDashboards.map((d) => ({ d, source: 'user' })),
		...groupDashboards.map((d) => ({ d, source: shared.has(d?.id) ? 'shared' : 'group' })),
	].filter(({ d }) => d && d.id !== undefined && d.id !== null)
	const seen = new Set()
	const items = []
	for (const { d, source } of rows) {
		if (seen.has(d.id) || items.length >= DASHBOARD_LIMIT) {
			continue
		}
		seen.add(d.id)
		const isDefault = Boolean(d.uuid) && d.uuid === defaultUuid
		items.push({
			id: `dashboard-${d.id}`,
			label: d.name || t('launchpad', 'Dashboard'),
			icon: markRaw(isDefault ? Home : ViewDashboardOutline),
			meta: dashboardMeta(isDefault, source),
			checked: d.id === activeId,
			onClick: () => onSwitch(d.id, source),
		})
	}
	items.push({
		id: 'all-dashboards',
		label: t('launchpad', 'All dashboards'),
		icon: markRaw(ViewGridOutline),
		onClick: () => onOpenAll(),
	})
	return items
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
export function dashboardActionItems({
	dashboard,
	isOwner = false,
	isDefault = false,
	isEditMode = false,
	canEdit = false,
	canShare = false,
	canDuplicate = false,
	emit,
}) {
	if (!dashboard) {
		return []
	}
	const action = (id, label, icon, event, extra = {}) => ({
		id,
		label,
		icon: markRaw(icon),
		onClick: () => emit(event),
		...extra,
	})
	return [
		canEdit && action(
			'edit',
			isEditMode ? t('launchpad', 'Save dashboard') : t('launchpad', 'Edit'),
			isEditMode ? ContentSave : Pencil,
			'toggleEdit',
		),
		canEdit && action('add-widget', t('launchpad', 'Add widget'), Plus, 'addWidget'),
		canShare && action('share', t('launchpad', 'Share'), ShareVariant, 'share'),
		action('copy-link', t('launchpad', 'Copy link to this dashboard'), LinkVariant, 'copyLink'),
		canDuplicate && action('duplicate', t('launchpad', 'Duplicate as new dashboard'), ContentCopy, 'duplicate'),
		action(
			'set-default',
			isDefault ? t('launchpad', 'Default dashboard') : t('launchpad', 'Set as default'),
			isDefault ? StarCheck : Star,
			'setDefault',
		),
		isOwner && action('delete', t('launchpad', 'Delete'), TrashCanOutline, 'delete', { destructive: true }),
	].filter(Boolean)
}
