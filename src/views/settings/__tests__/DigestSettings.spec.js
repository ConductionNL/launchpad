/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `DigestSettings.vue` (personal-settings REQ-PSET-002):
 * the summary email is Off until the person changes it, a choice is stored at
 * once for the caller, and a person without an email address is told so.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DigestSettings from '../DigestSettings.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

const stubs = {
	NcAppSettingsSection: { props: ['name'], template: '<section class="section"><slot /></section>' },
	NcNoteCard: { props: ['type'], template: '<div class="note" :data-type="type"><slot /></div>' },
	NcCheckboxRadioSwitch: {
		props: ['modelValue', 'value', 'disabled'],
		emits: ['update:modelValue'],
		template:
			'<button class="radio" :data-value="value" :data-checked="String(modelValue === value)" :disabled="disabled" @click="$emit(\'update:modelValue\', value)"><slot /></button>',
	},
}

/**
 * Mount the section and let its load settle.
 *
 * @return {Promise<object>} The wrapper.
 */
async function mounted() {
	const wrapper = mount(DigestSettings, { global: { stubs, mocks: { t: (app, text) => text } } })
	await settle()
	await settle()
	return wrapper
}

/**
 * @param {object} wrapper The section.
 * @return {string|undefined} The value of the checked radio.
 */
function checked(wrapper) {
	return wrapper.findAll('.radio').find((radio) => radio.attributes('data-checked') === 'true')?.attributes('data-value')
}

describe('DigestSettings', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.put.mockReset()
	})

	it('offers Off, Daily and Weekly with the board texts, Off by default', async () => {
		axios.get.mockResolvedValue({ data: { frequency: 'off', hasEmail: true } })

		const wrapper = await mounted()

		expect(axios.get).toHaveBeenCalledWith('/apps/launchpad/api/summary-mail/me')
		expect(wrapper.findAll('.radio').map((radio) => radio.attributes('data-value'))).toEqual(['off', 'daily', 'weekly'])
		expect(wrapper.text()).toContain('At 07:30, only when something changed')
		expect(wrapper.text()).toContain('On Monday at 07:30')
		expect(checked(wrapper)).toBe('off')
		expect(wrapper.find('[data-testid="launchpad-summary-mail-no-email"]').exists()).toBe(false)
	})

	it('choosing Daily stores it at once', async () => {
		axios.get.mockResolvedValue({ data: { frequency: 'off', hasEmail: true } })
		axios.put.mockResolvedValue({ data: { frequency: 'daily', hasEmail: true } })
		const wrapper = await mounted()

		await wrapper.find('[data-value="daily"]').trigger('click')
		await settle()

		expect(axios.put).toHaveBeenCalledWith('/apps/launchpad/api/summary-mail/me', { frequency: 'daily' })
		expect(checked(wrapper)).toBe('daily')
	})

	it('a failed save puts the previous choice back and says so', async () => {
		axios.get.mockResolvedValue({ data: { frequency: 'weekly', hasEmail: true } })
		axios.put.mockRejectedValue(new Error('500'))
		const wrapper = await mounted()

		await wrapper.find('[data-value="daily"]').trigger('click')
		await settle()

		expect(checked(wrapper)).toBe('weekly')
		expect(wrapper.find('.note[data-type="error"]').exists()).toBe(true)
	})

	it('a person without an email address is told so', async () => {
		axios.get.mockResolvedValue({ data: { frequency: 'off', hasEmail: false } })

		const wrapper = await mounted()

		expect(wrapper.find('[data-testid="launchpad-summary-mail-no-email"]').exists()).toBe(true)
	})

	it('an unreadable state keeps the choices disabled', async () => {
		axios.get.mockRejectedValue(new Error('500'))

		const wrapper = await mounted()

		expect(wrapper.findAll('.radio').every((radio) => radio.attributes('disabled') !== undefined)).toBe(true)
	})
})
