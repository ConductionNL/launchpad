/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Service health ping browser client (REQ-HPING-002/003).
 *
 * The browser NEVER pings a tile's health URL directly — it only ever calls
 * LaunchPad's own endpoints, which perform the allow-listed request
 * server-side and keep the URL/headers/response body out of the browser.
 * Exported as a standalone module (rather than inlined in the badge/form) so
 * unit tests can `vi.mock()` it and exercise loading/stale/error states
 * without a live network call.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Fetch the cached (or freshly resolved) health badge for one placement.
 *
 * @param {number|string} placementId the widget placement id.
 * @return {Promise<{state: string|null, checkedAt: string|null, latencyMs: number|null, stale: boolean}>} the badge.
 * @throws {Error} when the request fails (network error, 403, 404, 5xx, …) — the
 *   caller (HealthPingBadge.vue) catches this and hides the badge.
 * @spec openspec/specs/service-health-ping/spec.md
 */
export async function fetchHealthPingBadge(placementId) {
	const url = generateUrl('/apps/launchpad/api/health-ping/{placementId}', { placementId })
	const response = await axios.get(url)
	return response?.data || {}
}

/**
 * Validate a candidate health-ping config server-side before save
 * (REQ-HPING-001 "rejected at save time" — host allow-list, fail-closed).
 *
 * @param {object} config the candidate `{healthPingEnabled, healthUrl, expectedStatus, pingInterval}` config.
 * @return {Promise<{valid: boolean, errors: string[]}>} the validation result.
 * @spec openspec/specs/service-health-ping/spec.md
 */
export async function validateHealthPingConfig(config) {
	const url = generateUrl('/apps/launchpad/api/health-ping/validate')
	try {
		const response = await axios.post(url, { config })
		return response?.data || { valid: true, errors: [] }
	} catch (e) {
		// A validation-endpoint failure must not silently mark the config
		// valid (that would defeat fail-closed) — surface a generic error.
		return { valid: false, errors: ['validation_unavailable'] }
	}
}
