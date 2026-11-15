/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useDashboardFilterSession — one filter bus per open dashboard
 * (drill-down-cross-widget-filter, REQ-CWF-003, -006, -007).
 *
 * The dashboard view opens a session when a dashboard becomes active and
 * stops it when another one does: filters belong to a dashboard, so a switch
 * starts with an empty bus. Only the first session of a page load reads the
 * `f` parameter, because that is the one a bookmarked link points at; a later
 * dashboard switch pushes a fresh URL without it.
 *
 * The session's `clauses` is the bus's own reactive list, which the view
 * hands to the active-filters panel; `labels` names each hierarchy level for
 * the chips.
 */

import { useFilterBus } from './useFilterBus.js'
import { useFilterUrlSync } from './useFilterUrlSync.js'

/**
 * The hierarchy descriptors a dashboard declares, or none.
 *
 * @param {object|null} dashboard The dashboard.
 * @return {Array<object>}
 */
export function hierarchiesOf(dashboard) {
	const declared = dashboard?.hierarchies
	return Array.isArray(declared) ? declared.filter((entry) => entry && Array.isArray(entry.levels)) : []
}

/**
 * A display label per hierarchy level dimension.
 *
 * @param {Array<object>} hierarchies The hierarchy descriptors.
 * @return {{[key: string]: string}}
 */
export function levelLabels(hierarchies) {
	const labels = {}
	for (const hierarchy of hierarchies) {
		for (const level of hierarchy.levels) {
			if (level && typeof level.dimension === 'string' && typeof level.label === 'string' && level.label !== '') {
				labels[level.dimension] = level.label
			}
		}
	}
	return labels
}

/**
 * Open the filter session of a dashboard.
 *
 * @param {object} options The session.
 * @param {object|null} options.dashboard The active dashboard.
 * @param {boolean} options.readUrl Whether the URL's `f` parameter seeds the bus (the first dashboard of a page load).
 * @param {{href: string}} [options.location] The location (window.location).
 * @param {{replaceState: (state: unknown, title: string, url: string) => void, state: unknown}} [options.history] The history (window.history).
 * @return {{bus: object, clauses: import('vue').ShallowRef<Array<object>>, labels: {[key: string]: string}, remove: (dimension: string) => boolean, reset: () => boolean, stop: () => void}}
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-003-url-encoding-makes-filtered-views-bookmarkable
 */
export function useDashboardFilterSession({ dashboard, readUrl, location = window.location, history = window.history }) {
	const hierarchies = hierarchiesOf(dashboard)
	const bus = useFilterBus({ hierarchies })
	const sync = useFilterUrlSync({ bus, location, history, hydrate: readUrl })

	return {
		bus,
		clauses: bus.clauses,
		labels: levelLabels(hierarchies),
		remove: (dimension) => bus.remove(dimension),
		reset: () => bus.reset(),
		stop: () => sync.stop(),
	}
}
