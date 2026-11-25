/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The accessible name of a grid item (grid-layout REQ-GRID-008,
 * REQ-GRID-KBD-001).
 *
 * CnDashboardGrid uses a custom `itemLabel` verbatim, without the edit-mode
 * coordinates it appends to its own fallback name, so this label carries both:
 * "Widget: {title}" always, and the position and size while the dashboard is
 * being edited.
 */

import { translate as t } from '@nextcloud/l10n'
import { resolveWidgetTitle } from './widgetTitle.js'

/**
 * Build the accessible name of a placement on the grid.
 *
 * @param {object} placement The layout item (a widget placement with gridX, gridY, gridWidth, gridHeight).
 * @param {object} options The label context.
 * @param {boolean} options.editing Whether the dashboard is in edit mode, where items are tab stops.
 * @param {number} options.columns The grid's column count.
 * @param {Array<object>} options.availableWidgets The Nextcloud Dashboard widget catalog.
 * @return {string} The accessible name.
 * @spec openspec/specs/grid-layout/spec.md#req-grid-008
 */
export function gridItemLabel(placement, { editing, columns, availableWidgets }) {
	const title = resolveWidgetTitle(placement, availableWidgets)
	if (!editing) {
		return t('launchpad', 'Widget: {title}', { title })
	}
	return t(
		'launchpad',
		'Widget: {title}, column {column} of {columns}, row {row}, {width} columns wide, {height} rows tall',
		{
			title,
			column: whole(placement?.gridX, 0) + 1,
			columns,
			row: whole(placement?.gridY, 0) + 1,
			width: whole(placement?.gridWidth, 1),
			height: whole(placement?.gridHeight, 1),
		},
	)
}

/**
 * A finite number, or the fallback.
 *
 * @param {number|undefined} value The value.
 * @param {number} fallback The fallback.
 * @return {number}
 */
function whole(value, fallback) {
	return Number.isFinite(value) ? value : fallback
}
