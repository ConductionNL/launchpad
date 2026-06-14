/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `SpendAnalyticsForm.vue` (REQ-SAW-003).
 * Verifies the content contract emit shape, default application,
 * comma-list filter parsing, and that an out-of-enum viewMode/period
 * fails validation (keeping the modal Add/Save button disabled).
 */

import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import SpendAnalyticsForm from '../SpendAnalyticsForm.vue'

beforeEach(() => {
	globalThis.t = (_app, key, params = {}) => Object.entries(params)
		.reduce((acc, [k, v]) => acc.replace(`{${k}}`, v), key)
})

const stubs = {
	NcTextField: { template: '<input />' },
	NcSelect: { template: '<select></select>' },
}

describe('SpendAnalyticsForm (REQ-SAW-003)', () => {
	it('applies defaults when given empty content', () => {
		const wrapper = mount(SpendAnalyticsForm, { stubs, propsData: { value: {} } })
		expect(wrapper.vm.assembledContent).toEqual({
			viewMode: 'summary',
			period: 'quarter',
			filters: { categoryIds: [], departmentIds: [], vendorIds: [] },
			drillThroughTarget: 'detail-page',
			attachEvidence: true,
			aiInsights: { enabled: false },
		})
	})

	it('hydrates from an editing placement', () => {
		const wrapper = mount(SpendAnalyticsForm, {
			stubs,
			propsData: {
				editingWidget: {
					content: {
						viewMode: 'top-vendors',
						period: 'ytd',
						filters: { categoryIds: ['30190000'], departmentIds: [], vendorIds: ['acme'] },
						attachEvidence: false,
						aiInsights: { enabled: true },
					},
				},
			},
		})
		expect(wrapper.vm.viewMode).toBe('top-vendors')
		expect(wrapper.vm.period).toBe('ytd')
		expect(wrapper.vm.categoryIds).toEqual(['30190000'])
		expect(wrapper.vm.attachEvidence).toBe(false)
		expect(wrapper.vm.aiInsightsEnabled).toBe(true)
	})

	it('parses a comma-separated filter into a trimmed array and emits', async () => {
		const wrapper = mount(SpendAnalyticsForm, { stubs, propsData: { value: {} } })
		wrapper.vm.updateListField('categoryIds', ' 30190000 , 48000000 ,, ')
		expect(wrapper.vm.categoryIds).toEqual(['30190000', '48000000'])
		const emitted = wrapper.emitted('update:content')
		expect(emitted).toBeTruthy()
		expect(emitted[emitted.length - 1][0].filters.categoryIds).toEqual(['30190000', '48000000'])
	})

	it('validate() passes for an in-enum config', () => {
		const wrapper = mount(SpendAnalyticsForm, { stubs, propsData: { value: { viewMode: 'trend', period: 'fy' } } })
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('validate() rejects an out-of-enum viewMode (REQ-SAW-003 invalid viewMode)', () => {
		const wrapper = mount(SpendAnalyticsForm, { stubs, propsData: { value: {} } })
		wrapper.vm.viewMode = 'forecast'
		const errors = wrapper.vm.validate()
		expect(errors.length).toBeGreaterThan(0)
	})

	it('validate() rejects an out-of-enum period', () => {
		const wrapper = mount(SpendAnalyticsForm, { stubs, propsData: { value: {} } })
		wrapper.vm.period = 'decade'
		expect(wrapper.vm.validate().length).toBeGreaterThan(0)
	})
})
