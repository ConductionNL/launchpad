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
 * The first of some values that is a non-empty string, as a string.
 *
 * @param {...unknown} values The candidates.
 * @return {string} The value, '' for none.
 */
function firstText(...values) {
	const found = values.find((value) => typeof value === 'string' && value !== '')
	return found === undefined ? '' : found
}

/**
 * A case's status: its name and pill variant, from the extended status
 * object or the case's own public label.
 *
 * @param {object} record The case.
 * @return {{key: string, label: string, variant: string}}
 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-counts-the-open-cases-per-status-req-cases-002
 */
export function caseStatus(record) {
	const status = record?.status
	if (status && typeof status === 'object') {
		const label = firstText(status.name, status.publicLabel)
		return {
			key: firstText(status.id, status.uuid, status['@self']?.id, label),
			label,
			variant: COLOUR_VARIANTS[status.colour] || 'default',
		}
	}
	const label = firstText(record?.statusPublicLabel)
	return { key: firstText(status, label), label, variant: 'default' }
}

/**
 * One case as the widget shows it.
 *
 * @param {object} record The case.
 * @return {{id: string, title: string, identifier: string, deadline: string, status: object}}
 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-lists-the-viewers-open-cases-deadline-first-req-cases-001
 */
export function caseItem(record) {
	return {
		id: firstText(record.id, record.uuid, record['@self']?.id),
		title: firstText(record.title, record.identifier),
		identifier: firstText(record.identifier),
		deadline: firstText(record.deadline, record.statutoryDeadline),
		status: caseStatus(record),
	}
}

/**
 * The counts per status over cases.
 *
 * @param {Array<object>} cases The cases, as {@link caseItem} gives them.
 * @return {Array<{key: string, label: string, variant: string, count: number}>}
 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-counts-the-open-cases-per-status-req-cases-002
 */
export function countsPerStatus(cases) {
	const counts = new Map()
	for (const { status } of cases) {
		if (status.label !== '') {
			const entry = counts.get(status.key) || { key: status.key, label: status.label, variant: status.variant, count: 0 }
			entry.count++
			counts.set(status.key, entry)
		}
	}
	return [...counts.values()]
}

/**
 * Read the open cases assigned to a person, deadline first, with the counts
 * per status over them.
 *
 * @param {{register: string, schema: string, userId: string}} query Where to read and for whom.
 * @return {Promise<{state: string, cases: Array<object>, counts: Array<object>, total: number}>}
 *   `state` is `ok` or `error`.
 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-lists-the-viewers-open-cases-deadline-first-req-cases-001
 */
export async function loadAssignedCases({ register, schema, userId }) {
	try {
		const url = generateUrl('/apps/openregister/api/objects/{register}/{schema}', { register, schema })
		const { data } = await axios.get(url, {
			params: { assignee: userId, '_order[deadline]': 'asc', '_extend[]': 'status', _limit: READ_LIMIT },
		})
		const records = Array.isArray(data?.results) ? data.results : []
		const cases = records
			.filter((record) => record && record.isFinalStatus !== true)
			.map(caseItem)
			.filter((entry) => entry.id !== '' && entry.title !== '')
		return { state: 'ok', cases, counts: countsPerStatus(cases), total: cases.length }
	} catch {
		return { state: 'error', cases: [], counts: [], total: 0 }
	}
}
