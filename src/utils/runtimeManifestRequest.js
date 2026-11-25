/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { takeRuntimeManifest } from './loadInitialState.js'

let pending = null

/**
 * The page's one `GET /apps/launchpad/api/manifest` request. Every caller on
 * the page gets the same promise, so the manifest is fetched once per load,
 * and not at all when the page carries the manifest kept for the viewer.
 *
 * @return {Promise<object>} The axios response, or one shaped like it.
 * @spec openspec/changes/launchpad-manifest-tier-3/specs/manifest-routing/spec.md#requirement-req-route-004-the-shared-chrome-renders-with-the-workspace-inside-it
 * @spec openspec/changes/manifest-cached-per-user/tasks.md#task-3
 */
export function runtimeManifestRequest() {
	if (pending === null) {
		const carried = takeRuntimeManifest()
		pending = carried !== null
			? Promise.resolve({ status: 200, data: carried })
			: axios.get(generateUrl('/apps/launchpad/api/manifest'))
	}
	return pending
}

/**
 * Forget the pending request (tests).
 *
 * @return {void}
 * @spec exclude test seam: resets the memoised request between test cases
 */
export function resetRuntimeManifestRequest() {
	pending = null
}
