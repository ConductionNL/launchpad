/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `AnalyticsOptOutSettings.vue` (REQ-ANLT-UI-001): the
 * "Count my visits" switch reads the person's own opt-out, stores the opposite
 * of the switch, and stays out of the dialog while analytics is off for everyone.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AnalyticsOptOutSettings from '../AnalyticsOptOutSettings.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

const stubs = {
	NcAppSettingsSection: { template: '<section class="section"><slot /></section>' },
	NcNoteCard: { template: '<div class="note"><slot /></div>' },
	NcCheckboxRadioSwitch: {
		props: ['modelValue', 'disabled'],
		emits: ['update:modelValue'],
		template:
			'<button class="switch" :data-on="String(modelValue)" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
	},
}

/**
 * Mount the section and let its load settle.
 *
 * @return {Promise<object>} The wrapper.
 */
async function mounted() {
	const wrapper = mount(AnalyticsOptOutSettings, { global: { stubs } })
	await settle()
	await settle()
	return wrapper
}

describe('AnalyticsOptOutSettings', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.put.mockReset()
	})

	it('shows the switch on for a person who did not opt out', async () => {
		axios.get.mockResolvedValue({ data: { enabled: true, optedOut: false } })

		const wrapper = await mounted()

		expect(axios.get).toHaveBeenCalledWith('/apps/launchpad/api/analytics/me')
		expect(wrapper.find('.switch').text()).toBe('Count my visits')
		expect(wrapper.find('.switch').attributes('data-on')).toBe('true')
	})

	it('turning it off stores optedOut true, and on again stores false', async () => {
		axios.get.mockResolvedValue({ data: { enabled: true, optedOut: false } })
		axios.put
			.mockResolvedValueOnce({ data: { enabled: true, optedOut: true } })
			.mockResolvedValueOnce({ data: { enabled: true, optedOut: false } })
		const wrapper = await mounted()

		await wrapper.find('.switch').trigger('click')
		await settle()
		expect(axios.put).toHaveBeenLastCalledWith('/apps/launchpad/api/analytics/me', { optedOut: true })
		expect(wrapper.find('.switch').attributes('data-on')).toBe('false')

		await wrapper.find('.switch').trigger('click')
		await settle()
		expect(axios.put).toHaveBeenLastCalledWith('/apps/launchpad/api/analytics/me', { optedOut: false })
		expect(wrapper.find('.switch').attributes('data-on')).toBe('true')
	})

	it('is not shown while analytics is off for everyone, or when the state cannot be read', async () => {
		axios.get.mockResolvedValueOnce({ data: { enabled: false, optedOut: false } })
		expect((await mounted()).find('.section').exists()).toBe(false)

		axios.get.mockRejectedValueOnce(new Error('offline'))
		expect((await mounted()).find('.section').exists()).toBe(false)
	})

	it('puts the switch back and says so when saving fails', async () => {
		axios.get.mockResolvedValue({ data: { enabled: true, optedOut: true } })
		axios.put.mockRejectedValue(new Error('500'))
		const wrapper = await mounted()
		expect(wrapper.find('.switch').attributes('data-on')).toBe('false')

		await wrapper.find('.switch').trigger('click')
		await settle()

		expect(wrapper.find('.switch').attributes('data-on')).toBe('false')
		expect(wrapper.find('.note').text()).toBe('Your choice could not be saved.')
	})
})
