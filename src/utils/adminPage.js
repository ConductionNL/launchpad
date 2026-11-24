/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The administration page's address: `/apps/launchpad/admin/<tab>`, a
 * Launchpad page over the full width (admin-settings REQ-ADM-030). The tab
 * is in the path, so a link opens it and switching tabs changes it.
 *
 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */

import { generateUrl } from '@nextcloud/router'

/** The tab a page without a (known) tab opens on. */
export const DEFAULT_ADMIN_TAB = 'general'

/**
 * The tabs of the administration, as `AdminSettings.vue` declares them and
 * `AdminPageController::TABS` knows them.
 */
export const ADMIN_TABS = [
	'general',
	'templates',
	'operations',
	'roles-permissions',
	'versioning-audit',
	'sharing',
	'embeds',
	'kiosk',
	'communities',
	'org-navigation',
	'demo-data',
	'group-dashboards',
	'detail-fields',
	'profile-fields',
	'search-shortcuts',
	'app-catalogue',
]

/**
 * The tab to open for the one an address names: that tab when it exists,
 * else General.
 *
 * @param {string} [tab] The tab the address names.
 * @return {string} A tab id.
 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */
export function adminTab(tab) {
	return ADMIN_TABS.includes(tab) ? tab : DEFAULT_ADMIN_TAB
}

/**
 * The address of the administration page on one tab.
 *
 * @param {string} [tab] The tab.
 * @return {string} The URL.
 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */
export function adminPageUrl(tab = DEFAULT_ADMIN_TAB) {
	// The tab is one of ADMIN_TABS, so it needs no encoding.
	return generateUrl(`/apps/launchpad/admin/${adminTab(tab)}`)
}

/**
 * Whether an address is the administration page, which the server renders
 * with the administration's initial state.
 *
 * @param {string} pathname The pathname.
 * @return {boolean} True for `…/apps/launchpad/admin` and its tabs.
 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */
export function isAdminPagePath(pathname) {
	return /\/apps\/launchpad\/admin(?:\/|$)/.test(String(pathname ?? ''))
}
