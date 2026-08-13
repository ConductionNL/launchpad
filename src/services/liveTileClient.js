/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Live-data tile browser client (REQ-LIVETILE-003/005).
 *
 * The browser NEVER talks to a live-tile's source directly — it only ever
 * calls LaunchPad's own endpoints, which resolve the source server-side and
 * keep any URL/headers/credentials out of the browser. Exported as a
 * standalone module (rather than inlined in the widget/form) so unit tests
 * can `vi.mock()` it and exercise loading/stale/error/unavailable states
 * without a live network call.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Fetch the cached (or freshly resolved) value for one placement.
 *
 * @param {number|string} placementId the widget placement id.
 * @return {Promise<{value: *, formatted: string|null, badge: {state: string, label: string}|null, fetchedAt: string|null, stale: boolean}>} the reading.
 * @throws {Error} when the request fails (network error, 403, 404, 5xx, …) — the
 *   caller (LiveTileWidget.vue) catches this and renders the error state.
 * @spec openspec/specs/live-data-tile-widget/spec.md
 */
export async function fetchLiveTileValue(placementId) {
	const url = generateUrl('/apps/launchpad/api/livetile/{placementId}', {
		placementId,
	})
	const response = await axios.get(url)
	return response?.data || {}
}

/**
 * Check whether the OpenConnector `dashboard-http-datasource` capability is
 * currently available (REQ-LIVETILE-005) — drives whether the config form
 * offers `connector` source mode.
 *
 * @return {Promise<boolean>} true when `connector` source mode may be offered.
 * @spec openspec/specs/live-data-tile-widget/spec.md
 */
export async function fetchConnectorAvailability() {
	const url = generateUrl('/apps/launchpad/api/livetile/connector/status')
	try {
		const response = await axios.get(url)
		return response?.data?.available === true
	} catch (e) {
		// Fail closed on the picker too — an unreachable status endpoint is
		// treated the same as "OpenConnector absent" (REQ-LIVETILE-005).
		return false
	}
}

/**
 * Validate a candidate source config server-side before save
 * (REQ-LIVETILE-002 "rejected at save time" — host allow-list, fail-closed).
 *
 * @param {object} config the candidate `{sourceMode, url|sourceId, valueExpr, refresh}` config.
 * @return {Promise<{valid: boolean, errors: string[]}>} the validation result.
 * @spec openspec/specs/live-data-tile-widget/spec.md
 */
export async function validateLiveTileSource(config) {
	const url = generateUrl('/apps/launchpad/api/livetile/validate-source')
	try {
		const response = await axios.post(url, { config })
		return response?.data || { valid: true, errors: [] }
	} catch (e) {
		// A validation-endpoint failure must not silently mark the config
		// valid (that would defeat fail-closed) — surface a generic error.
		return { valid: false, errors: ['validation_unavailable'] }
	}
}
