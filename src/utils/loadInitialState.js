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
	},
	admin: {
		allGroups: [],
		configuredGroups: [],
		widgets: [],
		allowUserDashboards: false,
		linkCreateFileExtensions: ['txt', 'md', 'docx', 'xlsx', 'csv', 'odt'],
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

/**
 * The first dashboard load the page carries, `{visible, active}` with the
 * payloads of `GET /api/dashboards/visible` and `GET /api/dashboard`. Handed
 * out once: it describes the page as it was served, so every later load
 * asks the API. Null when the page did not carry it, or once taken.
 *
 * @return {{visible: (object|Array), active: (object|null)}|null} The first load, or null.
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-3
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
