/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `VisibilityRulesModal.vue`.
 *
 * As of the conditional-visibility-editor change, this modal is a thin
 * NcModal wrapper around `ConditionalVisibilityEditor` — all rule
 * list/add/edit/delete/preview behaviour is covered directly by
 * `ConditionalVisibilityEditor.spec.js` and `VisibilityRuleRow.spec.js`.
 * This file only covers the wrapper's own responsibilities: showing/hiding
 * on `open`, forwarding `placementId` / `availableGroups`, and re-emitting
 * the editor's `rule-added` / `rule-updated` / `rule-removed` events plus
 * its own `close`.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import VisibilityRulesModal from '../VisibilityRulesModal.vue'
import { api } from '../../services/api.js'

vi.mock('../../services/api.js', () => ({
	api: { getAdminGroups: vi.fn() },
}))

const ConditionalVisibilityEditorStub = {
	props: ['placementId', 'availableGroups'],
	template: '<div class="editor-stub" :data-placement-id="placementId" />',
}

const stubs = {
	NcModal: { template: '<div class="nc-modal-stub"><slot /></div>' },
	ConditionalVisibilityEditor: ConditionalVisibilityEditorStub,
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
	api.getAdminGroups.mockResolvedValue({ data: { active: [], inactive: [], allKnown: [] } })
})

describe('VisibilityRulesModal', () => {
	it('does not render when closed', () => {
		const wrapper = mountModal({ open: false })
		expect(wrapper.find('.nc-modal-stub').exists()).toBe(false)
	})

	it('renders ConditionalVisibilityEditor with the placement id and available groups forwarded', () => {
		const wrapper = mountModal({
			placementId: 42,
			availableGroups: ['marketing'],
		})
		const editor = wrapper.findComponent(ConditionalVisibilityEditorStub)

		expect(editor.exists()).toBe(true)
		expect(editor.props('placementId')).toBe(42)
		expect(editor.props('availableGroups')).toEqual(['marketing'])
	})

	it('re-emits rule-added / rule-updated / rule-removed from the editor', () => {
		const wrapper = mountModal()
		const editor = wrapper.findComponent(ConditionalVisibilityEditorStub)

		editor.vm.$emit('ruleAdded')
		editor.vm.$emit('ruleUpdated')
		editor.vm.$emit('ruleRemoved')

		expect(wrapper.emitted('ruleAdded')).toBeTruthy()
		expect(wrapper.emitted('ruleUpdated')).toBeTruthy()
		expect(wrapper.emitted('ruleRemoved')).toBeTruthy()
	})

	it('offers the instance\'s groups when none are passed (launchpad#97)', async () => {
		const groups = [{ id: 'mkt', displayName: 'Marketing' }]
		api.getAdminGroups.mockResolvedValue({ data: { active: [], inactive: groups, allKnown: groups } })
		const wrapper = mountModal()
		await flushPromises()

		expect(api.getAdminGroups).toHaveBeenCalledTimes(1)
		expect(wrapper.findComponent(ConditionalVisibilityEditorStub).props('availableGroups')).toEqual(groups)
	})

	it('does not read the groups when a list is passed or the modal is closed', async () => {
		mountModal({ availableGroups: ['marketing'] })
		mountModal({ open: false })
		await flushPromises()

		expect(api.getAdminGroups).not.toHaveBeenCalled()
	})

	it('offers no groups when the list is refused', async () => {
		api.getAdminGroups.mockRejectedValue(new Error('403'))
		const wrapper = mountModal()
		await flushPromises()

		expect(wrapper.findComponent(ConditionalVisibilityEditorStub).props('availableGroups')).toEqual([])
	})
})
