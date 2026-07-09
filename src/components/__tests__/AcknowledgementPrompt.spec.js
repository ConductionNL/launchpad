/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `AcknowledgementPrompt.vue` — the forced-delivery
 * read-gate (dashboard-acknowledgements REQ-ACK-002 / REQ-ACK-003):
 *  - renders the author-supplied prompt text and a single sign-off affordance
 *  - exposes NO dismiss / close / snooze affordance that bypasses acknowledging
 *  - signing off records the receipt (via the store) and emits `acknowledged`
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Vue from 'vue'
import { PiniaVuePlugin, createPinia, setActivePinia } from 'pinia'
import AcknowledgementPrompt from '../AcknowledgementPrompt.vue'
import { api } from '../../services/api.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('../../services/api.js', () => ({
	api: {
		acknowledge: vi.fn().mockResolvedValue({ data: {} }),
		getPendingAcknowledgements: vi.fn(),
	},
}))

Vue.use(PiniaVuePlugin)

const placement = {
	id: 7,
	widgetId: 'header',
	requiresAcknowledgement: 1,
	announcementKey: 'ak-1',
	acknowledgementPrompt: 'I have read the 2026 integriteitscode',
	acknowledgementContentVersion: 1,
	acknowledgementDeadline: null,
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	globalThis.n = (_app, sing, plur, count) => (count === 1 ? sing : plur)
	setActivePinia(createPinia())
	vi.clearAllMocks()
})

describe('AcknowledgementPrompt', () => {
	it('REQ-ACK-002: renders the prompt text and one sign-off affordance', () => {
		const wrapper = mount(AcknowledgementPrompt, {
			pinia: createPinia(),
			propsData: { placement },
		})

		expect(wrapper.find('[data-testid="acknowledgement-prompt"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="acknowledgement-prompt-text"]').text())
			.toContain('I have read the 2026 integriteitscode')
		expect(wrapper.findAll('[data-testid="acknowledgement-signoff"]')).toHaveLength(1)
	})

	it('REQ-ACK-002: exposes NO dismiss / close / snooze bypass affordance', () => {
		const wrapper = mount(AcknowledgementPrompt, {
			pinia: createPinia(),
			propsData: { placement },
		})
		const html = wrapper.html().toLowerCase()

		expect(wrapper.find('[data-testid*="dismiss"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid*="close"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid*="snooze"]').exists()).toBe(false)
		// The component template must not wire any close/dismiss handler.
		expect(html).not.toContain('snooze')
		expect(html).not.toContain('dismiss')
	})

	it('REQ-ACK-003: signing off records the receipt and emits acknowledged', async () => {
		const pinia = createPinia()
		setActivePinia(pinia)
		const wrapper = mount(AcknowledgementPrompt, {
			pinia,
			propsData: { placement },
		})

		await wrapper.vm.signOff()

		expect(api.acknowledge).toHaveBeenCalledWith('ak-1', 1)
		expect(wrapper.emitted('acknowledged')).toBeTruthy()
	})

	it('a failed sign-off surfaces an error and does NOT emit acknowledged', async () => {
		api.acknowledge.mockRejectedValueOnce(new Error('network'))
		const pinia = createPinia()
		setActivePinia(pinia)
		const wrapper = mount(AcknowledgementPrompt, {
			pinia,
			propsData: { placement },
		})

		await wrapper.vm.signOff()

		expect(wrapper.emitted('acknowledged')).toBeFalsy()
		expect(wrapper.vm.error).not.toBe('')
	})
})
