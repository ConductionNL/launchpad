/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * widgetBusDeclarations — whether a widget's `publishes` and `consumes`
 * fit the dashboard it is on (drill-down-cross-widget-filter REQ-CWF-008).
 *
 * A widget joins the filter bus only when its declarations make sense here:
 *   - every dimension it consumes is a level of one of the dashboard's
 *     hierarchies; otherwise it gets a warning (`consumes-unknown-dimension`)
 *     and stays out of the bus, so it never filters on a dimension nothing
 *     on this dashboard can publish;
 *   - every `fromField` it publishes from is a field of its data; otherwise
 *     it gets an error (`publishes-missing-field`) and stays out, so a click
 *     can never publish an empty value.
 * The findings are non-fatal: the widget still renders, unfiltered.
 */

/**
 * A declaration list as objects with a `dimension`; a bare string is one.
 *
 * @param {unknown} list The declared list.
 * @return {Array<object>}
 */
function entriesOf(list) {
	if (!Array.isArray(list)) {
		return []
	}
	return list
		.map((entry) => (typeof entry === 'string' ? { dimension: entry } : entry))
		.filter((entry) => entry && typeof entry.dimension === 'string' && entry.dimension !== '')
}

/**
 * Every dimension a level of the dashboard's hierarchies names.
 *
 * @param {Array<object>} hierarchies The hierarchy descriptors.
 * @return {Set<string>}
 */
function hierarchyDimensions(hierarchies) {
	const dimensions = new Set()
	for (const hierarchy of Array.isArray(hierarchies) ? hierarchies : []) {
		for (const level of Array.isArray(hierarchy?.levels) ? hierarchy.levels : []) {
			if (level && typeof level.dimension === 'string') {
				dimensions.add(level.dimension)
			}
		}
	}
	return dimensions
}

/**
 * Validate a widget's bus declarations against the dashboard.
 *
 * @param {object} options What to check.
 * @param {{publishes?: Array<object|string>, consumes?: Array<object|string>}} options.widget The widget's declarations.
 * @param {Array<object>} options.hierarchies The dashboard's hierarchy descriptors.
 * @param {object|null} [options.sampleRow] One row of the widget's data; without it `fromField` is not checked.
 * @return {{participates: boolean, findings: Array<{level: string, code: string, dimension: string, field?: string}>}}
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-008-widget-manifest-declares-publishesconsumes-dashboard-validates-compatibility
 */
export function validateBusDeclarations({ widget, hierarchies, sampleRow = null }) {
	const known = hierarchyDimensions(hierarchies)
	const findings = []

	for (const entry of entriesOf(widget?.consumes)) {
		if (!known.has(entry.dimension)) {
			findings.push({ level: 'warning', code: 'consumes-unknown-dimension', dimension: entry.dimension })
		}
	}

	const row = sampleRow && typeof sampleRow === 'object' ? sampleRow : null
	for (const entry of entriesOf(widget?.publishes)) {
		const field = typeof entry.fromField === 'string' && entry.fromField !== '' ? entry.fromField : entry.dimension
		if (row !== null && !Object.hasOwn(row, field)) {
			findings.push({ level: 'error', code: 'publishes-missing-field', dimension: entry.dimension, field })
		}
	}

	return { participates: findings.length === 0, findings }
}
