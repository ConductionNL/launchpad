/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The person's favourite dashboards (more-menu-favourites, REQ-SHELL-014 and
 * REQ-SHELL-016): a list of dashboard uuids in starring order, kept per person
 * on the server. The page carries the list in its initial state, so opening
 * More reads nothing; a star goes to `POST`/`DELETE
 * /api/dashboards/{uuid}/favourite`, which answers the new list.
 */

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { reactive } from 'vue'
import { toggleFavourite } from '../components/Workspace/moreMenuItems.js'
import { readFavouriteDashboards } from '../utils/loadInitialState.js'

/**
 * One list for the whole page, so the dashboard view's menu and the menu on
 * every other page show the same stars. Seeded lazily from the initial state.
 */
const state = reactive({ favourites: null })

/**
 * The favourites, seeding them from the initial state on first use.
 *
 * @return {string[]} The uuids in starring order.
 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-2
 */
export function favouriteDashboards() {
	if (state.favourites === null) {
		state.favourites = readFavouriteDashboards()
	}
	return state.favourites
}

/**
 * Replace the list (tests, and the server's answer after a star).
 *
 * @param {string[]|null} uuids The uuids, or null to reseed from the initial state.
 * @return {void}
 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-4
 */
export function setFavouriteDashboards(uuids) {
	state.favourites = Array.isArray(uuids) ? uuids.filter((u) => typeof u === 'string' && u !== '') : null
}

/**
 * The favourite endpoint of one dashboard.
 *
 * @param {string} uuid The dashboard uuid.
 * @return {string} The URL.
 */
function favouriteUrl(uuid) {
	return generateUrl('/apps/launchpad/api/dashboards/{uuid}/favourite', { uuid })
}

/**
 * Star or unstar a dashboard. The list changes at once; the server's answer
 * replaces it, and a failure puts the previous list back with an error toast.
 *
 * @param {string} uuid The dashboard uuid.
 * @return {Promise<boolean>} Whether the server took the change.
 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-4
 */
export async function toggleDashboardFavourite(uuid) {
	const before = favouriteDashboards()
	const starring = !before.includes(uuid)
	state.favourites = toggleFavourite(before, uuid)
	try {
		const { data } = starring
			? await axios.post(favouriteUrl(uuid))
			: await axios.delete(favouriteUrl(uuid))
		if (Array.isArray(data?.favourites)) {
			setFavouriteDashboards(data.favourites)
		}
		return true
	} catch {
		state.favourites = before
		showError(starring
			? t('launchpad', 'Could not add the dashboard to your favourites')
			: t('launchpad', 'Could not remove the dashboard from your favourites'))
		return false
	}
}

/**
 * The More menu's favourites state, shared by the dashboard view's menu and
 * the menu on every other page: `favourites`, `dashboardsExpanded`,
 * `toggleDashboardFavourite(uuid)`, `toggleDashboardsExpanded()` and
 * `foldDashboards()`.
 *
 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-3
 */
export const favouriteDashboardsMixin = {
	data() {
		return {
			dashboardsExpanded: false,
			openTrees: [],
		}
	},

	/**
	 * Seed the list from the page, outside the computed so reading never writes.
	 *
	 * @return {void}
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-2
	 */
	created() {
		favouriteDashboards()
	},

	computed: {
		/**
		 * The favourites in starring order.
		 *
		 * @return {string[]} The uuids.
		 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-3
		 */
		favourites() {
			return state.favourites ?? []
		},
	},

	methods: {
		/**
		 * More / Less in the Dashboards column.
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-3
		 */
		toggleDashboardsExpanded() {
			this.dashboardsExpanded = !this.dashboardsExpanded
		},

		/**
		 * Show or hide a parent's children in the full list (REQ-SHELL-019).
		 *
		 * @param {string} uuid The parent's uuid.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-16
		 */
		toggleTree(uuid) {
			this.openTrees = this.openTrees.includes(uuid)
				? this.openTrees.filter((open) => open !== uuid)
				: [...this.openTrees, uuid]
		},

		/**
		 * Back to the favourites (the menu opened again).
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-3
		 */
		foldDashboards() {
			this.dashboardsExpanded = false
		},

		/**
		 * Star or unstar a dashboard.
		 *
		 * @param {string} uuid The dashboard uuid.
		 * @return {Promise<boolean>} Whether the server took it.
		 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-4
		 */
		toggleDashboardFavourite(uuid) {
			return toggleDashboardFavourite(uuid)
		},
	},
}
