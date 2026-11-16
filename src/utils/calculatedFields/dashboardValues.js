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
	for (const widget of widgets || []) {
		if (widget && widget.id !== undefined && widget.id !== null) {
			byId.set(String(widget.id), { id: String(widget.id), calculatedFields: compiledFieldsOf(widget) })
		}
	}
	return byId
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
	const widgets = error.detail?.widgets?.length ? error.detail.widgets : [...byId.keys()]
	for (const id of widgets) {
		for (const field of byId.get(String(id))?.calculatedFields || []) {
			result.values[id][field.name] = null
			result.errors[id][field.name] = { error: error.message, expression: field.expression ?? '', inputs: {} }
		}
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
export function evaluateDashboard(widgets, dataById = {}, options = {}) {
	const byId = widgetsById(widgets)
	const result = emptyResult(byId)
	let order
	try {
		order = assertAcyclic([...byId.values()])
	} catch (error) {
		if (!(error instanceof CalculatedFieldError)) {
			throw error
		}
		markCycle(result, byId, error)
		return result
	}

	const data = dataById || {}
	for (const node of order) {
		const { widgetId, name } = splitNode(node)
		const field = byId.get(widgetId)?.calculatedFields.find((candidate) => candidate.name === name)
		if (!field) {
			continue
		}
		const outcome = evaluateField(field, {
			row: data[widgetId] ?? {},
			calculated: result.values[widgetId],
			widgets: data,
			widgetCalculated: result.values,
		}, options)
		if (Object.hasOwn(outcome, 'error')) {
			result.values[widgetId][name] = null
			result.errors[widgetId][name] = outcome
		} else {
			result.values[widgetId][name] = outcome.value
		}
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
	const readers = new Map()
	for (const widget of widgetsById(widgets).values()) {
		for (const field of widget.calculatedFields) {
			for (const target of referencedWidgets(field.compiledExpression)) {
				if (!readers.has(target)) {
					readers.set(target, new Set())
				}
				readers.get(target).add(widget.id)
			}
		}
	}

	const seen = new Set([String(changedId)])
	const queue = [String(changedId)]
	while (queue.length > 0) {
		for (const reader of readers.get(queue.shift()) || []) {
			if (!seen.has(reader)) {
				seen.add(reader)
				queue.push(reader)
			}
		}
	}
	return [...seen].sort()
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
	if (!node || typeof node !== 'object') {
		return found
	}
	if (Array.isArray(node)) {
		node.forEach((child) => referencedWidgets(child, found))
		return found
	}
	if (node.type === 'fieldRef' && typeof node.source === 'string' && node.source.startsWith('widget:')) {
		found.add(node.source.slice('widget:'.length))
	}
	for (const value of Object.values(node)) {
		if (value && typeof value === 'object') {
			referencedWidgets(value, found)
		}
	}
	return found
}
