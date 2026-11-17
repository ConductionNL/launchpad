/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * widgetBusConsumption — how a placed widget takes part in the dashboard's
 * filter bus (drill-down-cross-widget-filter REQ-CWF-002, REQ-CWF-008).
 *
 * A widget declares its part in its placement content, next to its
 * `dataSource` (top level, or under `props` like the other chart settings):
 *
 *   consumes:  ['gemeenteCode', { dimension: 'wijkCode', field: 'wijk' }]
 *   publishes: [{ dimension: 'gemeenteCode', fromField: 'gemeente' }]
 *
 * A consumed dimension filters the widget's data on `field` (the dimension
 * itself by default). The hierarchies are the dashboard's own. A widget that
 * declares nothing is not on the bus and renders as before.
 */

/** The operators a consumed clause can filter a data source with. */
const CONSUMABLE_OPERATORS = ['eq', 'in']

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
 * The bus declarations of a placement's content.
 *
 * @param {object|null} content The placement's content.
 * @return {{publishes: Array<object>, consumes: Array<object>}}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-8
 */
export function busDeclarationsOf(content) {
	const source = content && typeof content === 'object' ? content : {}
	const props = source.props && typeof source.props === 'object' ? source.props : {}
	return {
		publishes: entriesOf(source.publishes ?? props.publishes),
		consumes: entriesOf(source.consumes ?? props.consumes),
	}
}

/**
 * The data-source filter the bus's clauses make for a widget: one entry per
 * consumed dimension with a clause, on the declared field. Only `eq` (a
 * value) and `in` (a list) translate into a data-source filter; a clause with
 * another operator is left out rather than filtering wrongly.
 *
 * @param {Array<object>} clauses The bus's clauses.
 * @param {Array<object>} consumes The widget's consumed dimensions.
 * @return {{[field: string]: unknown}}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
 */
export function consumedFilter(clauses, consumes) {
	const filter = {}
	const list = Array.isArray(clauses) ? clauses : []
	for (const entry of entriesOf(consumes)) {
		const clause = list.find((candidate) => candidate?.dimension === entry.dimension)
		if (isConsumable(clause)) {
			filter[fieldOf(entry)] = clause.value
		}
	}
	return filter
}

/**
 * Whether a clause translates into a data-source filter: an `eq` clause, or
 * an `in` clause with a list.
 *
 * @param {object|undefined} clause The clause, if any.
 * @return {boolean}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
 */
function isConsumable(clause) {
	if (!clause) {
		return false
	}
	const operator = clause.operator ?? 'eq'
	return CONSUMABLE_OPERATORS.includes(operator) && (operator !== 'in' || Array.isArray(clause.value))
}

/**
 * The data-source field a consumed dimension filters on.
 *
 * @param {object} entry The consumed dimension.
 * @return {string}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
 */
function fieldOf(entry) {
	return typeof entry.field === 'string' && entry.field !== '' ? entry.field : entry.dimension
}

/**
 * A data source with the bus's filter added; the same object when there is
 * nothing to add, so an unfiltered widget does not refetch.
 *
 * @param {object|null} dataSource The widget's data source.
 * @param {{[field: string]: unknown}} busFilter The filter from {@link consumedFilter}.
 * @return {object|null}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
 */
export function withBusFilter(dataSource, busFilter) {
	if (!dataSource || typeof dataSource !== 'object' || Object.keys(busFilter).length === 0) {
		return dataSource
	}
	const declared = dataSource.filter && typeof dataSource.filter === 'object' ? dataSource.filter : {}
	return { ...dataSource, filter: { ...declared, ...busFilter } }
}
