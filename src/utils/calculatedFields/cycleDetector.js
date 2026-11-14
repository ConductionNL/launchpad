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
	const state = new Map()
	const order = []
	const stack = []
	let cycle = null

	const visit = (node) => {
		if (cycle) {
			return
		}
		if (state.get(node) === 'done') {
			return
		}
		if (state.get(node) === 'active') {
			cycle = [...stack.slice(stack.indexOf(node)), node]
			return
		}
		state.set(node, 'active')
		stack.push(node)
		for (const dependency of [...(graph.get(node) || [])].sort()) {
			visit(dependency)
		}
		stack.pop()
		state.set(node, 'done')
		order.push(node)
	}

	for (const node of [...graph.keys()].sort()) {
		visit(node)
	}
	return { order: cycle ? [] : order, cycle }
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
	const fieldGraph = new Map()
	const widgetGraph = new Map()
	const calculated = new Map()
	for (const widget of widgets || []) {
		calculated.set(String(widget.id), new Set((widget.calculatedFields || []).map((field) => field.name)))
		widgetGraph.set(String(widget.id), new Set())
	}

	for (const widget of widgets || []) {
		const id = String(widget.id)
		for (const field of widget.calculatedFields || []) {
			const node = `${id}.${field.name}`
			const dependencies = new Set()
			const refs = referencesOf(field.compiledExpression)
			for (const name of refs.calculated) {
				dependencies.add(`${id}.${name}`)
			}
			for (const ref of refs.fields) {
				const target = ref.source === 'self' ? id : ref.source.slice('widget:'.length)
				if (target !== id) {
					widgetGraph.get(id).add(target)
					if (!widgetGraph.has(target)) {
						widgetGraph.set(target, new Set())
					}
				}
				if (calculated.get(target)?.has(ref.path)) {
					dependencies.add(`${target}.${ref.path}`)
				}
			}
			fieldGraph.set(node, dependencies)
		}
	}

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
