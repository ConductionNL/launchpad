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
		inflightConfigPromise = axios.get(`${baseUrl}/api/tile-analytics/config`)
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
 */
/** @spec openspec/specs/dashboard-view-analytics/spec.md */
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

			axios.post(`${baseUrl}/api/tile-click/${encodeURIComponent(placementId)}`, {})
				.catch((error) => {
					console.warn('Failed to record tile click:', error)
				})
		})
	}

	return { recordTileClick }
}

/**
 * Test-only reset of the module-level config cache.
 *
 * @return {void}
 */
export function __resetTileClickTrackingForTest() {
	cachedEnabled = null
	inflightConfigPromise = null
}
