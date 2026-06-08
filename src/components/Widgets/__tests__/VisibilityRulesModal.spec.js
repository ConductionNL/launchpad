/**
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `VisibilityRulesModal.vue` (conditional-visibility
 * spec). Covers:
 *  - opening fetches the placement's rules and lists them
 *  - adding a group rule POSTs and appends the new rule (happy path)
 *  - deleting a rule calls the API and removes it from the list
 *  - a failed delete rolls the list back
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => `/index.php${p}` }))

vi.mock('../../../services/api.js', () => ({
	api: {
		getWidgetRules: vi.fn(),
		addWidgetRule: vi.fn(),
		deleteRule: vi.fn(),
	},
}))

import { api } from '../../../services/api.js'
import VisibilityRulesModal from '../VisibilityRulesModal.vue'

const stubs = {
	NcModal: { template: '<div><slot /></div>' },
	NcButton: { template: '<button @click="$emit(\'click\')"><slot /></button>' },
	NcSelect: { template: '<div class="nc-select-stub" />' },
	NcSelectTags: { template: '<div class="nc-selecttags-stub" />' },
	NcTextField: { template: '<input />' },
	NcLoadingIcon: { template: '<span />' },
}

function mountModal(props = {}) {
	return mount(VisibilityRulesModal, {
		propsData: { open: true, placementId: 10, ...props },
		stubs,
	})
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	vi.clearAllMocks()
})

describe('VisibilityRulesModal', () => {
	it('fetches and lists rules on open', async () => {
		api.getWidgetRules.mockResolvedValue({
			data: { data: { rules: [
				{ id: 1, ruleType: 'group', ruleConfig: { groups: ['marketing'] }, isInclude: true },
			] } },
		})
		const wrapper = mountModal()
		await new Promise((r) => setTimeout(r, 0))
		await wrapper.vm.$nextTick()
		expect(api.getWidgetRules).toHaveBeenCalledWith(10)
		expect(wrapper.findAll('[data-test="visibility-rules-item"]').length).toBe(1)
	})

	it('adds a group rule and appends it on success', async () => {
		api.getWidgetRules.mockResolvedValue({ data: { data: { rules: [] } } })
		api.addWidgetRule.mockResolvedValue({
			data: { data: { id: 99, ruleType: 'group', ruleConfig: { groups: ['sales'] }, isInclude: true } },
		})
		const wrapper = mountModal()
		await new Promise((r) => setTimeout(r, 0))
		await wrapper.vm.$nextTick()

		// Drive the draft directly (NcSelect/NcSelectTags are stubbed).
		wrapper.vm.draft.type = { id: 'group', label: 'Group' }
		wrapper.vm.draft.groups = ['sales']
		await wrapper.vm.addRule()

		expect(api.addWidgetRule).toHaveBeenCalledWith(10, {
			ruleType: 'group',
			ruleConfig: { groups: ['sales'] },
			isInclude: true,
		})
		expect(wrapper.vm.rules.map(r => r.id)).toContain(99)
		expect(wrapper.emitted('rule-added')).toBeTruthy()
	})

	it('removes a rule via the API and emits rule-removed', async () => {
		api.getWidgetRules.mockResolvedValue({
			data: { data: { rules: [{ id: 5, ruleType: 'time', ruleConfig: {}, isInclude: false }] } },
		})
		api.deleteRule.mockResolvedValue({ data: { status: 'ok' } })
		const wrapper = mountModal()
		await new Promise((r) => setTimeout(r, 0))
		await wrapper.vm.$nextTick()

		await wrapper.vm.removeRule({ id: 5 })
		expect(api.deleteRule).toHaveBeenCalledWith(5)
		expect(wrapper.vm.rules.length).toBe(0)
		expect(wrapper.emitted('rule-removed')).toBeTruthy()
	})

	it('rolls the list back when delete fails', async () => {
		api.getWidgetRules.mockResolvedValue({
			data: { data: { rules: [{ id: 7, ruleType: 'date', ruleConfig: {}, isInclude: true }] } },
		})
		api.deleteRule.mockRejectedValue(new Error('boom'))
		const wrapper = mountModal()
		await new Promise((r) => setTimeout(r, 0))
		await wrapper.vm.$nextTick()

		await wrapper.vm.removeRule({ id: 7 })
		expect(wrapper.vm.rules.length).toBe(1)
		expect(wrapper.vm.rules[0].id).toBe(7)
	})
})
