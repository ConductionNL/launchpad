/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Activity reporting and colleague activity
 * (dashboards-and-who-may-see-them REQ-DWMS-005..007).
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Read the activity reporting switch and its purpose (administrators).
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 * @return {Promise<{enabled: boolean, purpose: string}>} The policy.
 */
export async function getActivityPolicy() {
	const { data } = await axios.get(
		generateUrl('/apps/launchpad/api/admin/activity-reporting'),
	)
	return data
}

/**
 * Turn activity reporting on with a purpose, or off.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 * @param {boolean} enabled Whether to turn it on.
 * @param {string} purpose Why.
 * @return {Promise<{enabled: boolean, purpose: string}>} The stored policy.
 */
export async function saveActivityPolicy(enabled, purpose) {
	const { data } = await axios.put(
		generateUrl('/apps/launchpad/api/admin/activity-reporting'),
		{ enabled, purpose },
	)
	return data
}

/**
 * One person's activity for a period. An empty user id reads your own.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 * @param {{userId?: string, from: string, until: string}} query The period and person.
 * @return {Promise<object>} The report.
 */
export async function getActivityReport(query) {
	const { data } = await axios.get(
		generateUrl('/apps/launchpad/api/activity-report'),
		{ params: query },
	)
	return data
}

/**
 * The URL of the CSV export for the same query.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 * @param {{userId?: string, from: string, until: string}} query The period and person.
 * @return {string} The download URL.
 */
export function activityExportUrl(query) {
	const params = new URLSearchParams()
	for (const [key, value] of Object.entries(query)) {
		if (value) {
			params.set(key, value)
		}
	}
	return (
		generateUrl('/apps/launchpad/api/activity-report/export')
		+ '?'
		+ params.toString()
	)
}

/**
 * Recent colleague activity the caller may see.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 * @param {number} limit How many entries at most.
 * @return {Promise<{items: Array, total: number, available: boolean}>} The feed.
 */
export async function getColleagueActivity(limit) {
	const { data } = await axios.get(
		generateUrl('/apps/launchpad/api/colleague-activity'),
		{ params: { limit } },
	)
	return data
}
