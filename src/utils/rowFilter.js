/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

/**
 * The rows of a widget that match a typed filter term (REQ-DWMS-008).
 *
 * Pure: it reads the rendered rows and returns a new array, so a filter can
 * never reach the placement, its saved search or anything stored. An empty
 * or blank term keeps every row. Matching is case-insensitive on the text
 * fields the caller names; a row matches when any of them contains the term.
 *
 * @param {Array<object>} rows the rows the widget renders.
 * @param {string} term the filter term the person typed.
 * @param {Array<string>} fields the row fields to match against.
 * @return {Array<object>} the matching rows, in their original order.
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-008-search-inside-a-widgets-results
 */
export function filterRows(rows, term, fields) {
	const list = Array.isArray(rows) ? rows : []
	const needle = String(term || '').trim().toLowerCase()
	if (needle === '') {
		return list.slice()
	}

	return list.filter((row) => fields.some((field) => {
		const value = row ? row[field] : undefined
		return value !== undefined && value !== null && String(value).toLowerCase().includes(needle)
	}))
}
