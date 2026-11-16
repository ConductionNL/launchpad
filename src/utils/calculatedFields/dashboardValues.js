// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Evaluate every calculated field of a dashboard in dependency order
 * (REQ-CALC-003, REQ-CALC-008).
 *
 * Each widget's calculated fields read that widget's source data (`row`),
 * its own earlier calculated values, and the data and calculated values of
 * the widgets they reference (`widget:<id>.<path>`). The fields run in the
 * topological order the cycle detector answers, so a field that reads
 * another widget's calculated field always sees its current value. A field
 * that fails yields `null` and an inline error; the other fields and widgets
 * still evaluate.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */

import { assertAcyclic } from './cycleDetector.js'
import { evaluateField } from './evaluator.js'
import { CalculatedFieldError } from './parser.js'

/**
 * The calculated fields of a widget that can be evaluated: they carry a
 * compiled expression.
 *
 * @param {object} widget The widget.
 * @return {object[]} The fields.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
export function compiledFieldsOf(widget) {
	return (widget?.calculatedFields || []).filter((field) => field && field.name && field.compiledExpression)
}

/**
 * Split a `<widgetId>.<fieldName>` node of the cycle detector's order. Field
 * names are identifiers, so the last dot separates them.
 *
 * @param {string} node The node.
 * @return {{widgetId: string, name: string}} Its parts.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
function splitNode(node) {
	const at = node.lastIndexOf('.')
	return { widgetId: node.slice(0, at), name: node.slice(at + 1) }
}

/**
 * The evaluable widgets, each with only its compiled fields, keyed by id.
 *
 * @param {object[]} widgets The dashboard's widgets.
 * @return {Map<string, object>} The widgets by id.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
function widgetsById(widgets) {
	const byId = new Map()
	for (const widget of (widgets || []).filter(hasId)) {
		byId.set(String(widget.id), { id: String(widget.id), calculatedFields: compiledFieldsOf(widget) })
	}
	return byId
}

/**
 * Whether a widget carries an id.
 *
 * @param {object} widget The widget.
 * @return {boolean} True when it has one.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
function hasId(widget) {
	return widget?.id !== undefined && widget?.id !== null
}

/**
 * Empty value and error records for every widget.
 *
 * @param {Map<string, object>} byId The widgets by id.
 * @return {{values: object, errors: object}} The records.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
function emptyResult(byId) {
	const values = {}
	const errors = {}
	for (const id of byId.keys()) {
		values[id] = {}
		errors[id] = {}
	}
	return { values, errors }
}

/**
 * Mark every field of the widgets in a cycle as failed with the cycle error,
 * so those widgets show an inline error while the rest of the dashboard
 * renders.
 *
 * @param {{values: object, errors: object}} result The result to fill.
 * @param {Map<string, object>} byId The widgets by id.
 * @param {CalculatedFieldError} error The cycle error.
 * @return {void}
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
function markCycle(result, byId, error) {
	for (const id of cycleWidgets(error, byId)) {
		for (const field of byId.get(String(id))?.calculatedFields || []) {
			result.values[id][field.name] = null
			result.errors[id][field.name] = { error: error.message, expression: field.expression ?? '', inputs: {} }
		}
	}
}

/**
 * The widgets a cycle error names, else every widget.
 *
 * @param {CalculatedFieldError} error The cycle error.
 * @param {Map<string, object>} byId The widgets by id.
 * @return {string[]} The widget ids.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
function cycleWidgets(error, byId) {
	const listed = error.detail?.widgets
	return listed?.length ? listed : [...byId.keys()]
}

/**
 * The fields' evaluation order, or null after marking a cycle on the result.
 *
 * @param {Map<string, object>} byId The widgets by id.
 * @param {{values: object, errors: object}} result The result to mark on a cycle.
 * @return {string[]|null} The `<widgetId>.<fieldName>` nodes in order.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
function evaluationOrder(byId, result) {
	try {
		return assertAcyclic([...byId.values()])
	} catch (error) {
		if (!(error instanceof CalculatedFieldError)) {
			throw error
		}
		markCycle(result, byId, error)
		return null
	}
}

/**
 * Evaluate one field of the order into the result: its value, or null and
 * an inline error.
 *
 * @param {string} node The `<widgetId>.<fieldName>` node.
 * @param {Map<string, object>} byId The widgets by id.
 * @param {Record<string, object>} data Each widget's source data, by widget id.
 * @param {{values: object, errors: object}} result The result to fill.
 * @param {{now?: number}} options Evaluation options.
 * @return {void}
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
function evaluateNode(node, byId, data, result, options) {
	const { widgetId, name } = splitNode(node)
	const field = byId.get(widgetId)?.calculatedFields.find((candidate) => candidate.name === name)
	if (!field) {
		return
	}
	const outcome = evaluateField(field, {
		row: data[widgetId] ?? {},
		calculated: result.values[widgetId],
		widgets: data,
		widgetCalculated: result.values,
	}, options)
	const failed = Object.hasOwn(outcome, 'error')
	result.values[widgetId][name] = failed ? null : outcome.value
	if (failed) {
		result.errors[widgetId][name] = outcome
	}
}

/**
 * Evaluate the calculated fields of every widget on the dashboard.
 *
 * @param {object[]} widgets The widgets, each `{id, calculatedFields}`.
 * @param {Record<string, object>} dataById Each widget's current source data, by widget id.
 * @param {{now?: number}} [options] Evaluation options (a fixed clock for tests).
 * @return {{values: Record<string, Record<string, unknown>>, errors: Record<string, Record<string, object>>}} Values and inline errors per widget id and field name.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
export function evaluateDashboard(widgets, dataById, options = {}) {
	const byId = widgetsById(widgets)
	const result = emptyResult(byId)
	const data = dataById || {}
	for (const node of evaluationOrder(byId, result) || []) {
		evaluateNode(node, byId, data, result, options)
	}

	return result
}

/**
 * The widgets whose calculated fields read a given widget, directly or
 * through other widgets: the ones to re-evaluate when its data changes.
 *
 * @param {object[]} widgets The dashboard's widgets.
 * @param {string} changedId The widget whose data changed.
 * @return {string[]} The changed widget and every widget depending on it, sorted.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
export function dependentWidgets(widgets, changedId) {
	const readers = readersOf(widgets)
	const seen = new Set([String(changedId)])
	const queue = [String(changedId)]
	while (queue.length > 0) {
		const fresh = [...(readers.get(queue.shift()) || [])].filter((reader) => !seen.has(reader))
		fresh.forEach((reader) => {
			seen.add(reader)
			queue.push(reader)
		})
	}
	return [...seen].sort()
}

/**
 * For every widget id, the widgets whose calculated fields read it.
 *
 * @param {object[]} widgets The dashboard's widgets.
 * @return {Map<string, Set<string>>} The readers by widget id.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
function readersOf(widgets) {
	const readers = new Map()
	for (const widget of widgetsById(widgets).values()) {
		const targets = widget.calculatedFields.flatMap((field) => [...referencedWidgets(field.compiledExpression)])
		targets.forEach((target) => addReader(readers, target, widget.id))
	}
	return readers
}

/**
 * Note that a widget reads a target widget.
 *
 * @param {Map<string, Set<string>>} readers The readers by widget id, extended in place.
 * @param {string} target The widget read.
 * @param {string} reader The reading widget.
 * @return {void}
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
function addReader(readers, target, reader) {
	if (!readers.has(target)) {
		readers.set(target, new Set())
	}
	readers.get(target).add(reader)
}

/**
 * The ids of the widgets an expression reads through `widget:<id>` refs.
 *
 * @param {object} node An AST node.
 * @param {Set<string>} [found] The ids found so far.
 * @return {Set<string>} The ids.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
function referencedWidgets(node, found = new Set()) {
	if (node && typeof node === 'object') {
		const id = widgetRefOf(node)
		if (id !== null) {
			found.add(id)
		}
		Object.values(node).forEach((value) => referencedWidgets(value, found))
	}
	return found
}

/**
 * The widget id a `widget:<id>` field reference names, else null.
 *
 * @param {object} node An AST node.
 * @return {string|null} The id.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
function widgetRefOf(node) {
	const named = node.type === 'fieldRef' && typeof node.source === 'string' && node.source.startsWith('widget:')
	return named ? node.source.slice('widget:'.length) : null
}
