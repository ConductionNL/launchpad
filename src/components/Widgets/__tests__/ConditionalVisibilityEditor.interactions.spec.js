/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ConditionalVisibilityEditor.vue` driven through its
 * rendered controls: an exclude ("Hide when…") row's live update and save,
 * the Add rule button, the preview audience/date pickers feeding the
 * preview request, and a rules response that is a bare array.
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

// VisibilityRuleRow has its own specs; here it is a thin shell that emits
// the row events so this file stays on the editor's own wiring.
const RuleRowStub = {
	props: ['rule', 'availableGroups', 'busy', 'isNew'],
	emits: ['update:rule', 'save', 'remove'],
	template: `<div class="rule-row-stub" :data-rule-id="rule.id" :data-is-new="String(isNew)">
		<button class="save-btn" @click="$emit('save', { ruleType: rule.ruleType, ruleConfig: { groups: ['sales'] }, isInclude: rule.isInclude })">save</button>
		<button class="toggle-btn" @click="$emit('update:rule', { ruleType: rule.ruleType, ruleConfig: rule.ruleConfig, isInclude: !rule.isInclude })">toggle</button>
	</div>`,
}

const stubs = {
	VisibilityRuleRow: RuleRowStub,
	NcButton: {
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :disabled="disabled" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
	},
	NcSelectTags: {
		props: ['modelValue', 'options'],
		emits: ['update:modelValue'],
		template:
			'<div class="nc-selecttags-stub"><button class="pick-all" @click="$emit(\'update:modelValue\', options.slice())">all</button></div>',
	},
	NcTextField: {
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template:
			'<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcLoadingIcon: { template: '<span class="loading" />' },
	Plus: { template: '<span class="icon-plus" />' },
	Eye: { template: '<span class="icon-eye" />' },
	EyeOff: { template: '<span class="icon-eye-off" />' },
}

function mountEditor(props = {}) {
	return mount(ConditionalVisibilityEditor, {
		props: { placementId: 10, availableGroups: ['admin', 'sales'], ...props },
		global: { stubs },
	})
}

async function settle(wrapper) {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await wrapper.vm.$nextTick()
}

const excludeRule = {
	id: 7,
	ruleType: 'group',
	ruleConfig: { groups: ['admin'] },
	isInclude: false,
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	vi.clearAllMocks()
})

describe('ConditionalVisibilityEditor — exclude rows', () => {
	it('moves an exclude row to the include section on a live update', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [excludeRule] } })
		const wrapper = mountEditor()
		await settle(wrapper)

		const section = wrapper.find('[data-test="exclude-section"]')
		expect(section.findAll('.rule-row-stub')).toHaveLength(1)
		expect(wrapper.find('[data-test="include-section"]').exists()).toBe(false)

		await section.find('.toggle-btn').trigger('click')

		expect(wrapper.find('[data-test="exclude-section"]').exists()).toBe(false)
		const include = wrapper.find('[data-test="include-section"]')
		expect(include.find('.rule-row-stub').attributes('data-rule-id')).toBe('7')
		expect(wrapper.vm.rules[0]).toMatchObject({ id: 7, isInclude: true })
		expect(api.updateRule).not.toHaveBeenCalled()
	})

	it('saving an exclude row PUTs it and merges the server copy', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [excludeRule] } })
		api.updateRule.mockResolvedValue({
			data: { data: { id: 7, ruleType: 'group', ruleConfig: { groups: ['sales'] }, isInclude: false } },
		})
		const wrapper = mountEditor()
		await settle(wrapper)

		await wrapper.find('[data-test="exclude-section"] .save-btn').trigger('click')
		await settle(wrapper)

		expect(api.updateRule).toHaveBeenCalledWith(7, {
			ruleType: 'group',
			ruleConfig: { groups: ['sales'] },
			isInclude: false,
		})
		expect(wrapper.vm.rules[0].ruleConfig).toEqual({ groups: ['sales'] })
		expect(wrapper.emitted('ruleUpdated')).toHaveLength(1)
	})
})

describe('ConditionalVisibilityEditor — loading shapes', () => {
	it('accepts a bare array as the rules response', async () => {
		api.getWidgetRules.mockResolvedValue({ data: [excludeRule] })
		const wrapper = mountEditor()
		await settle(wrapper)

		expect(api.getWidgetRules).toHaveBeenCalledWith(10)
		expect(wrapper.vm.rules).toEqual([{ ...excludeRule, _localKey: undefined }])
		expect(wrapper.find('[data-test="visibility-empty-state"]').exists()).toBe(false)
	})
})

describe('ConditionalVisibilityEditor — add rule button', () => {
	it('renders its icon and appends a new include draft row', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [] } })
		const wrapper = mountEditor()
		await settle(wrapper)

		const addBtn = wrapper.findAll('button').find((b) => b.text() === 'Add rule')
		expect(addBtn.find('.icon-plus').exists()).toBe(true)
		expect(wrapper.find('[data-test="visibility-empty-state"]').exists()).toBe(true)

		await addBtn.trigger('click')

		const row = wrapper.find('[data-test="include-section"] .rule-row-stub')
		expect(row.attributes('data-is-new')).toBe('true')
		expect(wrapper.find('[data-test="visibility-empty-state"]').exists()).toBe(false)
	})
})

describe('ConditionalVisibilityEditor — preview pickers', () => {
	it('sends the groups and date/time chosen in the pickers', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [excludeRule] } })
		api.previewVisibility.mockResolvedValue({
			data: { visible: false, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [7] },
		})
		const wrapper = mountEditor()
		await settle(wrapper)

		await wrapper.find('[data-test="visibility-preview"] .pick-all').trigger('click')
		await wrapper.find('[data-test="visibility-preview"] input').setValue('2026-05-04T08:15')
		const runBtn = wrapper.findAll('button').find((b) => b.text() === 'Preview')
		await runBtn.trigger('click')
		await settle(wrapper)

		expect(api.previewVisibility).toHaveBeenCalledWith({
			rules: [{ id: 7, ruleType: 'group', ruleConfig: { groups: ['admin'] }, isInclude: false }],
			context: {
				groups: ['admin', 'sales'],
				datetime: new Date('2026-05-04T08:15').toISOString(),
			},
		})
		expect(wrapper.find('[data-test="preview-verdict-text"]').text()).toBe('Hidden')
	})
})
