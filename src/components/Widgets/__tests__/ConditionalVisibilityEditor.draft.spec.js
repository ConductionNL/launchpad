/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The rules dialog during edit mode changes the draft only (REQ-EDIT-001):
 * no rule request is sent; the draft gets the rules and how they combine,
 * and a dialog opened again shows the draft's rules.
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */

import { flushPromises, mount } from '@vue/test-utils'
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
		updateWidgetPlacement: vi.fn(),
	},
}))

const RuleRowStub = {
	props: ['rule', 'availableGroups', 'busy', 'isNew'],
	template: `<div class="rule-row-stub" :data-rule-id="rule.id">
		<button class="save-btn" @click="$emit('save', { ruleType: 'group', ruleConfig: { groups: ['hr'] }, isInclude: rule.isInclude })">save</button>
		<button class="remove-btn" @click="$emit('remove')">remove</button>
	</div>`,
}

const stubs = {
	VisibilityRuleRow: RuleRowStub,
	NcButton: {
		emits: ['click'],
		template: '<button :data-test="$attrs[\'data-test\']" :disabled="$attrs.disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcSelectTags: true,
	NcTextField: true,
	NcLoadingIcon: true,
	Plus: true,
	Eye: true,
	EyeOff: true,
}

/**
 * The editor on widget 10 with a draft.
 *
 * @param {Array<object>|null} rules The draft's rules for the widget.
 * @return {{wrapper: object, draft: object}}
 */
function mountWithDraft(rules = null) {
	const draft = { rules, setRules: vi.fn(), setMatch: vi.fn() }
	const wrapper = mount(ConditionalVisibilityEditor, { props: { placementId: 10, draft }, global: { stubs } })
	return { wrapper, draft }
}

describe('ConditionalVisibilityEditor in an edit-mode draft (REQ-EDIT-001)', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		globalThis.t = (_app, s) => s
		api.getWidgetRules.mockResolvedValue({ data: { rules: [{ id: 5, ruleType: 'group', ruleConfig: { groups: ['it'] }, isInclude: true }] } })
		api.previewVisibility.mockResolvedValue({ data: {} })
	})

	it('adds a rule to the draft, not through the rule routes', async () => {
		const { wrapper, draft } = mountWithDraft()
		await flushPromises()
		await wrapper.find('[data-test="add-rule"]').trigger('click')
		const rows = wrapper.findAll('.rule-row-stub')
		await rows[rows.length - 1].find('.save-btn').trigger('click')

		expect(api.addWidgetRule).not.toHaveBeenCalled()
		expect(draft.setRules).toHaveBeenLastCalledWith([
			{ id: 5, ruleType: 'group', ruleConfig: { groups: ['it'] }, isInclude: true },
			{ id: null, ruleType: 'group', ruleConfig: { groups: ['hr'] }, isInclude: true },
		])
		expect(wrapper.emitted('ruleAdded')).toHaveLength(1)
	})

	it('removes a stored rule from the draft without deleting it', async () => {
		const { wrapper, draft } = mountWithDraft()
		await flushPromises()
		await wrapper.find('[data-test="rule-card-delete"]').trigger('click')

		expect(api.deleteRule).not.toHaveBeenCalled()
		expect(draft.setRules).toHaveBeenLastCalledWith([])
	})

	it('shows the draft rules when opened again, without reading the stored ones', async () => {
		const { wrapper } = mountWithDraft([{ id: null, ruleType: 'group', ruleConfig: { groups: ['hr'] }, isInclude: false }])
		await flushPromises()

		expect(api.getWidgetRules).not.toHaveBeenCalled()
		expect(wrapper.vm.rules).toHaveLength(1)
		expect(wrapper.vm.isEditing(wrapper.vm.rules[0])).toBe(false)
	})

	it('sets how the rules combine in the draft', async () => {
		const { wrapper, draft } = mountWithDraft()
		await flushPromises()
		await wrapper.vm.setMatchMode('all')

		expect(api.updateWidgetPlacement).not.toHaveBeenCalled()
		expect(draft.setMatch).toHaveBeenCalledWith('all')
		expect(wrapper.emitted('ruleMatchChanged')).toEqual([['all']])
	})
})
