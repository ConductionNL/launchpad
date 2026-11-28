/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The group pickers offer Nextcloud groups, not system tags: `NcSelectTags`
 * fetches system tags unless `fetchTags` is off, and labels its options by
 * `displayName`, so the offered groups reach it as `{id, displayName}` with
 * fetching off (launchpad#97).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ConditionalVisibilityEditor from '../ConditionalVisibilityEditor.vue'
import VisibilityRuleRow from '../VisibilityRuleRow.vue'

vi.mock('../../../services/api.js', () => ({
	api: { getWidgetRules: vi.fn(async () => ({ data: { rules: [] } })) },
}))

const SelectTags = {
	name: 'NcSelectTags',
	props: ['modelValue', 'options', 'fetchTags'],
	template: '<div class="nc-selecttags-stub" />',
}

const stubs = {
	NcButton: { template: '<button><slot /></button>' },
	NcSelect: { template: '<div />' },
	NcSelectTags: SelectTags,
	NcTextField: { template: '<input />' },
	NcCheckboxRadioSwitch: { template: '<label><slot /></label>' },
	NcLoadingIcon: { template: '<span />' },
	VisibilityRuleRow: { template: '<div />' },
	Delete: { template: '<span />' },
	Plus: { template: '<span />' },
	Eye: { template: '<span />' },
	EyeOff: { template: '<span />' },
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('group pickers', () => {
	it('the rule row offers the groups, with tag fetching off, and keeps a chosen group', () => {
		const wrapper = mount(VisibilityRuleRow, {
			props: {
				rule: { ruleType: 'group', ruleConfig: { groups: ['gone'] }, isInclude: true },
				availableGroups: [{ id: 'mkt', displayName: 'Marketing' }, 'sales'],
			},
			global: { stubs },
		})
		const picker = wrapper.findComponent(SelectTags)
		expect(picker.props('fetchTags')).toBe(false)
		expect(picker.props('options')).toEqual([
			{ id: 'mkt', displayName: 'Marketing' },
			{ id: 'sales', displayName: 'sales' },
			{ id: 'gone', displayName: 'gone' },
		])
	})

	it('a group rule\'s card shows its groups as chips, by display name', async () => {
		const { api } = await import('../../../services/api.js')
		api.getWidgetRules.mockResolvedValueOnce({
			data: { rules: [{ id: 1, ruleType: 'group', ruleConfig: { groups: ['kcc', 'gone'] }, isInclude: true }] },
		})
		const wrapper = mount(ConditionalVisibilityEditor, {
			props: { placementId: 10, availableGroups: [{ id: 'kcc', displayName: 'KCC' }] },
			global: { stubs },
		})
		await flushPromises()
		const chips = wrapper.findAll('[data-test="rule-card-value"] .conditional-visibility-editor__chip')
		expect(chips.map((chip) => chip.text())).toEqual(['KCC', 'gone'])
		expect(wrapper.find('[data-test="rule-card-kind"]').text()).toBe('Groups')
	})
})
