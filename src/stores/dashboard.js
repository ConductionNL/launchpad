/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'
import {
	defaultSizeFor,
	placeNewWidget,
} from '../composables/useGridManager.js'
import { api } from '../services/api.js'
import { dropEarlyReads, startEarlyRead } from '../utils/carriedApiReads.js'
import { clonePlacements, DRAFT_FIELDS, draftChanges, draftRequestBody, draftRulesCount, newDraftId, withDraftRules } from '../utils/editDraft.js'
import { takeBootRead, takeDashboardFirstLoad } from '../utils/loadInitialState.js'
import { logger } from '../utils/logger.js'
import { parseServerStamp } from '../utils/serverStamp.js'

/**
 * Waits before each automatic retry of a failed layout save: three retries,
 * so a save is tried four times in all (grid-layout "Save failure with retry").
 *
 * @spec openspec/specs/grid-layout/spec.md
 */
export const LAYOUT_SAVE_RETRY_DELAYS_MS = [1000, 2000, 4000]

/** Counts layout saves, so a retry stops once a newer layout is being saved. */
let layoutSaveSequence = 0

/**
 * Resolve after `ms` milliseconds.
 *
 * @param {number} ms The wait.
 * @return {Promise<void>}
 */
function wait(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms))
}

/**
 * Stable backend error code returned by `POST /api/dashboard` when the admin
 * setting `allow_user_dashboards` is `false` (REQ-ASET-003). Surfaced as a
 * localised toast so the UI stays coherent even when the user reaches the
 * endpoint via a stale-cached affordance or a direct API call.
 *
 * @type {string}
 */
const ERR_PERSONAL_DASHBOARDS_DISABLED = 'personal_dashboards_disabled'

/**
 * The supported source values returned by GET /api/dashboards/visible.
 * Matches Dashboard::SOURCE_USER / SOURCE_GROUP / SOURCE_DEFAULT /
 * SOURCE_SHARED on the backend (REQ-DASH-013, REQ-SHARE-002).
 *
 * NOTE: every source-aware getter below filters by an EXPLICIT source
 * value, so a source no getter claims is silently dropped from the entire
 * UI. That is exactly how `shared` rows went missing: the server started
 * returning them and the switcher still showed nothing the moment the
 * store refreshed over the server-rendered initial state.
 */
export const SOURCE_USER = 'user'
export const SOURCE_GROUP = 'group'
export const SOURCE_DEFAULT = 'default'
export const SOURCE_SHARED = 'shared'

/**
 * Publication-state values mirrored from the PHP entity
 * (Dashboard::STATUS_DRAFT / STATUS_PUBLISHED / STATUS_SCHEDULED).
 * REQ-DASH-031..037.
 *
 * @type {string}
 */
export const STATUS_DRAFT = 'draft'
export const STATUS_PUBLISHED = 'published'
export const STATUS_SCHEDULED = 'scheduled'

/**
 * The checks resolveActive tries in order (REQ-DASH-018), each a predicate
 * over the visible rows, or `false` when it does not apply:
 *
 *   1. the chosen start dashboard (Set as default) if still in the visible
 *      list; the dashboard open now or looked at last does not count
 *   2. group-shared isDefault=1 in primaryGroup (state.primaryGroup)
 *   3. default-group isDefault=1
 *   4. first group-shared in primaryGroup
 *   5. first default-group dashboard
 *   6. first personal dashboard
 *
 * @param {object} state The Pinia store state.
 * @return {Array<((d: object) => boolean)|false>} The checks.
 * @spec openspec/changes/archive/2026-11-27-start-page-stays-vandaag/specs/dashboards/spec.md
 */
function resolutionRules(state) {
	const startUuid = state.startDashboardUuid || ''
	const primary = state.primaryGroup || ''
	const inPrimary = (d) => d.source === SOURCE_GROUP && d.groupId === primary
	const inDefault = (d) => d.source === SOURCE_DEFAULT
	const isDefault = (d) => Number(d.isDefault) === 1
	return [
		startUuid !== '' && ((d) => d.uuid === startUuid),
		primary !== '' && ((d) => inPrimary(d) && isDefault(d)),
		(d) => inDefault(d) && isDefault(d),
		primary !== '' && inPrimary,
		inDefault,
		(d) => d.source === SOURCE_USER,
	]
}

/**
 * The visible-dashboards and active-dashboard answers of a load. The page's
 * first load comes from its initial state, which carries both payloads
 * already, so the workspace opens without asking for them again; every
 * later load, or a page without them, fetches both together.
 *
 * @return {Promise<Array<{data: (object|Array|null)}>>} The visible and the active response.
 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-3
 */
function firstDashboardResponses() {
	const firstLoad = takeDashboardFirstLoad()
	if (firstLoad !== null) {
		return Promise.resolve([{ data: firstLoad.visible }, { data: firstLoad.active }])
	}
	return Promise.all([fetchVisibleDashboards(), fetchActiveDashboard()])
}

/**
 * Fetch the active dashboard. It is asked for together with the list, so a
 * failure here must not take the list down with it: the load then has no
 * active dashboard, as it had when the two were asked for one after the other.
 *
 * @return {Promise<{data: (object|null)}>} The axios response, or an empty one.
 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-3
 */
async function fetchActiveDashboard() {
	try {
		return await api.getActiveDashboard()
	} catch (error) {
		logger.error('Failed to load the active dashboard:', error)
		return { data: null }
	}
}

/**
 * Fetch the visible dashboards (REQ-DASH-013), falling back to
 * `/api/dashboards` when the `/visible` endpoint fails, so older clients
 * keep working.
 *
 * @return {Promise<object>} The axios response.
 */
async function fetchVisibleDashboards() {
	try {
		return await api.getVisibleDashboards()
	} catch (visibleError) {
		logger.warn(
			'Falling back to /api/dashboards (visible endpoint failed):',
			visibleError,
		)
		return await api.getDashboards()
	}
}

/**
 * The quota envelope of a dashboard list response, or null when the
 * response carries none (a bare array or an older backend).
 *
 * @param {unknown} payload The list response body.
 * @return {object|null} The quota.
 */
function quotaOf(payload) {
	if (!payload || Array.isArray(payload) || !payload.quota) {
		return null
	}
	return {
		maxDashboards: payload.quota.maxDashboards ?? 0,
		dashboardsUsed: payload.quota.dashboardsUsed ?? 0,
		maxWidgetsPerDashboard: payload.quota.maxWidgetsPerDashboard ?? 0,
	}
}

/**
 * The dashboard rows of a list response, each tagged with its source;
 * older backends may not tag rows, so an untagged row is personal.
 *
 * @param {unknown} payload The list response body: `{items}` or a bare array.
 * @return {Array<object>} The rows.
 */
function dashboardRowsOf(payload) {
	const rows = Array.isArray(payload) ? payload : (payload?.items ?? [])
	return (rows || []).map((d) => ({
		...d,
		source: d.source ?? SOURCE_USER,
	}))
}

/**
 * The active dashboard of a getActive response with its ownership tags.
 * getActive can resolve a group or showcase dashboard the user does not own
 * (via the last-used preference), so the tags it returns are kept; an older
 * backend that omits them means the user owns it. `hasVariants` says the
 * dashboard has more than one language version.
 *
 * @param {object} data The getActive response body.
 * @return {object} The active dashboard.
 */
function activeDashboardOf(data) {
	return {
		...data.dashboard,
		isOwner: data.isOwner ?? true,
		sharedBy: data.sharedBy ?? null,
		hasVariants: data.hasVariants === true,
	}
}

/**
 * The request body for a new personal dashboard: a plain name string, or an
 * object with name/description/icon and an optional `widgets` seed choice
 * (legacy callers may pass a string).
 *
 * @param {string|object} payload The createDashboard argument.
 * @return {object} The request body.
 */
function newDashboardData(payload) {
	if (typeof payload === 'string') {
		return { name: payload }
	}
	return {
		name: payload.name || 'My Dashboard',
		description: payload.description,
		// Optional registry key from the `dashboard-icons`
		// capability — null/undefined skips the field server-side.
		icon: payload.icon ?? null,
		// `starters` or `none`; absent seeds the default widget bundle.
		...(payload.widgets ? { widgets: payload.widgets } : {}),
	}
}

/**
 * Tell the user why a dashboard could not be created, when the backend says.
 * REQ-ASET-003 (extended): the stable `personal_dashboards_disabled` 403
 * gets a localised toast, as the UI may have offered a stale affordance or
 * the call may bypass the UI altogether. REQ-QUOTA-006 (race case): the
 * structured 409 `quota_exceeded` gets a clear message, and the quota
 * envelope is refreshed so the button disables.
 *
 * @param {object} store The dashboard store.
 * @param {object} error The axios error.
 * @return {void}
 */
function reportCreateFailure(store, error) {
	const status = error?.response?.status
	const code = error?.response?.data?.error
	if (status === 403 && code === ERR_PERSONAL_DASHBOARDS_DISABLED) {
		showError(
			t(
				'launchpad',
				'Personal dashboards are not enabled by your administrator',
			),
		)
	}
	reportDashboardQuota(store, error)
}

/**
 * On the structured 409 `quota_exceeded` of a create or fork, name the
 * dashboard limit and reread the quota envelope so the button disables
 * (REQ-QUOTA-006, race case).
 *
 * @param {object} store The dashboard store.
 * @param {object} error The axios error.
 * @return {boolean} Whether the error was a quota refusal.
 */
function reportDashboardQuota(store, error) {
	if (
		error?.response?.status !== 409
		|| error?.response?.data?.error !== 'quota_exceeded'
	) {
		return false
	}
	const limit = error.response.data.limit
	showError(
		t('launchpad', 'You have reached the limit of {limit} dashboards', {
			limit,
		}),
	)
	store.loadDashboards().catch(() => {})
	return true
}

/**
 * Where a new widget or tile goes (REQ-GRID-006 / REQ-GRID-014). A position
 * with both `x` and `y` is honoured as given, its missing size taken from
 * `size`; otherwise `placeNewWidget` appends it below the existing widgets.
 *
 * @param {object|null} position Explicit `{x, y, w, h}`, or a partial size.
 * @param {{size: {w: number, h: number}, store: object}} context The default size and the dashboard store.
 * @return {{x: number, y: number, w: number, h: number, pushed: Array}} The placement.
 */
function newPlacement(position, context) {
	const { size, store } = context
	if (position && Number.isFinite(position.x) && Number.isFinite(position.y)) {
		return {
			x: position.x,
			y: position.y,
			w: position.w ?? size.w,
			h: position.h ?? size.h,
			pushed: [],
		}
	}
	return placeNewWidget(
		{ w: position?.w ?? size.w, h: position?.h ?? size.h },
		store.widgetPlacements,
		{ gridColumns: store.activeDashboard?.gridColumns },
	)
}

/**
 * The addWidget request body. AddWidgetModal emits a `{type, content}`
 * payload for the registry-driven custom widgets (label, text, image, …);
 * the legacy callers pass a Nextcloud Dashboard API widget id as a string.
 * Both funnel through here into the API contract (`widgetId` string plus
 * `content` object for custom widgets).
 *
 * @param {string|object} widgetId A widget id, or a `{type, content}` payload.
 * @param {{x: number, y: number, w: number, h: number}} placement Where it goes.
 * @return {object} The request body.
 */
function widgetRequestBody(widgetId, placement) {
	const isCustomPayload =
		widgetId !== null
		&& typeof widgetId === 'object'
		&& typeof widgetId.type === 'string'
	const requestBody = {
		widgetId: isCustomPayload ? widgetId.type : widgetId,
		gridX: placement.x,
		gridY: placement.y,
		gridWidth: placement.w,
		gridHeight: placement.h,
	}
	if (isCustomPayload) {
		requestBody.content = widgetId.content ?? {}
	}
	return requestBody
}

/**
 * The lowest occupied edge of a layout (`gridY + gridHeight`), 0 when empty.
 *
 * @param {Array<object>} placements The placements.
 * @return {number}
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */
export function lowestEdge(placements) {
	return (placements ?? []).reduce((max, p) => Math.max(max, (Number(p.gridY) || 0) + (Number(p.gridHeight) || 0)), 0)
}

/** The fields a duplicate leaves behind: where it sits, and calculated fields. */
const DUPLICATE_SKIPPED = new Set(['gridX', 'gridY', 'gridWidth', 'gridHeight', 'calculatedFields'])

/**
 * REQ-WDG-050: the fields a duplicate takes from its original: every draft
 * field that is set, except its position and its calculated fields, deep
 * copied so the two never share an object.
 *
 * @param {object} original The placement to copy.
 * @return {object} The fields.
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
export function duplicateFields(original) {
	const fields = {}
	for (const field of DRAFT_FIELDS) {
		if (!DUPLICATE_SKIPPED.has(field) && original?.[field] !== undefined && original[field] !== null) {
			fields[field] = JSON.parse(JSON.stringify(original[field]))
		}
	}
	return fields
}

/**
 * A tile's placement fields, the tile form keys they come from (first one
 * set wins) and their default, as the tile route stores them.
 */
const DRAFT_TILE_FIELDS = [
	['tileTitle', ['title'], ''],
	['tileIcon', ['icon'], ''],
	['tileIconType', ['iconType'], 'class'],
	['tileBackgroundColor', ['backgroundColor', 'bgColor'], '#0082c9'],
	['tileTextColor', ['textColor', 'txtColor'], '#ffffff'],
	['tileLinkType', ['linkType'], 'app'],
	['tileLinkValue', ['linkValue', 'linkVal'], ''],
]

/**
 * REQ-EDIT-001: the placement fields of a tile added in a draft, from the
 * tile form's fields, as a stored tile carries them.
 *
 * @param {object} tileData The tile form's fields (`title`, `icon`, `backgroundColor`, ...).
 * @return {object} The placement's tile fields.
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */
function draftTileFields(tileData) {
	const fields = { tileType: 'custom' }
	for (const [field, keys, fallback] of DRAFT_TILE_FIELDS) {
		const key = keys.find((name) => tileData[name] !== undefined && tileData[name] !== null)
		fields[field] = key === undefined ? fallback : tileData[key]
	}
	if (tileData.content && typeof tileData.content === 'object') {
		fields.content = tileData.content
	}
	return fields
}

/**
 * REQ-EDIT-002: what a refused or failed draft Save tells the person, and
 * what the page gets back: a colleague's newer widgets (409) are named, any
 * other refusal shows the server's reason. The draft stays.
 *
 * @param {object} error The axios error.
 * @return {{saved: false, status: (number|null), conflicts: Array<object>}}
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */
function draftSaveFailure(error) {
	const status = error?.response?.status ?? null
	const data = error?.response?.data ?? {}
	logger.error('Failed to save the draft:', error)
	if (status === 409 && Array.isArray(data.placements)) {
		showError(t('launchpad', 'A colleague changed {count} of these widgets first. Nothing was saved.', { count: data.placements.length }))
		return { saved: false, status, conflicts: data.placements }
	}
	showError(data.message || t('launchpad', 'The changes could not be saved.'))
	return { saved: false, status, conflicts: [] }
}

/**
 * The toast text for a dashboard that could not be forked (REQ-ASET-003,
 * extended): the stable `personal_dashboards_disabled` 403 and a 404 get
 * their own message, anything else the generic one.
 *
 * @param {object} error The axios error.
 * @return {string} The localised message.
 * @spec openspec/specs/dashboards/spec.md
 */
function forkFailureText(error) {
	const status = error?.response?.status
	if (
		status === 403
		&& error?.response?.data?.error === ERR_PERSONAL_DASHBOARDS_DISABLED
	) {
		return t(
			'launchpad',
			'Personal dashboards are not enabled by your administrator',
		)
	}
	if (status === 404) {
		return t('launchpad', 'Dashboard not found')
	}
	return t('launchpad', 'Failed to fork dashboard')
}

/**
 * The publication-state fields of an updated dashboard entity, as they are
 * patched onto the copies already in the store (REQ-DASH-031..037).
 *
 * @param {object} dashboard The updated entity.
 * @return {object} The publication fields.
 * @spec openspec/specs/dashboards/spec.md
 */
function publicationFields(dashboard) {
	return {
		publicationStatus: dashboard.publicationStatus,
		publishAt: dashboard.publishAt,
		publishedAt: dashboard.publishedAt,
		unpublishAt: dashboard.unpublishAt ?? null,
	}
}

/**
 * The effective status of a scheduled dashboard (REQ-DASH-034): published
 * once its valid publish moment has passed, scheduled otherwise.
 *
 * @param {string|null|undefined} publishAt The scheduled publish moment.
 * @return {string} `'published' | 'scheduled'`.
 * @spec openspec/specs/dashboards/spec.md
 */
function scheduledStatus(publishAt) {
	if (!publishAt) {
		return STATUS_SCHEDULED
	}
	const when = parseServerStamp(publishAt)
	if (when === null) {
		return STATUS_SCHEDULED
	}
	return when.getTime() <= Date.now() ? STATUS_PUBLISHED : STATUS_SCHEDULED
}

/**
 * The cache key of a dashboard slug-chain path (REQ-DASH-027): the path
 * without leading and trailing slashes, or `''` when there is none.
 *
 * @param {unknown} path The slash-joined slug chain.
 * @return {string} The key.
 * @spec openspec/specs/dashboards/spec.md
 */
function dashboardPathKey(path) {
	return String(path || '')
		.replace(/\/+$/, '')
		.replace(/^\/+/, '')
}

/**
 * The metadata map of a metadata response (REQ-MDFL-004/005): its body when
 * that is a plain object, otherwise an empty map.
 *
 * @param {object|undefined} response The axios response.
 * @return {object} The metadata map.
 * @spec openspec/specs/dashboards/spec.md
 */
function metadataMapOf(response) {
	const payload = response?.data
	return payload && typeof payload === 'object' && !Array.isArray(payload)
		? payload
		: {}
}

/**
 * The dashboard a slug-chain path resolves to (REQ-DASH-027), or null when
 * none does; a failure other than a 404 miss is logged.
 *
 * @param {string} key The path cache key.
 * @return {Promise<object|null>} The dashboard payload or null.
 * @spec openspec/specs/dashboards/spec.md
 */
async function resolveDashboardPath(key) {
	try {
		const response = await api.getDashboardByPath(key)
		return response?.data?.dashboard ?? null
	} catch (error) {
		if (error?.response?.status !== 404) {
			logger.error('Failed to resolve dashboard path:', error)
		}
		return null
	}
}

/**
 * Cache a dashboard's metadata map on the store and return it.
 *
 * @param {object} store The dashboard store.
 * @param {{uuid: string, map: object}} entry The dashboard UUID and its map.
 * @return {object} The map.
 * @spec openspec/specs/dashboards/spec.md
 */
function cacheMetadata(store, { uuid, map }) {
	store.metadataByDashboard = {
		...store.metadataByDashboard,
		[uuid]: map,
	}
	return map
}

/**
 * Start the reads a dashboard switch is followed by, beside the dashboard
 * read: the target's reactions (the reactions bar) and, when its switcher
 * row says somebody else owns it, the caller's personal layer on it (the
 * "Hidden (n)" control). The HTTP client answers the components' own GETs
 * from these once (REQ-INIT-012). What the previous switch started and
 * nobody took is dropped first.
 *
 * @param {object|undefined} target The switcher row of the dashboard being opened.
 * @return {void}
 * @spec openspec/changes/archive/2026-11-27-dashboard-switch-reads-early/tasks.md#task-2
 */
export function startSwitchReads(target) {
	dropEarlyReads()
	if (!target) {
		return
	}
	const base = generateUrl('/apps/launchpad')
	if (typeof target.uuid === 'string' && target.uuid !== '') {
		startEarlyRead(`${base}/api/dashboards/${encodeURIComponent(target.uuid)}/reactions`)
	}
	if (target.isOwner === false && target.id !== undefined && target.id !== null) {
		startEarlyRead(`${base}/api/dashboards/${encodeURIComponent(target.id)}/personal-layer`)
	}
}

export const useDashboardStore = defineStore('dashboard', {
	state: () => ({
		// `dashboards` carries every dashboard visible to the user
		// (REQ-DASH-013). Each row carries a `source` field set by the
		// `/api/dashboards/visible` endpoint: `'user' | 'group' | 'default'`.
		// The frontend uses the source to route subsequent edit calls
		// to the correct backend endpoint (personal vs group-scoped).
		dashboards: [],
		// REQ-DASH-026: nested dashboard tree fetched from
		// `/api/dashboards/tree`. Each node carries
		// `{uuid, name, slug, sortOrder, children}`. Empty until
		// `loadDashboardTree()` runs.
		dashboardTree: [],
		// REQ-DASH-027: cache of resolved-by-path dashboards keyed on the
		// canonical slash-joined path. Populated by `dashboardByPath()`.
		pathCache: {},
		activeDashboard: null,
		widgetPlacements: [],
		// edit-mode-draft-and-save REQ-EDIT-001: while edit mode lasts, the
		// placements and grid columns as stored when it began; null outside
		// edit mode (or on a group dashboard, which still saves at once).
		draft: null,
		permissionLevel: 'full',
		// User's primary group id — read from initial state by the
		// workspace bootstrap. Used by `resolveActive` to mirror the
		// backend 7-step precedence (REQ-DASH-018).
		primaryGroup: '',
		// The person's chosen start dashboard (Set as default,
		// `default_dashboard_uuid`): step 1 of `resolveActive`. The
		// dashboard open now is NOT the start dashboard (REQ-DASH-018).
		startDashboardUuid: '',
		loading: false,
		// The first loadDashboards() has finished.
		loadedOnce: false,
		// A dashboard the address asked for before that load finished.
		requestedDashboardId: null,
		saving: false,
		// REQ-RXN-003 — per-dashboard reactions summary cache.
		// Map<uuid, {counts: {emoji: number, ...}, mine: [emoji, ...], enabled: boolean}>.
		reactionsSummary: {},
		// REQ-MDFL-001..006: dashboard-metadata-fields capability.
		// `metadataFields` caches the admin-managed field definitions
		// fetched from `GET /api/admin/metadata-fields` (admin only —
		// the call returns 403 for non-admins, in which case the array
		// stays empty and the metadata UI hides).
		metadataFields: [],
		// `metadataByDashboard` keys dashboard UUID → flat
		// {fieldKey: encodedValue} map. Lazily populated on first
		// `fetchDashboardMetadata(uuid)` call.
		metadataByDashboard: {},
		// dashboard-quota-limits REQ-QUOTA-006: the additive quota envelope
		// carried on the dashboards list response. `0` means unlimited.
		// `dashboardsUsed` is the user's live personal-dashboard count.
		// Defaults to all-zero (no quota UI) so unlimited instances render
		// pixel-identically to before.
		quota: {
			maxDashboards: 0,
			dashboardsUsed: 0,
			maxWidgetsPerDashboard: 0,
		},
		// dashboard-acknowledgements REQ-ACK-002: the current user's
		// outstanding mandatory-read items, fetched from
		// `/api/acknowledgements/pending`. Each entry carries
		// `{placementId, dashboardUuid, announcementKey, prompt, deadline,
		// contentVersion}`. Empty until `fetchPendingAcknowledgements()` runs
		// — so dashboards with no acknowledgement requirements render exactly
		// as before (no forced-delivery gate, no indicator).
		pendingAcknowledgements: [],
	}),

	getters: {
		activeDashboardId: (state) => state.activeDashboard?.id,

		/**
		 * REQ-EDIT-001: what the open draft changed, or null outside one.
		 *
		 * @param {object} state The store state.
		 * @return {object|null}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
		 */
		draftDiff: (state) => (state.draft === null
			? null
			: draftChanges(state.draft, {
				placements: state.widgetPlacements,
				columns: state.activeDashboard?.gridColumns ?? state.draft.columns,
			})),

		/**
		 * REQ-EDIT-001: the number of changed things the draft pill shows.
		 *
		 * @return {number}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
		 */
		draftChangeCount() {
			if (this.draft === null) {
				return 0
			}
			return (this.draftDiff?.count ?? 0) + draftRulesCount(this.draft, this.widgetPlacements)
		},

		/**
		 * REQ-EDIT-003: whether the open draft has changes to lose.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-leaving-with-unsaved-changes-asks-first-req-edit-003
		 */
		isDraftDirty() {
			return this.draftChangeCount > 0
		},

		getPlacementById: (state) => (id) => {
			return state.widgetPlacements.find((p) => p.id === id)
		},

		compulsoryPlacements: (state) => {
			return state.widgetPlacements.filter((p) => p.isCompulsory)
		},

		// dashboard-acknowledgements REQ-ACK-002: the count of the user's
		// outstanding (unacknowledged) mandatory items, surfaced as the
		// dashboard-level indicator.
		outstandingAcknowledgementCount: (state) =>
			state.pendingAcknowledgements.length,

		// The set of announcement keys the current user still owes a
		// sign-off on. Used by the widget wrapper to decide whether to force
		// delivery on a given placement.
		pendingAnnouncementKeys: (state) =>
			new Set(
				state.pendingAcknowledgements
					.map((item) => item.announcementKey)
					.filter((key) => !!key),
			),

		// REQ-ACK-002: whether a placement is an outstanding mandatory item
		// for the current user (requires acknowledgement AND its announcement
		// is still pending).
		isPlacementOutstanding: (state) => (placement) => {
			if (!placement || Number(placement.requiresAcknowledgement) !== 1) {
				return false
			}
			if (!placement.announcementKey) {
				return false
			}
			return state.pendingAcknowledgements.some(
				(item) => item.announcementKey === placement.announcementKey,
			)
		},

		// REQ-DASH-013 — personal user-owned dashboards. Backed by the
		// `/api/dashboards/visible` payload.
		userDashboards: (state) => {
			return state.dashboards.filter((d) => d.source === SOURCE_USER)
		},

		// REQ-DASH-013 — personal user-owned dashboards (alias for
		// userDashboards; matches the dev-branch naming).
		personalDashboards: (state) => {
			return state.dashboards.filter((d) => d.source === SOURCE_USER)
		},

		// REQ-DASH-014 — group-matching shared dashboards (`source ===
		// 'group'`).
		groupSharedDashboards: (state) => {
			return state.dashboards.filter((d) => d.source === SOURCE_GROUP)
		},

		// dashboard-quota-limits REQ-QUOTA-006: whether the user has hit
		// their personal-dashboard ceiling. `false` when unlimited
		// (`maxDashboards === 0`), so create affordances stay enabled on
		// instances that never configured a quota.
		dashboardQuotaReached: (state) => {
			const max = state.quota?.maxDashboards ?? 0
			if (max <= 0) {
				return false
			}
			return (state.quota?.dashboardsUsed ?? 0) >= max
		},

		// dashboard-quota-limits REQ-QUOTA-006: localised tooltip for the
		// disabled "New dashboard" affordance. Empty string when not at the
		// limit (no tooltip needed).
		dashboardQuotaTooltip: (state) => {
			const max = state.quota?.maxDashboards ?? 0
			if (max <= 0 || (state.quota?.dashboardsUsed ?? 0) < max) {
				return ''
			}
			return t(
				'launchpad',
				'You have reached the limit of {limit} dashboards',
				{ limit: max },
			)
		},

		// dashboard-quota-limits REQ-QUOTA-006: whether the active
		// dashboard has hit its widget ceiling. `false` when unlimited.
		widgetQuotaReached: (state) => {
			const max = state.quota?.maxWidgetsPerDashboard ?? 0
			if (max <= 0) {
				return false
			}
			return (state.widgetPlacements?.length ?? 0) >= max
		},

		// dashboard-quota-limits REQ-QUOTA-006: localised tooltip for the
		// disabled "Add widget" affordance. Empty string when not at the
		// limit.
		widgetQuotaTooltip: (state) => {
			const max = state.quota?.maxWidgetsPerDashboard ?? 0
			if (max <= 0 || (state.widgetPlacements?.length ?? 0) < max) {
				return ''
			}
			return t(
				'launchpad',
				'You have reached the limit of {limit} widgets on this dashboard',
				{ limit: max },
			)
		},

		// REQ-DASH-012 — default-group shared dashboards (`source ===
		// 'default'`).
		defaultGroupDashboards: (state) => {
			return state.dashboards.filter((d) => d.source === SOURCE_DEFAULT)
		},

		// REQ-SHARE-002 — dashboards reached through an explicit
		// per-dashboard share (`source === 'shared'`). Neither the caller's
		// own nor their group's, so they get their own bucket rather than
		// being folded into `groupSharedDashboards`.
		sharedWithMeDashboards: (state) => {
			return state.dashboards.filter((d) => d.source === SOURCE_SHARED)
		},

		/**
		 * Mirror of the backend 7-step resolver (REQ-DASH-018) for purely
		 * client-side fallback after store mutations (e.g. dashboard delete,
		 * group dashboards refreshed). The order is identical to the PHP
		 * resolver so the next page load picks the same dashboard:
		 *
		 *   1. the chosen start dashboard if still in the visible list
		 *   2. group-shared isDefault=1 in primaryGroup (state.primaryGroup)
		 *   3. default-group isDefault=1
		 *   4. first group-shared in primaryGroup
		 *   5. first default-group dashboard
		 *   6. first personal dashboard
		 *   7. null  → caller renders the empty-state UI
		 *
		 * Returns the dashboard descriptor (the row from state.dashboards)
		 * or null. Source is read off `descriptor.source`.
		 *
		 * @param {object} state The Pinia store state.
		 * @return {object|null} The resolved dashboard row, or null.
		 * @spec openspec/changes/archive/2026-11-27-start-page-stays-vandaag/specs/dashboards/spec.md
		 */
		resolveActive: (state) => {
			const list = state.dashboards || []
			for (const rule of resolutionRules(state).filter(Boolean)) {
				const match = list.find(rule)
				if (match !== undefined) {
					return match
				}
			}
			return null
		},

		// REQ-MDFL-004: returns the metadata map for the given UUID, or
		// an empty object when nothing has been fetched yet. Components
		// using this getter MUST call `fetchDashboardMetadata(uuid)`
		// once on mount to populate the cache.
		metadataFor: (state) => (uuid) => {
			return state.metadataByDashboard[uuid] || {}
		},

		// REQ-MDFL-001: alphabetised admin-managed field-definition list.
		metadataFieldsSorted: (state) => {
			return [...state.metadataFields].sort((a, b) => {
				const ao = Number(a.sortOrder || 0)
				const bo = Number(b.sortOrder || 0)
				return ao !== bo
					? ao - bo
					: String(a.label).localeCompare(String(b.label))
			})
		},
	},

	actions: {
		// dashboard-acknowledgements REQ-ACK-002: refresh the user's
		// outstanding mandatory-read items. Failures are non-fatal — a broken
		// acknowledgement fetch must never blank the dashboard, so the list is
		// simply left empty (no forced-delivery gate).
		// The first read takes the items the page carries
		// (workspace-boot-reads-from-initial-state); every later one asks.
		/**
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 * @spec openspec/changes/archive/2026-11-25-workspace-boot-reads-from-initial-state/tasks.md#task-3
		 */
		async fetchPendingAcknowledgements() {
			const carried = takeBootRead('pendingAcknowledgements')
			if (carried && Array.isArray(carried.items)) {
				this.pendingAcknowledgements = carried.items
				return
			}
			try {
				const response = await api.getPendingAcknowledgements()
				this.pendingAcknowledgements = response?.data?.items ?? []
			} catch (error) {
				logger.warn('Failed to load pending acknowledgements:', error)
				this.pendingAcknowledgements = []
			}
		},

		/**
		 * Record the current user's sign-off for a placement's announcement
		 * (REQ-ACK-002 / REQ-ACK-003). Idempotent server-side; on success the
		 * placement drops out of the outstanding set.
		 *
		 * @param {object} placement Placement carrying the `announcementKey`
		 *   to acknowledge; a placement without one is ignored.
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 */
		async acknowledgePlacement(placement) {
			if (!placement?.announcementKey) {
				return
			}
			await api.acknowledge(
				placement.announcementKey,
				Number(placement.acknowledgementContentVersion) || 1,
			)
			this.pendingAcknowledgements = this.pendingAcknowledgements.filter(
				(item) => item.announcementKey !== placement.announcementKey,
			)
		},

		/**
		 * @spec openspec/specs/dashboards/spec.md
		 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-3
		 */
		async loadDashboards() {
			this.loading = true
			try {
				// dashboard-quota-limits REQ-QUOTA-006: the list response is
				// now `{items: [...], quota: {...}}`. Stay tolerant of older
				// backends that returned a bare array so a version skew never
				// blanks the dashboard list.
				// The list and the active dashboard do not depend on each
				// other, so they are asked for together: one round trip, not two.
				const [visibleResponse, activeResponse] = await firstDashboardResponses()
				const payload = visibleResponse.data
				const quota = quotaOf(payload)
				if (quota !== null) {
					this.quota = quota
				}
				this.dashboards = dashboardRowsOf(payload)

				if (activeResponse.data) {
					this.activeDashboard = activeDashboardOf(activeResponse.data)
					this.widgetPlacements = activeResponse.data.placements || []
					this.permissionLevel =
						activeResponse.data.permissionLevel || 'full'
				}
			} catch (error) {
				logger.error('Failed to load dashboards:', error)
			} finally {
				this.loading = false
				this.loadedOnce = true
			}

			const requested = this.requestedDashboardId
			this.requestedDashboardId = null
			if (requested !== null && String(this.activeDashboardId) !== String(requested)) {
				await this.switchDashboard(requested)
			}
		},

		/**
		 * Open the dashboard an address names (`/dashboards/:id`). Before the
		 * first load has finished the request is kept and applied when it
		 * does, so the stored active dashboard does not replace it.
		 *
		 * @param {string|number} dashboardId Id of the dashboard to open.
		 * @return {Promise<void>}
		 * @spec openspec/specs/manifest-routing/spec.md#requirement-req-route-003-a-dashboard-has-an-address
		 */
		async openDashboard(dashboardId) {
			if (!this.loadedOnce) {
				this.requestedDashboardId = dashboardId
				return
			}
			await this.switchDashboard(dashboardId)
		},

		/**
		 * Make another dashboard active and load its placements.
		 *
		 * @param {string|number} dashboardId Id of the dashboard to switch to.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async switchDashboard(dashboardId) {
			// REQ-EDIT-003: a draft belongs to one dashboard; leaving it drops
			// it (the page asked first whether to save).
			if (this.draft !== null && this.draft.dashboardId !== dashboardId) {
				this.draft = null
			}
			this.loading = true
			try {
				const target = this.dashboards.find((d) => d.id === dashboardId)
				// The legacy id-based activate endpoint writes the `is_active`
				// SMALLINT column, which is only meaningful for personal
				// `user`-type rows the caller owns. Group/default dashboards
				// (user_id NULL) would be rejected with "Access denied"; they
				// rely solely on the UUID preference persisted below.
				const isPersonalOwned =
					target?.source === SOURCE_USER && target?.isOwner !== false

				startSwitchReads(target)

				// Always load full dashboard data via the by-id endpoint;
				// it returns placements + the user's effective permission
				// level for both owned and shared dashboards. For an owned
				// personal dashboard the active flag is persisted alongside:
				// the read does not depend on it, so the two go out together
				// instead of one after the other (in-app navigation budget,
				// ADR-124). A failure of either still fails the switch.
				const [response] = await Promise.all([
					api.getDashboardById(dashboardId),
					isPersonalOwned ? api.activateDashboard(dashboardId) : null,
				])
				this.activeDashboard = {
					...response.data.dashboard,
					isOwner: response.data.isOwner,
					sharedBy: response.data.sharedBy,
					hasVariants: response.data.hasVariants === true,
				}
				this.widgetPlacements = response.data.placements || []
				this.permissionLevel = response.data.permissionLevel || 'full'

				// REQ-DASH-019: persist the user's choice so subsequent
				// page loads honour it via the backend resolver. Fire and
				// forget — failure here is logged but does not block the
				// UI; the resolver tolerates a missing pref.
				this.persistActivePreference(
					this.activeDashboard?.uuid || dashboardId,
				)
			} catch (error) {
				logger.error('Failed to switch dashboard:', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Re-read the active dashboard's placements in place, without the
		 * loading state, so a kiosk screen updates with no flash
		 * (REQ-KIOSK-004). A failed read keeps what is on screen.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		async reloadPlacements() {
			const id = this.activeDashboard?.id
			if (!id) {
				return
			}
			try {
				const response = await api.getDashboardById(id)
				this.widgetPlacements = response.data.placements || []
			} catch (error) {
				logger.error('Failed to reload the dashboard:', error)
			}
		},

		/**
		 * Re-read whether the visibility rules hide each placement of the
		 * active dashboard for its viewers, and copy that onto the loaded
		 * placements. The layout itself is left alone, so unsaved edits stay.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/conditional-visibility/spec.md
		 */
		async refreshPlacementVisibility() {
			const id = this.activeDashboard?.id
			if (!id) {
				return
			}
			try {
				const response = await api.getDashboardById(id)
				const hidden = new Map(
					(response.data.placements || []).map((p) => [p.id, p.hiddenForViewers === true]),
				)
				this.widgetPlacements = this.widgetPlacements.map((p) =>
					hidden.has(p.id) ? { ...p, hiddenForViewers: hidden.get(p.id) } : p,
				)
			} catch (error) {
				logger.error('Failed to refresh widget visibility:', error)
			}
		},

		/**
		 * Persist the active-dashboard preference (REQ-DASH-019).
		 *
		 * Fire-and-forget: a network error here is logged but does NOT
		 * surface as a toast or block the UI. The backend tolerates a
		 * missing pref — the resolver just falls through to step 2.
		 *
		 * @param {string} uuid The dashboard UUID, or empty string to clear.
		 * @return {void}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		persistActivePreference(uuid) {
			api.setActiveDashboardPreference(uuid || '').catch((error) => {
				logger.warn('Failed to persist active dashboard preference:', error)
			})
		},

		/**
		 * Publish a dashboard (REQ-DASH-032).
		 *
		 * On success the local copy is patched in place so the UI reflects
		 * the new publicationStatus / publishedAt without a full reload.
		 *
		 * @param {string} uuid The dashboard UUID to publish.
		 * @return {Promise<object|null>} The updated dashboard payload or
		 *   `null` on failure (an error toast is surfaced).
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async publishDashboard(uuid) {
			try {
				const response = await api.publishDashboard(uuid)
				this.applyPublicationPatch(response.data?.dashboard)
				return response.data?.dashboard ?? null
			} catch (error) {
				logger.error('Failed to publish dashboard:', error)
				showError(t('launchpad', 'Publish dashboard'))
				return null
			}
		},

		/**
		 * Unpublish a dashboard (REQ-DASH-033). Preserves publishedAt
		 * server-side; the local copy is patched in place.
		 *
		 * @param {string} uuid The dashboard UUID to unpublish.
		 * @return {Promise<object|null>} The updated dashboard or null.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async unpublishDashboard(uuid) {
			try {
				const response = await api.unpublishDashboard(uuid)
				this.applyPublicationPatch(response.data?.dashboard)
				return response.data?.dashboard ?? null
			} catch (error) {
				logger.error('Failed to unpublish dashboard:', error)
				showError(t('launchpad', 'Unpublish dashboard'))
				return null
			}
		},

		/**
		 * Schedule a dashboard for automatic publication (REQ-DASH-034).
		 *
		 * @param {string} uuid      The dashboard UUID to schedule.
		 * @param {string} publishAt The future ISO-8601 timestamp.
		 * @return {Promise<object|null>} The updated dashboard or null.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		/**
		 * Schedule go-live and/or take-down (sharing-dashboard-schedule-screen).
		 * Rethrows so the dialog can show the server's refusal.
		 *
		 * @param {string} uuid Dashboard UUID.
		 * @param {string|null} publishAt Go-live time, or null.
		 * @param {string|null} [unpublishAt] Take-down time, or null.
		 * @return {Promise<object|null>} The updated dashboard.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async scheduleDashboard(uuid, publishAt, unpublishAt = null) {
			const response = await api.scheduleDashboard(
				uuid,
				publishAt,
				unpublishAt,
			)
			this.applyPublicationPatch(response.data?.dashboard)
			return response.data?.dashboard ?? null
		},

		/**
		 * Patch the publication-state fields on a dashboard already in the
		 * store. No-op when the dashboard is not yet loaded — the next
		 * `loadDashboards()` call will fetch the canonical state.
		 *
		 * @param {object|null|undefined} dashboard The updated entity.
		 * @return {void}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		applyPublicationPatch(dashboard) {
			if (!dashboard || !dashboard.uuid) {
				return
			}
			const fields = publicationFields(dashboard)
			const idx = this.dashboards.findIndex((d) => d.uuid === dashboard.uuid)
			if (idx >= 0) {
				this.dashboards[idx] = { ...this.dashboards[idx], ...fields }
			}
			if (this.activeDashboard?.uuid === dashboard.uuid) {
				this.activeDashboard = { ...this.activeDashboard, ...fields }
			}
		},

		/**
		 * Merge changed fields into a loaded dashboard and, when it is open,
		 * into the active one, so the page follows a setting stored elsewhere.
		 *
		 * @param {string} uuid The dashboard's uuid.
		 * @param {object} fields The changed fields.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
		 */
		applyFieldsPatch(uuid, fields) {
			if (!uuid || !fields) {
				return
			}
			const idx = this.dashboards.findIndex((d) => d.uuid === uuid)
			if (idx >= 0) {
				this.dashboards[idx] = { ...this.dashboards[idx], ...fields }
			}
			if (this.activeDashboard?.uuid === uuid) {
				this.activeDashboard = { ...this.activeDashboard, ...fields }
			}
		},

		/**
		 * Resolve the effective publication status for a dashboard,
		 * applying client-side lazy materialisation of scheduled-as-
		 * published rows for instant UI feedback (REQ-DASH-034). The
		 * backend remains the source of truth; this is purely a UX hint.
		 *
		 * @param {object} dashboard The dashboard entity.
		 * @return {string} `'draft' | 'published' | 'scheduled'`.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		effectivePublicationStatus(dashboard) {
			if (!dashboard) {
				return STATUS_DRAFT
			}
			const status = dashboard.publicationStatus || STATUS_PUBLISHED
			if (status !== STATUS_SCHEDULED) {
				return status
			}
			return scheduledStatus(dashboard.publishAt)
		},

		/**
		 * Create a personal dashboard.
		 *
		 * @param {string|object} [payload] Either a plain name string or an
		 *   object with `name` / `description` / `icon`; the string form is
		 *   accepted for legacy callers.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async createDashboard(payload = 'My Dashboard') {
			const data = newDashboardData(payload)
			this.loading = true
			try {
				const response = await api.createDashboard(data)
				this.dashboards.push({
					...response.data.dashboard,
					// Tag as user-scope so the source-aware getters work.
					source: response.data.dashboard.source ?? SOURCE_USER,
				})
				this.activeDashboard = response.data.dashboard
				// Backend seeds a default widget bundle on every new
				// user-created dashboard and returns the placements in
				// the same envelope shape as `getActive()`. Older
				// servers that haven't shipped the seed yet return no
				// `placements` key — fall back to an empty array so the
				// dashboard renders cleanly until the user adds widgets.
				this.widgetPlacements = response.data.placements ?? []
			} catch (error) {
				reportCreateFailure(this, error)
				logger.error('Failed to create dashboard:', error)
				throw error
			} finally {
				this.loading = false
			}
		},

		/**
		 * Fork any dashboard the user can see — personal, group, or the
		 * default-group sentinel — into a brand-new personal copy
		 * (REQ-DASH-020).
		 *
		 * The copy becomes the user's active dashboard: it is pushed onto
		 * `dashboards` tagged `source: 'user'` so the source-aware getters
		 * keep working, and pinned as `activeDashboard` so the UI rerenders
		 * without a reload.
		 *
		 * @param {string} sourceUuid UUID of the dashboard to copy.
		 * @param {string|null} name Name for the copy; null lets the backend
		 *   apply the localised `My copy of {source name}` default.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async forkDashboard(sourceUuid, name) {
			this.loading = true
			try {
				const response = await api.forkDashboard(sourceUuid, name)
				const fork = response.data?.dashboard
				if (fork) {
					// Tag as `user`-source so `userDashboards` getter
					// surfaces it without waiting for a /visible refresh.
					this.dashboards.push({
						...fork,
						source: 'user',
						isOwner: true,
						sharedBy: null,
					})
					this.activeDashboard = { ...fork, isOwner: true, sharedBy: null }
					this.widgetPlacements = []
					this.permissionLevel = 'full'
					// The fork carries the source's widgets; the by-id
					// endpoint is the canonical source of its placements.
					await this.switchDashboard(fork.id)
				}
				return fork
			} catch (error) {
				if (!reportDashboardQuota(this, error)) {
					showError(forkFailureText(error))
				}
				logger.error('Failed to fork dashboard:', error)
				throw error
			} finally {
				this.loading = false
			}
		},

		/**
		 * REQ-EDIT-001: begin a draft as edit mode begins: the placements and
		 * columns stored now are kept to compare and to go back to. A group
		 * dashboard keeps saving each change, until its endpoint takes drafts.
		 *
		 * @return {boolean} Whether a draft is open.
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
		 */
		beginDraft() {
			const active = this.activeDashboard
			if (!active || active.source === SOURCE_GROUP || active.source === SOURCE_DEFAULT) {
				this.draft = null
				return false
			}
			this.draft = {
				dashboardId: active.id,
				placements: clonePlacements(this.widgetPlacements),
				columns: active.gridColumns ?? 12,
				rules: {},
			}
			return true
		},

		/**
		 * REQ-EDIT-001: the rules dialog changes the draft only: a widget's
		 * visibility rules as they will be stored on Save.
		 *
		 * @param {number|string} placementId The widget.
		 * @param {Array<object>} rules Its rules (`ruleType`, `ruleConfig`, `isInclude`).
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
		 */
		setDraftRules(placementId, rules) {
			if (this.draft === null) {
				return
			}
			this.draft = { ...this.draft, rules: { ...(this.draft.rules ?? {}), [String(placementId)]: JSON.parse(JSON.stringify(rules ?? [])) } }
		},

		/**
		 * REQ-EDIT-004: drop the draft and show what was stored before edit
		 * mode began. Nothing is sent.
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-cancel-discards-the-draft-req-edit-004
		 */
		discardDraft() {
			if (this.draft === null) {
				return
			}
			if (this.activeDashboard?.id === this.draft.dashboardId) {
				this.widgetPlacements = clonePlacements(this.draft.placements)
				this.activeDashboard = { ...this.activeDashboard, gridColumns: this.draft.columns }
			}
			this.draft = null
		},

		/**
		 * REQ-EDIT-002: store the draft in one request. On success the stored
		 * placements replace the draft and the draft ends; a refusal (403, 400)
		 * or a colleague's newer widget (409) keeps the draft and says why.
		 *
		 * @return {Promise<{saved: boolean, status: (number|null), conflicts: Array<object>}>}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
		 */
		async saveDraft() {
			const draft = this.draft
			if (draft === null) {
				return { saved: true, status: null, conflicts: [] }
			}
			const current = { placements: this.widgetPlacements, columns: this.activeDashboard?.gridColumns ?? draft.columns }
			this.saving = true
			try {
				const response = await api.updateDashboard(draft.dashboardId, withDraftRules(draftRequestBody(draft, current), draft))
				this.takeSavedDraft(draft, response.data?.data ?? response.data ?? {})
				return { saved: true, status: 200, conflicts: [] }
			} catch (error) {
				return draftSaveFailure(error)
			} finally {
				this.saving = false
			}
		},

		/**
		 * REQ-EDIT-002: after a stored draft, the stored placements and
		 * dashboard replace the draft, and the draft ends.
		 *
		 * @param {object} draft The draft that was saved.
		 * @param {object} body The answer: `placements` and `dashboard`.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
		 */
		takeSavedDraft(draft, body) {
			if (Array.isArray(body.placements)) {
				this.widgetPlacements = body.placements
			}
			if (body.dashboard && this.activeDashboard?.id === draft.dashboardId) {
				this.activeDashboard = { ...this.activeDashboard, ...body.dashboard }
			}
			this.draft = null
		},

		/**
		 * REQ-EDIT-001: add a widget to the draft only, with a draft id, and
		 * apply the push-downs its spot caused.
		 *
		 * @param {object} body The widget as the add request would carry it (`widgetId`, grid, content, tile fields).
		 * @param {Array<object>} pushed The push-down side effects of its spot.
		 * @return {object} The draft placement.
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
		 */
		addDraftPlacement(body, pushed = []) {
			const placement = {
				id: newDraftId(),
				dashboardId: this.activeDashboard?.id ?? null,
				isCompulsory: 0,
				isVisible: 1,
				showTitle: 1,
				content: null,
				...body,
			}
			this.widgetPlacements.push(placement)
			if (Array.isArray(pushed) && pushed.length > 0) {
				const pushIndex = new Map(pushed.map((p) => [String(p.id), p.gridY]))
				this.widgetPlacements = this.widgetPlacements.map((p) => (
					pushIndex.has(String(p.id)) ? { ...p, gridY: pushIndex.get(String(p.id)) } : p
				))
			}
			return placement
		},

		/**
		 * REQ-WDG-050: Duplicate in a widget's edit menu. The copy carries the
		 * widget's type, size, content, look and rules, goes below the other
		 * widgets (REQ-GRID-006), and joins the draft; without a draft (a group
		 * dashboard) it is added and given its fields at once. Calculated
		 * fields stay with the original: they write to a source schema, and a
		 * draft may not change them (REQ-EDIT-002).
		 *
		 * @param {number|string} placementId The widget to copy.
		 * @return {Promise<object|null>} The copy, or null when nothing was copied.
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
		 */
		async duplicatePlacement(placementId) {
			const original = this.widgetPlacements.find((p) => String(p.id) === String(placementId))
			if (!original) {
				return null
			}
			const spot = newPlacement(null, {
				size: { w: Number(original.gridWidth) || 4, h: Number(original.gridHeight) || 4 },
				store: this,
			})
			const fields = duplicateFields(original)
			const body = {
				widgetId: original.widgetId,
				...fields,
				gridX: spot.x,
				gridY: spot.y,
				gridWidth: spot.w,
				gridHeight: spot.h,
			}
			if (this.draft !== null) {
				return this.addDraftPlacement(body, spot.pushed)
			}
			try {
				const { content, ...rest } = fields
				const response = await api.addWidget(this.activeDashboard.id, {
					widgetId: body.widgetId,
					gridX: body.gridX,
					gridY: body.gridY,
					gridWidth: body.gridWidth,
					gridHeight: body.gridHeight,
					...(content !== undefined ? { content } : {}),
				})
				let copy = response.data
				if (Object.keys(rest).length > 0 && copy?.id !== undefined) {
					copy = (await api.updateWidgetPlacement(copy.id, rest)).data ?? copy
				}
				this.widgetPlacements.push(copy)
				return copy
			} catch (error) {
				this.handleWidgetQuotaError(error)
				logger.error('Failed to duplicate widget:', error)
				showError(t('launchpad', 'The widget could not be duplicated.'))
				return null
			}
		},

		/**
		 * Persist the whole placement layout. Local state updates immediately
		 * for responsiveness; the write is debounced and routed to the
		 * personal or group endpoint per the active dashboard's source
		 * (REQ-DASH-013).
		 *
		 * @param {Array<object>} placements Every placement on the dashboard,
		 *   carrying the geometry to store.
		 * @spec openspec/specs/dashboards/spec.md
		 * @spec openspec/specs/grid-layout/spec.md
		 */
		async updatePlacements(placements) {
			logger.debug(
				'[DashboardStore] updatePlacements called, count:',
				placements.length,
			)

			// Update local state immediately for responsiveness
			this.widgetPlacements = placements
			// REQ-EDIT-001: in a draft a move or resize changes the draft only.
			if (this.draft !== null) {
				return
			}

			// Debounced save to backend
			this.saving = true
			const sequence = ++layoutSaveSequence
			const placementsData = placements.map((p) => ({
				id: p.id,
				gridX: p.gridX,
				gridY: p.gridY,
				gridWidth: p.gridWidth,
				gridHeight: p.gridHeight,
			}))
			logger.debug(
				'[DashboardStore] Sending placements to API:',
				JSON.stringify(placementsData, null, 2),
			)

			// REQ-DASH-013 — route the PUT to the correct endpoint
			// based on the active dashboard's source.
			const active = this.activeDashboard
			const save = () => (
				active
				&& (active.source === SOURCE_GROUP
					|| active.source === SOURCE_DEFAULT)
					? api.updateGroupDashboard(active.groupId, active.uuid, {
						placements: placementsData,
					})
					: api.updateDashboard(active.id, {
						placements: placementsData,
					})
			)

			// A failed save is reported once and retried automatically, up
			// to three times; a newer layout replaces the retries of an older
			// one (grid-layout "Save failure with retry").
			try {
				for (let attempt = 0; ; attempt++) {
					try {
						await save()
						logger.debug('[DashboardStore] Successfully saved placements')
						return
					} catch (error) {
						logger.error('Failed to save placements:', error)
						if (attempt === 0) {
							showError(t('launchpad', 'The layout could not be saved. Trying again.'))
						}
						if (attempt >= LAYOUT_SAVE_RETRY_DELAYS_MS.length) {
							showError(t('launchpad', 'The layout could not be saved.'))
							return
						}
					}
					await wait(LAYOUT_SAVE_RETRY_DELAYS_MS[attempt])
					if (sequence !== layoutSaveSequence) {
						return
					}
				}
			} finally {
				if (sequence === layoutSaveSequence) {
					this.saving = false
				}
			}
		},

		/**
		 * Give the active dashboard 6, 8 or 12 grid columns (the edit bar's
		 * Columns), through the personal or the group endpoint like the
		 * placements. The grid follows at once; a refused save puts the old
		 * count back.
		 *
		 * @param {number} columns The new column count.
		 * @return {Promise<boolean>} Whether the server stored it.
		 * @spec openspec/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
		 */
		async setGridColumns(columns) {
			const active = this.activeDashboard
			if (!active || active.gridColumns === columns) {
				return false
			}
			const previous = active.gridColumns
			const apply = (value) => {
				this.activeDashboard = { ...this.activeDashboard, gridColumns: value }
				this.dashboards = this.dashboards.map((d) =>
					d.id === active.id ? { ...d, gridColumns: value } : d,
				)
			}
			apply(columns)
			// REQ-EDIT-001: in a draft the columns change the draft only.
			if (this.draft !== null) {
				return true
			}
			try {
				if (active.source === SOURCE_GROUP || active.source === SOURCE_DEFAULT) {
					await api.updateGroupDashboard(active.groupId, active.uuid, {
						gridColumns: columns,
					})
				} else {
					await api.updateDashboard(active.id, { gridColumns: columns })
				}
				return true
			} catch (error) {
				logger.error('Failed to save the grid columns:', error)
				apply(previous)
				showError(t('launchpad', 'The number of columns could not be saved.'))
				return false
			}
		},

		/**
		 * Add a widget to the active dashboard. Routes through
		 * `placeNewWidget` (REQ-GRID-014) so the placement algorithm
		 * (REQ-GRID-006: append in a fresh row below all existing
		 * widgets, never moving them) is the single source of truth for
		 * "where does this go?".
		 *
		 * Position-only callers (e.g. legacy code that passed a fully
		 * computed `{x, y, w, h}`) MAY still supply a `position` object;
		 * if it includes both `x` AND `y` we honour the explicit choice
		 * and skip the auto-placement path. Otherwise we delegate to
		 * `placeNewWidget`. The helper never moves existing widgets, so
		 * `placement.pushed` is always empty; the batch-update branch
		 * (REQ-WDG-008, debounce 300 ms) is retained for caller
		 * compatibility but is a no-op under bottom-append.
		 *
		 * @param {string|object} widgetId widget identifier OR a `{type, content}` payload from AddWidgetModal
		 * @param {object|null} [position] explicit `{x, y, w, h}` (skips auto-placement) or partial spec to seed the helper
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async addWidgetToDashboard(widgetId, position = null) {
			try {
				const type = typeof widgetId === 'object' && widgetId !== null ? widgetId.type : widgetId
				const placement = newPlacement(position, {
					size: defaultSizeFor(type, this.activeDashboard?.gridColumns || undefined),
					store: this,
				})
				// REQ-EDIT-001: in a draft the widget joins the draft only.
				if (this.draft !== null) {
					return this.addDraftPlacement(widgetRequestBody(widgetId, placement), placement.pushed)
				}
				const response = await api.addWidget(
					this.activeDashboard.id,
					widgetRequestBody(widgetId, placement),
				)
				this.widgetPlacements.push(response.data)

				if (placement.pushed.length > 0) {
					await this.applyPushedPlacements(placement.pushed)
				}

				// Return the created placement so callers (e.g. the unified
				// add/edit modal) can apply chrome (title/background/icon) to a
				// brand-new widget via a follow-up updateWidgetPlacement patch.
				return response.data
			} catch (error) {
				this.handleWidgetQuotaError(error)
				logger.error('Failed to add widget:', error)
			}
		},

		/**
		 * Import chosen browser bookmarks onto the active dashboard. The server
		 * appends the containers and tiles at the bottom and answers with them
		 * and the skipped bookmarks; the new placements join the store. A 409
		 * (not enough room left) is rethrown for the dialog to explain.
		 *
		 * @param {{folders: Array<object>, bookmarks: Array<object>}} chosen The chosen folders and loose bookmarks.
		 * @return {Promise<{placements: Array<object>, skipped: Array<object>}>}
		 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
		 */
		async importBookmarks(chosen) {
			// REQ-EDIT-001: in a draft the server only lays the import out
			// below the draft's lowest edge; the draft adds it, Save stores it.
			const inDraft = this.draft !== null
			const body = inDraft ? { ...chosen, draft: true, bottom: lowestEdge(this.widgetPlacements) } : chosen
			const response = await api.importBookmarks(this.activeDashboard.id, body)
			const answered = Array.isArray(response.data?.placements) ? response.data.placements : []
			const placements = inDraft ? answered.map((placement) => this.addDraftPlacement(placement)) : answered
			if (!inDraft) {
				this.widgetPlacements.push(...placements)
			}
			return { placements, skipped: Array.isArray(response.data?.skipped) ? response.data.skipped : [] }
		},

		/**
		 * Add a tile to the active dashboard. Tiles default to a smaller
		 * 2×2 footprint than regular widgets but still funnel through
		 * `placeNewWidget` so the bottom-append placement algorithm is
		 * applied consistently (REQ-GRID-006 / REQ-GRID-014).
		 *
		 * @param {object} tileData tile payload (title/icon/colours/link)
		 * @param {object|null} [position] explicit `{x, y, w, h}` (skips auto-placement) or partial spec to seed the helper
		 * @return {Promise<object|undefined>} the created placement (service-health-ping REQ-HPING-001 follow-up patch), or undefined on failure.
		 * @spec openspec/specs/dashboards/spec.md
		 * @spec openspec/specs/service-health-ping/spec.md
		 */
		async addTileToDashboard(tileData, position = null) {
			try {
				const placement = newPlacement(position, {
					size: { w: 2, h: 2 },
					store: this,
				})

				// REQ-EDIT-001: in a draft the tile joins the draft only, with the
				// tile fields a stored tile carries; Save creates it as a tile.
				if (this.draft !== null) {
					return this.addDraftPlacement({
						widgetId: 'tile',
						...draftTileFields(tileData),
						gridX: placement.x,
						gridY: placement.y,
						gridWidth: placement.w,
						gridHeight: placement.h,
					}, placement.pushed)
				}
				const response = await api.addTile(this.activeDashboard.id, {
					...tileData,
					gridX: placement.x,
					gridY: placement.y,
					gridWidth: placement.w,
					gridHeight: placement.h,
				})
				this.widgetPlacements.push(response.data)

				if (placement.pushed.length > 0) {
					await this.applyPushedPlacements(placement.pushed)
				}

				// Return the created placement so callers (e.g. TileEditor's
				// health-ping block) can apply a follow-up updateWidgetPlacement
				// patch for fields `addTile` does not itself accept — mirrors
				// the `addWidget` create-then-patch pattern above.
				return response.data
			} catch (error) {
				this.handleWidgetQuotaError(error)
				logger.error('Failed to add tile:', error)
				throw error
			}
		},

		/**
		 * dashboard-quota-limits REQ-QUOTA-006 (race case): when a
		 * placement-creation call returns the structured 409
		 * `quota_exceeded` body, surface a localised message. The active
		 * dashboard's widget count is authoritative for the disabled-button
		 * getter, so no envelope refetch is needed here.
		 *
		 * @param {object} error the axios error (may be undefined)
		 * @return {void}
		 * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-006-quota-status-surfacing-in-ui
		 */
		handleWidgetQuotaError(error) {
			if (
				error?.response?.status === 409
				&& error?.response?.data?.error === 'quota_exceeded'
				&& error?.response?.data?.quota === 'widgets'
			) {
				const limit = error.response.data.limit
				showError(
					t(
						'launchpad',
						'You have reached the limit of {limit} widgets on this dashboard',
						{ limit },
					),
				)
			}
		},

		/**
		 * Apply the push-down side effects produced by `placeNewWidget`
		 * via the existing batch-update path (REQ-GRID-005). The new
		 * `gridY` values are merged into the in-memory placement list and
		 * the whole list is sent in a single round-trip — preserves the
		 * REQ-WDG-008 single-batch contract (no per-widget PUT storm) and
		 * inherits the 300 ms debounce already in `updatePlacements`.
		 *
		 * @param {Array<{id: (number|string), gridY: number}>} pushed list of push-down side effects from `placeNewWidget`
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async applyPushedPlacements(pushed) {
			if (!pushed || pushed.length === 0) {
				return
			}
			const pushIndex = new Map(pushed.map((p) => [String(p.id), p.gridY]))
			const merged = this.widgetPlacements.map((p) => {
				const newY = pushIndex.get(String(p.id))
				if (newY !== undefined) {
					return { ...p, gridY: newY }
				}
				return p
			})
			await this.updatePlacements(merged)
		},

		/**
		 * Delete a placement from the active dashboard.
		 *
		 * @param {number|string} placementId Id of the placement to remove.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async removeWidgetFromDashboard(placementId) {
			const placement = this.getPlacementById(placementId)
			if (placement?.isCompulsory && this.permissionLevel !== 'full') {
				logger.warn('Cannot remove compulsory widget')
				return
			}

			// REQ-EDIT-001: in a draft the widget leaves the draft only.
			if (this.draft !== null) {
				this.widgetPlacements = this.widgetPlacements.filter((p) => p.id !== placementId)
				return
			}

			try {
				await api.removeWidget(placementId)
				this.widgetPlacements = this.widgetPlacements.filter(
					(p) => p.id !== placementId,
				)
			} catch (error) {
				logger.error('Failed to remove widget:', error)
			}
		},

		/**
		 * Promote a group-shared dashboard to the group's default
		 * (REQ-DASH-015). Optimistically flips `isDefault` to 1 on the
		 * target row and to 0 on every other row in the same group, then
		 * calls the backend. On 4xx/5xx the snapshot is restored so the
		 * UI never lies.
		 *
		 * @param {string} groupId The dashboard's group id.
		 * @param {string} uuid The target dashboard's uuid.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async setGroupDashboardDefault(groupId, uuid) {
			// Snapshot the affected rows so we can roll back on failure.
			const snapshot = this.dashboards
				.filter((d) => d.groupId === groupId && d.source !== 'user')
				.map((d) => ({ id: d.id, uuid: d.uuid, isDefault: d.isDefault }))

			// Optimistic update: target → 1, every other row in group → 0.
			this.dashboards = this.dashboards.map((d) => {
				if (d.groupId !== groupId || d.source === 'user') {
					return d
				}
				return { ...d, isDefault: d.uuid === uuid ? 1 : 0 }
			})

			try {
				await api.setGroupDashboardDefault(groupId, uuid)
			} catch (error) {
				// Roll back the snapshot — restore every flipped row.
				this.dashboards = this.dashboards.map((d) => {
					const prev = snapshot.find((s) => s.uuid === d.uuid)
					if (prev === undefined) {
						return d
					}
					return { ...d, isDefault: prev.isDefault }
				})
				logger.error('Failed to set group default dashboard:', error)
				throw error
			}
		},

		/**
		 * Fetch the nested dashboard tree (REQ-DASH-026).
		 *
		 * Stores the result on `state.dashboardTree`. Failures are
		 * logged to the console; callers fall back to the flat
		 * `dashboards` getter when the tree is empty.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async loadDashboardTree() {
			try {
				const response = await api.getDashboardTree()
				this.dashboardTree = Array.isArray(response.data)
					? response.data
					: []
			} catch (error) {
				logger.error('Failed to load dashboard tree:', error)
				this.dashboardTree = []
			}
		},

		/**
		 * Resolve a slug-chain path to a dashboard (REQ-DASH-027).
		 *
		 * Caches the result on `state.pathCache` keyed by the
		 * canonical path so repeated lookups during a session avoid
		 * the round-trip. Returns `null` on miss.
		 *
		 * @param {string} path The slash-joined slug chain.
		 * @return {Promise<object|null>} The dashboard payload or null.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async dashboardByPath(path) {
			const key = dashboardPathKey(path)
			if (key === '') {
				return null
			}

			if (this.pathCache[key] !== undefined) {
				return this.pathCache[key]
			}

			const payload = await resolveDashboardPath(key)
			this.pathCache[key] = payload
			return payload
		},

		/**
		 * REQ-ANLT-002 / REQ-ANLT-011 — record a dashboard view event.
		 *
		 * Fire-and-forget POST to `/api/dashboards/{uuid}/view-event`.
		 * The 204 response is intentionally silent in the UI; network
		 * errors are logged to the console but never surfaced as
		 * toasts because view-event tracking must never affect the
		 * primary read path. Per-uuid debounce (1s) lives in the
		 * caller (`Views.vue`) — the store action is idempotent on
		 * the server side via the same-day cache dedup.
		 *
		 * @param {string} uuid The dashboard UUID being viewed.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async recordViewEvent(uuid) {
			if (!uuid) {
				return
			}
			try {
				await api.recordDashboardViewEvent(uuid)
			} catch (error) {
				logger.warn('Failed to record dashboard view event:', error)
			}
		},

		/**
		 * Patch a single placement (content, chrome, geometry, …).
		 *
		 * @param {number|string} placementId Id of the placement to update.
		 * @param {object} updates Changed fields to write.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async updateWidgetPlacement(placementId, updates) {
			logger.debug(
				'[DashboardStore] updateWidgetPlacement called:',
				JSON.stringify({ placementId, updates }, null, 2),
			)
			// REQ-EDIT-001: in a draft a configured widget changes the draft only.
			if (this.draft !== null) {
				const at = this.widgetPlacements.findIndex((p) => p.id === placementId)
				if (at !== -1) {
					this.widgetPlacements.splice(at, 1, { ...this.widgetPlacements[at], ...updates })
				}
				return
			}
			try {
				const response = await api.updateWidgetPlacement(
					placementId,
					updates,
				)
				logger.debug(
					'[DashboardStore] API response:',
					JSON.stringify(response.data, null, 2),
				)

				const index = this.widgetPlacements.findIndex(
					(p) => p.id === placementId,
				)
				logger.debug('[DashboardStore] Found placement at index:', index)

				if (index !== -1) {
					// Use splice for reactive update.
					this.widgetPlacements.splice(index, 1, response.data)
					logger.debug(
						'[DashboardStore] Updated placement:',
						JSON.stringify(this.widgetPlacements[index], null, 2),
					)
				}
			} catch (error) {
				logger.error('Failed to update widget placement:', error)
				logger.error('Error details:', error.response?.data)
			}
		},

		/**
		 * Fetch and cache the reactions summary for a dashboard. REQ-RXN-003.
		 *
		 * @param {string} dashboardUuid The dashboard UUID.
		 * @return {Promise<object|null>} The summary, or null on error.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async fetchReactionsSummary(dashboardUuid) {
			if (!dashboardUuid) {
				return null
			}

			try {
				const response = await api.getDashboardReactions(dashboardUuid)
				this.reactionsSummary = {
					...this.reactionsSummary,
					[dashboardUuid]: response.data,
				}
				return response.data
			} catch (error) {
				logger.error('Failed to fetch reactions summary:', error)
				return null
			}
		},

		/**
		 * Add a reaction (idempotent — REQ-RXN-001). Updates the cached
		 * summary on success.
		 *
		 * @param {string} dashboardUuid The dashboard UUID.
		 * @param {string} emoji         The emoji to add.
		 * @return {Promise<object|null>} Updated summary, or null on error.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async addReaction(dashboardUuid, emoji) {
			if (!dashboardUuid || !emoji) {
				return null
			}

			try {
				const response = await api.addDashboardReaction(dashboardUuid, emoji)
				this.reactionsSummary = {
					...this.reactionsSummary,
					[dashboardUuid]: response.data,
				}
				return response.data
			} catch (error) {
				const message =
					error.response?.data?.error || t('launchpad', 'Operation failed')
				showError(message)
				return null
			}
		},

		/**
		 * Remove a reaction (idempotent — REQ-RXN-002). Optimistically
		 * refreshes the cached summary after the DELETE.
		 *
		 * @param {string} dashboardUuid The dashboard UUID.
		 * @param {string} emoji         The emoji to remove.
		 * @return {Promise<object|null>} Updated summary, or null on error.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async removeReaction(dashboardUuid, emoji) {
			if (!dashboardUuid || !emoji) {
				return null
			}

			try {
				await api.removeDashboardReaction(dashboardUuid, emoji)
				return await this.fetchReactionsSummary(dashboardUuid)
			} catch (error) {
				const message =
					error.response?.data?.error || t('launchpad', 'Operation failed')
				showError(message)
				return null
			}
		},

		/**
		 * Fetch the admin-managed metadata-field registry
		 * (REQ-MDFL-001). Non-admin callers receive HTTP 403; the
		 * action swallows the error so the caller does not need to
		 * branch on admin status.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async fetchMetadataFields() {
			try {
				const response = await api.getMetadataFields()
				const payload = response?.data
				if (Array.isArray(payload?.fields)) {
					this.metadataFields = payload.fields
				} else if (Array.isArray(payload)) {
					this.metadataFields = payload
				} else {
					this.metadataFields = []
				}
			} catch (error) {
				if (error?.response?.status !== 403) {
					logger.error('Failed to load metadata fields:', error)
				}
				this.metadataFields = []
			}
		},

		/**
		 * Fetch the metadata key-value map for a single dashboard
		 * (REQ-MDFL-004). Caches the result on
		 * `state.metadataByDashboard[uuid]` so repeated reads in the
		 * same session avoid the round-trip.
		 *
		 * @param {string} uuid The dashboard UUID.
		 * @return {Promise<object>} The metadata map (always an object).
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async fetchDashboardMetadata(uuid) {
			if (!uuid) {
				return {}
			}

			try {
				const response = await api.getDashboardMetadata(uuid)
				return cacheMetadata(this, { uuid, map: metadataMapOf(response) })
			} catch (error) {
				if (![403, 404].includes(error?.response?.status)) {
					logger.error('Failed to load dashboard metadata:', error)
				}
				cacheMetadata(this, { uuid, map: {} })
				return {}
			}
		},

		/**
		 * Upsert metadata key-values for a dashboard (REQ-MDFL-005).
		 * On 400 (validation failure) the user-facing error message
		 * is shown via the standard `showError` helper.
		 *
		 * @param {string} uuid     The dashboard UUID.
		 * @param {object} metadata Flat key-value map. Omitted keys are
		 *                          NOT removed; only keys present in
		 *                          this object are upserted.
		 * @return {Promise<object|null>} The updated metadata map or
		 *                                null on failure.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async updateDashboardMetadata(uuid, metadata) {
			if (!uuid) {
				return null
			}

			try {
				const response = await api.updateDashboardMetadata(uuid, metadata)
				return cacheMetadata(this, { uuid, map: metadataMapOf(response) })
			} catch (error) {
				const message =
					error?.response?.data?.message
					|| t('launchpad', 'Failed to update dashboard metadata')
				logger.error('Failed to update dashboard metadata:', error)
				showError(message)
				return null
			}
		},
	},
})
