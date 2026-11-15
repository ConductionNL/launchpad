/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Recent colleague activity, as the server filtered it for the reader
 * (openspec/changes/dashboards-and-who-may-see-them REQ-DWMS-007).
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Load what other people did recently that the signed-in user may see.
 *
 * @param {object} options the request
 * @param {number} options.limit how many entries to list
 * @param {number} options.days how many days back
 * @return {Promise<{available: boolean, days: number, entries: Array<object>, total: number, counts: object, actors: number}>}
 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
 */
export async function loadColleagueActivity({ limit, days }) {
	const { data } = await axios.get(generateUrl('/apps/launchpad/api/colleague-activity'), {
		params: { limit, days },
	})
	return {
		available: data?.available === true,
		days: Number(data?.days) || days,
		entries: Array.isArray(data?.entries) ? data.entries : [],
		total: Number(data?.total) || 0,
		counts: data?.counts && typeof data.counts === 'object' ? data.counts : {},
		actors: Number(data?.actors) || 0,
	}
}
