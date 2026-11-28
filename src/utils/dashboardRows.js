/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Helpers for one dashboard row of the More menu: whether the caller owns it,
 * and the Beheer ▸ Embeds address for it. They lived in the removed
 * dashboards sidebar's row actions (more-menu-favourites, task 9).
 */

import { adminPageUrl } from './adminPage.js'
import { embedAdminSearch } from './embedToken.js'

/**
 * The address of Beheer ▸ Embeds with a dashboard pre-selected, or one
 * widget placement of it when `placement` is given.
 *
 * @param {object|null} dashboard The dashboard.
 * @param {object|null} placement A widget placement on the dashboard.
 * @return {string}
 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
 */
export function embedAdminHref(dashboard, placement = null) {
	const search = placement
		? embedAdminSearch('widget', placement.id ?? '', dashboard?.id ?? null)
		: embedAdminSearch('dashboard', dashboard?.uuid ?? '', dashboard?.id ?? null)
	return adminPageUrl('embeds', search)
}

/**
 * Whether the caller owns a dashboard: a row under "My dashboards" (the
 * `user` source) always, any other row only when the dashboard says
 * `isOwner: true`. Owner-gated actions (configure, delete) use it.
 *
 * @param {object|null} dashboard The dashboard.
 * @param {string} source The dashboard's section.
 * @return {boolean}
 * @spec openspec/specs/runtime-shell/spec.md
 */
export function isOwnedDashboard(dashboard, source) {
	if (source === 'user') {
		return true
	}
	return dashboard?.isOwner === true
}

