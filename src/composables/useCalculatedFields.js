/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { computed, unref, watch } from 'vue'
import { useCalculatedFieldsStore } from '../stores/calculatedFields.js'

/**
 * The calculated fields of one widget (REQ-CALC-003, REQ-CALC-008).
 *
 * Watches the widget's source data and the dashboard filters, reports them to
 * the calculated-fields store (which re-evaluates every field of the
 * dashboard in dependency order, so dependent widgets follow), and answers
 * this widget's values and inline errors as `Map`s keyed by field name.
 *
 * @param {object} options The inputs.
 * @param {string|import('vue').Ref<string>} options.widgetId The widget.
 * @param {object|import('vue').Ref<object>} [options.data] The widget's current source data.
 * @param {object[]|import('vue').Ref<object[]>} [options.filters] The dashboard's active filters.
 * @return {{values: import('vue').ComputedRef<Map<string, unknown>>, errors: import('vue').ComputedRef<Map<string, object>>}} The values and errors.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */
export function useCalculatedFields({ widgetId, data = undefined, filters = undefined }) {
	const store = useCalculatedFieldsStore()

	if (data !== undefined) {
		watch(
			() => [unref(widgetId), unref(data)],
			([id, current]) => store.setWidgetData(id, current),
			{ immediate: true, deep: true },
		)
	}
	if (filters !== undefined) {
		watch(
			() => unref(filters),
			(current) => store.setFilters(current),
			{ deep: true },
		)
	}

	const values = computed(() => new Map(Object.entries(store.valuesOf(unref(widgetId)))))
	const errors = computed(() => new Map(Object.entries(store.errorsOf(unref(widgetId)))))

	return { values, errors }
}
