/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

/**
 * savedViews — named filter sets of one dashboard, kept as `saved_view`
 * objects in the launchpad register (REQ-CWF-009). Reading, saving, editing,
 * sharing and deleting go through OpenRegister's object endpoints; this
 * module only shapes the rows and the requests.
 *
 * @module services/savedViews
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-009-saved-views-persist-named-filter-combinations
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/** The register and schema the views live in. */
export const SAVED_VIEW_REGISTER = 'launchpad'
export const SAVED_VIEW_SCHEMA = 'saved_view'

/** The most views one dashboard lists. */
export const SAVED_VIEW_LIMIT = 200

/** The clause fields a view keeps. */
const CLAUSE_FIELDS = ['dimension', 'operator', 'value', 'source', 'sourceWidgetId', 'pinned']

/**
 * The collection URL, or one view's URL.
 *
 * @param {string} [id] The view's id.
 * @return {string}
 */
function url(id) {
	const params = { register: SAVED_VIEW_REGISTER, schema: SAVED_VIEW_SCHEMA }
	if (id === undefined) {
		return generateUrl('/apps/openregister/api/objects/{register}/{schema}', params)
	}
	return generateUrl('/apps/openregister/api/objects/{register}/{schema}/{id}', { ...params, id })
}

/**
 * A view as the UI uses it: its id lifted from wherever OpenRegister put it.
 *
 * @param {object} raw An object from the register.
 * @return {object|null} The view, or null for a row without id or name.
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-009-saved-views-persist-named-filter-combinations
 */
export function normaliseView(raw) {
	if (!raw || typeof raw !== 'object') {
		return null
	}
	const id = raw.id ?? raw.uuid ?? raw['@self']?.id ?? null
	if (id === null || typeof raw.name !== 'string' || raw.name === '') {
		return null
	}
	return {
		id: String(id),
		dashboardId: raw.dashboardId,
		name: raw.name,
		description: typeof raw.description === 'string' ? raw.description : '',
		filters: Array.isArray(raw.filters) ? raw.filters : [],
		isShared: raw.isShared === true,
		createdBy: typeof raw.createdBy === 'string' ? raw.createdBy : '',
		createdAt: typeof raw.createdAt === 'string' ? raw.createdAt : '',
	}
}

/**
 * The bus's clauses as a view keeps them: the known fields only, empty
 * source widgets left out.
 *
 * @param {Array<object>} clauses The bus's clauses.
 * @return {Array<object>}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-11
 */
export function viewFilters(clauses) {
	return (Array.isArray(clauses) ? clauses : [])
		.filter((clause) => clause && typeof clause.dimension === 'string' && clause.dimension !== '')
		.map((clause) => {
			const kept = {}
			for (const field of CLAUSE_FIELDS) {
				if (clause[field] !== undefined && clause[field] !== null && clause[field] !== '') {
					kept[field] = clause[field]
				}
			}
			kept.pinned = clause.pinned === true
			return kept
		})
}

/**
 * The views split for the dropdown: the user's own, and the ones others
 * shared. Each list is sorted by name.
 *
 * @param {Array<object>} views The dashboard's views.
 * @param {string} userId The current user.
 * @return {{mine: Array<object>, shared: Array<object>}}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-12
 */
export function groupViews(views, userId) {
	const byName = (left, right) => left.name.localeCompare(right.name)
	const list = Array.isArray(views) ? views : []
	return {
		mine: list.filter((view) => userId !== '' && view.createdBy === userId).sort(byName),
		shared: list.filter((view) => view.isShared && view.createdBy !== userId).sort(byName),
	}
}

/**
 * Whether the user may edit, share or delete a view: only its creator.
 *
 * @param {object} view The view.
 * @param {string} userId The current user.
 * @return {boolean}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-13
 */
export function canManageView(view, userId) {
	return Boolean(view) && typeof userId === 'string' && userId !== '' && view.createdBy === userId
}

/**
 * The dashboard's views.
 *
 * @param {string} dashboardId The dashboard's uuid.
 * @param {object} [http] The HTTP client (axios).
 * @return {Promise<Array<object>>}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-12
 */
export async function listSavedViews(dashboardId, http = axios) {
	const response = await http.get(url(), { params: { dashboardId, _limit: SAVED_VIEW_LIMIT } })
	const data = response?.data
	const rows = Array.isArray(data) ? data : (Array.isArray(data?.results) ? data.results : [])
	return rows
		.map(normaliseView)
		.filter((view) => view !== null && view.dashboardId === dashboardId)
}

/**
 * Save the current clauses as a new view.
 *
 * @param {object} input The view.
 * @param {string} input.dashboardId The dashboard's uuid.
 * @param {string} input.name The name.
 * @param {string} [input.description] What it shows.
 * @param {boolean} [input.isShared] Whether others may apply it.
 * @param {Array<object>} input.clauses The bus's clauses.
 * @param {string} input.createdBy The current user.
 * @param {object} [http] The HTTP client (axios).
 * @param {() => Date} [now] The clock.
 * @return {Promise<object|null>} The saved view.
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-11
 */
export async function createSavedView({ dashboardId, name, description = '', isShared = false, clauses, createdBy }, http = axios, now = () => new Date()) {
	const row = {
		dashboardId,
		name: name.trim(),
		description: description.trim(),
		filters: viewFilters(clauses),
		isShared: isShared === true,
		createdBy,
		createdAt: now().toISOString(),
	}
	const response = await http.post(url(), row)
	return normaliseView({ ...row, ...(response?.data ?? {}) })
}

/**
 * Change a view's name, description or sharing.
 *
 * @param {string} id The view's id.
 * @param {{name?: string, description?: string, isShared?: boolean}} changes The changes.
 * @param {object} [http] The HTTP client (axios).
 * @return {Promise<unknown>}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-13
 */
export function updateSavedView(id, changes, http = axios) {
	const patch = {}
	if (typeof changes.name === 'string') {
		patch.name = changes.name.trim()
	}
	if (typeof changes.description === 'string') {
		patch.description = changes.description.trim()
	}
	if (typeof changes.isShared === 'boolean') {
		patch.isShared = changes.isShared
	}
	return http.patch(url(id), patch)
}

/**
 * Delete a view.
 *
 * @param {string} id The view's id.
 * @param {object} [http] The HTTP client (axios).
 * @return {Promise<unknown>}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-13
 */
export function deleteSavedView(id, http = axios) {
	return http.delete(url(id))
}
