/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The conversation part of the Sharing tab (REQ-LEAF-006): a switch on a
 * shared dashboard that is stored at once, and a note on an unshared one.
 *
 * @spec openspec/changes/sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SharingConversation from '../SharingConversation.vue'

const api = vi.hoisted(() => ({ updateDashboard: vi.fn() }))
vi.mock('../../../../services/api.js', () => ({ api }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const SwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'loading', 'disabled', 'type'],
	emits: ['update:modelValue'],
	template: '<label class="switch" :data-checked="String(modelValue)"><slot /></label>',
}

function mountIt(props) {
	return mount(SharingConversation, { props, global: { stubs: { NcCheckboxRadioSwitch: SwitchStub } } })
}

describe('SharingConversation', () => {
	beforeEach(() => {
		api.updateDashboard.mockReset()
	})

	it('says the conversation appears once shared, without a switch, on an unshared dashboard', () => {
		const wrapper = mountIt({ dashboard: { id: 1 }, shared: false })
		expect(wrapper.find('[data-test="sharing-conversation-unshared"]').text()).toBe(
			'This dashboard is yours alone. Share it with people or a group and the conversation appears here too.',
		)
		expect(wrapper.findComponent(SwitchStub).exists()).toBe(false)
	})

	it('shows the switch on a shared dashboard, on by default', () => {
		const wrapper = mountIt({ dashboard: { id: 1, conversationEnabled: null }, shared: true })
		expect(wrapper.findComponent(SwitchStub).props('modelValue')).toBe(true)
		expect(wrapper.text()).toContain('Show the conversation beside the dashboard')
	})

	it('stores the switch at once and reports the stored value', async () => {
		api.updateDashboard.mockResolvedValue({ data: {} })
		const wrapper = mountIt({ dashboard: { id: 4, conversationEnabled: 1 }, shared: true })
		wrapper.findComponent(SwitchStub).vm.$emit('update:modelValue', false)
		await flushPromises()
		expect(api.updateDashboard).toHaveBeenCalledWith(4, { conversationEnabled: false })
		expect(wrapper.emitted('update:conversationEnabled')).toEqual([[0]])
		expect(wrapper.findComponent(SwitchStub).props('modelValue')).toBe(false)
	})

	it('returns the switch when storing fails', async () => {
		api.updateDashboard.mockRejectedValue(new Error('down'))
		const wrapper = mountIt({ dashboard: { id: 4, conversationEnabled: 1 }, shared: true })
		wrapper.findComponent(SwitchStub).vm.$emit('update:modelValue', false)
		await flushPromises()
		expect(wrapper.findComponent(SwitchStub).props('modelValue')).toBe(true)
		expect(wrapper.find('[role="alert"]').text()).toBe('The conversation setting could not be saved.')
		expect(wrapper.emitted('update:conversationEnabled')).toBeUndefined()
	})
})
