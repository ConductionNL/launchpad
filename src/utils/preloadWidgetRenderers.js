/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Start loading the renderers of a dashboard's widgets while the page is
 * still mounting, so a widget that comes into view finds its renderer
 * already there instead of asking for it then.
 *
 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
 */

import { getWidgetTypeEntry } from '../constants/widgetRegistry.js'

/**
 * Ask each widget type on the dashboard for its renderer, once per type.
 * Types without a lazily loaded renderer (tiles, Nextcloud widgets) are
 * skipped.
 *
 * @param {Array<object>} placements The dashboard's widget placements.
 * @param {(type: string) => object|null} [entryOf] The registry lookup.
 * @return {number} How many renderers were asked for.
 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
 */
export function preloadWidgetRenderers(placements, entryOf = getWidgetTypeEntry) {
	const types = new Set()
	for (const placement of Array.isArray(placements) ? placements : []) {
		if (typeof placement?.widgetId === 'string' && placement.widgetId !== '') {
			types.add(placement.widgetId)
		}
	}
	let asked = 0
	for (const type of types) {
		const load = entryOf(type)?.renderer?.__cnLazy?.load
		if (typeof load === 'function') {
			load()
			asked++
		}
	}
	return asked
}
