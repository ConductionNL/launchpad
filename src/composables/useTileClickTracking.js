/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useTileClickTracking — lightweight fire-and-forget hook for the
 * tile usage-analytics capability (REQ-TANLT-002, REQ-TANLT-003).
 *
 * `recordTileClick(placementId)` posts `/api/tile-click/{placementId}`
 * exactly once per call, asynchronously and non-blocking — it MUST
 * never throw, block, or interfere with the tile's own navigation.
 * Before posting, it checks `/api/tile-analytics/config` (module-level
 * cached, fetched at most once per page load) and suppresses the
 * record call entirely when tracking is not active for the current
 * user (analytics globally disabled or the user opted out) — the
 * SAME reused gates as dashboard view-event tracking, not a second
 * opt-out surface.
 *
 * A config-fetch failure fails OPEN (assumes tracking is active) so a
 * transient network error never silently disables analytics; the
 * server still enforces `analytics_enabled` / `analytics_optout`
 * authoritatively regardless of what the client believes.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { logger } from '../utils/logger.js'

const baseUrl = generateUrl('/apps/launchpad')

/**
 * Module-level cache so every `recordTileClick()` call across the
 * whole page shares one config fetch. `null` means "not yet fetched".
 *
 * @type {boolean|null}
 */
let cachedEnabled = null

/**
 * In-flight config fetch, shared so concurrent tile clicks before the
 * first response don't each trigger their own request.
 *
 * @type {Promise<boolean>|null}
 */
let inflightConfigPromise = null

/**
 * Resolve whether tile-click tracking is currently active, using the
 * module-level cache described above.
 *
 * @return {Promise<boolean>} Resolves `true` when the record call
 *                            should be sent.
 */
function isTrackingActive() {
	if (cachedEnabled !== null) {
		return Promise.resolve(cachedEnabled)
	}

	if (!inflightConfigPromise) {
		inflightConfigPromise = axios
			.get(`${baseUrl}/api/tile-analytics/config`)
			.then((response) => {
				cachedEnabled = response?.data?.enabled !== false
				return cachedEnabled
			})
			.catch(() => {
				// Fail open — see module docblock.
				cachedEnabled = true
				return cachedEnabled
			})
			.finally(() => {
				inflightConfigPromise = null
			})
	}

	return inflightConfigPromise
}

/**
 * Tile usage-analytics client hook.
 *
 * @return {{recordTileClick: (placementId: (string|number)) => void}}
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */
export function useTileClickTracking() {
	/**
	 * Fire a tile-click record call. Fire-and-forget: never awaited by
	 * the caller, never throws, never blocks the tile's own navigation.
	 *
	 * @param {string|number} placementId The widget-placement (tile) ID.
	 * @return {void}
	 */
	function recordTileClick(placementId) {
		if (!placementId && placementId !== 0) {
			return
		}

		isTrackingActive().then((active) => {
			if (active === false) {
				return
			}

			axios
				.post(
					`${baseUrl}/api/tile-click/${encodeURIComponent(placementId)}`,
					{},
				)
				.catch((error) => {
					logger.warn('Failed to record tile click:', error)
				})
		})
	}

	return { recordTileClick }
}

/**
 * Test-only reset of the module-level config cache.
 *
 * @spec exclude test-only harness hook — the `__` prefix and the name mark it as such, and `src/composables/__tests__/useTileClickTracking.spec.js` is its only importer anywhere in the repo; it implements no product behaviour, it only clears `cachedEnabled` / `inflightConfigPromise` so each test starts from a cold cache.
 * @return {void}
 */
export function __resetTileClickTrackingForTest() {
	cachedEnabled = null
	inflightConfigPromise = null
}

/**
 * Browser storage key of the viewer's own tile use (launcher-tile-sorting).
 *
 * @type {string}
 */
const TILE_USE_KEY = 'launchpad.tileUse'

/**
 * Read the viewer's own tile use: `{ [placementId]: {count, lastUsedAt} }`.
 * The counts live only in this browser and are never sent to the server
 * (REQ-TSO-002). Corrupt data reads as no use; blocked storage reads as null.
 *
 * @return {Object<string, {count: number, lastUsedAt: number}>|null} Use per placement id, or null when storage is unavailable.
 * @spec openspec/specs/container-widget/spec.md
 */
export function readLocalTileUse() {
	let raw
	try {
		raw = window.localStorage.getItem(TILE_USE_KEY)
	} catch {
		return null
	}
	try {
		const parsed = JSON.parse(raw || '{}')
		return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
			? parsed
			: {}
	} catch {
		return {}
	}
}

/**
 * Write the use map; a blocked storage is ignored.
 *
 * @param {object} use The use map.
 * @return {boolean} True when stored.
 */
function writeLocalTileUse(use) {
	try {
		window.localStorage.setItem(TILE_USE_KEY, JSON.stringify(use))
		return true
	} catch {
		return false
	}
}

/**
 * Count one use of a tile in this browser (REQ-TSO-002). Never throws.
 *
 * @param {string|number} placementId The tile's placement id.
 * @return {void}
 * @spec openspec/specs/container-widget/spec.md
 */
export function recordLocalTileUse(placementId) {
	if (!placementId && placementId !== 0) {
		return
	}
	const use = readLocalTileUse()
	if (use === null) {
		return
	}
	const key = String(placementId)
	const previous = use[key] || { count: 0, lastUsedAt: 0 }
	use[key] = { count: (Number(previous.count) || 0) + 1, lastUsedAt: Date.now() }
	writeLocalTileUse(use)
}

/**
 * Forget this browser's use of the given tiles (REQ-TSO-003).
 *
 * @param {Array<string|number>} placementIds The tiles to forget.
 * @return {void}
 * @spec openspec/specs/container-widget/spec.md
 */
export function forgetLocalTileUse(placementIds) {
	const use = readLocalTileUse()
	if (use === null) {
		return
	}
	placementIds.forEach((id) => {
		delete use[String(id)]
	})
	writeLocalTileUse(use)
}

/**
 * Test-only reset of the local tile use and the page-load shuffle.
 *
 * @spec exclude test-only harness hook; `src/components/Widgets/Renderers/__tests__/ContainerWidget.sort.spec.js` is its only importer. It clears the browser use map and the random ranks so each test starts cold.
 * @return {void}
 */
export function __resetTileUseForTest() {
	try {
		window.localStorage.removeItem(TILE_USE_KEY)
	} catch {
		// blocked storage: nothing to clear
	}
	resetRandomRanks()
}

/**
 * Random rank per placement id, assigned once per page load (REQ-TSO-004).
 *
 * @type {Map<string, number>}
 */
let randomRanks = new Map()

/**
 * The page-load random rank of a tile.
 *
 * @param {string} key The tile key.
 * @return {number} A rank in [0, 1).
 * @spec openspec/specs/container-widget/spec.md
 */
export function randomRankFor(key) {
	if (!randomRanks.has(key)) {
		randomRanks.set(key, Math.random())
	}
	return randomRanks.get(key)
}

/**
 * Drop the page-load random ranks.
 *
 * @return {void}
 */
function resetRandomRanks() {
	randomRanks = new Map()
}
