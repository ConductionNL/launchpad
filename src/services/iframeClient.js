/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Iframe-embed widget browser client (REQ-IFRAME-002).
 *
 * The allow-list is admin-controlled and enforced server-side, FAIL-CLOSED.
 * This client only calls LaunchPad's own validation endpoint so the config
 * form can surface a fast "this host isn't allowed" error before save — it
 * never talks to the embed target itself. Exported as a standalone module
 * (rather than inlined in the form) so unit tests can `vi.mock()` it.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Validate a candidate iframe config server-side before save
 * (REQ-IFRAME-002 "rejected at save time" — host allow-list, fail-closed).
 *
 * @param {object} config the candidate `{url, title, height, aspect, sandbox}` config.
 * @return {Promise<{valid: boolean, errors: string[]}>} the validation result.
 * @spec openspec/specs/iframe-embed-widget/spec.md
 */
export async function validateIframeUrl(config) {
	const url = generateUrl('/apps/launchpad/api/iframe/validate-url')
	try {
		const response = await axios.post(url, { config })
		return response?.data || { valid: true, errors: [] }
	} catch (e) {
		// A validation-endpoint failure must not silently mark the config
		// valid (that would defeat fail-closed) — surface a generic error.
		return { valid: false, errors: ['validation_unavailable'] }
	}
}

/**
 * Ask the server whether a URL may actually be framed (REQ-IFRAME-003
 * "graceful degradation"). The browser cannot tell an `X-Frame-Options:
 * DENY` / `frame-ancestors 'none'` refusal apart from a normal cross-origin
 * embed (both leave `contentDocument` null), so the widget checks server-side
 * before rendering the iframe and shows the fallback card up front when the
 * target refuses framing. Fails to "not framable" so a check outage never
 * leaves a blank frame masquerading as a live embed.
 *
 * @param {string} targetUrl the candidate embed URL.
 * @return {Promise<{framable: boolean, reason: string}>} whether the URL may be framed.
 * @spec openspec/specs/iframe-embed-widget/spec.md
 */
export async function checkIframeFramable(targetUrl) {
	const url = generateUrl('/apps/launchpad/api/iframe/framable')
	try {
		const response = await axios.post(url, { url: targetUrl })
		return response?.data || { framable: false, reason: 'no_response' }
	} catch (e) {
		return { framable: false, reason: 'check_unavailable' }
	}
}
