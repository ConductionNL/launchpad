/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Status reports: the product prefills the figures, the rating and a
 * summary; a person may rewrite the rating and the summary before keeping it
 * (openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them REQ-DWMS-004).
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const SUBJECT_KEYS = ['register', 'schema', 'title', 'deadline', 'statusProperty', 'dueProperty']

/**
 * The body of work as the endpoints take it, from a widget's content.
 * `closedValues` may be stored as a list or as a comma-separated string.
 *
 * @param {object} content the widget content
 * @return {object} the subject
 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-3.3
 */
export function subjectOf(content) {
	const subject = {}
	for (const key of SUBJECT_KEYS) {
		const value = content?.[key]
		if (typeof value === 'string' && value.trim() !== '') {
			subject[key] = value.trim()
		}
	}
	const raw = content?.closedValues
	const closed = (Array.isArray(raw) ? raw : String(raw ?? '').split(','))
		.map((value) => String(value).trim())
		.filter((value) => value !== '')
	if (closed.length > 0) {
		subject.closedValues = closed
	}
	return subject
}

/**
 * Turn a refused request into an answer the widget can say in words.
 *
 * @param {Error} error the axios error
 * @return {{refused: true, status: number}} the refusal
 * @throws {Error} anything that is not an HTTP refusal
 */
function refusalOf(error) {
	const status = error?.response?.status
	if (typeof status === 'number' && status >= 400 && status < 600) {
		return { refused: true, status }
	}
	throw error
}

/**
 * Ask the product to prefill a report on the body of work.
 *
 * @param {object} subject the body of work, from subjectOf
 * @return {Promise<object>} the draft, or a refusal
 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-3.3
 */
export async function draftStatusReport(subject) {
	try {
		const { data } = await axios.post(generateUrl('/apps/launchpad/api/status-reports/draft'), subject)
		return { refused: false, report: data }
	} catch (error) {
		return refusalOf(error)
	}
}

/**
 * Keep the report with the person's rating and summary. The server computes
 * the figures again; a person cannot write them.
 *
 * @param {object} subject the body of work, from subjectOf
 * @param {string} health the rating
 * @param {string} summary the summary
 * @return {Promise<object>} the kept report, or a refusal
 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-3.3
 */
export async function keepStatusReport(subject, health, summary) {
	try {
		const { data } = await axios.post(generateUrl('/apps/launchpad/api/status-reports'), { ...subject, health, summary })
		return { refused: false, report: data }
	} catch (error) {
		return refusalOf(error)
	}
}
