/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the edge paths of `ConditionalVisibilityEditor.vue`
 * that the main spec does not reach: no placement means no load, a failed
 * load or delete leaves a consistent list, a create answer without an id
 * reloads, the preview sends the date/time as ISO and names matched exclude
 * rules, a failed preview shows its error, and describeRule() summarises
 * every rule type.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ConditionalVisibilityEditor from '../ConditionalVisibilityEditor.vue'
import { api } from '../../../services/api.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => `/index.php${p}` }))

vi.mock('../../../services/api.js', () => ({
	api: {
		getWidgetRules: vi.fn(),
		addWidgetRule: vi.fn(),
		updateRule: vi.fn(),
		deleteRule: vi.fn(),
		previewVisibility: vi.fn(),
	},
}))

// VisibilityRuleRow is OUR OWN component with its own dedicated spec
// (VisibilityRuleRow.spec.js covers its operand/validation behaviour in
// depth); here it is stubbed to a thin, controllable shell so this file
// stays focused on ConditionalVisibilityEditor's own responsibilities:
// list loading, include/exclude grouping, CRUD wiring, and preview.
const RuleRowStub = {
	props: ['rule', 'availableGroups', 'busy', 'isNew'],
	template: `<div class="rule-row-stub" :data-rule-id="rule.id" :data-is-new="isNew">
		<button class="save-btn" @click="$emit('save', { ruleType: rule.ruleType, ruleConfig: rule.ruleConfig, isInclude: rule.isInclude })">save</button>
		<button class="remove-btn" @click="$emit('remove')">remove</button>
		<button class="toggle-btn" @click="$emit('update:rule', { ruleType: rule.ruleType, ruleConfig: rule.ruleConfig, isInclude: !rule.isInclude })">toggle</button>
	</div>`,
}

const stubs = {
	VisibilityRuleRow: RuleRowStub,
	NcButton: {
		emits: ['click'],
		template:
			'<button :data-test="$attrs[\'data-test\']" :disabled="$attrs.disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcSelectTags: { template: '<div class="nc-selecttags-stub" />' },
	NcTextField: {
		props: ['value'],
		template:
			'<input :data-test="$attrs[\'data-test\']" @input="$emit(\'update:value\', $event.target.value)" />',
	},
	NcLoadingIcon: { template: '<span class="loading" />' },
	Plus: { template: '<span />' },
	Eye: { template: '<span class="icon-eye" />' },
	EyeOff: { template: '<span class="icon-eye-off" />' },
}

function mountEditor(props = {}) {
	return mount(ConditionalVisibilityEditor, {
		propsData: { placementId: 10, ...props },
		stubs,
	})
}

beforeEach(() => {
	globalThis.t = (_app, key, params) => {
		if (!params) {
			return key
		}
		return key.replace(/\{(\w+)\}/g, (_, name) => params[name])
	}
	vi.clearAllMocks()
})

async function settle(wrapper) {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await wrapper.vm.$nextTick()
}

const groupRule = {
	id: 1,
	ruleType: 'group',
	ruleConfig: { groups: ['marketing'] },
	isInclude: true,
}
const timeRule = {
	id: 2,
	ruleType: 'time',
	ruleConfig: { startTime: '09:00', endTime: '17:00' },
	isInclude: false,
}

describe('ConditionalVisibilityEditor — loading', () => {
	it('does not call the API and shows no rules without a placement id', async () => {
		const wrapper = mountEditor({ placementId: null })
		await settle(wrapper)
		expect(api.getWidgetRules).not.toHaveBeenCalled()
		expect(wrapper.vm.rules).toEqual([])
	})

	it('falls back to an empty list when loading fails', async () => {
		api.getWidgetRules.mockRejectedValue(new Error('boom'))
		const wrapper = mountEditor()
		await settle(wrapper)
		expect(api.getWidgetRules).toHaveBeenCalledWith(10)
		expect(wrapper.vm.rules).toEqual([])
		expect(wrapper.vm.loading).toBe(false)
	})

	it('reloads when the placement id changes', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [] } })
		const wrapper = mountEditor()
		await settle(wrapper)
		await wrapper.setProps({ placementId: 11 })
		await settle(wrapper)
		expect(api.getWidgetRules).toHaveBeenLastCalledWith(11)
	})
})

describe('ConditionalVisibilityEditor — row edits', () => {
	it('ignores an update for a row that is no longer in the list', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [groupRule] } })
		const wrapper = mountEditor()
		await settle(wrapper)
		const before = wrapper.vm.rules.slice()
		wrapper.vm.onRowUpdate({ id: 99 }, { isInclude: false })
		expect(wrapper.vm.rules).toEqual(before)
	})

	it('restores the removed row and emits nothing when the delete fails', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [groupRule, timeRule] } })
		api.deleteRule.mockRejectedValue(new Error('denied'))
		const wrapper = mountEditor()
		await settle(wrapper)
		const row = wrapper.vm.rules[0]
		await wrapper.vm.onRowRemove(row)
		expect(api.deleteRule).toHaveBeenCalledWith(1)
		expect(wrapper.vm.rules.map((r) => r.id)).toEqual([1, 2])
		expect(wrapper.emitted('ruleRemoved')).toBeUndefined()
		expect(wrapper.vm.isRowBusy(row)).toBe(false)
	})

	it('reloads the list when a create answer carries no id', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [] } })
		api.addWidgetRule.mockResolvedValue({ data: {} })
		const wrapper = mountEditor()
		await settle(wrapper)
		wrapper.vm.addRule()
		const draft = wrapper.vm.rules[0]
		await wrapper.vm.onRowSave(draft, { ruleType: 'group', ruleConfig: { groups: ['a'] }, isInclude: true })
		expect(api.addWidgetRule).toHaveBeenCalledWith(10, { ruleType: 'group', ruleConfig: { groups: ['a'] }, isInclude: true })
		expect(api.getWidgetRules).toHaveBeenCalledTimes(2)
		expect(wrapper.emitted('ruleAdded')).toHaveLength(1)
	})

	it('emits nothing and clears the busy flag when a save fails', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [groupRule] } })
		api.updateRule.mockRejectedValue(new Error('bad'))
		const wrapper = mountEditor()
		await settle(wrapper)
		const row = wrapper.vm.rules[0]
		await wrapper.vm.onRowSave(row, { ruleType: 'group', ruleConfig: {}, isInclude: true })
		expect(wrapper.emitted('ruleUpdated')).toBeUndefined()
		expect(wrapper.vm.isRowBusy(row)).toBe(false)
	})

	it('gives every new draft its own local key', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [] } })
		const wrapper = mountEditor()
		await settle(wrapper)
		wrapper.vm.addRule()
		wrapper.vm.addRule()
		const keys = wrapper.vm.rules.map((r) => wrapper.vm.rowKey(r))
		expect(keys).toEqual(['local--1', 'local--2'])
	})
})

describe('ConditionalVisibilityEditor — preview', () => {
	it('sends the chosen date/time as an ISO string', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [groupRule] } })
		api.previewVisibility.mockResolvedValue({
			data: { visible: true, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [] },
		})
		const wrapper = mountEditor()
		await settle(wrapper)
		wrapper.vm.previewDatetime = '2026-03-01T10:30'
		await wrapper.vm.runPreview()
		const body = api.previewVisibility.mock.calls[0][0]
		expect(body.context.datetime).toBe(new Date('2026-03-01T10:30').toISOString())
	})

	it('names the matched exclude rule when the widget is hidden', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [groupRule, timeRule] } })
		api.previewVisibility.mockResolvedValue({
			data: { visible: false, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [2] },
		})
		const wrapper = mountEditor()
		await settle(wrapper)
		await wrapper.find('[data-test="run-preview"]').trigger('click')
		await settle(wrapper)
		expect(wrapper.find('[data-test="preview-verdict-text"]').text()).toBe('Hidden')
		const reasons = wrapper.findAll('[data-test="preview-matched-exclude"]')
		expect(reasons).toHaveLength(1)
		expect(reasons[0].text()).toBe('Matched exclude rule: 09:00–17:00')
		expect(wrapper.find('[data-test="preview-matched-include"]').exists()).toBe(false)
	})

	it('shows the preview error when the preview request fails', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [groupRule] } })
		api.previewVisibility.mockRejectedValue(new Error('500'))
		const wrapper = mountEditor()
		await settle(wrapper)
		await wrapper.vm.runPreview()
		await settle(wrapper)
		expect(wrapper.find('[data-test="preview-error"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="preview-result"]').exists()).toBe(false)
	})
})

describe('ConditionalVisibilityEditor — describeRule()', () => {
	it('summarises each rule type', () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [] } })
		const vm = mountEditor({ placementId: null }).vm
		expect(vm.describeRule({ ruleType: 'group', ruleConfig: { groups: ['a', 'b'] } })).toBe('a, b')
		expect(vm.describeRule({ ruleType: 'group', ruleConfig: {} })).toBe('')
		expect(vm.describeRule({ ruleType: 'time', ruleConfig: { startTime: '08:00' } })).toBe('08:00–')
		expect(vm.describeRule({ ruleType: 'time' })).toBe('–')
		expect(vm.describeRule({ ruleType: 'date', ruleConfig: { startDate: '2026-01-01', endDate: '2026-02-01' } })).toBe('2026-01-01 → 2026-02-01')
		expect(vm.describeRule({ ruleType: 'date', ruleConfig: {} })).toBe(['…', '…'].join(' → '))
		expect(vm.describeRule({ ruleType: 'attribute', ruleConfig: { attribute: 'dept', operator: 'equals', value: 'IT' } })).toBe('dept equals IT')
		expect(vm.describeRule({ ruleType: 'attribute', ruleConfig: {} })).toBe('  ')
		expect(vm.describeRule({ ruleType: 'something-else', ruleConfig: { x: 1 } })).toBe('')
	})
})
