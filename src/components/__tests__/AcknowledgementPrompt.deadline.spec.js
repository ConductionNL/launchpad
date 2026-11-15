/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for AcknowledgementPrompt's fallback prompt text, the
 * deadline line and its overdue marker, and the double-submit guard.
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AcknowledgementPrompt from '../AcknowledgementPrompt.vue'
import { api } from '../../services/api.js'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('../../services/api.js', () => ({
	api: {
		acknowledge: vi.fn(),
		getPendingAcknowledgements: vi.fn(),
	},
}))

const BASE = {
	id: 7,
	widgetId: 'header',
	requiresAcknowledgement: 1,
	announcementKey: 'ak-1',
	acknowledgementPrompt: '',
	acknowledgementContentVersion: 1,
	acknowledgementDeadline: null,
}

/**
 * Mount the prompt for a placement.
 *
 * @param {object} overrides Placement fields to change.
 * @return {object} The wrapper.
 */
function mountPrompt(overrides = {}) {
	const pinia = createPinia()
	setActivePinia(pinia)
	return mount(AcknowledgementPrompt, {
		global: { plugins: [pinia] },
		props: { placement: { ...BASE, ...overrides } },
	})
}

beforeEach(() => {
	vi.clearAllMocks()
	vi.useFakeTimers({ toFake: ['Date'] })
	vi.setSystemTime(new Date('2026-06-15T12:00:00Z'))
})

afterEach(() => {
	vi.useRealTimers()
})

describe('AcknowledgementPrompt deadline and fallback', () => {
	it('falls back to a generic prompt when the author gave no text', () => {
		const wrapper = mountPrompt()

		expect(
			wrapper.find('[data-testid="acknowledgement-prompt-text"]').text(),
		).toBe('Please confirm you have read this item.')
	})

	it('shows no deadline line without a deadline', () => {
		const wrapper = mountPrompt()

		expect(wrapper.vm.isOverdue).toBe(false)
		expect(
			wrapper.find('[data-testid="acknowledgement-prompt-deadline"]').exists(),
		).toBe(false)
	})

	it('marks a past deadline overdue', () => {
		const wrapper = mountPrompt({ acknowledgementDeadline: '2026-06-14' })
		const line = wrapper.find('[data-testid="acknowledgement-prompt-deadline"]')

		expect(wrapper.vm.isOverdue).toBe(true)
		expect(line.classes()).toContain('launchpad-ack-prompt__deadline--overdue')
		expect(line.text()).toBe('Deadline: {date}')
	})

	it('does not mark a deadline of today or later overdue', () => {
		for (const deadline of ['2026-06-15', '2026-07-01']) {
			const wrapper = mountPrompt({ acknowledgementDeadline: deadline })
			const line = wrapper.find('[data-testid="acknowledgement-prompt-deadline"]')

			expect(wrapper.vm.isOverdue).toBe(false)
			expect(line.classes()).not.toContain(
				'launchpad-ack-prompt__deadline--overdue',
			)
		}
	})

	it('ignores a second sign-off while the first is in flight', async () => {
		let resolve
		api.acknowledge.mockReturnValue(new Promise((r) => { resolve = r }))
		const wrapper = mountPrompt()

		const first = wrapper.vm.signOff()
		await wrapper.vm.signOff()
		resolve({ data: {} })
		await first

		expect(api.acknowledge).toHaveBeenCalledTimes(1)
		expect(wrapper.emitted('acknowledged')).toHaveLength(1)
		expect(wrapper.vm.submitting).toBe(false)
	})
})
