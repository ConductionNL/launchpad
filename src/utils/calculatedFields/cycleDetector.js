// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Cycle detection for calculated fields (REQ-CALC-006): within a widget a
 * calculated field may not depend on itself through other calculated fields,
 * and across a dashboard a widget whose calculated fields read another widget
 * may not be read back by it. A cycle is refused with `cycleDetected`, naming
 * what forms it; an acyclic set gets its evaluation order.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */

import { CalculatedFieldError, referencesOf } from './parser.js'

/**
 * A topological order of a graph, or the first cycle found.
 *
 * @param {Map<string, Set<string>>} graph - Node => the nodes it depends on.
 * @return {{order: string[], cycle: string[]|null}}
 */
function topologicalOrder(graph) {
	const walk = { graph, state: new Map(), order: [], stack: [], cycle: null }
	for (const node of [...graph.keys()].sort()) {
		visit(walk, node)
	}
	return { order: walk.cycle ? [] : walk.order, cycle: walk.cycle }
}

/**
 * Visit a node depth-first: its dependencies first, then itself.
 *
 * @param {{graph: Map, state: Map, order: string[], stack: string[], cycle: (string[]|null)}} walk - The walk, extended here.
 * @param {string} node - The node.
 * @return {void}
 */
function visit(walk, node) {
	if (walk.cycle || walk.state.get(node) === 'done') {
		return
	}
	if (walk.state.get(node) === 'active') {
		walk.cycle = cycleAt(walk.stack, node)
		return
	}
	walk.state.set(node, 'active')
	walk.stack.push(node)
	for (const dependency of dependenciesOf(walk.graph, node)) {
		visit(walk, dependency)
	}
	walk.stack.pop()
	walk.state.set(node, 'done')
	walk.order.push(node)
}

/**
 * The cycle closed by reaching a node already on the stack.
 *
 * @param {string[]} stack - The nodes being visited.
 * @param {string} node - The node reached again.
 * @return {string[]} The cycle, its first node repeated last.
 */
function cycleAt(stack, node) {
	return [...stack.slice(stack.indexOf(node)), node]
}

/**
 * A node's dependencies, sorted.
 *
 * @param {Map<string, Set<string>>} graph - The graph.
 * @param {string} node - The node.
 * @return {string[]} Its dependencies; none when it has no entry.
 */
function dependenciesOf(graph, node) {
	return [...(graph.get(node) || [])].sort()
}

/**
 * Refuse a dashboard's calculated fields when they form a cycle; otherwise
 * answer the order to evaluate them in, as `<widgetId>.<field>`.
 *
 * @param {Array<{id: string, calculatedFields?: Array<{name: string, compiledExpression: object}>}>} widgets - The dashboard's widgets.
 * @return {string[]} The evaluation order, dependencies first.
 * @throws {CalculatedFieldError} `cycleDetected` with the `cycle` and the `widgets` in it.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
export function assertAcyclic(widgets) {
	const { fieldGraph, widgetGraph } = dependencyGraphs(widgets || [])
	const fields = topologicalOrder(fieldGraph)
	if (fields.cycle) {
		throw cycleError(fields.cycle, fields.cycle.map((node) => node.slice(0, node.indexOf('.'))))
	}
	const across = topologicalOrder(widgetGraph)
	if (across.cycle) {
		throw cycleError(across.cycle, across.cycle)
	}
	return fields.order
}

/**
 * The field-level and widget-level dependency graphs of a dashboard.
 *
 * @param {Array<object>} widgets - The dashboard's widgets.
 * @return {{fieldGraph: Map<string, Set<string>>, widgetGraph: Map<string, Set<string>>}}
 */
function dependencyGraphs(widgets) {
	const fieldGraph = new Map()
	const widgetGraph = new Map()
	const calculated = new Map()
	for (const widget of widgets) {
		calculated.set(String(widget.id), new Set(calculatedFieldsOf(widget).map((field) => field.name)))
		widgetGraph.set(String(widget.id), new Set())
	}

	for (const widget of widgets) {
		const id = String(widget.id)
		for (const field of calculatedFieldsOf(widget)) {
			fieldGraph.set(`${id}.${field.name}`, fieldDependencies(id, field, calculated, widgetGraph))
		}
	}

	return { fieldGraph, widgetGraph }
}

/**
 * A widget's calculated fields.
 *
 * @param {{calculatedFields?: Array<object>}} widget - The widget.
 * @return {Array<object>} Its calculated fields; none when unset.
 */
function calculatedFieldsOf(widget) {
	return widget.calculatedFields || []
}

/**
 * The calculated fields one field depends on; records the widgets it reads in the widget graph.
 *
 * @param {string} id - The field's widget.
 * @param {{compiledExpression: object}} field - The field.
 * @param {Map<string, Set<string>>} calculated - Calculated field names per widget.
 * @param {Map<string, Set<string>>} widgetGraph - Widget dependencies, extended here.
 * @return {Set<string>} The `<widgetId>.<field>` nodes it depends on.
 */
function fieldDependencies(id, field, calculated, widgetGraph) {
	const refs = referencesOf(field.compiledExpression)
	const dependencies = new Set(refs.calculated.map((name) => `${id}.${name}`))
	for (const ref of refs.fields) {
		const target = targetWidget(id, ref)
		linkWidgets(widgetGraph, id, target)
		if (calculated.get(target)?.has(ref.path)) {
			dependencies.add(`${target}.${ref.path}`)
		}
	}
	return dependencies
}

/**
 * The widget a field reference reads.
 *
 * @param {string} id - The reading widget.
 * @param {{source: string}} ref - The field reference.
 * @return {string} The widget id read.
 */
function targetWidget(id, ref) {
	return ref.source === 'self' ? id : ref.source.slice('widget:'.length)
}

/**
 * Record that one widget reads another.
 *
 * @param {Map<string, Set<string>>} widgetGraph - Widget dependencies.
 * @param {string} from - The reading widget.
 * @param {string} to - The widget read.
 * @return {void}
 */
function linkWidgets(widgetGraph, from, to) {
	if (to === from) {
		return
	}
	widgetGraph.get(from).add(to)
	if (!widgetGraph.has(to)) {
		widgetGraph.set(to, new Set())
	}
}

/**
 * The cycleDetected refusal.
 *
 * @param {string[]} cycle - The nodes of the cycle, first repeated last.
 * @param {string[]} widgetIds - The widgets the nodes belong to.
 * @return {CalculatedFieldError}
 */
function cycleError(cycle, widgetIds) {
	return new CalculatedFieldError({
		error: 'cycleDetected',
		message: `Calculated fields depend on each other in a cycle: ${cycle.join(' -> ')}`,
		cycle,
		widgets: [...new Set(widgetIds)],
	})
}
