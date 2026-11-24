/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * "Who sees this widget?" as board LpZichtbaarheid draws it
 * (conditional-visibility-editor REQ-CVUI-006): the two choice cards save
 * how the rules combine, each rule is a card with Edit and Delete, and View
 * as previews the rules for a person at a moment and says so in a sentence.
 *
 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ConditionalVisibilityEditor from '../ConditionalVisibilityEditor.vue'
import { api } from '../../../services/api.js'

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: () => ({ uid: 'admin', displayName: 'Ada Admin' }),
}))

vi.mock('../../../services/api.js', () => ({
	api: {
		getWidgetRules: vi.fn(),
		addWidgetRule: vi.fn(),
		updateRule: vi.fn(),
		deleteRule: vi.fn(),
		previewVisibility: vi.fn(),
		searchSharees: vi.fn(),
		updateWidgetPlacement: vi.fn(),
	},
}))

const RULES = [
	{ id: 1, ruleType: 'group', ruleConfig: { groups: ['kcc'] }, isInclude: true },
	{
		id: 2,
		ruleType: 'time',
		ruleConfig: { startTime: '08:00', endTime: '17:00', days: ['mon', 'tue', 'wed', 'thu', 'fri'] },
		isInclude: true,
	},
]

const stubs = {
	VisibilityRuleRow: { props: ['rule'], template: '<div class="rule-row-stub" :data-rule-id="rule.id" />' },
	NcButton: {
		props: ['disabled'],
		emits: ['click'],
		template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
	},
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options'],
		emits: ['update:modelValue', 'search'],
		template: '<div class="nc-select-stub" />',
	},
	NcTextField: {
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcLoadingIcon: { template: '<span />' },
	Plus: { template: '<span />' },
	Eye: { template: '<span />' },
	EyeOff: { template: '<span />' },
}

/**
 * The editor for placement 10 with the board's two rules, settled.
 *
 * @param {object} props Extra props.
 * @return {Promise<object>} the wrapper.
 */
async function mountEditor(props = {}) {
	api.getWidgetRules.mockResolvedValue({ data: { rules: RULES } })
	const wrapper = mount(ConditionalVisibilityEditor, {
		props: { placementId: 10, availableGroups: [{ id: 'kcc', displayName: 'KCC' }], ...props },
		global: { stubs },
	})
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	vi.clearAllMocks()
})

describe('choice cards: all rules or one rule must match', () => {
	it('shows both cards as a radio group with the stored mode checked', async () => {
		const wrapper = await mountEditor({ ruleMatch: 'all' })
		expect(wrapper.find('[data-test="rule-match"]').attributes('role')).toBe('radiogroup')
		const all = wrapper.find('[data-test="rule-match-all"]')
		const any = wrapper.find('[data-test="rule-match-any"]')
		expect(all.text()).toBe('If all rules match')
		expect(any.text()).toBe('If one rule matches')
		expect(all.attributes('aria-checked')).toBe('true')
		expect(all.attributes('tabindex')).toBe('0')
		expect(any.attributes('aria-checked')).toBe('false')
		expect(any.attributes('tabindex')).toBe('-1')
		expect(wrapper.find('[data-test="include-section"] h3').text()).toBe('Show when ALL of these match')
	})

	it('saves the chosen mode on the placement and tells the dialog', async () => {
		api.updateWidgetPlacement.mockResolvedValue({ data: {} })
		const wrapper = await mountEditor()

		await wrapper.find('[data-test="rule-match-all"]').trigger('click')
		await flushPromises()

		expect(api.updateWidgetPlacement).toHaveBeenCalledWith(10, { ruleMatch: 'all' })
		expect(wrapper.emitted('ruleMatchChanged')).toEqual([['all']])
		expect(wrapper.find('[data-test="rule-match-all"]').attributes('aria-checked')).toBe('true')
	})

	it('takes the choice back when saving fails, and saves nothing for the chosen card', async () => {
		api.updateWidgetPlacement.mockRejectedValue(new Error('403'))
		const wrapper = await mountEditor()

		await wrapper.find('[data-test="rule-match-any"]').trigger('click')
		expect(api.updateWidgetPlacement).not.toHaveBeenCalled()

		await wrapper.find('[data-test="rule-match-all"]').trigger('click')
		await flushPromises()

		expect(wrapper.emitted('ruleMatchChanged')).toBeUndefined()
		expect(wrapper.find('[data-test="rule-match-any"]').attributes('aria-checked')).toBe('true')
	})

	it('moves the choice with the arrow keys', async () => {
		api.updateWidgetPlacement.mockResolvedValue({ data: {} })
		const wrapper = await mountEditor()

		await wrapper.find('[data-test="rule-match-any"]').trigger('keydown', { key: 'ArrowLeft' })
		await flushPromises()

		expect(api.updateWidgetPlacement).toHaveBeenCalledWith(10, { ruleMatch: 'all' })
		await wrapper.find('[data-test="rule-match-all"]').trigger('keydown', { key: 'Enter' })
		expect(api.updateWidgetPlacement).toHaveBeenCalledTimes(1)
	})
})

describe('rule cards', () => {
	it('shows each rule with its kind, its value, Edit and Delete', async () => {
		const wrapper = await mountEditor()
		const cards = wrapper.findAll('[data-test="visibility-rule-card"]')

		expect(cards).toHaveLength(2)
		expect(cards[0].find('[data-test="rule-card-kind"]').text()).toBe('Groups')
		expect(cards[0].find('[data-test="rule-card-value"]').text()).toBe('KCC')
		expect(cards[1].find('[data-test="rule-card-kind"]').text()).toBe('Times')
		expect(cards[1].find('[data-test="rule-card-value"]').text()).toBe('Mon–Fri 08:00–17:00')
		expect(cards[1].find('[data-test="rule-card-edit"]').text()).toBe('Edit')
		expect(cards[1].find('[data-test="rule-card-delete"]').text()).toBe('Delete')
	})

	it('opens and closes a saved rule\'s editor with Edit', async () => {
		const wrapper = await mountEditor()
		expect(wrapper.find('.rule-row-stub').exists()).toBe(false)

		await wrapper.findAll('[data-test="rule-card-edit"]')[1].trigger('click')
		expect(wrapper.find('.rule-row-stub').attributes('data-rule-id')).toBe('2')

		await wrapper.findAll('[data-test="rule-card-edit"]')[1].trigger('click')
		expect(wrapper.find('.rule-row-stub').exists()).toBe(false)
	})
})

describe('View as', () => {
	it('previews as the chosen person at the chosen moment and says why they see the widget', async () => {
		api.searchSharees.mockResolvedValue({ data: { users: [{ id: 'sanne', displayName: 'Sanne de Vries' }], groups: [] } })
		api.previewVisibility.mockResolvedValue({ data: { visible: true, matchedIncludeRuleIds: [1, 2], matchedExcludeRuleIds: [] } })
		api.updateWidgetPlacement.mockResolvedValue({ data: {} })
		const wrapper = await mountEditor()
		await wrapper.find('[data-test="rule-match-all"]').trigger('click')

		const person = wrapper.findComponent({ name: 'NcSelect' })
		expect(person.props('modelValue')).toEqual({ id: 'admin', displayName: 'Ada Admin' })
		person.vm.$emit('search', 'san')
		await flushPromises()
		expect(api.searchSharees).toHaveBeenCalledWith('san')
		expect(person.props('options').map((o) => o.id)).toEqual(['admin', 'sanne'])
		person.vm.$emit('update:modelValue', person.props('options')[1])
		await wrapper.find('[data-test="visibility-preview"] input').setValue('2026-10-13T10:00')

		await wrapper.find('[data-test="run-preview"]').trigger('click')
		await flushPromises()

		const body = api.previewVisibility.mock.calls[0][0]
		expect(body.context).toEqual({ groups: [], datetime: new Date('2026-10-13T10:00').toISOString(), userId: 'sanne' })
		expect(body.match).toBe('all')
		expect(body.placementId).toBe(10)
		const sentence = wrapper.find('[data-test="visibility-sentence"]').text()
		expect(sentence).toMatch(/^Sanne de Vries sees this widget, because Sanne de Vries is in KCC and .+ 10:00 is within the times\.$/)
	})

	it('says why a person does not see the widget', async () => {
		api.previewVisibility.mockResolvedValue({ data: { visible: false, matchedIncludeRuleIds: [1], matchedExcludeRuleIds: [] } })
		api.updateWidgetPlacement.mockResolvedValue({ data: {} })
		const wrapper = await mountEditor({ ruleMatch: 'all' })
		await wrapper.find('[data-test="visibility-preview"] input').setValue('2026-10-17T10:00')

		await wrapper.find('[data-test="run-preview"]').trigger('click')
		await flushPromises()

		expect(api.previewVisibility.mock.calls[0][0].context.userId).toBe('admin')
		expect(wrapper.find('[data-test="preview-verdict-text"]').text()).toBe('Hidden')
		expect(wrapper.find('[data-test="visibility-sentence"]').text()).toMatch(
			/^Ada Admin does not see this widget, because .+ 10:00 is outside the times\.$/,
		)
	})
})
