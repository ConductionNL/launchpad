/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The person's favourite dashboards (Ruben's review, 27 Nov 2026, item 6):
 * a list of dashboard uuids kept per user through the app's generic
 * preferences endpoint. The More menu reads them once and writes the whole
 * list back on every star.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { parseFavourites, toggleFavourite } from '../components/Workspace/moreMenuItems.js'

/** The preference key the favourites live under. */
export const FAVOURITE_DASHBOARDS_KEY = 'favourite-dashboards'

/**
 * The favourites endpoint.
 *
 * @return {string} The URL.
 */
function url() {
	return generateUrl(`/apps/launchpad/api/preferences/${FAVOURITE_DASHBOARDS_KEY}`)
}

/**
 * Read the person's favourites. A failed read is no favourites.
 *
 * @return {Promise<string[]>} The uuids.
 * @spec openspec/changes/more-menu-favourites/specs/runtime-shell/spec.md#requirement-req-shell-017-a-star-marks-a-dashboard-as-a-favourite-per-person
 */
export async function loadFavouriteDashboards() {
	try {
		const { data } = await axios.get(url())
		return parseFavourites(data?.value ?? null)
	} catch {
		return []
	}
}

/**
 * Store the person's favourites.
 *
 * @param {string[]} uuids The uuids.
 * @return {Promise<void>}
 * @spec openspec/changes/more-menu-favourites/specs/runtime-shell/spec.md#requirement-req-shell-017-a-star-marks-a-dashboard-as-a-favourite-per-person
 */
export async function saveFavouriteDashboards(uuids) {
	await axios.put(url(), { value: uuids.length > 0 ? JSON.stringify(uuids) : '' })
}

/**
 * The More menu's favourites state, shared by the dashboard view's menu and
 * the menu on every other page: `favourites`, `dashboardsExpanded`, a load
 * on mount, `toggleDashboardFavourite(uuid)` and `toggleDashboardsExpanded()`.
 * A failed write puts the previous list back.
 *
 * @spec openspec/changes/more-menu-favourites/specs/runtime-shell/spec.md#requirement-req-shell-017-a-star-marks-a-dashboard-as-a-favourite-per-person
 */
export const favouriteDashboardsMixin = {
	data() {
		return {
			favourites: [],
			dashboardsExpanded: false,
		}
	},

	async mounted() {
		this.favourites = await loadFavouriteDashboards()
	},

	methods: {
		/**
		 * Unfold the Dashboards column into every dashboard, or fold it.
		 *
		 * @return {void}
		 * @spec openspec/changes/more-menu-favourites/specs/runtime-shell/spec.md#requirement-req-shell-016-the-dashboards-column-lists-the-favourites-and-unfolds-in-place
		 */
		toggleDashboardsExpanded() {
			this.dashboardsExpanded = !this.dashboardsExpanded
		},

		/**
		 * Fold the Dashboards column again, as every opening starts folded.
		 *
		 * @return {void}
		 * @spec openspec/changes/more-menu-favourites/specs/runtime-shell/spec.md#requirement-req-shell-016-the-dashboards-column-lists-the-favourites-and-unfolds-in-place
		 */
		foldDashboards() {
			this.dashboardsExpanded = false
		},

		/**
		 * Star or unstar a dashboard and store the list.
		 *
		 * @param {string} uuid The dashboard's uuid.
		 * @return {Promise<void>}
		 * @spec openspec/changes/more-menu-favourites/specs/runtime-shell/spec.md#requirement-req-shell-017-a-star-marks-a-dashboard-as-a-favourite-per-person
		 */
		async toggleDashboardFavourite(uuid) {
			const before = this.favourites
			this.favourites = toggleFavourite(before, uuid)
			try {
				await saveFavouriteDashboards(this.favourites)
			} catch {
				this.favourites = before
			}
		},
	},
}
