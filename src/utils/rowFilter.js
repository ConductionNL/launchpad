/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * In-widget search (dashboards-and-who-may-see-them REQ-DWMS-008).
 *
 * The filter works on what the widget has already rendered. It marks rows
 * that do not match with an attribute the stylesheet hides, and it never
 * reaches the placement, its content or its saved search: there is nothing
 * to write back because nothing but the DOM is touched. Reloading the page
 * renders the rows afresh, without the marker.
 */

/** Widget types that render rows, so the filter field makes sense on them. */
export const ROW_WIDGET_TYPES = [
	'table',
	'object-list',
	'people',
	'news',
	'files',
	'attention',
]

/** What counts as a row: table body rows, list items, and explicit rows. */
export const ROW_SELECTOR = 'tbody tr, li, [data-row]'

/** The attribute that hides a row that does not match. */
export const FILTERED_ATTRIBUTE = 'data-lp-filtered-out'

/**
 * Whether a placement renders rows.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 * @param {object|null} placement The widget placement.
 * @return {boolean} True for a row-rendering widget.
 */
export function isRowWidget(placement) {
	return ROW_WIDGET_TYPES.includes(placement?.widgetId)
}

/**
 * Mark the rows under `root` that do not hold `term`.
 *
 * Only the innermost rows count, so a list item that groups other items is
 * judged by its children rather than hidden or kept as a whole.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 * @param {Element|null} root The widget's rendered content.
 * @param {string} term What the person typed.
 * @return {{total: number, shown: number}} Rows found and rows left visible.
 */
export function applyRowFilter(root, term) {
	if (!root) {
		return { total: 0, shown: 0 }
	}

	const needle = String(term ?? '')
		.trim()
		.toLocaleLowerCase()
	const rows = [...root.querySelectorAll(ROW_SELECTOR)].filter(
		(row) => row.querySelector(ROW_SELECTOR) === null,
	)

	let shown = 0
	for (const row of rows) {
		const match =
			needle === '' || row.textContent.toLocaleLowerCase().includes(needle)
		if (match) {
			row.removeAttribute(FILTERED_ATTRIBUTE)
			shown++
		} else {
			row.setAttribute(FILTERED_ATTRIBUTE, '')
		}
	}

	// A parent row whose children are all hidden carries no marker of its
	// own, so it keeps its label. That is deliberate: hiding it would also
	// hide a match a later keystroke brings back.
	return { total: rows.length, shown }
}
