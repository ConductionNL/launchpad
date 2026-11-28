/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin settings page's address: `/settings/admin/launchpad#<tab>`,
 * inside Nextcloud's settings framework (admin-settings REQ-ADM-032). The
 * open tab is the URL fragment, so a link opens it and Back returns to the
 * previous tab, without a router. `lib/Settings/AdminTabs.php` knows the
 * same tabs.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
 */

import { generateUrl } from '@nextcloud/router'

/** The tab a page without a (known) tab opens on. */
export const DEFAULT_ADMIN_TAB = 'general'

/** The tab a delegated announcements manager holds (REQ-ADM-034). */
export const ANNOUNCEMENTS_TAB = 'announcements'

/**
 * The thirteen tabs, in the boards' order (LpBeheer*): General,
 * Announcements, Templates, Roles and rights, Sharing, Organisation menu,
 * Kiosk, Versions and analytics, Controls, Demo data, Group dashboards,
 * Detail fields, Profile fields.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
 */
export const ADMIN_TABS = [
	'general',
	'announcements',
	'templates',
	'roles-permissions',
	'sharing',
	'org-navigation',
	'kiosk',
	'versioning-audit',
	'operations',
	'demo-data',
	'group-dashboards',
	'detail-fields',
	'profile-fields',
]

/**
 * Former tabs that are now a section of a drawn tab, and the tab that holds
 * each: their fragment opens the host tab at that section.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
 */
export const FOLDED_ADMIN_TABS = Object.freeze({
	embeds: 'general',
	'search-shortcuts': 'general',
	communities: 'group-dashboards',
	'app-catalogue': 'operations',
})

/**
 * The tab to open for the one an address names: that tab when it exists,
 * the tab that holds it when it is a former tab, else General.
 *
 * @param {string} [tab] The tab the address names.
 * @return {string} A tab id.
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
 */
export function adminTab(tab) {
	if (ADMIN_TABS.includes(tab)) {
		return tab
	}
	return Object.hasOwn(FOLDED_ADMIN_TABS, tab ?? '') ? FOLDED_ADMIN_TABS[tab] : DEFAULT_ADMIN_TAB
}

/**
 * The tab and section a URL fragment names: `#sharing` opens Sharing,
 * `#embeds` opens General at its Embeds section,
 * `#sharing/section-dashboard-registry` opens Sharing at the element with
 * that id (a settings link of `lib/Settings/connections.json`), an empty or
 * unknown fragment opens General.
 *
 * @param {string} [hash] The fragment, with or without its `#`.
 * @return {{tab: string, section: string}} The tab and the section ('' for none).
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
 */
export function parseAdminFragment(hash) {
	const [name, rest = ''] = String(hash ?? '').replace(/^#/, '').split('/')
	let section = ''
	if (Object.hasOwn(FOLDED_ADMIN_TABS, name)) {
		section = name
	} else if (/^section-[a-z0-9-]+$/.test(rest)) {
		section = rest
	}
	return { tab: adminTab(name), section }
}

/**
 * The tab to open among the ones the viewer holds: the named tab when held,
 * else the first held tab.
 *
 * @param {string} tab The tab the address names.
 * @param {string[]} held The tabs the viewer holds, in the page's order.
 * @return {string} A tab id ('' when the viewer holds none).
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
 */
export function heldTab(tab, held) {
	if (held.includes(tab)) {
		return tab
	}
	return held[0] ?? ''
}

/**
 * The tabs the viewer holds, in the page's order: the server's list
 * (initial state `adminTabs`), every tab when it sent none.
 *
 * @param {unknown} provided The `adminTabs` initial state.
 * @return {string[]} Tab ids.
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
 */
export function heldTabs(provided) {
	if (!Array.isArray(provided) || provided.length === 0) {
		return [...ADMIN_TABS]
	}
	return ADMIN_TABS.filter((tab) => provided.includes(tab))
}

/**
 * The address of the admin settings page on one tab (or a former tab's
 * section), with an optional query, e.g. the widget an embed token is for.
 *
 * @param {string} [tab] The tab or former tab.
 * @param {string} [query] A query string without `?`.
 * @return {string} The URL.
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
 */
export function adminPageUrl(tab = DEFAULT_ADMIN_TAB, query = '') {
	const fragment = ADMIN_TABS.includes(tab) || Object.hasOwn(FOLDED_ADMIN_TABS, tab ?? '') ? tab : DEFAULT_ADMIN_TAB
	const search = query ? `?${query}` : ''
	return `${generateUrl('/settings/admin/launchpad')}${search}#${fragment}`
}
