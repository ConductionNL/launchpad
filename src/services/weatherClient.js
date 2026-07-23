/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Weather-widget browser client (REQ-WEATHER-001).
 *
 * The browser NEVER talks to a weather provider directly — it only ever
 * calls LaunchPad's own `GET /api/weather/{placementId}` endpoint, which
 * resolves the provider server-side and keeps any API key out of the
 * browser (REQ-WEATHER-001 "key never exposed"). Exported as a standalone
 * module (rather than inlined in `WeatherWidget.vue`) so unit tests can
 * `vi.mock()` it and exercise the widget's loading/stale/error states
 * without a live external call.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Fetch the cached weather reading for one placement.
 *
 * @param {number|string} placementId the widget placement id.
 * @return {Promise<{location: string, tempValue: number, units: string, condition: string, conditionText: string, language: string, fetchedAt: string, stale: boolean}>} the reading.
 * @throws {Error} when the request fails (network error, 403, 5xx, …) — the
 *   caller (WeatherWidget.vue) catches this and renders the error state.
 */
export async function fetchWeatherReading(placementId) {
	const url = generateUrl('/apps/launchpad/api/weather/{placementId}', { placementId })
	const response = await axios.get(url)
	return response?.data || {}
}
