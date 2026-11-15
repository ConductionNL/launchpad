/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * AiAssistantWidget guards (launchpad-ai-dashboard-assistant): outside the
 * workspace page the chat context carries no dashboard uuid; a blank
 * question or an unavailable Hermiq never reaches the chat; a failing send
 * leaves the error on screen instead of throwing.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import AiAssistantWidget from '../AiAssistantWidget.vue'

const state = vi.hoisted(() => ({ storeThrows: false, sendError: null }))
const created = []
vi.mock('@conduction/nextcloud-vue', () => ({
	chatHealthUrl: (appId) => `/index.php/apps/${appId}/api/chat/health`,
	useAiChatStream: (instance, options) => {
		const chat = reactive({
			messages: [],
			isStreaming: false,
			currentText: '',
			error: null,
			options,
		})
		chat.send = vi.fn(async () => {
			if (state.sendError) {
				chat.error = state.sendError
				throw state.sendError
			}
		})
		created.push(chat)
		return chat
	},
}))
vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))
vi.mock('../../../../stores/dashboard.js', () => ({
	useDashboardStore: () => {
		if (state.storeThrows) {
			throw new Error('no active pinia')
		}
		return { activeDashboard: { uuid: 'team' } }
	},
}))

const big = { id: 1, gridWidth: 4, gridHeight: 4 }
const small = { id: 2, gridWidth: 2, gridHeight: 2 }

beforeEach(() => {
	vi.clearAllMocks()
	created.length = 0
	state.storeThrows = false
	state.sendError = null
})

describe('AiAssistantWidget guards', () => {
	it('sends a null dashboard uuid when the dashboard store is unavailable', async () => {
		state.storeThrows = true
		axios.get.mockResolvedValue({ status: 200, data: {} })
		mount(AiAssistantWidget, { props: { content: {}, placement: big } })
		await flushPromises()
		expect(created[0].options.context.objectUuid).toBeNull()
		expect(created[0].options.context.appId).toBe('launchpad')
	})

	it('does not send a whitespace-only question', async () => {
		axios.get.mockResolvedValue({ status: 200, data: {} })
		const wrapper = mount(AiAssistantWidget, {
			props: { content: {}, placement: big },
		})
		await flushPromises()
		const input = wrapper.find('[data-testid="ai-assistant-input"]')
		await input.setValue('   ')
		expect(
			wrapper.find('[data-testid="ai-assistant-send"]').attributes('disabled'),
		).toBeDefined()
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(created[0].send).not.toHaveBeenCalled()
		expect(input.element.value).toBe('   ')
	})

	it('does not send a question while the health probe is still pending', async () => {
		axios.get.mockReturnValue(new Promise(() => {}))
		const wrapper = mount(AiAssistantWidget, {
			props: { content: {}, placement: big },
		})
		await flushPromises()
		const input = wrapper.find('[data-testid="ai-assistant-input"]')
		expect(input.attributes('disabled')).toBeDefined()
		// v-model still writes through the disabled input's value.
		await input.setValue('Anything overdue?')
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(created[0].send).not.toHaveBeenCalled()
	})

	it('does not summarise before Hermiq has answered the health probe', async () => {
		axios.get.mockReturnValue(new Promise(() => {}))
		const wrapper = mount(AiAssistantWidget, {
			props: { content: {}, placement: small },
		})
		await flushPromises()
		const button = wrapper.find('[data-testid="ai-assistant-summarise"]')
		expect(button.attributes('disabled')).toBeDefined()
		await wrapper.vm.summarise()
		expect(created[0].send).not.toHaveBeenCalled()
	})

	it('shows the chat error when sending a question fails', async () => {
		state.sendError = new Error('Hermiq timed out')
		axios.get.mockResolvedValue({ status: 200, data: {} })
		const wrapper = mount(AiAssistantWidget, {
			props: { content: { agentUuid: 'a-9' }, placement: big },
		})
		await flushPromises()
		await wrapper
			.find('[data-testid="ai-assistant-input"]')
			.setValue('  Status?  ')
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(created[0].send).toHaveBeenCalledWith('Status?', { agentUuid: 'a-9' })
		expect(wrapper.find('[data-testid="ai-assistant-error"]').text()).toBe(
			'Hermiq timed out',
		)
		expect(
			wrapper.find('[data-testid="ai-assistant-input"]').element.value,
		).toBe('')
	})

	it('shows the chat error when the summary request fails', async () => {
		state.sendError = new Error('No agent')
		axios.get.mockResolvedValue({ status: 200, data: {} })
		const wrapper = mount(AiAssistantWidget, {
			props: { content: {}, placement: small },
		})
		await flushPromises()
		await wrapper.find('[data-testid="ai-assistant-summarise"]').trigger('click')
		await flushPromises()
		expect(created[0].send).toHaveBeenCalledWith(
			'Summarise this dashboard for me.',
			{ agentUuid: '' },
		)
		expect(wrapper.find('[data-testid="ai-assistant-error"]').text()).toBe(
			'No agent',
		)
	})
})
