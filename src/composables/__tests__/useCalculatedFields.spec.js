/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The calculated-fields store and composable re-evaluate the dashboard when a
 * widget's data or the filters change (tasks 17, 18).
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-003-client-side-evaluator-reactive-to-filter-changes
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { useCalculatedFieldsStore } from '../../stores/calculatedFields.js'
import { parse } from '../../utils/calculatedFields/parser.js'
import { useCalculatedFields } from '../useCalculatedFields.js'

/**
 * A widget with calculated fields parsed from expressions.
 *
 * @param {string} id - The widget id.
 * @param {Record<string, string>} fields - Name => expression.
 * @return {object}
 */
function widget(id, fields) {
	const names = Object.keys(fields)
	return {
		id,
		calculatedFields: names.map((name) => ({ name, expression: fields[name], compiledExpression: parse(fields[name], { calculatedNames: names }) })),
	}
}

describe('useCalculatedFieldsStore', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('re-evaluates the readers of a widget when its data arrives', () => {
		const store = useCalculatedFieldsStore()
		store.setWidgets([widget('a', { share: 'part / widget:b.whole * 100' }), { id: 'b' }])
		store.setWidgetData('a', { part: 10 })

		expect(store.valuesOf('a')).toEqual({ share: null })

		store.setWidgetData('b', { whole: 40 })

		expect(store.valuesOf('a')).toEqual({ share: 25 })
		expect(store.errorsOf('a')).toEqual({})
	})

	it('counts filter changes and keeps the filters', () => {
		const store = useCalculatedFieldsStore()
		store.setFilters([{ dimension: 'year', value: 2026 }])
		store.setFilters(null)

		expect(store.filterRevision).toBe(2)
		expect(store.filters).toEqual([])
	})

	it('answers empty values and errors for an unknown widget, and forgets all on reset', () => {
		const store = useCalculatedFieldsStore()
		store.setWidgets([widget('w', { one: '1' })])

		expect(store.valuesOf('w')).toEqual({ one: 1 })
		expect(store.valuesOf('nope')).toEqual({})

		store.reset()

		expect(store.valuesOf('w')).toEqual({})
		expect(store.widgets).toEqual([])
	})
})

describe('useCalculatedFields', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('reports the widget data and answers values and errors as maps that follow changes', async () => {
		const store = useCalculatedFieldsStore()
		store.setWidgets([widget('w', { ratio: 'a / b' })])
		const data = ref({ a: 6, b: 3 })
		const scope = effectScope()
		const { values, errors } = scope.run(() => useCalculatedFields({ widgetId: 'w', data }))

		expect(values.value.get('ratio')).toBe(2)
		expect(errors.value.size).toBe(0)

		data.value = { a: 6, b: 0 }
		await nextTick()

		expect(values.value.get('ratio')).toBeNull()
		expect(errors.value.get('ratio')).toMatchObject({ expression: 'a / b' })
		scope.stop()
	})

	it('passes filter changes to the store', async () => {
		const store = useCalculatedFieldsStore()
		const filters = ref([])
		const scope = effectScope()
		scope.run(() => useCalculatedFields({ widgetId: 'w', filters }))

		filters.value = [{ dimension: 'region', value: 'north' }]
		await nextTick()

		expect(store.filters).toEqual([{ dimension: 'region', value: 'north' }])
		expect(store.filterRevision).toBe(1)
		scope.stop()
	})
})
