/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget dialog gets the edited widget's filter notices
 * (drill-down-cross-widget-filter task 9), and a dismissed one stays away
 * for that widget (task 29).
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-29
 */

import { describe, expect, it } from 'vitest'
import Views from '../Views.vue'

const notices = (state) => Views.computed.customWidgetNotices.call({ dismissedWidgetNotices: {}, ...state })

describe('Views.customWidgetNotices', () => {
	it('hands the dialog a warning for a consumed dimension the dashboard does not publish', () => {
		const result = notices({ customWidgetEditing: { content: { consumes: ['buurtCode'] } }, filterSession: { bus: { hierarchies: [] } } })
		expect(result).toHaveLength(1)
		expect(result[0]).toMatchObject({ id: 'consumes-unknown-dimension:buurtCode', type: 'warning' })
	})

	it('hands nothing when no widget is edited, or there is no filter session', () => {
		expect(notices({ customWidgetEditing: null, filterSession: null })).toEqual([])
		expect(notices({ customWidgetEditing: { content: {} }, filterSession: null })).toEqual([])
	})

	it('leaves out a notice dismissed for that widget only', () => {
		const state = { dismissedWidgetNotices: {}, customWidgetEditing: { id: 7, content: { consumes: ['buurtCode'] } }, filterSession: { bus: { hierarchies: [] } } }
		Views.methods.dismissWidgetNotice.call(state, 'consumes-unknown-dimension:buurtCode')
		expect(state.dismissedWidgetNotices).toEqual({ 7: ['consumes-unknown-dimension:buurtCode'] })
		expect(notices(state)).toEqual([])
		expect(notices({ ...state, customWidgetEditing: { id: 8, content: { consumes: ['buurtCode'] } } })).toHaveLength(1)
	})

	it('remembers nothing while no saved widget is edited', () => {
		const state = { dismissedWidgetNotices: {}, customWidgetEditing: { content: { consumes: ['buurtCode'] } } }
		Views.methods.dismissWidgetNotice.call(state, 'consumes-unknown-dimension:buurtCode')
		expect(state.dismissedWidgetNotices).toEqual({})
	})
})
