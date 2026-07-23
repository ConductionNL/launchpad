/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `VisibilityRuleRow.vue`
 * (conditional-visibility-editor spec, REQ-CVUI-002). Covers:
 *  - each rule type emits the canonical camelCase `ruleConfig` shape
 *  - the date type omits an empty `endDate` key
 *  - the include/exclude toggle emits `isInclude`
 *  - client-side validation blocks Save on malformed operands (bad time,
 *    empty groups) and does NOT emit `save`
 *  - `update:rule` fires live on every valid change, before Save
 */

import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import VisibilityRuleRow from '../VisibilityRuleRow.vue'

const stubs = {
	NcButton: { template: '<button :disabled="$attrs.disabled" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>' },
	NcSelect: { template: '<div class="nc-select-stub" />' },
	NcSelectTags: { template: '<div class="nc-selecttags-stub" />' },
	NcTextField: {
		props: ['value'],
		template: '<input @input="$emit(\'update:value\', $event.target.value)" />',
	},
	NcCheckboxRadioSwitch: {
		props: ['checked'],
		template: '<label><input type="checkbox" :checked="checked" @change="$emit(\'update:checked\', $event.target.checked)" /><slot /></label>',
	},
	Delete: { template: '<span />' },
}

function mountRow(rule, props = {}) {
	return mount(VisibilityRuleRow, {
		propsData: { rule, availableGroups: ['marketing', 'sales'], ...props },
		stubs,
	})
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('VisibilityRuleRow', () => {
	it('group row emits ruleConfig {groups: [...]} on save', async () => {
		const wrapper = mountRow({ ruleType: 'group', ruleConfig: { groups: [] }, isInclude: true })
		wrapper.vm.local.ruleConfig.groups = ['marketing', 'sales']
		await wrapper.vm.$nextTick()
		wrapper.vm.save()

		const saved = wrapper.emitted('save')
		expect(saved).toBeTruthy()
		expect(saved[0][0]).toEqual({
			ruleType: 'group',
			ruleConfig: { groups: ['marketing', 'sales'] },
			isInclude: true,
		})
	})

	it('time row emits camelCase startTime/endTime/days', async () => {
		const wrapper = mountRow({ ruleType: 'time', ruleConfig: {}, isInclude: true })
		wrapper.vm.onStartTimeChange('09:00')
		wrapper.vm.onEndTimeChange('17:00')
		wrapper.vm.toggleDay('mon')
		wrapper.vm.toggleDay('tue')
		wrapper.vm.save()

		const saved = wrapper.emitted('save')
		expect(saved[0][0]).toEqual({
			ruleType: 'time',
			ruleConfig: { startTime: '09:00', endTime: '17:00', days: ['mon', 'tue'] },
			isInclude: true,
		})
	})

	it('time row omits the days key when none selected', async () => {
		const wrapper = mountRow({ ruleType: 'time', ruleConfig: {}, isInclude: true })
		wrapper.vm.onStartTimeChange('09:00')
		wrapper.vm.onEndTimeChange('17:00')
		wrapper.vm.save()

		const saved = wrapper.emitted('save')[0][0]
		expect(saved.ruleConfig).toEqual({ startTime: '09:00', endTime: '17:00' })
		expect('days' in saved.ruleConfig).toBe(false)
	})

	it('date row emits startDate only and does NOT emit an empty endDate key', async () => {
		const wrapper = mountRow({ ruleType: 'date', ruleConfig: {}, isInclude: true })
		wrapper.vm.onStartDateChange('2026-12-01')
		wrapper.vm.save()

		const saved = wrapper.emitted('save')[0][0]
		expect(saved.ruleConfig).toEqual({ startDate: '2026-12-01' })
		expect('endDate' in saved.ruleConfig).toBe(false)
	})

	it('attribute row emits {attribute, operator, value}', async () => {
		const wrapper = mountRow({ ruleType: 'attribute', ruleConfig: {}, isInclude: true })
		wrapper.vm.onAttributeFieldChange('attribute', 'language')
		wrapper.vm.onAttributeFieldChange('value', 'nl')
		wrapper.vm.save()

		const saved = wrapper.emitted('save')[0][0]
		expect(saved.ruleConfig).toEqual({ attribute: 'language', operator: 'equals', value: 'nl' })
	})

	it('toggling to exclude emits isInclude: false', async () => {
		const wrapper = mountRow({ ruleType: 'group', ruleConfig: { groups: ['marketing'] }, isInclude: true })
		wrapper.vm.setMode(false)
		wrapper.vm.save()

		const saved = wrapper.emitted('save')[0][0]
		expect(saved.isInclude).toBe(false)
	})

	it('blocks save with an empty groups array (client-side validation)', () => {
		const wrapper = mountRow({ ruleType: 'group', ruleConfig: { groups: [] }, isInclude: true })
		wrapper.vm.save()

		expect(wrapper.emitted('save')).toBeFalsy()
		expect(wrapper.vm.isValid).toBe(false)
	})

	it('blocks save with a malformed time (not HH:MM)', () => {
		const wrapper = mountRow({ ruleType: 'time', ruleConfig: {}, isInclude: true })
		wrapper.vm.onStartTimeChange('9am')
		wrapper.vm.onEndTimeChange('17:00')
		wrapper.vm.save()

		expect(wrapper.emitted('save')).toBeFalsy()
		expect(wrapper.vm.isValid).toBe(false)
	})

	it('accepts a date row with only an open-ended startDate', () => {
		const wrapper = mountRow({ ruleType: 'date', ruleConfig: {}, isInclude: true })
		wrapper.vm.onStartDateChange('2026-01-01')

		expect(wrapper.vm.isValid).toBe(true)
	})

	it('emits update:rule live on every valid change, before Save is pressed', async () => {
		const wrapper = mountRow({ ruleType: 'group', ruleConfig: { groups: [] }, isInclude: true })
		wrapper.vm.local.ruleConfig.groups = ['marketing']
		wrapper.vm.onChange()

		expect(wrapper.emitted('update:rule')).toBeTruthy()
		expect(wrapper.emitted('save')).toBeFalsy()
		const lastUpdate = wrapper.emitted('update:rule').slice(-1)[0][0]
		expect(lastUpdate.ruleConfig).toEqual({ groups: ['marketing'] })
	})

	it('remove emits without needing validation to pass', async () => {
		const wrapper = mountRow({ ruleType: 'group', ruleConfig: { groups: [] }, isInclude: true })
		await wrapper.find('[data-test="rule-remove"]').trigger('click')

		expect(wrapper.emitted('remove')).toBeTruthy()
	})

	it('switching ruleType resets ruleConfig to a fresh shape for the new type', () => {
		const wrapper = mountRow({ ruleType: 'group', ruleConfig: { groups: ['marketing'] }, isInclude: true })
		wrapper.vm.typeOption = { id: 'attribute', label: 'User attribute' }

		expect(wrapper.vm.local.ruleType).toBe('attribute')
		expect(wrapper.vm.local.ruleConfig).toEqual({ attribute: '', operator: 'equals', value: '' })
	})
})
