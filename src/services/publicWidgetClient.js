/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Token-scoped reads of the live widgets (news, weather, live data) on a
 * public link or a kiosk screen. The anonymous visitor only ever calls
 * LaunchPad's own per-placement endpoint behind the link's or playlist's
 * token; the server decides whether the placement is shared.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * A reader of one placement's `{widget, data}` through a token route.
 *
 * @param {string} route The route prefix, `s` (share) or `kiosk`.
 * @param {string} token The share or playlist token.
 * @param {object} headers The request headers.
 * @return {function((number|string)): Promise<{widget: string, data: object}>} The reader.
 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */
function tokenReader(route, token, headers) {
	const base = generateUrl('/apps/launchpad') + '/' + route + '/' + encodeURIComponent(token) + '/placements/'
	return async (placementId) => {
		const response = await axios.get(base + encodeURIComponent(String(placementId)), { headers })
		return response?.data || {}
	}
}

/**
 * The live widget reader of a public share link, passing the password the
 * visitor unlocked the link with.
 *
 * @param {string} token The share token.
 * @param {string|null} password The share password, when the link has one.
 * @return {function((number|string)): Promise<{widget: string, data: object}>} The reader.
 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */
export function shareWidgetReader(token, password = null) {
	const headers = password ? { 'X-Share-Password': password } : {}
	return tokenReader('s', token, headers)
}

/**
 * The live widget reader of a kiosk playlist.
 *
 * @param {string} token The playlist token.
 * @return {function((number|string)): Promise<{widget: string, data: object}>} The reader.
 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */
export function kioskWidgetReader(token) {
	return tokenReader('kiosk', token, {})
}
