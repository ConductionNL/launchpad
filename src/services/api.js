/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/launchpad')

export const api = {
	// Dashboard endpoints
	getDashboards() {
		return axios.get(`${baseUrl}/api/dashboards`)
	},

	// REQ-DASH-013 — deduplicated union of personal + group + default dashboards.
	getVisibleDashboards() {
		return axios.get(`${baseUrl}/api/dashboards/visible`)
	},

	/**
	 * Create a dashboard shared with a Nextcloud group (REQ-DASH-014).
	 *
	 * @param {string} groupId Nextcloud group id that will own the dashboard.
	 * @param {object} data Dashboard attributes (name, description, icon, …).
	 * @return {Promise} Axios response resolving to the created dashboard.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	createGroupDashboard(groupId, data) {
		return axios.post(
			`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}`,
			data,
		)
	},

	/**
	 * Update one of a group's shared dashboards (REQ-DASH-014).
	 *
	 * @param {string} groupId Nextcloud group id owning the dashboard.
	 * @param {string} uuid UUID of the dashboard to update.
	 * @param {object} data Changed dashboard attributes.
	 * @return {Promise} Axios response resolving to the updated dashboard.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	updateGroupDashboard(groupId, uuid, data) {
		return axios.put(
			`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}/${encodeURIComponent(uuid)}`,
			data,
		)
	},

	/**
	 * Delete one of a group's shared dashboards (REQ-DASH-014).
	 *
	 * @param {string} groupId Nextcloud group id owning the dashboard.
	 * @param {string} uuid UUID of the dashboard to delete.
	 * @return {Promise} Axios response for the delete call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	deleteGroupDashboard(groupId, uuid) {
		return axios.delete(
			`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}/${encodeURIComponent(uuid)}`,
		)
	},

	getActiveDashboard() {
		return axios.get(`${baseUrl}/api/dashboard`)
	},

	/**
	 * Persist the user's active-dashboard preference (REQ-DASH-019).
	 *
	 * The endpoint does NOT validate that the UUID exists — the resolver's
	 * stale-pref path discards unknown UUIDs on the next page load.
	 *
	 * @param {string} uuid Dashboard UUID to make active; the empty string
	 *   clears the preference so the resolver falls back through its
	 *   7-step chain on the next render.
	 * @return {Promise} Axios response for the preference write.
	 */
	setActiveDashboardPreference(uuid) {
		return axios.post(`${baseUrl}/api/dashboards/active`, { uuid })
	},

	/**
	 * Pin the user's EXPLICIT default-dashboard choice (Wave3.7).
	 *
	 * Distinct from `setActiveDashboardPreference`, which auto-overwrites on
	 * every switch — this is only written when the user clicks "Set as
	 * default" on a row's cog menu. The resolver checks it BEFORE the active
	 * preference, so the pin survives across switches.
	 *
	 * @param {string} uuid Dashboard UUID to pin as the user's default.
	 * @return {Promise} Axios response for the preference write.
	 */
	setDefaultDashboardPreference(uuid) {
		return axios.post(`${baseUrl}/api/dashboards/default`, { uuid })
	},
	/** @spec openspec/specs/dashboards/spec.md */
	clearDefaultDashboardPreference() {
		return axios.post(`${baseUrl}/api/dashboards/default`, { uuid: '' })
	},
	getDefaultDashboardPreference() {
		return axios.get(`${baseUrl}/api/dashboards/default`)
	},

	/**
	 * Create a personal dashboard.
	 *
	 * @param {object} data Dashboard attributes (name, description, icon, …).
	 * @return {Promise} Axios response resolving to the created dashboard.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	createDashboard(data) {
		return axios.post(`${baseUrl}/api/dashboard`, data)
	},

	/**
	 * Update an existing dashboard.
	 *
	 * @param {number|string} id Numeric id of the dashboard to update.
	 * @param {object} data Changed dashboard attributes.
	 * @return {Promise} Axios response resolving to the updated dashboard.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	updateDashboard(id, data) {
		return axios.put(`${baseUrl}/api/dashboard/${id}`, data)
	},

	/**
	 * Delete a dashboard and its placements.
	 *
	 * @param {number|string} id Numeric id of the dashboard to delete.
	 * @return {Promise} Axios response for the delete call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	deleteDashboard(id) {
		return axios.delete(`${baseUrl}/api/dashboard/${id}`)
	},

	/**
	 * Make a dashboard the caller's active dashboard server-side.
	 *
	 * @param {number|string} id Numeric id of the dashboard to activate.
	 * @return {Promise} Axios response for the activate call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	activateDashboard(id) {
		return axios.post(`${baseUrl}/api/dashboard/${id}/activate`)
	},

	/**
	 * Fetch a single dashboard by its numeric id.
	 *
	 * @param {number|string} id Numeric id of the dashboard to fetch.
	 * @return {Promise} Axios response resolving to the dashboard.
	 */
	getDashboardById(id) {
		return axios.get(`${baseUrl}/api/dashboard/${id}`)
	},

	/**
	 * List a group's shared dashboards (REQ-DASH-014).
	 *
	 * @param {string} groupId Nextcloud group id to list dashboards for.
	 * @return {Promise} Axios response resolving to the group's dashboards.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	listGroupDashboards(groupId) {
		return axios.get(
			`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}`,
		)
	},

	/**
	 * Promote one group-shared dashboard to the group's default
	 * (REQ-DASH-015). Admin-only — the backend enforces the admin guard and
	 * runs the flip in a single transaction.
	 *
	 * @param {string} groupId Nextcloud group id owning the dashboard.
	 * @param {string} uuid UUID of the dashboard to promote.
	 * @return {Promise} Axios response for the promotion call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	setGroupDashboardDefault(groupId, uuid) {
		return axios.post(
			`${baseUrl}/api/dashboards/group/${encodeURIComponent(groupId)}/default`,
			{ uuid },
		)
	},

	/**
	 * Fork any visible dashboard into a brand-new personal copy
	 * (REQ-DASH-020).
	 *
	 * @param {string} sourceUuid UUID of the dashboard to copy.
	 * @param {string} [name] Name for the copy; when omitted (or empty) the
	 *   backend applies the localised default `My copy of {source name}`.
	 * @return {Promise} Axios response resolving to the new dashboard.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	forkDashboard(sourceUuid, name) {
		const payload =
			name === undefined || name === null || name === '' ? {} : { name }
		return axios.post(
			`${baseUrl}/api/dashboards/${encodeURIComponent(sourceUuid)}/fork`,
			payload,
		)
	},

	/**
	 * Publish a dashboard (REQ-DASH-032). Owner-or-admin only on the
	 * backend; a 403 envelope surfaces here when the caller lacks permission.
	 *
	 * @param {string} uuid UUID of the dashboard to publish.
	 * @return {Promise} Axios response for the publish call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	publishDashboard(uuid) {
		return axios.post(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/publish`,
		)
	},

	/**
	 * Unpublish a dashboard, returning it to draft (REQ-DASH-033). The
	 * backend preserves `publishedAt` for audit history.
	 *
	 * @param {string} uuid UUID of the dashboard to unpublish.
	 * @return {Promise} Axios response for the unpublish call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	unpublishDashboard(uuid) {
		return axios.post(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/unpublish`,
		)
	},

	/**
	 * Schedule a dashboard for automatic publication (REQ-DASH-034).
	 *
	 * @param {string} uuid UUID of the dashboard to schedule.
	 * @param {string} publishAt Future ISO-8601 timestamp; the backend
	 *   rejects past dates with a localised 400 error message.
	 * @return {Promise} Axios response for the schedule call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	scheduleDashboard(uuid, publishAt) {
		return axios.post(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/schedule`,
			{ publishAt },
		)
	},

	/**
	 * Record a dashboard view event (REQ-ANLT-002). Authenticated users
	 * only; the server short-circuits to 204 silently when the user has
	 * opted out (REQ-ANLT-004) or analytics is globally disabled
	 * (REQ-ANLT-005).
	 *
	 * @param {string} uuid UUID of the dashboard that was viewed.
	 * @return {Promise} Axios response for the (empty-bodied) event post.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	recordDashboardViewEvent(uuid) {
		return axios.post(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/view-event`,
			{},
		)
	},

	/**
	 * Record a tile click (REQ-TANLT-002). Authenticated users only; the
	 * server short-circuits to 204 silently when the user has opted out or
	 * analytics is globally disabled — the same gates as the dashboard
	 * view-event above.
	 *
	 * @param {number|string} placementId Id of the clicked tile placement.
	 * @return {Promise} Axios response for the (empty-bodied) click post.
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	recordTileClick(placementId) {
		return axios.post(
			`${baseUrl}/api/tile-click/${encodeURIComponent(placementId)}`,
			{},
		)
	},

	// REQ-TANLT-003: whether tile-click tracking is currently active
	// for the calling user (globally enabled AND not opted out).
	/** @spec openspec/specs/dashboard-view-analytics/spec.md */
	getTileAnalyticsConfig() {
		return axios.get(`${baseUrl}/api/tile-analytics/config`)
	},

	/**
	 * Top-N tiles by click count for the given period (REQ-TANLT-004).
	 *
	 * @param {string} [period] Reporting window, e.g. `7d` / `30d` / `90d`.
	 * @param {number} [limit] Maximum tiles to return.
	 * @return {Promise} Axios response resolving to the ranked tile list.
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	getAnalyticsTopTiles(period = '30d', limit = 10) {
		return axios.get(`${baseUrl}/api/admin/analytics/tiles/top`, {
			params: { period, limit },
		})
	},

	/**
	 * Per-dashboard tile-click breakdown for the given period
	 * (REQ-TANLT-004).
	 *
	 * @param {string} uuid UUID of the dashboard to break down.
	 * @param {string} [period] Reporting window, e.g. `7d` / `30d` / `90d`.
	 * @return {Promise} Axios response resolving to the per-tile breakdown.
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	getAnalyticsTileDashboardBreakdown(uuid, period = '30d') {
		return axios.get(
			`${baseUrl}/api/admin/analytics/tiles/by-dashboard/${encodeURIComponent(uuid)}`,
			{ params: { period } },
		)
	},

	/**
	 * Top-N dashboards by view count for the given period (REQ-ANLT-006).
	 *
	 * @param {string} [period] Reporting window, e.g. `7d` / `30d` / `90d`.
	 * @param {number} [limit] Maximum dashboards to return.
	 * @return {Promise} Axios response resolving to the ranked dashboard list.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	getAnalyticsTopDashboards(period = '30d', limit = 10) {
		return axios.get(`${baseUrl}/api/admin/analytics/dashboards/top`, {
			params: { period, limit },
		})
	},

	/**
	 * Instance-wide analytics totals plus the top-5 dashboards
	 * (REQ-ANLT-008).
	 *
	 * @param {string} [period] Reporting window, e.g. `7d` / `30d` / `90d`.
	 * @return {Promise} Axios response resolving to the summary payload.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	getAnalyticsInstanceSummary(period = '30d') {
		return axios.get(`${baseUrl}/api/admin/analytics/summary`, {
			params: { period },
		})
	},

	/**
	 * Download dashboard analytics as CSV (REQ-ANLT-010). Returns the raw
	 * blob response so the caller can stream it to disk or hand it to
	 * `URL.createObjectURL` for an in-browser download.
	 *
	 * @param {string} [period] Reporting window, e.g. `7d` / `30d` / `90d`.
	 * @return {Promise} Axios response whose `data` is a CSV Blob.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	getAnalyticsCsvExport(period = '30d') {
		return axios.get(`${baseUrl}/api/admin/analytics/export`, {
			params: { period },
			responseType: 'blob',
		})
	},

	/**
	 * Download tile analytics as CSV (REQ-TANLT-005).
	 *
	 * @param {string} [period] Reporting window, e.g. `7d` / `30d` / `90d`.
	 * @return {Promise} Axios response whose `data` is a CSV Blob.
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	getTileAnalyticsCsvExport(period = '30d') {
		return axios.get(`${baseUrl}/api/admin/analytics/tiles/export`, {
			params: { period },
			responseType: 'blob',
		})
	},

	// Fetch the nested dashboard tree (REQ-DASH-026).
	getDashboardTree() {
		return axios.get(`${baseUrl}/api/dashboards/tree`)
	},

	/**
	 * Resolve a slug-chain path to a dashboard (REQ-DASH-027).
	 *
	 * @param {string} path Slug chain, e.g. `marketing/campaigns`. Segments
	 *   are forwarded verbatim; the backend folds case and strips trailing
	 *   slashes, but callers SHOULD pass lowercase without leading/trailing
	 *   `/` to maximise the cache hit rate.
	 * @return {Promise} Axios response resolving to the matched dashboard.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	getDashboardByPath(path) {
		const cleanPath = String(path || '')
			.replace(/^\/+/, '')
			.replace(/\/+$/, '')
		return axios.get(`${baseUrl}/api/dashboards/by-path/${cleanPath}`)
	},

	/**
	 * Reverse lookup: dashboard UUID to canonical slug-chain path. Used by
	 * the sidebar after every switch to keep `window.location.pathname` in
	 * sync with the active dashboard.
	 *
	 * @param {string} uuid UUID of the dashboard to resolve.
	 * @return {Promise} Axios response resolving to `{path: string}`. An
	 *   empty path is valid — the dashboard has no slug and therefore no
	 *   addressable URL.
	 */
	getDashboardPath(uuid) {
		return axios.get(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/path`,
		)
	},

	// Sharing endpoints

	/**
	 * List the shares currently attached to a dashboard.
	 *
	 * @param {number|string} dashboardId Numeric id of the dashboard.
	 * @return {Promise} Axios response resolving to the share list.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	listShares(dashboardId) {
		return axios.get(`${baseUrl}/api/dashboard/${dashboardId}/shares`)
	},

	/**
	 * Replace a dashboard's entire share list in one call.
	 *
	 * @param {number|string} dashboardId Numeric id of the dashboard.
	 * @param {Array<object>} shares Full desired share set; any existing
	 *   share absent from this array is removed.
	 * @return {Promise} Axios response resolving to the stored share list.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	replaceShares(dashboardId, shares) {
		return axios.put(`${baseUrl}/api/dashboard/${dashboardId}/shares`, {
			shares,
		})
	},

	/**
	 * Search users and groups eligible to receive a dashboard share.
	 *
	 * @param {string} query Free-text search term typed by the user.
	 * @return {Promise} Axios response resolving to matching sharees.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	searchSharees(query) {
		return axios.get(`${baseUrl}/api/sharees`, { params: { query } })
	},

	// Widget endpoints
	getAvailableWidgets() {
		return axios.get(`${baseUrl}/api/widgets`)
	},

	/**
	 * Fetch items for a list of Nextcloud widget IDs (REQ-WDG-021).
	 * The limit parameter is passed explicitly so callers can rely on the
	 * URL including `limit=<n>` regardless of server-side defaults.
	 *
	 * @param {string[]} widgetIds Widget IDs to fetch items for.
	 * @param {number} limit Maximum items per widget (default 7 per REQ-WDG-021).
	 * @return {Promise} Axios response promise.
	 *
	 * @spec openspec/changes/nc-dashboard-widget-proxy/specs/widgets/spec.md#req-wdg-021
	 */
	getWidgetItems(widgetIds, limit = 7) {
		const params = new URLSearchParams()
		widgetIds.forEach((id) => params.append('widgets[]', id))
		params.append('limit', String(limit))
		return axios.get(`${baseUrl}/api/widgets/items?${params.toString()}`)
	},

	/**
	 * List the current user's calendars for the calendar widget's config-form
	 * picker (REQ-CAL-002).
	 *
	 * @return {Promise} resolves to `{ calendars: [{key, name, color}] }`.
	 * @spec openspec/specs/calendar-widget/spec.md
	 */
	getCalendarWidgetCalendars() {
		return axios.get(`${baseUrl}/api/widgets/calendar/calendars`)
	},

	/**
	 * Place a widget on a dashboard.
	 *
	 * @param {number|string} dashboardId Numeric id of the target dashboard.
	 * @param {object} data Placement payload (widget type, grid geometry,
	 *   content, …).
	 * @return {Promise} Axios response resolving to the created placement.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	addWidget(dashboardId, data) {
		return axios.post(`${baseUrl}/api/dashboard/${dashboardId}/widgets`, data)
	},

	/**
	 * Place a launcher tile on a dashboard.
	 *
	 * @param {number|string} dashboardId Numeric id of the target dashboard.
	 * @param {object} data Tile payload (title, icon, link target, colours, …).
	 * @return {Promise} Axios response resolving to the created tile placement.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	addTile(dashboardId, data) {
		return axios.post(`${baseUrl}/api/dashboard/${dashboardId}/tile`, data)
	},

	/**
	 * Update an existing widget placement.
	 *
	 * @param {number|string} placementId Id of the placement to update.
	 * @param {object} data Changed placement fields (geometry, content, …).
	 * @return {Promise} Axios response resolving to the updated placement.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	updateWidgetPlacement(placementId, data) {
		return axios.put(`${baseUrl}/api/widgets/${placementId}`, data)
	},

	/**
	 * Remove a widget placement from its dashboard.
	 *
	 * @param {number|string} placementId Id of the placement to remove.
	 * @return {Promise} Axios response for the delete call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	removeWidget(placementId) {
		return axios.delete(`${baseUrl}/api/widgets/${placementId}`)
	},

	// Conditional rules endpoints

	/**
	 * List the conditional-visibility rules attached to a placement.
	 *
	 * @param {number|string} placementId Id of the placement to inspect.
	 * @return {Promise} Axios response resolving to `{rules: [...]}`.
	 */
	getWidgetRules(placementId) {
		return axios.get(`${baseUrl}/api/widgets/${placementId}/rules`)
	},

	/**
	 * Attach a new conditional-visibility rule to a placement.
	 *
	 * @param {number|string} placementId Id of the placement to rule on.
	 * @param {object} data Rule payload (`ruleType`, `ruleConfig`, `isInclude`).
	 * @return {Promise} Axios response resolving to the created rule.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	addWidgetRule(placementId, data) {
		return axios.post(`${baseUrl}/api/widgets/${placementId}/rules`, data)
	},

	/**
	 * Delete a conditional-visibility rule.
	 *
	 * @param {number|string} ruleId Id of the rule to delete.
	 * @return {Promise} Axios response for the delete call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	deleteRule(ruleId) {
		return axios.delete(`${baseUrl}/api/rules/${ruleId}`)
	},

	/**
	 * Edit an existing conditional-visibility rule through the UI
	 * (REQ-CVUI-001).
	 *
	 * @param {number|string} ruleId Id of the rule to update.
	 * @param {object} data Only the changed field(s) — `ruleType`,
	 *   `ruleConfig` and/or `isInclude`; the backend preserves the rest.
	 * @return {Promise} Axios response resolving to the updated rule.
	 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-001-rule-builder-in-placement-settings
	 */
	updateRule(ruleId, data) {
		return axios.put(`${baseUrl}/api/rules/${ruleId}`, data)
	},

	/**
	 * Read-only, non-persisting "preview as audience/date" evaluation
	 * (REQ-CVUI-004/005). Runs the SAME evaluation pipeline used at render
	 * time, so a preview result always matches live behaviour.
	 *
	 * @param {object} data `{rules, context}` — the in-editor rule set plus
	 *   the hypothetical audience/date to evaluate it against.
	 * @return {Promise} Axios response resolving to the visibility verdict
	 *   and the matched include/exclude rule ids.
	 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-005-preview-endpoint-reuses-the-render-time-evaluation-path-and-never-persists
	 */
	previewVisibility(data) {
		return axios.post(`${baseUrl}/api/visibility/preview`, data)
	},

	// Admin conditional-visibility overview (Beheer ▸ Versioning & Audit).
	/** @spec openspec/specs/conditional-visibility/spec.md */
	getAdminWidgetsWithRules() {
		return axios.get(`${baseUrl}/api/admin/widgets/with-rules`)
	},

	// Operations tab — Prometheus metrics + health (prometheus-metrics spec).
	/** @spec openspec/specs/prometheus-metrics/spec.md */
	getMetrics() {
		return axios.get(`${baseUrl}/api/metrics`, { responseType: 'text' })
	},

	/** @spec openspec/specs/prometheus-metrics/spec.md */
	getHealth() {
		return axios.get(`${baseUrl}/api/health`)
	},

	// Admin endpoints
	getAdminTemplates() {
		return axios.get(`${baseUrl}/api/admin/templates`)
	},

	/**
	 * Create an admin dashboard template.
	 *
	 * @param {object} data Template attributes (name, layout, widgets, …).
	 * @return {Promise} Axios response resolving to the created template.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	createAdminTemplate(data) {
		return axios.post(`${baseUrl}/api/admin/templates`, data)
	},

	/**
	 * Update an admin dashboard template.
	 *
	 * @param {number|string} id Numeric id of the template to update.
	 * @param {object} data Changed template attributes.
	 * @return {Promise} Axios response resolving to the updated template.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	updateAdminTemplate(id, data) {
		return axios.put(`${baseUrl}/api/admin/templates/${id}`, data)
	},

	/**
	 * Delete an admin dashboard template.
	 *
	 * @param {number|string} id Numeric id of the template to delete.
	 * @return {Promise} Axios response for the delete call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	deleteAdminTemplate(id) {
		return axios.delete(`${baseUrl}/api/admin/templates/${id}`)
	},

	/**
	 * Push a template's current layout to the copies users already hold.
	 *
	 * @param {number|string} id Numeric id of the template to re-sync.
	 * @param {object} options Re-sync options.
	 * @param {string} options.strategy `overwrite` replaces each copy's
	 *   layout; `merge` keeps user-added widgets.
	 * @param {boolean} options.dryRun When true the backend reports what
	 *   WOULD change without writing anything.
	 * @return {Promise} Axios response resolving to the affected-copy plan.
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	resyncAdminTemplate(id, { strategy, dryRun }) {
		return axios.post(`${baseUrl}/api/admin/templates/${id}/resync`, {
			strategy,
			dryRun,
		})
	},

	getAdminSettings() {
		return axios.get(`${baseUrl}/api/admin/settings`)
	},

	/**
	 * Persist changed instance-wide admin settings.
	 *
	 * @param {object} data Changed settings keys and their new values.
	 * @return {Promise} Axios response resolving to the stored settings.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	updateAdminSettings(data) {
		return axios.put(`${baseUrl}/api/admin/settings`, data)
	},

	// Admin group-priority order (REQ-ASET-012/013/014).
	// Both endpoints are admin-only on the server side; the UI gates
	// rendering of the section behind the same admin check.
	getAdminGroups() {
		return axios.get(`${baseUrl}/api/admin/groups`)
	},

	/**
	 * Persist the admin group-priority order (REQ-ASET-012/013/014).
	 *
	 * @param {string[]} groups Nextcloud group ids in priority order; the
	 *   first entry wins when a user belongs to several groups.
	 * @return {Promise} Axios response resolving to the stored order.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	updateAdminGroupOrder(groups) {
		return axios.post(`${baseUrl}/api/admin/groups`, { groups })
	},

	// ADR-023 action-authorization matrix. Both endpoints are admin-only
	// on the server side via #[AuthorizedAdminSetting]; the UI gates the
	// section behind the same admin check.
	getActionMatrix() {
		return axios.get(`${baseUrl}/api/admin/action-matrix`)
	},

	/**
	 * Persist the ADR-023 action-authorization matrix. Admin-only on the
	 * server via `#[AuthorizedAdminSetting]`.
	 *
	 * @param {object} matrix Action-to-role permission map to store.
	 * @return {Promise} Axios response resolving to the stored matrix.
	 * @spec openspec/architecture/adr-023-action-authorization.md
	 */
	updateActionMatrix(matrix) {
		return axios.put(`${baseUrl}/api/admin/action-matrix`, { matrix })
	},

	// Setup wizard endpoints (REQ-WIZ-008, REQ-WIZ-009, REQ-WIZ-003).
	// All three are admin-only on the server side; the UI gates the
	// banner + modal behind the same check via `getSetupWizardState`.
	getSetupWizardState() {
		return axios.get(`${baseUrl}/api/admin/setup-wizard/state`)
	},

	/** @spec openspec/specs/dashboards/spec.md */
	completeSetupWizard() {
		return axios.post(`${baseUrl}/api/admin/setup-wizard/complete`)
	},

	/**
	 * Persist the setup wizard's content-storage choice (REQ-WIZ-003).
	 *
	 * @param {string} storage Backend to store widget content in —
	 *   `database` or `groupfolder`.
	 * @return {Promise} Axios response resolving to the updated wizard state.
	 */
	setSetupWizardStorage(storage) {
		return axios.post(`${baseUrl}/api/admin/setup-wizard/storage`, { storage })
	},

	/**
	 * Export dashboards as a downloadable archive (REQ-EXIM-002..004).
	 * Requires Nextcloud-admin, gated server-side; the admin UI renders the
	 * control behind the same check.
	 *
	 * @param {object} [options] Export options.
	 * @param {string} [options.scope] `site` exports everything; any other
	 *   scope narrows the export.
	 * @param {string|null} [options.dashboardUuid] Restrict the export to a
	 *   single dashboard; omitted when exporting the whole scope.
	 * @return {Promise} Axios response whose `data` is the archive Blob.
	 * @spec openspec/specs/dashboards/spec.md
	 */
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

	/**
	 * Import dashboards from a previously exported archive
	 * (REQ-EXIM-002..004). Admin-only, gated server-side.
	 *
	 * @param {File} file Archive selected in the admin UI, sent multipart.
	 * @param {object} [options] Import options.
	 * @param {boolean} [options.preserveUuids] Keep the UUIDs recorded in the
	 *   archive instead of minting fresh ones — used when round-tripping a
	 *   dashboard back onto the instance it came from.
	 * @return {Promise} Axios response resolving to the import summary.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	importDashboards(file, { preserveUuids = false } = {}) {
		const form = new FormData()
		form.append('file', file)
		return axios.post(`${baseUrl}/api/admin/import`, form, {
			params: { preserveUuids },
			headers: { 'Content-Type': 'multipart/form-data' },
		})
	},

	/**
	 * Preview a Confluence HTML export without touching the database
	 * (REQ-CFLI-001..012). Admin-only server-side.
	 *
	 * @param {File} file Confluence HTML export archive, sent multipart.
	 * @return {Promise} Axios response resolving to the parse preview.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	confluenceImportDryRun(file) {
		const form = new FormData()
		form.append('file', file)
		return axios.post(`${baseUrl}/api/admin/import/confluence/dry-run`, form, {
			headers: { 'Content-Type': 'multipart/form-data' },
		})
	},

	/**
	 * Import a Confluence HTML export, creating one LaunchPad dashboard per
	 * Confluence page (REQ-CFLI-001..012). Admin-only server-side.
	 *
	 * @param {File} file Confluence HTML export archive, sent multipart.
	 * @param {object} [options] Import options.
	 * @param {string|null} [options.parentUuid] Dashboard to nest the
	 *   imported tree under; omitted imports at the top level.
	 * @return {Promise} Axios response resolving to the import summary.
	 * @spec openspec/specs/dashboards/spec.md
	 */
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

	// Dashboard metadata-fields admin CRUD (REQ-MDFL-001..003).
	getMetadataFields() {
		return axios.get(`${baseUrl}/api/admin/metadata-fields`)
	},

	// Per-dashboard metadata read/write (REQ-MDFL-004..006, REQ-MDFL-008).
	getDashboardMetadata(uuid) {
		return axios.get(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/metadata`,
		)
	},

	/**
	 * Write a dashboard's metadata-field values
	 * (REQ-MDFL-004..006, REQ-MDFL-008).
	 *
	 * @param {string} uuid UUID of the dashboard to annotate.
	 * @param {object} metadata Field-key to value map.
	 * @return {Promise} Axios response resolving to the stored metadata.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	updateDashboardMetadata(uuid, metadata) {
		return axios.put(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/metadata`,
			{ metadata },
		)
	},

	// Org-wide navigation editor (REQ-ONAV-001..012). The GET endpoint
	// is accessible to any logged-in user (the backend filters the tree
	// by group visibility); the PUT endpoint is admin-only on the
	// server. The position endpoints follow the same pattern. The lang
	// query parameter selects which per-language JSON file is read or
	// written; defaults to 'nl' on the backend.
	getOrgNavigation(lang = 'nl') {
		return axios.get(`${baseUrl}/api/admin/org-navigation`, { params: { lang } })
	},

	/**
	 * Replace the org-wide navigation tree for one language
	 * (REQ-ONAV-001..012). Admin-only server-side.
	 *
	 * @param {Array<object>} tree Nested navigation entries to store.
	 * @param {string} [lang] Which per-language JSON file to write.
	 * @return {Promise} Axios response resolving to the stored tree.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	updateOrgNavigation(tree, lang = 'nl') {
		return axios.put(
			`${baseUrl}/api/admin/org-navigation`,
			{ tree },
			{ params: { lang } },
		)
	},

	getOrgNavigationPosition() {
		return axios.get(`${baseUrl}/api/admin/org-navigation/position`)
	},

	/**
	 * Set where the org navigation rail is rendered (REQ-ONAV-001..012).
	 *
	 * @param {string} position Placement keyword for the rail.
	 * @return {Promise} Axios response resolving to the stored position.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	updateOrgNavigationPosition(position) {
		return axios.put(`${baseUrl}/api/admin/org-navigation/position`, {
			position,
		})
	},

	// Dashboard bulk operations (REQ-BULK-001..011). All four endpoints
	// are admin-only on the server side; the UI gates the controls behind
	// the same admin check. Each accepts `dryRun` to preview outcomes
	// without mutating the database, and returns
	// `{deletedCount|movedCount|updatedCount|reindexedCount, skippedCount, errors, dryRun}`
	// (or the `wouldX` variants in dry-run mode).

	/**
	 * Delete many dashboards in one call (REQ-BULK-001..011).
	 *
	 * @param {string[]} dashboardUuids UUIDs of the dashboards to delete.
	 * @param {object} [options] Bulk options.
	 * @param {boolean} [options.dryRun] Preview the outcome without deleting.
	 * @param {boolean} [options.cascade] Also delete nested child dashboards.
	 * @return {Promise} Axios response resolving to the bulk-result envelope.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	bulkDeleteDashboards(dashboardUuids, { dryRun = false, cascade = false } = {}) {
		return axios.post(`${baseUrl}/api/admin/dashboards/bulk-delete`, {
			dashboardUuids,
			dryRun,
			cascade,
		})
	},

	/**
	 * Re-parent many dashboards in one call (REQ-BULK-001..011).
	 *
	 * @param {string[]} dashboardUuids UUIDs of the dashboards to move.
	 * @param {string|null} parentUuid New parent dashboard UUID; null moves
	 *   them to the top level.
	 * @param {object} [options] Bulk options.
	 * @param {boolean} [options.dryRun] Preview the outcome without moving.
	 * @return {Promise} Axios response resolving to the bulk-result envelope.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	bulkMoveDashboards(dashboardUuids, parentUuid, { dryRun = false } = {}) {
		return axios.post(`${baseUrl}/api/admin/dashboards/bulk-move`, {
			dashboardUuids,
			parentUuid,
			dryRun,
		})
	},

	/**
	 * Change the publication status of many dashboards in one call
	 * (REQ-BULK-001..011).
	 *
	 * @param {string[]} dashboardUuids UUIDs of the dashboards to update.
	 * @param {string} publicationStatus Target status — `draft`,
	 *   `published` or `scheduled`.
	 * @param {object} [options] Bulk options.
	 * @param {string|null} [options.publishAt] ISO-8601 timestamp, required
	 *   when the target status is `scheduled`.
	 * @param {boolean} [options.dryRun] Preview the outcome without writing.
	 * @return {Promise} Axios response resolving to the bulk-result envelope.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	bulkStatusDashboards(
		dashboardUuids,
		publicationStatus,
		{ publishAt = null, dryRun = false } = {},
	) {
		return axios.post(`${baseUrl}/api/admin/dashboards/bulk-status`, {
			dashboardUuids,
			publicationStatus,
			publishAt,
			dryRun,
		})
	},

	/**
	 * Rebuild the search index for many dashboards in one call
	 * (REQ-BULK-001..011).
	 *
	 * @param {string[]} dashboardUuids UUIDs of the dashboards to reindex.
	 * @param {object} [options] Bulk options.
	 * @param {boolean} [options.dryRun] Report what would be reindexed only.
	 * @return {Promise} Axios response resolving to the bulk-result envelope.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	bulkReindexDashboards(dashboardUuids, { dryRun = false } = {}) {
		return axios.post(`${baseUrl}/api/admin/dashboards/bulk-reindex`, {
			dashboardUuids,
			dryRun,
		})
	},

	// Demo showcase install / list / uninstall (REQ-DEMO-002..006).
	// All three endpoints are admin-only on the server side; the UI
	// only renders the controls behind the same admin check.
	/** @spec openspec/specs/dashboards/spec.md */
	listDemoShowcases() {
		return axios.get(`${baseUrl}/api/admin/demo-showcases`)
	},

	/**
	 * Install a demo showcase bundle (REQ-DEMO-002..006). Admin-only.
	 *
	 * @param {string} id Identifier of the showcase to install.
	 * @param {object} [options] Install options.
	 * @param {string} [options.lang] Language variant to install.
	 * @param {boolean} [options.force] Reinstall even when already present.
	 * @return {Promise} Axios response resolving to the install summary.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	installDemoShowcase(id, { lang = 'nl', force = false } = {}) {
		const params = { lang }
		if (force) {
			params.force = true
		}
		return axios.post(
			`${baseUrl}/api/admin/demo-showcases/${encodeURIComponent(id)}/install`,
			null,
			{ params },
		)
	},

	/**
	 * Remove a previously installed demo showcase (REQ-DEMO-002..006).
	 *
	 * @param {string} id Identifier of the showcase to uninstall.
	 * @return {Promise} Axios response for the uninstall call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	uninstallDemoShowcase(id) {
		return axios.delete(
			`${baseUrl}/api/admin/demo-showcases/${encodeURIComponent(id)}`,
		)
	},

	// Tile endpoints
	getTiles() {
		return axios.get(`${baseUrl}/api/tiles`)
	},

	/**
	 * Create a reusable launcher tile.
	 *
	 * @param {object} data Tile attributes (title, icon, link target, …).
	 * @return {Promise} Axios response resolving to the created tile.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	createTile(data) {
		return axios.post(`${baseUrl}/api/tiles`, data)
	},

	/**
	 * Update a reusable launcher tile.
	 *
	 * @param {number|string} id Numeric id of the tile to update.
	 * @param {object} data Changed tile attributes.
	 * @return {Promise} Axios response resolving to the updated tile.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	updateTile(id, data) {
		return axios.put(`${baseUrl}/api/tiles/${id}`, data)
	},

	/**
	 * Delete a reusable launcher tile.
	 *
	 * @param {number|string} id Numeric id of the tile to delete.
	 * @return {Promise} Axios response for the delete call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	deleteTile(id) {
		return axios.delete(`${baseUrl}/api/tiles/${id}`)
	},

	// Dashboard reaction endpoints (REQ-RXN-001..004).
	getDashboardReactions(uuid) {
		return axios.get(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/reactions`,
		)
	},

	/**
	 * Add the calling user's emoji reaction to a dashboard (REQ-RXN-001..004).
	 *
	 * @param {string} uuid UUID of the dashboard being reacted to.
	 * @param {string} emoji The reaction emoji.
	 * @return {Promise} Axios response resolving to the updated reaction set.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	addDashboardReaction(uuid, emoji) {
		return axios.post(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/reactions`,
			{ emoji },
		)
	},

	/**
	 * Withdraw the calling user's emoji reaction (REQ-RXN-001..004).
	 *
	 * @param {string} uuid UUID of the dashboard to un-react to.
	 * @param {string} emoji The reaction emoji to withdraw.
	 * @return {Promise} Axios response for the delete call.
	 * @spec openspec/specs/dashboards/spec.md
	 */
	removeDashboardReaction(uuid, emoji) {
		return axios.delete(
			`${baseUrl}/api/dashboards/${encodeURIComponent(uuid)}/reactions/${encodeURIComponent(emoji)}`,
		)
	},

	// Mandatory-read acknowledgement endpoints (REQ-ACK-002..006).

	/**
	 * Record the current user's sign-off for an announcement. Idempotent
	 * server-side.
	 *
	 * @param {string} announcementKey Key identifying the announcement.
	 * @param {number} [contentVersion] Version of the text the user signed
	 *   off; a later version re-prompts them.
	 * @return {Promise} Axios response for the acknowledgement write.
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	acknowledge(announcementKey, contentVersion = 1) {
		return axios.post(`${baseUrl}/api/acknowledgements`, {
			announcementKey,
			contentVersion,
		})
	},

	/** @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md */
	getPendingAcknowledgements() {
		return axios.get(`${baseUrl}/api/acknowledgements/pending`)
	},

	/**
	 * Admin read-receipt report for one announcement — who has signed off
	 * and who is still outstanding (REQ-ACK-004).
	 *
	 * @param {string} announcementKey Key identifying the announcement.
	 * @return {Promise} Axios response resolving to the report payload.
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	getAcknowledgementReport(announcementKey) {
		return axios.get(
			`${baseUrl}/api/acknowledgements/report/${encodeURIComponent(announcementKey)}`,
		)
	},

	/**
	 * URL of the read-receipt CSV export (REQ-ACK-006). Returned as a plain
	 * string rather than fetched, so it can be used as an `<a href>` target
	 * and let the browser stream the DataDownloadResponse directly.
	 *
	 * @param {string} announcementKey Key identifying the announcement.
	 * @return {string} Download URL for the report CSV.
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	getAcknowledgementReportCsvUrl(announcementKey) {
		return `${baseUrl}/api/acknowledgements/report/${encodeURIComponent(announcementKey)}/csv`
	},
}
