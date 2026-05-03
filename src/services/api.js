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

	// Group-shared dashboard CRUD alias (REQ-DASH-014). `listGroupDashboards`
	// mirrors the `getGroupDashboards` method above for callers that prefer
	// the verb-prefixed name.
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

	// Dashboard export / import (REQ-EXIM-002..004). Both endpoints
	// require Nextcloud-admin and are gated server-side; the admin UI
	// only renders the controls behind the same admin check. Export
	// returns a binary blob streamed straight to disk; import accepts a
	// multipart upload.
	exportDashboards({ scope = 'site', dashboardUuid = null } = {}) {
		const params = { scope }
		if (dashboardUuid) {
			params.dashboardUuid = dashboardUuid
		}
		return axios.post(`${baseUrl}/api/admin/export`, null, {
			params,
			responseType: 'blob',
		})
	},

	importDashboards(file, { preserveUuids = false } = {}) {
		const form = new FormData()
		form.append('file', file)
		return axios.post(`${baseUrl}/api/admin/import`, form, {
			params: { preserveUuids },
			headers: { 'Content-Type': 'multipart/form-data' },
		})
	},

	// Confluence HTML export importer (REQ-CFLI-001..012). Both endpoints
	// are admin-only on the server side; the UI gates the controls behind
	// the same admin check. Dry-run returns the parse preview without
	// touching the database; the import endpoint creates one MyDash
	// dashboard per Confluence page.
	confluenceImportDryRun(file) {
		const form = new FormData()
		form.append('file', file)
		return axios.post(
			`${baseUrl}/api/admin/import/confluence/dry-run`,
			form,
			{ headers: { 'Content-Type': 'multipart/form-data' } },
		)
	},

	confluenceImport(file, { parentUuid = null } = {}) {
		const form = new FormData()
		form.append('file', file)
		const params = {}
		if (parentUuid) {
			params.parentUuid = parentUuid
		}
		return axios.post(`${baseUrl}/api/admin/import/confluence`, form, {
			params,
			headers: { 'Content-Type': 'multipart/form-data' },
		})
	},

	// Dashboard comments (REQ-CMNT-001..009). Threaded comments backed by
	// Nextcloud's ICommentsManager. The `enabled` field on the GET
	// response carries the effective per-dashboard / global toggle.
	listDashboardComments(uuid) {
		return axios.get(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/comments`)
	},

	createDashboardComment(uuid, payload) {
		return axios.post(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/comments`, payload)
	},

	updateDashboardComment(uuid, id, payload) {
		return axios.put(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/comments/${id}`, payload)
	},

	deleteDashboardComment(uuid, id) {
		return axios.delete(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/comments/${id}`)
	},

	// Dashboard metadata-fields admin CRUD (REQ-MDFL-001..003).
	getMetadataFields() {
		return axios.get(`${baseUrl}/api/admin/metadata-fields`)
	},

	createMetadataField(field) {
		return axios.post(`${baseUrl}/api/admin/metadata-fields`, field)
	},

	getMetadataField(id) {
		return axios.get(`${baseUrl}/api/admin/metadata-fields/${id}`)
	},

	updateMetadataField(id, patch) {
		return axios.put(`${baseUrl}/api/admin/metadata-fields/${id}`, patch)
	},

	deleteMetadataField(id, cascade = false) {
		const query = cascade ? '?cascade=true' : ''
		return axios.delete(`${baseUrl}/api/admin/metadata-fields/${id}${query}`)
	},

	// Per-dashboard metadata read/write (REQ-MDFL-004..006, REQ-MDFL-008).
	getDashboardMetadata(uuid) {
		return axios.get(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/metadata`)
	},

	updateDashboardMetadata(uuid, metadata) {
		return axios.put(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/metadata`, { metadata })
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

	// Dashboard reaction endpoints (REQ-RXN-001..004).
	getDashboardReactions(uuid) {
		return axios.get(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/reactions`)
	},

	addDashboardReaction(uuid, emoji) {
		return axios.post(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/reactions`,
			{ emoji },
		)
	},

	removeDashboardReaction(uuid, emoji) {
		return axios.delete(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/reactions/${encodeURIComponent(emoji)}`,
		)
	},

	getDashboardReactors(uuid, emoji, cursor = null) {
		const params = cursor !== null && cursor !== '' ? { cursor } : {}
		return axios.get(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/reactions/${encodeURIComponent(emoji)}/users`,
			{ params },
		)
	},

	// Dashboard translation endpoints (REQ-DASH-038..044) — per-language
	// content variants for dashboards. All endpoints scope by dashboard
	// UUID and require ownership; the server returns 403 for cross-user
	// attempts.
	listDashboardTranslations(uuid) {
		return axios.get(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/translations`)
	},

	createDashboardTranslation(uuid, data) {
		return axios.post(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/translations`, data)
	},

	updateDashboardTranslation(uuid, lang, data) {
		return axios.put(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/translations/${encodeURIComponent(lang)}`, data)
	},

	deleteDashboardTranslation(uuid, lang) {
		return axios.delete(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/translations/${encodeURIComponent(lang)}`)
	},

	setDashboardTranslationPrimary(uuid, lang) {
		return axios.post(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/translations/${encodeURIComponent(lang)}/set-primary`)
	},

	// Resolve the dashboard for the viewer's locale; optional `?lang=`
	// query parameter overrides the user's Nextcloud locale.
	getResolvedDashboard(uuid, lang) {
		const params = (lang !== undefined && lang !== null && lang !== '') ? { params: { lang } } : {}
		return axios.get(`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/resolved`, params)
	},
}
