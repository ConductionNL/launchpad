/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The open cases assigned to the viewer, read from an OpenRegister register
 * (dossiq's `dossiq` register and `case` schema by default) with the viewer's
 * own session, so OpenRegister decides which cases they may see.
 *
 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/** How many open cases one read takes in, for the counts per status. */
export const READ_LIMIT = 100

/** The status colours of a case type, as status pill variants. */
const COLOUR_VARIANTS = {
	blue: 'primary',
	'blue-light': 'info',
	green: 'success',
	'green-light': 'success',
	orange: 'warning',
	'orange-light': 'warning',
	red: 'error',
	'red-light': 'error',
	purple: 'purple',
	'purple-light': 'purple',
	teal: 'teal',
	'teal-light': 'teal',
	grey: 'default',
	'grey-light': 'default',
}

/**
 * A case's status: its name and pill variant, from the extended status
 * object or the case's own public label.
 *
 * @param {object} record The case.
 * @return {{key: string, label: string, variant: string}}
 */
export function caseStatus(record) {
	const status = record?.status
	if (status && typeof status === 'object') {
		const label = String(status.name || status.publicLabel || '')
		return {
			key: String(status.id || status.uuid || status['@self']?.id || label),
			label,
			variant: COLOUR_VARIANTS[status.colour] || 'default',
		}
	}
	const label = String(record?.statusPublicLabel || '')
	return { key: typeof status === 'string' && status !== '' ? status : label, label, variant: 'default' }
}

/**
 * Read the open cases assigned to a person, deadline first, with the counts
 * per status over them.
 *
 * @param {{register: string, schema: string, userId: string}} query Where to read and for whom.
 * @return {Promise<{state: string, cases: Array<object>, counts: Array<object>, total: number}>}
 *   `state` is `ok` or `error`.
 */
export async function loadAssignedCases({ register, schema, userId }) {
	try {
		const url = generateUrl('/apps/openregister/api/objects/{register}/{schema}', { register, schema })
		const { data } = await axios.get(url, {
			params: { assignee: userId, '_order[deadline]': 'asc', '_extend[]': 'status', _limit: READ_LIMIT },
		})
		const records = (Array.isArray(data?.results) ? data.results : []).filter((record) => record && record.isFinalStatus !== true)
		const counts = new Map()
		const cases = records.map((record) => {
			const status = caseStatus(record)
			if (status.label !== '') {
				const entry = counts.get(status.key) || { key: status.key, label: status.label, variant: status.variant, count: 0 }
				entry.count++
				counts.set(status.key, entry)
			}
			return {
				id: String(record.id || record.uuid || record['@self']?.id || ''),
				title: String(record.title || record.identifier || ''),
				identifier: String(record.identifier || ''),
				deadline: String(record.deadline || record.statutoryDeadline || ''),
				status,
			}
		}).filter((entry) => entry.id !== '' && entry.title !== '')
		return { state: 'ok', cases, counts: [...counts.values()], total: cases.length }
	} catch {
		return { state: 'error', cases: [], counts: [], total: 0 }
	}
}
