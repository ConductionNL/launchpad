/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A person's activity as counts per type and per day, with the purpose the
 * administrator recorded (openspec/changes/dashboards-and-who-may-see-them
 * REQ-DWMS-005 and REQ-DWMS-006).
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const REPORT_URL = '/apps/launchpad/api/activity-report'

/**
 * The query the report and its CSV share: an empty user means the reader.
 *
 * @param {object} request the request
 * @param {string} request.user the person reported on, empty for yourself
 * @param {string} request.from first day, YYYY-MM-DD
 * @param {string} request.to last day, YYYY-MM-DD
 * @return {object} the query parameters
 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.2
 */
function queryOf({ user, from, to }) {
	const params = {}
	if (typeof user === 'string' && user.trim() !== '') {
		params.user = user.trim()
	}
	if (from) {
		params.from = from
	}
	if (to) {
		params.to = to
	}
	return params
}

/**
 * Load the report. A refusal is an answer, not an error: it comes back as
 * `{ refused: true, message, enabled }` so the screen can say why.
 *
 * @param {object} request the request, as for queryOf
 * @return {Promise<object>} the report, or the refusal
 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.2
 */
export async function loadActivityReport(request) {
	try {
		const { data } = await axios.get(generateUrl(REPORT_URL), { params: queryOf(request) })
		return {
			refused: false,
			subject: String(data?.subject ?? ''),
			self: data?.self === true,
			from: String(data?.from ?? ''),
			to: String(data?.to ?? ''),
			purpose: typeof data?.purpose === 'string' && data.purpose !== '' ? data.purpose : null,
			available: data?.available === true,
			counts: data?.counts && typeof data.counts === 'object' && !Array.isArray(data.counts) ? data.counts : {},
			days: data?.days && typeof data.days === 'object' && !Array.isArray(data.days) ? data.days : {},
			total: Number(data?.total) || 0,
		}
	} catch (error) {
		const status = error?.response?.status
		if (status === 400 || status === 403) {
			return {
				refused: true,
				status,
				message: String(error.response.data?.error ?? ''),
				enabled: error.response.data?.activityReportingEnabled === true,
			}
		}
		throw error
	}
}

/**
 * The CSV of the same report, for a download link.
 *
 * @param {object} request the request, as for queryOf
 * @return {string} the URL
 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.6
 */
export function activityReportCsvUrl(request) {
	const query = new URLSearchParams(queryOf(request)).toString()
	return generateUrl(REPORT_URL + '/export') + (query === '' ? '' : '?' + query)
}
