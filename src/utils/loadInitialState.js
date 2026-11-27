/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Centralised JS reader for the per-page LaunchPad initial-state contract
 * (REQ-INIT-003). The matching PHP builder lives at
 * `lib/Service/InitialStateBuilder.php`. Adding, removing, or renaming a
 * key here is a deliberate spec change — bump
 * {@link INITIAL_STATE_SCHEMA_VERSION} in lockstep with the PHP constant
 * and update the per-page table below.
 *
 * This module is the only place in the frontend allowed to call
 * `loadState('launchpad', ...)`; a CI grep guard in `package.json`'s
 * `lint:initial-state` script enforces that.
 */

import { loadState } from '@nextcloud/initial-state'
import { logger } from './logger.js'

/**
 * Schema version compiled into the JS bundle. Compared against the
 * server-pushed `_schemaVersion`; mismatch logs a console warning so
 * deploy-skew between PHP and JS surfaces (REQ-INIT-002).
 */
export const INITIAL_STATE_SCHEMA_VERSION = 3

/**
 * Reserved key carrying the schema version on the wire.
 */
export const SCHEMA_VERSION_KEY = '_schemaVersion'

/**
 * Per-page key/default tables. MUST mirror REQ-INIT-002's Data Model
 * exactly. Defaults guarantee the reader never returns `undefined`.
 */
const PAGE_KEYS = {
	workspace: {
		widgets: [],
		layout: [],
		primaryGroup: 'default',
		primaryGroupName: '',
		isAdmin: false,
		activeDashboardId: '',
		dashboardSource: 'group',
		groupDashboards: [],
		userDashboards: [],
		allowUserDashboards: false,
		// REQ-RFP-010: list of widget IDs the caller is permitted to see.
		// `null` = no role-feature-permissions configured (legacy, unrestricted).
		allowedWidgets: null,
		// Canonical slug-chain path for the active dashboard. Empty when
		// no dashboard is active or the active one has no slug. The
		// frontend uses this on mount to bring `window.location.pathname`
		// in line with whichever dashboard the server actually rendered
		// (handles renamed parents / stale bookmarks via replaceState).
		// Optional key — older servers don't push it, so the default
		// keeps reads typed even before the deploy lands.
		deepLinkPath: '',
		// tile-quick-search REQ-QSEARCH-004: admin-configured no-match
		// fallback target — 'none' | 'unified-search' | a validated
		// `https` URL template containing `{query}`. Optional key (same
		// rationale as `deepLinkPath`): a server that hasn't deployed the
		// PHP side yet simply omits it, and 'none' is the safe default
		// (no navigation on no-match Enter).
		quicksearchFallbackTarget: 'none',
		// search-ai-prefix-shortcuts REQ-SPX-002: the admin's search
		// shortcuts `{prefix, name, urlTemplate}`. Optional key; an empty
		// list keeps every search box as it was.
		searchShortcuts: [],
		// platform-dashboard-theme-and-css REQ-THM-003: the administrator CSS
		// for all of LaunchPad, cleaned and scoped by the server. Optional key.
		customCss: '',
		// drill-down-cross-widget-filter REQ-CWF-010: whether the dashboard's
		// filter bus records filter_event rows. Optional key; off by default.
		filterTelemetryEnabled: false,
		// dashboard-view-analytics REQ-ANLT-011: whether the administrator
		// switched view analytics on. Optional key; `true` keeps an older
		// server's pages sending view-events as before.
		analyticsEnabled: true,
		// launcher-tile-launch-types: what the tile editor offers for program
		// and single sign-on tiles. Optional keys; empty lists offer nothing.
		tileAllowedSchemes: [],
		ssoLaunchTemplates: [],
		// launcher-tile-internal-address REQ-TIA-003: whether the server saw
		// this request come from an office network. Optional key: a server
		// without the setter leaves every tile on its main address.
		onOfficeNetwork: false,
		// engagement-communities REQ-COMM-004: whether Talk is enabled for the
		// viewer. Optional key; `false` makes the Communities widget explain
		// that Talk is needed instead of calling it.
		talkEnabled: false,
		// wiki-widget REQ-WIKI-004: which apps behind the integration widgets
		// are enabled for the viewer, by app id. Optional key; `{}` keeps every
		// integration widget out of the picker.
		integrationApps: {},
		// engagement-communities REQ-COMM-003: the Talk conversation tokens the
		// organisation pinned, in the order pinned. Optional key.
		pinnedCommunities: [],
		// clock-weather-widgets REQ-CLOCK-002: the viewer's Nextcloud timezone,
		// which a clock without its own zone shows. Optional key; '' follows
		// the device.
		userTimezone: '',
		// dashboard-first-load-from-initial-state: `{visible, active}`, the
		// payloads of GET /api/dashboards/visible and GET /api/dashboard the
		// page already computed. Optional key; `null` makes the store fetch.
		dashboardFirstLoad: null,
		// org-navigation-from-initial-state: `{position, tree, language}`, the
		// answers of GET /api/admin/org-navigation/position and
		// GET /api/admin/org-navigation the page already read. Optional key;
		// `null` makes the shell fetch.
		orgNavigation: null,
		// workspace-boot-reads-from-initial-state: `{tiles,
		// pendingAcknowledgements, defaultDashboard}`, the answers of
		// GET /api/tiles, GET /api/acknowledgements/pending and
		// GET /api/dashboards/default the page already read. Optional key,
		// each part optional; a missing part makes its reader fetch.
		workspaceBootReads: null,
		// manifest-cached-per-user: the runtime manifest GET /api/manifest
		// built for the viewer within the last minute. Optional key; `null`
		// makes the page fetch it.
		runtimeManifest: null,
		// sharing-policy-board-sync REQ-SHARE-015: `{mayShare, publicLinksAllowed}`
		// for the viewer. Optional key; `null` offers both (the server refuses).
		sharingPolicy: null,
		// footer-customization REQ-FTR-004 / REQ-FTR-006: the footer below the
		// grid, resolved for the active dashboard. Optional key; `null` renders
		// no footer.
		effectiveFooter: null,
		// footer-customization REQ-FTR-007: the viewer's Nextcloud language,
		// which picks the footer variant. Optional key.
		viewerLocale: 'en',
	},
	admin: {
		allGroups: [],
		configuredGroups: [],
		widgets: [],
		allowUserDashboards: false,
		linkCreateFileExtensions: ['txt', 'md', 'docx', 'xlsx', 'csv', 'odt'],
		// admin-page-in-nextcloud-settings REQ-ADM-034: the tabs the viewer
		// holds. Optional key; empty means every tab.
		adminTabs: [],
		// REQ-ADM-032: the running app version for the version card. Optional key.
		version: '',
	},
	// sharing-public-start-page D3 (version 3): the public link the start
	// page at /apps/launchpad/start shows. Absent on /s/{token}, where the
	// token is the URL's last segment.
	'public-start': {
		publicStartToken: '',
	},
}

/**
 * Read every initial-state key declared for the given page, fill defaults
 * for any missing keys, and validate the schema version stamp.
 *
 * @param {('workspace'|'admin'|'public-start')} page Destination page identifier.
 * @return {object} Typed snapshot — never carries `undefined` values.
 * @throws {Error} When `page` is not a known page identifier.
 * @spec openspec/specs/initial-state-contract/spec.md
 */
export function loadInitialState(page) {
	const defaults = PAGE_KEYS[page]
	if (!defaults) {
		throw new Error(
			`loadInitialState: unknown page "${page}" — known: ${Object.keys(PAGE_KEYS).join(', ')}`,
		)
	}

	const state = {}
	for (const [key, fallback] of Object.entries(defaults)) {
		state[key] = readKey(key, fallback)
	}

	const serverVersion = readKey(SCHEMA_VERSION_KEY, null)
	if (serverVersion !== null && serverVersion !== INITIAL_STATE_SCHEMA_VERSION) {
		logger.warn(
			`LaunchPad initial-state schema mismatch: server v${serverVersion} vs client v${INITIAL_STATE_SCHEMA_VERSION} — refresh recommended`,
		)
	}

	return state
}

/**
 * Whether the page's first dashboard load has been handed out.
 */
let dashboardFirstLoadTaken = false
let orgNavigationTaken = false
const bootReadsTaken = new Set()
let runtimeManifestTaken = false

/**
 * What the Sharing tab may offer the viewer (REQ-SHARE-015): adding people or
 * groups, and creating a public link. A page without the key offers both; the
 * server refuses what the policy does not allow.
 *
 * @return {{mayShare: boolean, publicLinksAllowed: boolean}} The policy.
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
 */
export function readSharingPolicy() {
	const policy = readKey('sharingPolicy', null)
	return {
		mayShare: policy?.mayShare !== false,
		publicLinksAllowed: policy?.publicLinksAllowed !== false,
	}
}

/**
 * The first dashboard load the page carries, `{visible, active}` with the
 * payloads of `GET /api/dashboards/visible` and `GET /api/dashboard`. Handed
 * out once: it describes the page as it was served, so every later load
 * asks the API. Null when the page did not carry it, or once taken.
 *
 * @return {{visible: (object|Array), active: (object|null)}|null} The first load, or null.
 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-3
 */
export function takeDashboardFirstLoad() {
	if (dashboardFirstLoadTaken) {
		return null
	}
	dashboardFirstLoadTaken = true
	const firstLoad = readKey('dashboardFirstLoad', null)
	if (firstLoad === null || typeof firstLoad !== 'object' || !('visible' in firstLoad)) {
		return null
	}
	return { visible: firstLoad.visible, active: firstLoad.active ?? null }
}

/**
 * The org navigation the page carries, `{position, tree, language}`. Handed
 * out once, like {@link takeDashboardFirstLoad}. Null when the page did not
 * carry it, when it is malformed, or once taken.
 *
 * @return {{position: string, tree: Array, language: string}|null} The first load, or null.
 * @spec openspec/changes/archive/2026-11-25-org-navigation-from-initial-state/tasks.md#task-3
 */
export function takeOrgNavigation() {
	if (orgNavigationTaken) {
		return null
	}
	orgNavigationTaken = true
	const firstLoad = readKey('orgNavigation', null)
	if (firstLoad === null || typeof firstLoad !== 'object'
		|| typeof firstLoad.position !== 'string' || !Array.isArray(firstLoad.tree)) {
		return null
	}
	return { position: firstLoad.position, tree: firstLoad.tree, language: String(firstLoad.language || 'nl') }
}

/**
 * One part of what the workspace view reads while it boots, as the page
 * carries it: `tiles`, `pendingAcknowledgements` or `defaultDashboard`.
 * Handed out once per part, like {@link takeDashboardFirstLoad}; every later
 * read asks the API. Undefined when the page did not carry the part, or once
 * taken.
 *
 * @param {string} part The part.
 * @return {object|Array|undefined} The part's payload, or undefined.
 * @spec openspec/changes/archive/2026-11-25-workspace-boot-reads-from-initial-state/tasks.md#task-3
 */
export function takeBootRead(part) {
	if (bootReadsTaken.has(part)) {
		return undefined
	}
	bootReadsTaken.add(part)
	const reads = readKey('workspaceBootReads', null)
	if (reads === null || typeof reads !== 'object' || !Object.hasOwn(reads, part)) {
		return undefined
	}
	return reads[part]
}

/**
 * The API reads the workspace page answered while it rendered
 * (`workspaceApiReads`: `[{path, params, data}]`), or an empty list when the
 * page carries none or the key is malformed.
 *
 * @return {Array<object>} The reads.
 * @spec openspec/changes/archive/2026-11-27-workspace-api-reads-from-initial-state/tasks.md#task-3
 */
export function readWorkspaceApiReads() {
	const reads = readKey('workspaceApiReads', [])
	return Array.isArray(reads) ? reads : []
}

/**
 * The runtime manifest the page carries, handed out once. Null when the page
 * did not carry one (none was kept), when it is not a manifest, or once taken.
 *
 * @return {object|null} The manifest, or null.
 * @spec openspec/changes/archive/2026-11-25-manifest-cached-per-user/tasks.md#task-3
 */
export function takeRuntimeManifest() {
	if (runtimeManifestTaken) {
		return null
	}
	runtimeManifestTaken = true
	const manifest = readKey('runtimeManifest', null)
	if (manifest === null || typeof manifest !== 'object' || !manifest.$schema) {
		return null
	}
	return manifest
}

/**
 * Whether the server saw this request come from inside the office networks.
 *
 * @return {boolean} True only when the server said so.
 * @spec openspec/changes/archive/2026-11-19-launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
 */
export function isOnOfficeNetwork() {
	return readKey('onOfficeNetwork', false) === true
}

/**
 * The Nextcloud dashboard widgets the page carries for the card library,
 * already narrowed to the user's role (REQ-RFP-010). Empty when the server
 * could not list them without loading them, or on a page without the key.
 *
 * @return {object[]} The widget descriptors.
 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-010-initial-state-widget-allowlist
 */
export function initialWidgetCatalogue() {
	const widgets = readKey('widgets', [])
	return Array.isArray(widgets) ? widgets : []
}

/**
 * Wrap loadState so a missing key (the helper throws) silently degrades
 * to the documented default. The reader contract guarantees no
 * `undefined` reads — the JS bundle never crashes when PHP omits a key.
 *
 * @param {string} key The initial-state key.
 * @param {T} fallback Default to use when the key is missing.
 * @return {T} The pushed value, or the fallback.
 * @template T
 */
function readKey(key, fallback) {
	// The app is installed under its canonical id 'launchpad', so PHP provides
	// initial state under that namespace. loadState() returns the fallback when
	// the element is absent; the try/catch is a belt-and-suspenders guard so the
	// bundle never crashes if the contract is violated.
	try {
		return loadState('launchpad', key, fallback)
	} catch {
		return fallback
	}
}
