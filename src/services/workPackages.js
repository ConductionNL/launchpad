/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The open OpenProject work packages of the work packages widget, from
 * launchpad's `/api/work-packages`, which asks the official OpenProject
 * integration with the person's own token.
 *
 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Read the open work packages.
 *
 * @param {{scope: string, project: string, limit: number}} query What to list.
 * @return {Promise<{state: string, workPackages: Array<object>, connectUrl?: string}>}
 *   `state` is `ok`, `not-installed`, `not-connected` or `error`.
 */
export async function loadWorkPackages({ scope, project, limit }) {
	try {
		const { data } = await axios.get(generateUrl('/apps/launchpad/api/work-packages'), {
			params: { scope, project, limit },
		})
		return {
			state: typeof data?.state === 'string' ? data.state : 'error',
			workPackages: Array.isArray(data?.workPackages) ? data.workPackages : [],
			connectUrl: typeof data?.connectUrl === 'string' ? generateUrl(data.connectUrl) : '',
		}
	} catch {
		return { state: 'error', workPackages: [], connectUrl: '' }
	}
}
