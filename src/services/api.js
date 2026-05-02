/**
 * SPDX-FileCopyrightText: 2024 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/mydash')

export const api = {
	// Dashboard endpoints
	getDashboards() {
		return axios.get(`${baseUrl}/api/dashboards`)
	},

	// REQ-DASH-013 — deduplicated union of personal + group + default dashboards.
	getVisibleDashboards() {
		return axios.get(`${baseUrl}/api/dashboards/visible`)
	},

	// REQ-DASH-014 — group-shared dashboard CRUD.
	getGroupDashboards(groupId) {
		return axios.get(`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}`)
	},

	createGroupDashboard(groupId, data) {
		return axios.post(`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}`, data)
	},

	getGroupDashboard(groupId, uuid) {
		return axios.get(`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}/${encodeURIComponent(uuid)}`)
	},

	updateGroupDashboard(groupId, uuid, data) {
		return axios.put(`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}/${encodeURIComponent(uuid)}`, data)
	},

	deleteGroupDashboard(groupId, uuid) {
		return axios.delete(`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}/${encodeURIComponent(uuid)}`)
	},

	getActiveDashboard() {
		return axios.get(`${baseUrl}/api/dashboard`)
	},

	// Persist the user's active-dashboard preference (REQ-DASH-019).
	// Empty string clears the pref so the resolver falls back through the
	// 7-step chain on next render. The endpoint does NOT validate that
	// the UUID exists — the resolver's stale-pref path handles invalid
	// UUIDs on the next page load.
	setActiveDashboardPreference(uuid) {
		return axios.post(`${baseUrl}/api/dashboards/active`, { uuid })
	},

	createDashboard(data) {
		return axios.post(`${baseUrl}/api/dashboard`, data)
	},

	updateDashboard(id, data) {
		return axios.put(`${baseUrl}/api/dashboard/${id}`, data)
	},

	deleteDashboard(id) {
		return axios.delete(`${baseUrl}/api/dashboard/${id}`)
	},

	activateDashboard(id) {
		return axios.post(`${baseUrl}/api/dashboard/${id}/activate`)
	},

	getDashboardById(id) {
		return axios.get(`${baseUrl}/api/dashboard/${id}`)
	},

	// Group-shared dashboard CRUD alias (REQ-DASH-014).
	// `listGroupDashboards` mirrors the `getGroupDashboards` method
	// above for callers that prefer the verb spelling.
	listGroupDashboards(groupId) {
		return axios.get(`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}`)
	},

	// Promote a single group-shared dashboard to the group's default
	// (REQ-DASH-015). Admin-only — backend enforces the admin guard and
	// runs the flip in a single transaction.
	setGroupDashboardDefault(groupId, uuid) {
		return axios.post(
			`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}/default`,
			{ uuid },
		)
	},

	// Fork any visible dashboard into a brand-new personal copy
	// (REQ-DASH-020). Body shape is `{name?: string}` — when `name`
	// is omitted the backend applies the localised default
	// `My copy of {source name}`.
	forkDashboard(sourceUuid, name) {
		const payload = (name === undefined || name === null || name === '')
			? {}
			: { name }
		return axios.post(`${baseUrl}/api/dashboards/${encodeURIComponent(sourceUuid)}/fork`, payload)
	},

	// REQ-DASH-032: publish a dashboard. Owner-or-admin only on the
	// backend; a 403 envelope surfaces here when the caller lacks
	// permission.
	publishDashboard(uuid) {
		return axios.post(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/publish`)
	},

	// REQ-DASH-033: unpublish (return to draft). Preserves publishedAt
	// on the backend for audit history.
	unpublishDashboard(uuid) {
		return axios.post(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/unpublish`)
	},

	// REQ-DASH-034: schedule a dashboard for automatic publication at a
	// future ISO-8601 timestamp. Backend rejects past dates with a
	// localised 400 error message.
	scheduleDashboard(uuid, publishAt) {
		return axios.post(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/schedule`,
			{ publishAt },
		)
	},

	// Fetch the nested dashboard tree (REQ-DASH-026).
	getDashboardTree() {
		return axios.get(`${baseUrl}/api/dashboards/tree`)
	},

	// Resolve a slug-chain path to a dashboard (REQ-DASH-027).
	// Path segments are forwarded verbatim — the backend folds case and
	// strips trailing slashes, but callers SHOULD normalise to lowercase
	// without leading/trailing `/` to maximise the cache hit rate.
	getDashboardByPath(path) {
		const cleanPath = String(path || '').replace(/^\/+/, '').replace(/\/+$/, '')
		return axios.get(`${baseUrl}/api/dashboards/by-path/${cleanPath}`)
	},

	// Sharing endpoints
	listShares(dashboardId) {
		return axios.get(`${baseUrl}/api/dashboard/${dashboardId}/shares`)
	},

	addShare(dashboardId, data) {
		return axios.post(`${baseUrl}/api/dashboard/${dashboardId}/shares`, data)
	},

	replaceShares(dashboardId, shares) {
		return axios.put(`${baseUrl}/api/dashboard/${dashboardId}/shares`, { shares })
	},

	removeShare(shareId) {
		return axios.delete(`${baseUrl}/api/dashboard/share/${shareId}`)
	},

	revokeAllForRecipient(shareType, shareWith) {
		return axios.delete(`${baseUrl}/api/sharees/${shareType}/${encodeURIComponent(shareWith)}`)
	},

	searchSharees(query) {
		return axios.get(`${baseUrl}/api/sharees`, { params: { query } })
	},

	// Widget endpoints
	getAvailableWidgets() {
		return axios.get(`${baseUrl}/api/widgets`)
	},

	getWidgetItems(widgetIds) {
		const params = new URLSearchParams()
		widgetIds.forEach(id => params.append('widgets[]', id))
		return axios.get(`${baseUrl}/api/widgets/items?${params.toString()}`)
	},

	addWidget(dashboardId, data) {
		return axios.post(`${baseUrl}/api/dashboard/${dashboardId}/widgets`, data)
	},

	addTile(dashboardId, data) {
		return axios.post(`${baseUrl}/api/dashboard/${dashboardId}/tile`, data)
	},

	updateWidgetPlacement(placementId, data) {
		return axios.put(`${baseUrl}/api/widgets/${placementId}`, data)
	},

	removeWidget(placementId) {
		return axios.delete(`${baseUrl}/api/widgets/${placementId}`)
	},

	// Conditional rules endpoints
	getWidgetRules(placementId) {
		return axios.get(`${baseUrl}/api/widgets/${placementId}/rules`)
	},

	addWidgetRule(placementId, data) {
		return axios.post(`${baseUrl}/api/widgets/${placementId}/rules`, data)
	},

	updateRule(ruleId, data) {
		return axios.put(`${baseUrl}/api/rules/${ruleId}`, data)
	},

	deleteRule(ruleId) {
		return axios.delete(`${baseUrl}/api/rules/${ruleId}`)
	},

	// Admin endpoints
	getAdminTemplates() {
		return axios.get(`${baseUrl}/api/admin/templates`)
	},

	createAdminTemplate(data) {
		return axios.post(`${baseUrl}/api/admin/templates`, data)
	},

	getAdminTemplate(id) {
		return axios.get(`${baseUrl}/api/admin/templates/${id}`)
	},

	updateAdminTemplate(id, data) {
		return axios.put(`${baseUrl}/api/admin/templates/${id}`, data)
	},

	deleteAdminTemplate(id) {
		return axios.delete(`${baseUrl}/api/admin/templates/${id}`)
	},

	getAdminSettings() {
		return axios.get(`${baseUrl}/api/admin/settings`)
	},

	updateAdminSettings(data) {
		return axios.put(`${baseUrl}/api/admin/settings`, data)
	},

	// Admin group-priority order (REQ-ASET-012/013/014).
	// Both endpoints are admin-only on the server side; the UI gates
	// rendering of the section behind the same admin check.
	getAdminGroups() {
		return axios.get(`${baseUrl}/api/admin/groups`)
	},

	updateAdminGroupOrder(groups) {
		return axios.post(`${baseUrl}/api/admin/groups`, { groups })
	},

	// REQ-FEED-001..003 — per-user RSS / Atom feed-token management.
	// `getFeedToken` issues-or-returns the existing token; `regenerate`
	// atomically rotates; `revoke` soft-revokes (idempotent — 204 even
	// when no token exists).
	getFeedToken() {
		return axios.get(`${baseUrl}/api/feed/token`)
	},

	regenerateFeedToken() {
		return axios.post(`${baseUrl}/api/feed/token/regenerate`)
	},

	revokeFeedToken() {
		return axios.delete(`${baseUrl}/api/feed/token`)
	},

	// Tile endpoints
	getTiles() {
		return axios.get(`${baseUrl}/api/tiles`)
	},

	createTile(data) {
		return axios.post(`${baseUrl}/api/tiles`, data)
	},

	updateTile(id, data) {
		return axios.put(`${baseUrl}/api/tiles/${id}`, data)
	},

	deleteTile(id) {
		return axios.delete(`${baseUrl}/api/tiles/${id}`)
	},
}
