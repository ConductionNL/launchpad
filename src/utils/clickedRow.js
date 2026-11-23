/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * clickedRow — the fields of the category a reader clicked on a chart, for
 * the filter bus (drill-down-cross-widget-filter REQ-CWF-004).
 *
 * A chart click names only the field the chart groups by. When that field is
 * published as a hierarchy level (a wijk), the bus infers the levels above it
 * (gemeente, provincie) from the clicked row, so the row is completed from
 * one record of the clicked category: each ancestor is read from the
 * record's field named like the ancestor's dimension. A record that cannot be
 * read leaves the row as it was, and the bus skips those levels with a
 * warning.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { ancestorDimensions } from '../composables/useFilterBus.js'

/**
 * Whether a value is missing: undefined, null or empty text.
 *
 * @param {unknown} value The value.
 * @return {boolean}
 */
function isBlank(value) {
	return value === undefined || value === null || value === ''
}

/**
 * The ancestor dimensions the clauses need that the row does not hold.
 *
 * @param {Array<object>} hierarchies The dashboard's hierarchy descriptors.
 * @param {Array<{dimension: string}>} clauses The clauses the click publishes.
 * @param {object} row The row so far.
 * @return {string[]}
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-004-drill-down-hierarchies-update-breadcrumb-and-descendant-filters
 */
export function missingAncestors(hierarchies, clauses, row) {
	const missing = new Set()
	for (const clause of clauses) {
		for (const dimension of ancestorDimensions(hierarchies, clause.dimension)) {
			if (isBlank(row[dimension])) {
				missing.add(dimension)
			}
		}
	}
	return [...missing]
}

/**
 * The first record of the clicked category in the chart's register and
 * schema, or null when it cannot be read.
 *
 * @param {{register: string, schema: string}} source The chart's data source.
 * @param {string} field The grouped field.
 * @param {unknown} value The clicked key.
 * @return {Promise<object|null>}
 */
async function firstRecord(source, field, value) {
	try {
		const { data } = await axios.get(
			generateUrl('/apps/openregister/api/objects/{register}/{schema}', { register: source.register, schema: source.schema }),
			{ params: { [field]: value, _limit: 1 } },
		)
		const results = Array.isArray(data?.results) ? data.results : []
		return results[0] && typeof results[0] === 'object' ? results[0] : null
	} catch {
		return null
	}
}

/**
 * The clicked row: the grouped field with the clicked key and, when the
 * published clauses are hierarchy levels, the ancestor fields of one record
 * of that category.
 *
 * @param {object} options The click.
 * @param {{field: string, value: unknown}} options.selection CnChartWidget's `select` payload.
 * @param {Array<{dimension: string}>} options.clauses The clauses the click publishes.
 * @param {Array<object>} options.hierarchies The dashboard's hierarchy descriptors.
 * @param {object|null} options.dataSource The chart's data source (`register`, `schema`).
 * @return {Promise<object>} The row.
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-004-drill-down-hierarchies-update-breadcrumb-and-descendant-filters
 */
export async function clickedRow({ selection, clauses, hierarchies, dataSource }) {
	const row = { [selection.field]: selection.value }
	const missing = missingAncestors(hierarchies, clauses, row)
	if (missing.length === 0 || !selection.field || !dataSource?.register || !dataSource?.schema) {
		return row
	}
	const record = await firstRecord(dataSource, selection.field, selection.value)
	for (const dimension of missing) {
		if (record && !isBlank(record[dimension])) {
			row[dimension] = record[dimension]
		}
	}
	return row
}
