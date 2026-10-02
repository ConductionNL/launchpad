/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * AiAssistantWidget (launchpad-ai-dashboard-assistant REQ-ADA-004,
 * REQ-ADA-006): questions go to Hermiq through useAiChatStream with the
 * dashboard as context; without Hermiq the input is disabled and says so;
 * a small placement summarises.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import AiAssistantWidget from '../AiAssistantWidget.vue'

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
		chat.send = vi.fn(async (content) => {
			chat.messages.push(
				{ role: 'user', content },
				{ role: 'assistant', content: 'Two cases are overdue.' },
			)
		})
		created.push(chat)
		return chat
	},
}))
vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))
vi.mock('../../../../stores/dashboard.js', () => ({
	useDashboardStore: () => ({ activeDashboard: { uuid: 'team' } }),
}))

const big = { id: 1, gridWidth: 4, gridHeight: 4 }

beforeEach(() => {
	vi.clearAllMocks()
	created.length = 0
})

describe('AiAssistantWidget', () => {
	it('REQ-ADA-004: a question goes to Hermiq with the dashboard as context', async () => {
		axios.get.mockResolvedValue({ status: 200, data: { status: 'ok' } })
		const wrapper = mount(AiAssistantWidget, {
			props: { content: { agentUuid: 'agent-1' }, placement: big },
		})
		await flushPromises()
		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/hermiq/api/chat/health',
			{ timeout: 5000 },
		)
		expect(created[0].options.chatAppId).toBe('hermiq')
		expect(created[0].options.context).toMatchObject({
			appId: 'launchpad',
			pageKind: 'dashboard',
			objectUuid: 'team',
		})
		await wrapper
			.find('[data-testid="ai-assistant-input"]')
			.setValue('Which of my cases are overdue?')
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(created[0].send).toHaveBeenCalledWith(
			'Which of my cases are overdue?',
			{ agentUuid: 'agent-1' },
		)
		expect(wrapper.text()).toContain('Two cases are overdue.')
	})

	it('REQ-ADA-004: without Hermiq the input is disabled and says so', async () => {
		axios.get.mockRejectedValue(
			Object.assign(new Error('x'), { response: { status: 404 } }),
		)
		const wrapper = mount(AiAssistantWidget, {
			props: { content: {}, placement: big },
		})
		await flushPromises()
		expect(
			wrapper.find('[data-testid="ai-assistant-unavailable"]').exists(),
		).toBe(true)
		expect(wrapper.find('[data-testid="ai-assistant-input"]').exists()).toBe(
			false,
		)
	})

	it('REQ-ADA-006: a small placement summarises the dashboard', async () => {
		axios.get.mockResolvedValue({ status: 200, data: {} })
		const wrapper = mount(AiAssistantWidget, {
			props: {
				content: {},
				placement: { id: 2, gridWidth: 2, gridHeight: 2 },
			},
		})
		await flushPromises()
		expect(wrapper.find('form').exists()).toBe(false)
		await wrapper.find('[data-testid="ai-assistant-summarise"]').trigger('click')
		await flushPromises()
		expect(created[0].send).toHaveBeenCalledWith(
			'Summarise this dashboard for me.',
			{ agentUuid: '' },
		)
		expect(wrapper.text()).toContain('Two cases are overdue.')
	})
})
