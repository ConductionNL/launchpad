/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/admin-group-management/tasks.md#task-2
 *
 * Pinia store slice powering the admin-only "Group dashboards" tab. Wraps
 * the existing /api/dashboards/group/... endpoints from the
 * multi-scope-dashboards change so the admin UI never reaches axios
 * directly. State is intentionally minimal — the tab + modals re-fetch on
 * mount, so we don't try to keep an authoritative client-side cache.
 */

import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import { api } from '../services/api.js'

/**
 * Sentinel group id matching the backend `Dashboard::DEFAULT_GROUP_ID`. The
 * group dashboards tab renders this row alongside real NC groups so admins
 * can manage the org-wide default through the same UX.
 */
export const DEFAULT_GROUP_ID = 'default'

/**
 * Stable backend error code returned by `DELETE /api/dashboards/group/...`
 * when the deletion would leave the group with no dashboard at all
 * (REQ-DASH-016 last-in-group guard).
 *
 * @type {string}
 */
const ERR_LAST_GROUP_DASHBOARD = 'last_group_dashboard'

export const useGroupDashboardsStore = defineStore('groupDashboards', {
	state: () => ({
		/**
		 * Hydrated lazily by `fetchGroups()`. Each entry mirrors the shape
		 * returned by the existing admin groups endpoint:
		 * `{ id: string, displayName: string }`.
		 */
		groups: [],
		/**
		 * Map of `groupId -> Array<dashboard>` keyed by Nextcloud group id
		 * (or the `DEFAULT_GROUP_ID` sentinel). Filled by
		 * `fetchGroupDashboards()` on demand — never eagerly for every
		 * group, because the per-group endpoint already paginates server
		 * side.
		 */
		dashboardsByGroup: {},
		/** Bookkeeping for the admin tab UI. */
		loading: false,
		error: null,
	}),

	getters: {
		/**
		 * @param {object} state The store state.
		 * @return {(groupId: string) => Array<object>} Dashboards for a
		 *  given group id. Returns an empty array when not yet fetched —
		 *  the tab UI treats that as a "needs fetch" signal.
		 */
		dashboardsFor: (state) => (groupId) =>
			state.dashboardsByGroup[groupId] ?? [],

		/**
		 * @return {(groupId: string) => number} Group-scoped dashboard
		 *  count badge shown next to the group name in the tab list.
		 */
		countFor() {
			return (groupId) => this.dashboardsFor(groupId).length
		},
	},

	actions: {
		/**
		 * Fetch the admin groups list. The tab UI renders one row per
		 * group plus the `default` sentinel — REQ-DASH-015 "Admin opens the
		 * Group dashboards tab".
		 *
		 * @spec openspec/specs/dashboards/spec.md#req-dash-015-admin-group-management-ui
		 * @return {Promise<void>}
		 */
		async fetchGroups() {
			this.loading = true
			this.error = null
			try {
				const res = await api.getAdminGroups()
				// `GET /api/admin/groups` answers with the disjoint split
				// envelope `{active, inactive, allKnown}` (see
				// AdminSettingsController::getGroups) — `allKnown` is the
				// full `{id, displayName}` list this tab renders. The older
				// `res.data.data` / bare-array shapes are kept as fallbacks
				// for forward/backward compatibility. Without picking
				// `allKnown` the envelope OBJECT fell through to the bare
				// `res.data` branch and `groups.filter(...)` threw
				// "filter is not a function", which the catch below swallowed
				// into a "Failed to load groups" toast — so the tab rendered
				// its empty state even though the request returned 200.
				const payload = res.data?.allKnown ?? res.data?.data ?? res.data
				const groups = Array.isArray(payload) ? payload : []
				// Always make sure the `default` sentinel sits at index 0
				// so admins find the org-wide default first.
				this.groups = [
					{
						id: DEFAULT_GROUP_ID,
						displayName: t('launchpad', 'Default group'),
					},
					...groups.filter((g) => g.id !== DEFAULT_GROUP_ID),
				]
			} catch (e) {
				this.error = e
				showError(t('launchpad', 'Failed to load groups'))
			} finally {
				this.loading = false
			}
		},

		/**
		 * Fetch group-scoped dashboards for a single group id.
		 *
		 * @param {string} groupId NC group id (or DEFAULT_GROUP_ID).
		 * @return {Promise<Array<object>>}
		 * @spec openspec/specs/dashboards/spec.md#req-dash-015-admin-group-management-ui
		 */
		async fetchGroupDashboards(groupId) {
			try {
				const res = await api.listGroupDashboards(groupId)
				const list = res.data?.data ?? res.data ?? []
				this.dashboardsByGroup = {
					...this.dashboardsByGroup,
					[groupId]: list,
				}
				return list
			} catch (e) {
				showError(t('launchpad', 'Failed to load group dashboards'))
				throw e
			}
		},

		/**
		 * Create a new group-shared dashboard via the existing endpoint.
		 *
		 * @param {string} groupId NC group id (or DEFAULT_GROUP_ID).
		 * @param {{name: string, icon?: string, layout?: object, isDefault?: boolean}} payload
		 *   Attributes for the new dashboard.
		 * @return {Promise<object>} The created dashboard row.
		 */
		async create(groupId, payload) {
			const res = await api.createGroupDashboard(groupId, payload)
			const created = res.data?.data ?? res.data
			const list = this.dashboardsByGroup[groupId] ?? []
			this.dashboardsByGroup = {
				...this.dashboardsByGroup,
				[groupId]: [...list, created],
			}
			return created
		},

		/**
		 * Update an existing group-shared dashboard.
		 *
		 * @param {string} groupId NC group id (or DEFAULT_GROUP_ID).
		 * @param {string} uuid    Dashboard UUID.
		 * @param {object} payload Partial update payload.
		 * @return {Promise<object>} The updated dashboard row.
		 */
		async update(groupId, uuid, payload) {
			const res = await api.updateGroupDashboard(groupId, uuid, payload)
			const updated = res.data?.data ?? res.data
			const list = (this.dashboardsByGroup[groupId] ?? []).map((d) =>
				d.uuid === uuid ? { ...d, ...updated } : d,
			)
			this.dashboardsByGroup = { ...this.dashboardsByGroup, [groupId]: list }
			return updated
		},

		/**
		 * Delete a group-shared dashboard. Surfaces the backend's
		 * last-in-group guard as a localized toast and re-throws so the
		 * caller's confirmation modal can stay open.
		 *
		 * @param {string} groupId NC group id (or DEFAULT_GROUP_ID).
		 * @param {string} uuid    Dashboard UUID.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md#req-dash-015-admin-group-management-ui
		 */
		async delete(groupId, uuid) {
			try {
				await api.deleteGroupDashboard(groupId, uuid)
				const list = (this.dashboardsByGroup[groupId] ?? []).filter(
					(d) => d.uuid !== uuid,
				)
				this.dashboardsByGroup = {
					...this.dashboardsByGroup,
					[groupId]: list,
				}
			} catch (e) {
				const code = e?.response?.data?.error
				if (code === ERR_LAST_GROUP_DASHBOARD) {
					showError(t('launchpad', 'Last dashboard cannot be deleted'))
				} else {
					showError(t('launchpad', 'Failed to delete dashboard'))
				}
				throw e
			}
		},

		/**
		 * Promote a group-shared dashboard to the group's default.
		 *
		 * @param {string} groupId NC group id (or DEFAULT_GROUP_ID).
		 * @param {string} uuid    Dashboard UUID.
		 * @return {Promise<void>}
		 */
		async setDefault(groupId, uuid) {
			await api.setGroupDashboardDefault(groupId, uuid)
			const list = (this.dashboardsByGroup[groupId] ?? []).map((d) => ({
				...d,
				isDefault: d.uuid === uuid,
			}))
			this.dashboardsByGroup = { ...this.dashboardsByGroup, [groupId]: list }
		},
	},
})
