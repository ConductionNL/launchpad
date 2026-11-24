/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The date fields of a register source, for the "Date field for the period"
 * choice in a widget form (dashboard-period-and-download REQ-DPD-002). A few
 * records are sampled and a field counts as a date when every value it holds
 * reads as an ISO date (`YYYY-MM-DD`, with or without a time).
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/** How many records are sampled. */
const SAMPLE = 5

/** An ISO date, with or without a time. */
const ISO_DATE = /^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/

/** @type {Map<string, Promise<string[]>>} */
const cache = new Map()

/**
 * The fields that hold a date in every sampled record that has a value for them.
 *
 * @param {Array<object>} records The sampled records.
 * @return {string[]} The field names, sorted.
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */
export function dateFieldsOf(records) {
	const verdict = new Map()
	for (const record of Array.isArray(records) ? records : []) {
		if (!record || typeof record !== 'object') {
			continue
		}
		for (const [field, value] of Object.entries(record)) {
			if (field.startsWith('@') || value === null || value === '' || value === undefined) {
				continue
			}
			const isDate = typeof value === 'string' && ISO_DATE.test(value) && !Number.isNaN(Date.parse(value))
			verdict.set(field, (verdict.get(field) ?? true) && isDate)
		}
	}
	return [...verdict.entries()].filter(([, isDate]) => isDate).map(([field]) => field).sort()
}

/**
 * The date fields of a register and schema, from a sample of its records;
 * empty when either is unset or the records cannot be read. Cached per page.
 *
 * @param {string} register The register.
 * @param {string} schema The schema.
 * @return {Promise<string[]>} The date field names.
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */
export function fetchDateFields(register, schema) {
	if (!register || !schema) {
		return Promise.resolve([])
	}
	const key = `${register}/${schema}`
	if (!cache.has(key)) {
		const url = generateUrl('/apps/openregister/api/objects/{register}/{schema}', { register, schema })
		cache.set(key, axios.get(url, { params: { _limit: SAMPLE } })
			.then((response) => dateFieldsOf(response?.data?.results))
			.catch(() => {
				cache.delete(key)
				return []
			}))
	}
	return cache.get(key)
}
