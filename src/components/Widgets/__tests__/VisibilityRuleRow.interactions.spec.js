/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `VisibilityRuleRow.vue` driven through its rendered
 * controls (conditional-visibility-editor spec, REQ-CVUI-002): the rule-type
 * and operator selects, the groups picker, the time/date/attribute fields,
 * the day toggles and the include/exclude radios. Also covers the paths the
 * main spec does not reach — an end date, un-toggling a day, clearing the
 * operator, inline validation messages and an unknown rule type.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import VisibilityRuleRow from '../VisibilityRuleRow.vue'

const stubs = {
	NcButton: {
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :disabled="disabled" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
	},
	NcSelect: {
		props: ['modelValue', 'options'],
		emits: ['update:modelValue'],
		template: `<div class="nc-select-stub" :data-selected="modelValue && modelValue.id">
			<button v-for="o in options" :key="o.id" class="opt" :data-id="o.id" @click="$emit('update:modelValue', o)">{{ o.label }}</button>
			<button class="clear" @click="$emit('update:modelValue', null)">clear</button>
		</div>`,
	},
	NcSelectTags: {
		props: ['modelValue', 'options'],
		emits: ['update:modelValue'],
		template:
			'<div class="nc-selecttags-stub"><button v-for="o in options" :key="o" class="tag" @click="$emit(\'update:modelValue\', [o])">{{ o }}</button></div>',
	},
	NcTextField: {
		props: ['modelValue', 'label', 'error', 'helperText'],
		emits: ['update:modelValue'],
		template: `<div class="nc-textfield-stub" :data-error="String(!!error)">
			<input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />
			<span class="helper">{{ helperText }}</span>
		</div>`,
	},
	NcCheckboxRadioSwitch: {
		props: ['modelValue', 'type', 'name'],
		emits: ['update:modelValue'],
		template:
			'<label><input type="checkbox" :name="name" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
	},
	Delete: { template: '<span class="icon-delete" />' },
}

function mountRow(rule, props = {}) {
	return mount(VisibilityRuleRow, {
		props: { rule, availableGroups: ['marketing', 'sales'], ...props },
		global: { stubs },
	})
}

function lastUpdate(wrapper) {
	return wrapper.emitted('update:rule').slice(-1)[0][0]
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('VisibilityRuleRow — rule type select', () => {
	it('switches to the date type from the select and emits the new empty shape', async () => {
		const wrapper = mountRow({ ruleType: 'group', ruleConfig: { groups: ['sales'] }, isInclude: true })
		await wrapper.find('[data-test="rule-type-select"] .opt[data-id="date"]').trigger('click')

		expect(wrapper.find('[data-test="rule-type-select"]').attributes('data-selected')).toBe('date')
		expect(lastUpdate(wrapper)).toEqual({ ruleType: 'date', ruleConfig: {}, isInclude: true })
		expect(wrapper.find('[data-test="rule-start-date"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="rule-groups"]').exists()).toBe(false)
	})

	it('ignores re-selecting the current type and a cleared selection', async () => {
		const wrapper = mountRow({ ruleType: 'group', ruleConfig: { groups: ['sales'] }, isInclude: true })
		await wrapper.find('[data-test="rule-type-select"] .opt[data-id="group"]').trigger('click')
		await wrapper.find('[data-test="rule-type-select"] .clear').trigger('click')

		expect(wrapper.emitted('update:rule')).toBeUndefined()
		expect(wrapper.vm.local.ruleConfig).toEqual({ groups: ['sales'] })
	})
})

describe('VisibilityRuleRow — group fields', () => {
	it('picking a group updates the config, emits it live and enables Save', async () => {
		const wrapper = mountRow({ ruleType: 'group', ruleConfig: { groups: [] }, isInclude: true }, { isNew: true })
		const saveBtn = wrapper.find('[data-test="rule-save"]')
		expect(saveBtn.attributes('disabled')).toBeDefined()
		expect(saveBtn.text()).toBe('Add rule')

		await wrapper.find('[data-test="rule-groups"] .tag').trigger('click')

		expect(lastUpdate(wrapper).ruleConfig).toEqual({ groups: ['marketing'] })
		expect(saveBtn.attributes('disabled')).toBeUndefined()
		await saveBtn.trigger('click')
		expect(wrapper.emitted('save')[0][0]).toEqual({
			ruleType: 'group',
			ruleConfig: { groups: ['marketing'] },
			isInclude: true,
		})
	})

	it('labels the actions Save / Remove for a persisted rule and disables them while busy', () => {
		const wrapper = mountRow({ id: 4, ruleType: 'group', ruleConfig: { groups: ['sales'] }, isInclude: true }, { busy: true })
		expect(wrapper.find('[data-test="rule-save"]').text()).toBe('Save')
		expect(wrapper.find('[data-test="rule-remove"]').text()).toBe('Remove')
		expect(wrapper.find('[data-test="rule-save"]').attributes('disabled')).toBeDefined()
		expect(wrapper.find('[data-test="rule-remove"]').attributes('disabled')).toBeDefined()
		expect(wrapper.find('[data-test="rule-mode-include"] input').attributes('name')).toBe('rule-mode-4')
	})
})

describe('VisibilityRuleRow — time fields', () => {
	it('typing start/end times emits them and flags a malformed time inline', async () => {
		const wrapper = mountRow({ ruleType: 'time', ruleConfig: {}, isInclude: true })
		await wrapper.find('[data-test="rule-start-time"] input').setValue('9am')
		await wrapper.find('[data-test="rule-end-time"] input').setValue('17:00')

		expect(wrapper.find('[data-test="rule-start-time"]').attributes('data-error')).toBe('true')
		expect(wrapper.find('[data-test="rule-start-time"] .helper').text()).toBe('Use HH:MM, e.g. 09:00')
		expect(wrapper.find('[data-test="rule-end-time"]').attributes('data-error')).toBe('false')
		expect(wrapper.find('[data-test="rule-save"]').attributes('disabled')).toBeDefined()

		await wrapper.find('[data-test="rule-start-time"] input').setValue('09:00')
		expect(lastUpdate(wrapper).ruleConfig).toEqual({ startTime: '09:00', endTime: '17:00' })
		expect(wrapper.find('[data-test="rule-save"]').attributes('disabled')).toBeUndefined()
	})

	it('toggling a day on and off again drops it from the emitted days', async () => {
		const wrapper = mountRow({
			ruleType: 'time',
			ruleConfig: { startTime: '09:00', endTime: '17:00' },
			isInclude: true,
		})
		const toggles = wrapper.findAll('[data-test="rule-day-toggle"] input')
		expect(toggles).toHaveLength(7)

		await toggles[0].setValue(true)
		await toggles[2].setValue(true)
		expect(lastUpdate(wrapper).ruleConfig.days).toEqual(['mon', 'wed'])
		expect(toggles[0].element.checked).toBe(true)

		await toggles[0].setValue(false)
		expect(lastUpdate(wrapper).ruleConfig.days).toEqual(['wed'])

		await toggles[2].setValue(false)
		expect('days' in lastUpdate(wrapper).ruleConfig).toBe(false)
	})
})

describe('VisibilityRuleRow — date fields', () => {
	it('emits both bounds when start and end dates are entered', async () => {
		const wrapper = mountRow({ ruleType: 'date', ruleConfig: {}, isInclude: true })
		await wrapper.find('[data-test="rule-start-date"] input').setValue('2026-12-01')
		await wrapper.find('[data-test="rule-end-date"] input').setValue('2026-12-31')

		expect(lastUpdate(wrapper).ruleConfig).toEqual({ startDate: '2026-12-01', endDate: '2026-12-31' })
		await wrapper.find('[data-test="rule-save"]').trigger('click')
		expect(wrapper.emitted('save')[0][0].ruleConfig).toEqual({
			startDate: '2026-12-01',
			endDate: '2026-12-31',
		})
	})

	it('emits an end-only range and blocks Save on a malformed end date', async () => {
		const wrapper = mountRow({ ruleType: 'date', ruleConfig: {}, isInclude: true })
		await wrapper.find('[data-test="rule-end-date"] input').setValue('2026-12-31')
		expect(lastUpdate(wrapper).ruleConfig).toEqual({ endDate: '2026-12-31' })
		expect(wrapper.vm.isValid).toBe(true)

		await wrapper.find('[data-test="rule-end-date"] input').setValue('31/12/2026')
		expect(wrapper.find('[data-test="rule-end-date"] .helper').text()).toBe('Use YYYY-MM-DD')
		expect(wrapper.vm.isValid).toBe(false)
		await wrapper.find('[data-test="rule-save"]').trigger('click')
		expect(wrapper.emitted('save')).toBeUndefined()
	})

	it('is invalid with neither bound set', () => {
		const wrapper = mountRow({ ruleType: 'date', ruleConfig: {}, isInclude: true })
		expect(wrapper.vm.isValid).toBe(false)
	})
})

describe('VisibilityRuleRow — attribute fields', () => {
	it('typing attribute and value and choosing an operator emits the full config', async () => {
		const wrapper = mountRow({ ruleType: 'attribute', ruleConfig: {}, isInclude: true })
		await wrapper.find('[data-test="rule-attribute"] input').setValue('language')
		await wrapper.find('[data-test="rule-value"] input').setValue('nl')
		await wrapper.find('[data-test="rule-operator"] .opt[data-id="starts_with"]').trigger('click')

		expect(wrapper.find('[data-test="rule-operator"]').attributes('data-selected')).toBe('starts_with')
		expect(lastUpdate(wrapper).ruleConfig).toEqual({
			attribute: 'language',
			operator: 'starts_with',
			value: 'nl',
		})
	})

	it('falls back to equals when the operator selection is cleared', async () => {
		const wrapper = mountRow({
			ruleType: 'attribute',
			ruleConfig: { attribute: 'dept', operator: 'contains', value: 'IT' },
			isInclude: true,
		})
		expect(wrapper.find('[data-test="rule-operator"]').attributes('data-selected')).toBe('contains')

		await wrapper.find('[data-test="rule-operator"] .clear').trigger('click')

		expect(wrapper.vm.local.ruleConfig.operator).toBe('equals')
		expect(lastUpdate(wrapper).ruleConfig).toEqual({ attribute: 'dept', operator: 'equals', value: 'IT' })
	})
})

describe('VisibilityRuleRow — include/exclude radios', () => {
	it('emits isInclude false then true from the exclude and include radios', async () => {
		const wrapper = mountRow({ ruleType: 'group', ruleConfig: { groups: ['sales'] }, isInclude: true })
		await wrapper.find('[data-test="rule-mode-exclude"] input').setValue(true)
		expect(lastUpdate(wrapper).isInclude).toBe(false)
		expect(wrapper.find('[data-test="rule-mode-exclude"] input').element.checked).toBe(true)

		await wrapper.find('[data-test="rule-mode-include"] input').setValue(true)
		expect(lastUpdate(wrapper).isInclude).toBe(true)
		expect(wrapper.emitted('update:rule')).toHaveLength(2)
	})
})

describe('VisibilityRuleRow — unknown rule type', () => {
	it('keeps the given config, is never valid and emits an empty ruleConfig', async () => {
		const wrapper = mountRow({ ruleType: 'weather', ruleConfig: { sunny: true }, isInclude: false })

		expect(wrapper.vm.local.ruleConfig).toEqual({ sunny: true })
		expect(wrapper.vm.isValid).toBe(false)
		expect(wrapper.find('[data-test="rule-type-select"]').attributes('data-selected')).toBe('group')

		await wrapper.find('[data-test="rule-mode-include"] input').setValue(true)
		expect(lastUpdate(wrapper)).toEqual({ ruleType: 'weather', ruleConfig: {}, isInclude: true })
		await wrapper.find('[data-test="rule-save"]').trigger('click')
		expect(wrapper.emitted('save')).toBeUndefined()
	})
})
