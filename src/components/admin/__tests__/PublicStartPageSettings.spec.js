/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `PublicStartPageSettings.vue`
 * (sharing-public-start-page REQ-PSP-001): only the links the server offers
 * are listed, by dashboard name; a live start page reads "Public start page:
 * <name>"; a switched-on page whose link went bad says why; a save sends the
 * chosen token and the switch, and a refusal shows the server's message.
 *
 * The `api` module is mocked at the import boundary.
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PublicStartPageSettings from '../PublicStartPageSettings.vue'
import { api } from '../../../services/api.js'

vi.mock('../../../services/api.js', () => ({
	api: {
		getPublicStart: vi.fn(),
		updatePublicStart: vi.fn(),
	},
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

const stubs = {
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel', 'disabled'],
		emits: ['update:modelValue'],
		template: '<div class="select"><span v-for="o in options" :key="o.token" class="option">{{ o.label }}</span></div>',
	},
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue', 'disabled'],
		emits: ['update:modelValue'],
		template: '<label class="switch"><slot /></label>',
	},
	NcNoteCard: { name: 'NcNoteCard', template: '<div class="note"><slot /></div>' },
	NcButton: {
		name: 'NcButton',
		props: ['variant', 'disabled'],
		emits: ['click'],
		template: '<button type="button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}

const NOORD = { token: 'noord', dashboardUuid: 'dash-noord', dashboardName: 'Bibliotheek Noord', expiresAt: null }
const ZUID = { token: 'zuid', dashboardUuid: 'dash-zuid', dashboardName: '', expiresAt: null }

/**
 * Mount the section over a server status.
 *
 * @param {object} status The GET answer.
 * @return {Promise<object>} The wrapper, after the load settled
 */
async function mountWith(status) {
	api.getPublicStart.mockResolvedValue({ data: status })
	const wrapper = mount(PublicStartPageSettings, { global: { stubs } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	vi.clearAllMocks()
})

describe('PublicStartPageSettings', () => {
	it('lists the offered links by dashboard name, the token when the dashboard has none', async () => {
		const wrapper = await mountWith({ enabled: false, token: '', active: false, reason: 'off', candidates: [NOORD, ZUID] })

		const select = wrapper.findComponent({ name: 'NcSelect' })
		expect(select.props('inputLabel')).toBe('Public link')
		expect(wrapper.findAll('.option').map((o) => o.text())).toEqual(['Bibliotheek Noord', 'zuid'])
		expect(wrapper.find('[data-test="public-start-no-links"]').exists()).toBe(false)
	})

	it('reads "Public start page: Bibliotheek Noord" when the start page is live', async () => {
		const wrapper = await mountWith({ enabled: true, token: 'noord', active: true, reason: null, candidates: [NOORD] })

		expect(wrapper.find('[data-test="public-start-status"]').text()).toBe('Public start page: Bibliotheek Noord')
		expect(wrapper.findComponent({ name: 'NcSelect' }).props('modelValue')).toEqual({ token: 'noord', label: 'Bibliotheek Noord' })
		expect(wrapper.find('.note').exists()).toBe(false)
	})

	it.each([
		['revoked', 'The start page is off: the chosen public link has been revoked.'],
		['password', 'The start page is off: the chosen public link now has a password.'],
		['expired', 'The start page is off: the chosen public link has expired.'],
		['not-found', 'The start page is off: the chosen public link no longer exists.'],
		['no-link', 'The start page is off: no public link is chosen.'],
	])('says why a switched-on start page is off (%s)', async (reason, message) => {
		const wrapper = await mountWith({ enabled: true, token: 'noord', active: false, reason, candidates: [] })

		expect(wrapper.find('[data-test="public-start-reason"]').text()).toBe(message)
		expect(wrapper.find('[data-test="public-start-status"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="public-start-no-links"]').exists()).toBe(true)
	})

	it('says nothing about a start page that is switched off', async () => {
		const wrapper = await mountWith({ enabled: false, token: '', active: false, reason: 'off', candidates: [NOORD] })

		expect(wrapper.find('.note').exists()).toBe(false)
		expect(wrapper.find('[data-test="public-start-status"]').exists()).toBe(false)
	})

	it('saves the chosen link and the switch, and shows the new status', async () => {
		const wrapper = await mountWith({ enabled: false, token: '', active: false, reason: 'off', candidates: [NOORD] })
		api.updatePublicStart.mockResolvedValue({ data: { enabled: true, token: 'noord', active: true, reason: null } })

		await wrapper.findComponent({ name: 'NcSelect' }).vm.$emit('update:modelValue', { token: 'noord', label: 'Bibliotheek Noord' })
		await wrapper.findComponent({ name: 'NcCheckboxRadioSwitch' }).vm.$emit('update:modelValue', true)
		await wrapper.find('[data-test="public-start-save"]').trigger('click')
		await flushPromises()

		expect(api.updatePublicStart).toHaveBeenCalledWith({ enabled: true, token: 'noord' })
		expect(showSuccess).toHaveBeenCalledWith('Start page saved.')
		expect(wrapper.find('[data-test="public-start-status"]').text()).toBe('Public start page: Bibliotheek Noord')
	})

	it('cannot save a switched-on start page without a link', async () => {
		const wrapper = await mountWith({ enabled: false, token: '', active: false, reason: 'off', candidates: [NOORD] })

		await wrapper.findComponent({ name: 'NcCheckboxRadioSwitch' }).vm.$emit('update:modelValue', true)

		expect(wrapper.find('[data-test="public-start-save"]').attributes('disabled')).toBeDefined()
	})

	it('shows the server refusal as it is', async () => {
		const wrapper = await mountWith({ enabled: false, token: '', active: false, reason: 'off', candidates: [NOORD] })
		api.updatePublicStart.mockRejectedValue({ response: { status: 400, data: { message: 'A public link with a password cannot be the start page.' } } })

		await wrapper.findComponent({ name: 'NcSelect' }).vm.$emit('update:modelValue', { token: 'noord', label: 'Bibliotheek Noord' })
		await wrapper.find('[data-test="public-start-save"]').trigger('click')
		await flushPromises()

		expect(showError).toHaveBeenCalledWith('A public link with a password cannot be the start page.')
		expect(showSuccess).not.toHaveBeenCalled()
	})

	it('still renders when the status cannot be read', async () => {
		api.getPublicStart.mockRejectedValue(new Error('offline'))
		const wrapper = mount(PublicStartPageSettings, { global: { stubs } })
		await flushPromises()

		expect(wrapper.find('[data-test="public-start-settings"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="public-start-no-links"]').exists()).toBe(true)
	})
})
