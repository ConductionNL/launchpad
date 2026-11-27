/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The administration page's address: `/apps/launchpad/admin/<tab>`, a
 * Launchpad page over the full width (admin-settings REQ-ADM-030). The tab
 * is in the path, so a link opens it and switching tabs changes it.
 *
 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */

import { generateUrl } from '@nextcloud/router'

/** The tab a page without a (known) tab opens on. */
export const DEFAULT_ADMIN_TAB = 'general'

/**
 * The tabs of the administration, as `AdminSettings.vue` declares them and
 * `AdminPageController::TABS` knows them: the twelve tabs the Beheer boards
 * draw (LpBeheer*), in their order.
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-the-administration-shows-the-twelve-drawn-tabs-req-adm-032
 */
export const ADMIN_TABS = [
	'general',
	'templates',
	'operations',
	'roles-permissions',
	'versioning-audit',
	'sharing',
	'kiosk',
	'org-navigation',
	'demo-data',
	'group-dashboards',
	'detail-fields',
	'profile-fields',
]

/**
 * Former tabs that are now a section of a drawn tab (REQ-ADM-032), and the
 * tab that holds each: an old address or link opens the host tab on that
 * section. `AdminPageController::FOLDED_TABS` knows the same map.
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-the-administration-shows-the-twelve-drawn-tabs-req-adm-032
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
 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 * @spec openspec/specs/admin-settings/spec.md#requirement-the-administration-shows-the-twelve-drawn-tabs-req-adm-032
 */
export function adminTab(tab) {
	if (ADMIN_TABS.includes(tab)) {
		return tab
	}
	return Object.hasOwn(FOLDED_ADMIN_TABS, tab ?? '') ? FOLDED_ADMIN_TABS[tab] : DEFAULT_ADMIN_TAB
}

/**
 * The folded section an address points at: the former tab it names in its
 * path or in `?section=` / `?tab=`, or null.
 *
 * @param {string} [tab] The tab the path names.
 * @param {string} [search] The query string, e.g. `?tab=embeds&embedType=widget`.
 * @return {string|null} A key of FOLDED_ADMIN_TABS, or null.
 * @spec openspec/specs/admin-settings/spec.md#requirement-the-administration-shows-the-twelve-drawn-tabs-req-adm-032
 */
export function adminSection(tab, search = '') {
	const query = new URLSearchParams(String(search ?? ''))
	const candidates = [tab, query.get('section'), query.get('tab')]
	const found = candidates.find((name) => typeof name === 'string' && Object.hasOwn(FOLDED_ADMIN_TABS, name))
	return found ?? null
}

/**
 * The address of the administration page on one tab.
 *
 * @param {string} [tab] The tab.
 * @return {string} The URL.
 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */
export function adminPageUrl(tab = DEFAULT_ADMIN_TAB) {
	// The tab is one of ADMIN_TABS, so it needs no encoding. A former tab
	// opens its host tab (REQ-ADM-032); a caller that wants the section
	// names it in the query (`tab=` or `section=`), see adminSection().
	return generateUrl(`/apps/launchpad/admin/${adminTab(tab)}`)
}

/**
 * Whether an address is the administration page, which the server renders
 * with the administration's initial state.
 *
 * @param {string} pathname The pathname.
 * @return {boolean} True for `…/apps/launchpad/admin` and its tabs.
 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */
export function isAdminPagePath(pathname) {
	return /\/apps\/launchpad\/admin(?:\/|$)/.test(String(pathname ?? ''))
}
