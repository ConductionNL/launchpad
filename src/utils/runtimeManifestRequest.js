/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

let pending = null

/**
 * The page's one `GET /apps/launchpad/api/manifest` request. Every caller on
 * the page gets the same promise, so the manifest is fetched once per load.
 *
 * @return {Promise<object>} The axios response.
 * @spec openspec/changes/launchpad-manifest-tier-3/specs/manifest-routing/spec.md#requirement-req-route-004-the-shared-chrome-renders-with-the-workspace-inside-it
 */
export function runtimeManifestRequest() {
	if (pending === null) {
		pending = axios.get(generateUrl('/apps/launchpad/api/manifest'))
	}
	return pending
}

/**
 * Forget the pending request (tests).
 *
 * @return {void}
 */
export function resetRuntimeManifestRequest() {
	pending = null
}
