/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ConditionalVisibilityEditor.vue`
 * (conditional-visibility-editor spec). Covers:
 *  - loads a placement's rules on open (REQ-CVUI-001)
 *  - groups rules under "Show when…" (include) / "Hide when…" (exclude)
 *    headings, moving a row when its mode toggles (REQ-CVUI-003)
 *  - empty state explains default visibility (REQ-CVUI-003)
 *  - adding + saving a new rule POSTs; editing + saving an existing rule
 *    PUTs; deleting an existing rule DELETEs; discarding an unsaved draft
 *    makes NO API call (REQ-CVUI-001)
 *  - preview affordance calls the preview endpoint with the in-editor rule
 *    set and renders Visible/Hidden with matched-rule reasons (REQ-CVUI-004)
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { api } from '../../../services/api.js'
import ConditionalVisibilityEditor from '../ConditionalVisibilityEditor.vue'

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

describe('ConditionalVisibilityEditor', () => {
	it('loads and lists rules split into include / exclude sections', async () => {
		api.getWidgetRules.mockResolvedValue({
			data: {
				rules: [
					{
						id: 1,
						ruleType: 'group',
						ruleConfig: { groups: ['marketing'] },
						isInclude: true,
					},
					{
						id: 2,
						ruleType: 'date',
						ruleConfig: {
							startDate: '2026-07-01',
							endDate: '2026-07-31',
						},
						isInclude: false,
					},
				],
			},
		})
		const wrapper = mountEditor()
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		expect(api.getWidgetRules).toHaveBeenCalledWith(10)
		expect(wrapper.find('[data-test="include-section"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="exclude-section"]').exists()).toBe(true)
		expect(
			wrapper.findAll('[data-test="visibility-rule-row-include"]'),
		).toHaveLength(1)
		expect(
			wrapper.findAll('[data-test="visibility-rule-row-exclude"]'),
		).toHaveLength(1)
	})

	it('shows the empty-state message explaining default visibility when there are no rules', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [] } })
		const wrapper = mountEditor()
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		expect(wrapper.find('[data-test="visibility-empty-state"]').exists()).toBe(
			true,
		)
	})

	it("toggling a row's mode moves it live from the include to the exclude section", async () => {
		api.getWidgetRules.mockResolvedValue({
			data: {
				rules: [
					{
						id: 1,
						ruleType: 'group',
						ruleConfig: { groups: ['marketing'] },
						isInclude: true,
					},
				],
			},
		})
		const wrapper = mountEditor()
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		expect(
			wrapper.findAll('[data-test="visibility-rule-row-include"]'),
		).toHaveLength(1)

		await wrapper.find('.toggle-btn').trigger('click')

		expect(
			wrapper.findAll('[data-test="visibility-rule-row-include"]'),
		).toHaveLength(0)
		expect(
			wrapper.findAll('[data-test="visibility-rule-row-exclude"]'),
		).toHaveLength(1)
	})

	it('adding a rule then saving it POSTs to addWidgetRule (new row, no id yet)', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [] } })
		api.addWidgetRule.mockResolvedValue({
			data: {
				id: 99,
				ruleType: 'group',
				ruleConfig: { groups: ['sales'] },
				isInclude: true,
			},
		})
		const wrapper = mountEditor()
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		await wrapper.find('[data-test="add-rule"]').trigger('click')
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.rule-row-stub').attributes('data-is-new')).toBe('true')

		await wrapper.find('.save-btn').trigger('click')
		await wrapper.vm.$nextTick()

		expect(api.addWidgetRule).toHaveBeenCalledWith(10, {
			ruleType: 'group',
			ruleConfig: { groups: [] },
			isInclude: true,
		})
		expect(wrapper.emitted('rule-added')).toBeTruthy()
		expect(wrapper.vm.rules[0].id).toBe(99)
	})

	it('saving an existing (already-persisted) row PUTs to updateRule', async () => {
		api.getWidgetRules.mockResolvedValue({
			data: {
				rules: [
					{
						id: 5,
						ruleType: 'group',
						ruleConfig: { groups: ['marketing'] },
						isInclude: true,
					},
				],
			},
		})
		api.updateRule.mockResolvedValue({
			data: {
				id: 5,
				ruleType: 'group',
				ruleConfig: { groups: ['marketing', 'management'] },
				isInclude: true,
			},
		})
		const wrapper = mountEditor()
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		await wrapper.find('.save-btn').trigger('click')
		await wrapper.vm.$nextTick()

		expect(api.updateRule).toHaveBeenCalledWith(5, {
			ruleType: 'group',
			ruleConfig: { groups: ['marketing'] },
			isInclude: true,
		})
		expect(api.addWidgetRule).not.toHaveBeenCalled()
		expect(wrapper.emitted('rule-updated')).toBeTruthy()
	})

	it('removing an existing rule calls deleteRule and emits rule-removed', async () => {
		api.getWidgetRules.mockResolvedValue({
			data: {
				rules: [
					{ id: 5, ruleType: 'time', ruleConfig: {}, isInclude: false },
				],
			},
		})
		api.deleteRule.mockResolvedValue({ data: { status: 'ok' } })
		const wrapper = mountEditor()
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		await wrapper.find('.remove-btn').trigger('click')
		await wrapper.vm.$nextTick()

		expect(api.deleteRule).toHaveBeenCalledWith(5)
		expect(wrapper.emitted('rule-removed')).toBeTruthy()
		expect(wrapper.vm.rules).toHaveLength(0)
	})

	it('discarding a never-saved draft row makes NO API call', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { rules: [] } })
		const wrapper = mountEditor()
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		await wrapper.find('[data-test="add-rule"]').trigger('click')
		await wrapper.vm.$nextTick()
		await wrapper.find('.remove-btn').trigger('click')
		await wrapper.vm.$nextTick()

		expect(api.deleteRule).not.toHaveBeenCalled()
		expect(wrapper.vm.rules).toHaveLength(0)
	})

	it('preview: calls the preview endpoint with the in-editor rule set and shows Visible', async () => {
		api.getWidgetRules.mockResolvedValue({
			data: {
				rules: [
					{
						id: 1,
						ruleType: 'group',
						ruleConfig: { groups: ['marketing'] },
						isInclude: true,
					},
				],
			},
		})
		api.previewVisibility.mockResolvedValue({
			data: {
				visible: true,
				matchedIncludeRuleIds: [1],
				matchedExcludeRuleIds: [],
			},
		})
		const wrapper = mountEditor()
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		wrapper.vm.previewGroups = ['marketing']
		await wrapper.find('[data-test="run-preview"]').trigger('click')
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		expect(api.previewVisibility).toHaveBeenCalled()
		const body = api.previewVisibility.mock.calls[0][0]
		expect(body.rules).toEqual([
			{
				id: 1,
				ruleType: 'group',
				ruleConfig: { groups: ['marketing'] },
				isInclude: true,
			},
		])
		expect(body.context.groups).toEqual(['marketing'])

		expect(wrapper.find('[data-test="preview-verdict-text"]').text()).toBe(
			'Visible',
		)
		expect(wrapper.find('[data-test="preview-matched-include"]').exists()).toBe(
			true,
		)
	})

	it('preview: shows Hidden with no matched include rule for a non-matching audience', async () => {
		api.getWidgetRules.mockResolvedValue({
			data: {
				rules: [
					{
						id: 1,
						ruleType: 'group',
						ruleConfig: { groups: ['marketing'] },
						isInclude: true,
					},
				],
			},
		})
		api.previewVisibility.mockResolvedValue({
			data: {
				visible: false,
				matchedIncludeRuleIds: [],
				matchedExcludeRuleIds: [],
			},
		})
		const wrapper = mountEditor()
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		await wrapper.find('[data-test="run-preview"]').trigger('click')
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		expect(wrapper.find('[data-test="preview-verdict-text"]').text()).toBe(
			'Hidden',
		)
		expect(wrapper.find('[data-test="preview-matched-include"]').exists()).toBe(
			false,
		)
	})
})
