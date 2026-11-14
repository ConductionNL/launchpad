/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useFilterBus — the dashboard's shared filter state
 * (drill-down-cross-widget-filter, REQ-CWF-001, -002, -004, -005, -006).
 *
 * THE CONTRACT
 * ------------
 * A widget that declares `publishes` turns a click into a clause
 * `{dimension, operator, value, source, sourceWidgetId, pinned}` and hands it
 * to `publish()`. The bus keeps AT MOST ONE clause per dimension and tells
 * every subscriber the new clause list after each change; the dashboard passes
 * the clauses a widget `consumes` to that widget, which re-renders.
 *
 * CONFLICTS (REQ-CWF-005): last write wins per dimension.
 *   - a pinned clause is never replaced; the publication is dropped with one
 *     console line, so a developer can see why the click did not stick;
 *   - widget click over widget click replaces it and asks for a toast
 *     (`audit.silent === false`), naming the old and the new value;
 *   - anything over a `urlBootstrap` or `parentScope` clause replaces it
 *     silently: those are an initial state and an inference, not a choice;
 *   - the same clause clicked again on the same widget toggles it off
 *     (REQ-CWF-001).
 *
 * HIERARCHIES (REQ-CWF-004). A dimension that is a level of a dashboard
 * hierarchy (e.g. provincieCode > gemeenteCode > wijkCode > buurtCode):
 *   - publishing it clears every DESCENDANT level's clause (drilling up);
 *   - its ANCESTOR levels are inferred as `parentScope` clauses from the
 *     clicked row, when the row carries the ancestor's field (drilling down).
 *     A missing ancestor value is skipped with a console warning;
 *   - a clause whose last explicit descendant is gone leaves with it.
 *
 * ACCESS (REQ-CWF-004). An optional `canAccess(clause)` refuses a clause for
 * a scope the user may not see; the bus is then left untouched and the caller
 * gets `reason: 'forbidden'` to explain the refusal next to the widget.
 */

import { shallowRef } from 'vue'
import { logger } from '../utils/logger.js'

/**
 * Sources whose clause gives way silently to a newer one for its dimension.
 *
 * @type {string[]}
 */
const TRANSIENT_SOURCES = ['urlBootstrap', 'parentScope']

/**
 * Whether two values are the same filter value (scalars, arrays, tuples).
 *
 * @param {unknown} left A value.
 * @param {unknown} right Another.
 * @return {boolean}
 */
function sameValue(left, right) {
	return JSON.stringify(left) === JSON.stringify(right)
}

/**
 * A clause with every field present.
 *
 * @param {object} clause The published clause.
 * @return {object}
 */
function normalise(clause) {
	const normalised = {
		dimension: String(clause.dimension),
		operator: clause.operator || 'eq',
		source: clause.source || 'userExplicit',
		sourceWidgetId: clause.sourceWidgetId || null,
		pinned: clause.pinned === true,
	}
	if (normalised.operator !== 'notNull') {
		normalised.value = clause.value
	}
	return normalised
}

/**
 * The hierarchy a dimension is a level of, with its position.
 *
 * @param {Array<object>} hierarchies The dashboard's hierarchy descriptors.
 * @param {string} dimension The dimension.
 * @return {{levels: Array<object>, index: number}|null}
 */
function levelOf(hierarchies, dimension) {
	for (const hierarchy of Array.isArray(hierarchies) ? hierarchies : []) {
		const levels = Array.isArray(hierarchy?.levels) ? hierarchy.levels : []
		const index = levels.findIndex((level) => level && level.dimension === dimension)
		if (index !== -1) {
			return { levels, index }
		}
	}
	return null
}

/**
 * Apply the hierarchy to a publication: the descendant dimensions to clear and
 * the ancestor clauses to infer from the clicked row.
 *
 * @param {object} clause The new clause.
 * @param {Array<object>} hierarchies The dashboard's hierarchy descriptors.
 * @param {object} row The clicked row's fields, where ancestor values are read.
 * @return {{descendants: string[], ancestors: Array<object>}}
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-004-drill-down-hierarchies-update-breadcrumb-and-descendant-filters
 */
export function resolveHierarchy(clause, hierarchies, row = {}) {
	const position = levelOf(hierarchies, clause.dimension)
	if (position === null) {
		return { descendants: [], ancestors: [] }
	}
	const descendants = position.levels.slice(position.index + 1).map((level) => level.dimension)
	const ancestors = []
	for (const level of position.levels.slice(0, position.index)) {
		const value = row && typeof row === 'object' ? row[level.dimension] : undefined
		if (value === undefined || value === null || value === '') {
			logger.warn(`[launchpad] no ${level.dimension} on the clicked row; the breadcrumb skips that level`)
			continue
		}
		ancestors.push({ dimension: level.dimension, operator: 'eq', value, source: 'parentScope', sourceWidgetId: null, pinned: false })
	}
	return { descendants, ancestors }
}

/**
 * The filter bus.
 *
 * @param {object} [options] Bus options.
 * @param {Array<object>} [options.hierarchies] The dashboard's hierarchy descriptors.
 * @param {(clause: object) => boolean} [options.canAccess] False refuses the clause.
 * @return {object} The bus: `clauses` (a read-only ref), `publish`, `remove`, `reset`, `hydrate`, `subscribe`, `clausesFor`, `breadcrumb`.
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-001-widget-click-publishes-to-the-filter-bus
 */
export function useFilterBus({ hierarchies = [], canAccess = () => true } = {}) {
	const clauses = shallowRef([])
	const subscribers = new Set()

	/**
	 * Replace the clause list and tell every subscriber.
	 *
	 * @param {Array<object>} next The new list.
	 * @return {void}
	 */
	function commit(next) {
		clauses.value = Object.freeze(next.slice())
		subscribers.forEach((subscriber) => subscriber(clauses.value))
	}

	/**
	 * Drop `parentScope` clauses that no longer have an explicit clause below them.
	 *
	 * @param {Array<object>} list The clause list.
	 * @return {Array<object>}
	 */
	function pruneOrphanedAncestors(list) {
		return list.filter((clause) => {
			if (clause.source !== 'parentScope') {
				return true
			}
			const position = levelOf(hierarchies, clause.dimension)
			if (position === null) {
				return true
			}
			const below = position.levels.slice(position.index + 1).map((level) => level.dimension)
			return list.some((other) => other.source !== 'parentScope' && below.includes(other.dimension))
		})
	}

	/**
	 * Decide what an incoming clause does to the existing one for its dimension.
	 *
	 * @param {object} incoming The new clause.
	 * @param {object|undefined} existing The clause it meets.
	 * @return {{action: string, audit: object|null}} `add`, `replace`, `drop` or `toggle`.
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-005-filter-conflict-resolution-is-last-write-wins-per-dimension
	 */
	function applyConflictResolution(incoming, existing) {
		if (!existing) {
			return { action: 'add', audit: null }
		}
		if (existing.pinned) {
			return { action: 'drop', audit: null }
		}
		if (
			incoming.source === 'widgetClick'
			&& existing.source === 'widgetClick'
			&& existing.sourceWidgetId === incoming.sourceWidgetId
			&& existing.operator === incoming.operator
			&& sameValue(existing.value, incoming.value)
		) {
			return { action: 'toggle', audit: null }
		}
		return {
			action: 'replace',
			audit: {
				dimension: incoming.dimension,
				from: existing.value,
				to: incoming.value,
				fromSource: existing.source,
				toSource: incoming.source,
				silent: TRANSIENT_SOURCES.includes(existing.source) || sameValue(existing.value, incoming.value),
			},
		}
	}

	/**
	 * Publish a clause.
	 *
	 * @param {object} clause The clause: `{dimension, operator, value, source, sourceWidgetId, pinned}`.
	 * @param {object} [context] `{row}`: the clicked row, for inferring ancestor levels.
	 * @return {{accepted: boolean, reason?: string, toggled?: boolean, audit?: object|null}}
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-001-widget-click-publishes-to-the-filter-bus
	 */
	function publish(clause, context = {}) {
		if (!clause || typeof clause.dimension !== 'string' || clause.dimension === '') {
			return { accepted: false, reason: 'invalid' }
		}
		const incoming = normalise(clause)
		if (canAccess(incoming) !== true) {
			return { accepted: false, reason: 'forbidden' }
		}

		const existing = clauses.value.find((entry) => entry.dimension === incoming.dimension)
		const decision = applyConflictResolution(incoming, existing)
		if (decision.action === 'drop') {
			logger.warn(`[launchpad] ${incoming.dimension} is pinned; the new filter was dropped`)
			return { accepted: false, reason: 'pinned' }
		}
		if (decision.action === 'toggle') {
			commit(pruneOrphanedAncestors(clauses.value.filter((entry) => entry !== existing)))
			return { accepted: true, toggled: true, audit: null }
		}

		const { descendants, ancestors } = resolveHierarchy(incoming, hierarchies, context.row)
		const pinnedAncestor = ancestors.find((ancestor) => clauses.value.some(
			(entry) => entry.dimension === ancestor.dimension && entry.pinned && !sameValue(entry.value, ancestor.value),
		))
		if (pinnedAncestor) {
			logger.warn(`[launchpad] ${pinnedAncestor.dimension} is pinned to another value; the new filter was dropped`)
			return { accepted: false, reason: 'pinned' }
		}

		const replaced = new Set([incoming.dimension, ...ancestors.map((ancestor) => ancestor.dimension)])
		const kept = clauses.value.filter((entry) => {
			if (replaced.has(entry.dimension)) {
				return entry.pinned
			}
			return entry.pinned || !descendants.includes(entry.dimension)
		})
		const inferred = ancestors.filter((ancestor) => !kept.some((entry) => entry.dimension === ancestor.dimension))
		commit(pruneOrphanedAncestors([...kept, ...inferred, incoming]))
		return { accepted: true, toggled: false, audit: decision.audit }
	}

	/**
	 * Remove the clause for one dimension, unless it is pinned.
	 *
	 * @param {string} dimension The dimension.
	 * @return {boolean} Whether anything was removed.
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-007-filter-stack-ui-shows-every-active-clause-with-its-source
	 */
	function remove(dimension) {
		const existing = clauses.value.find((entry) => entry.dimension === dimension)
		if (!existing || existing.pinned) {
			return false
		}
		commit(pruneOrphanedAncestors(clauses.value.filter((entry) => entry !== existing)))
		return true
	}

	/**
	 * Clear every non-pinned clause. A bus with nothing to clear is left
	 * untouched and tells nobody, so a double click makes no noise.
	 *
	 * @return {boolean} Whether anything was cleared.
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-006-reset-filters-clears-all-non-pinned-clauses
	 */
	function reset() {
		const kept = clauses.value.filter((entry) => entry.pinned)
		if (kept.length === clauses.value.length) {
			return false
		}
		commit(kept)
		return true
	}

	/**
	 * Replace the state with clauses restored from elsewhere (the URL, a saved
	 * view), marked with that origin. Pinned clauses already on the bus stay.
	 *
	 * @param {Array<object>} restored The clauses.
	 * @param {string} [source] Their origin, `urlBootstrap` by default.
	 * @return {void}
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-003-url-encoding-makes-filtered-views-bookmarkable
	 */
	function hydrate(restored, source = 'urlBootstrap') {
		const pinned = clauses.value.filter((entry) => entry.pinned)
		const next = [...pinned]
		for (const clause of Array.isArray(restored) ? restored : []) {
			if (!clause || typeof clause.dimension !== 'string' || next.some((entry) => entry.dimension === clause.dimension)) {
				continue
			}
			next.push(normalise({ ...clause, source: clause.pinned ? clause.source : source }))
		}
		commit(next)
	}

	/**
	 * Listen to every change; the listener gets the new clause list.
	 *
	 * @param {(clauses: Array<object>) => void} listener Gets the new clause list.
	 * @return {() => boolean} Stops listening.
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-002-all-consuming-widgets-re-render-on-filter-change
	 */
	function subscribe(listener) {
		subscribers.add(listener)
		return () => subscribers.delete(listener)
	}

	/**
	 * The clauses a widget consumes, for its filter context. A widget mounting
	 * later gets the current state the same way, so it never renders unfiltered.
	 *
	 * @param {string[]} consumes The dimensions the widget consumes.
	 * @return {Array<object>}
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-002-all-consuming-widgets-re-render-on-filter-change
	 */
	function clausesFor(consumes) {
		const dimensions = Array.isArray(consumes) ? consumes : []
		return clauses.value.filter((entry) => dimensions.includes(entry.dimension))
	}

	/**
	 * The active path of a hierarchy, broadest level first, for the breadcrumb.
	 *
	 * @param {string} hierarchyId The hierarchy id.
	 * @return {Array<{dimension: string, label: string, value: unknown}>}
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-004-drill-down-hierarchies-update-breadcrumb-and-descendant-filters
	 */
	function breadcrumb(hierarchyId) {
		const hierarchy = (Array.isArray(hierarchies) ? hierarchies : []).find((entry) => entry && entry.id === hierarchyId)
		const levels = Array.isArray(hierarchy?.levels) ? hierarchy.levels : []
		const path = []
		for (const level of levels) {
			const clause = clauses.value.find((entry) => entry.dimension === level.dimension)
			if (!clause) {
				continue
			}
			path.push({ dimension: level.dimension, label: level.label, value: clause.value })
		}
		return path
	}

	return { clauses, publish, remove, reset, hydrate, subscribe, clausesFor, breadcrumb }
}
