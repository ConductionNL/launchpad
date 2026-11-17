/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { defineStore } from 'pinia'
import { evaluateDashboard } from '../utils/calculatedFields/dashboardValues.js'

/**
 * The calculated-field values of the open dashboard (REQ-CALC-003).
 *
 * Widget renderers report their current source data with `setWidgetData`;
 * the dashboard reports its widgets with `setWidgets` and its active filters
 * with `setFilters`. Every report re-evaluates all calculated fields in
 * topological order, so a field reading another widget follows that widget's
 * data and calculated values. Filters change what the renderers load; the
 * filter revision is kept so a renderer that reloads after a filter change
 * re-evaluates even when the data happens to be identical.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
export const useCalculatedFieldsStore = defineStore('calculatedFields', {
	state: () => ({
		widgets: [],
		dataById: {},
		filters: [],
		filterRevision: 0,
		values: {},
		errors: {},
	}),

	getters: {
		/**
		 * The calculated values of one widget, by field name.
		 *
		 * @param {object} state The store state.
		 * @return {function(string): Record<string, unknown>} The lookup.
		 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
		 */
		valuesOf: (state) => (widgetId) => state.values[String(widgetId)] || {},

		/**
		 * The inline errors of one widget, by field name.
		 *
		 * @param {object} state The store state.
		 * @return {function(string): Record<string, object>} The lookup.
		 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-008-inline-error-display-preserves-dashboard-usability
		 */
		errorsOf: (state) => (widgetId) => state.errors[String(widgetId)] || {},
	},

	actions: {
		/**
		 * Set the dashboard's widgets and re-evaluate.
		 *
		 * @param {object[]} widgets The widgets, each `{id, calculatedFields}`.
		 * @return {void}
		 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
		 */
		setWidgets(widgets) {
			this.widgets = Array.isArray(widgets) ? widgets : []
			this.recompute()
		},

		/**
		 * Record one widget's current source data and re-evaluate.
		 *
		 * @param {string} widgetId The widget.
		 * @param {object} data Its source data.
		 * @return {void}
		 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
		 */
		setWidgetData(widgetId, data) {
			this.dataById = { ...this.dataById, [String(widgetId)]: data ?? {} }
			this.recompute()
		},

		/**
		 * Record the dashboard's active filters and re-evaluate.
		 *
		 * @param {object[]} filters The filter clauses.
		 * @return {void}
		 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
		 */
		setFilters(filters) {
			this.filters = Array.isArray(filters) ? filters : []
			this.filterRevision++
			this.recompute()
		},

		/**
		 * Forget the dashboard (on leaving it).
		 *
		 * @return {void}
		 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
		 */
		reset() {
			this.widgets = []
			this.dataById = {}
			this.filters = []
			this.values = {}
			this.errors = {}
		},

		/**
		 * Re-evaluate every calculated field in topological order.
		 *
		 * @param {{now?: number}} [options] Evaluation options.
		 * @return {void}
		 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
		 */
		recompute(options = {}) {
			const { values, errors } = evaluateDashboard(this.widgets, this.dataById, options)
			this.values = values
			this.errors = errors
		},
	},
})
