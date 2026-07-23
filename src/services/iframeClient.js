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
