/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The external image host allow-list section shows the stored hosts one per
 * line, saves the lines through the admin API, and shows the server's
 * refusal under the list (REQ-IMG-006).
 *
 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ImageHostSettings from '../ImageHostSettings.vue'

const http = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn() }))
const dialogs = vi.hoisted(() => ({ showSuccess: vi.fn(), showError: vi.fn() }))
vi.mock('@nextcloud/axios', () => ({ default: http }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, key) => key, n: (_app, one) => one }))
vi.mock('@nextcloud/dialogs', () => dialogs)
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const TextAreaStub = {
	name: 'NcTextArea',
	props: ['modelValue', 'label', 'disabled'],
	emits: ['update:modelValue'],
	template: '<textarea class="textarea-stub" :value="modelValue" />',
}
const ButtonStub = {
	name: 'NcButton',
	props: ['disabled'],
	emits: ['click'],
	template: '<button class="button-stub" @click="$emit(\'click\')"><slot /></button>',
}

const URL = '/index.php/apps/launchpad/api/admin/image-hosts'

/**
 * Mount the section with stubbed Nextcloud components.
 *
 * @return {object} The wrapper.
 */
function mountSettings() {
	return mount(ImageHostSettings, {
		global: { stubs: { NcTextArea: TextAreaStub, NcButton: ButtonStub } },
	})
}

describe('ImageHostSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('shows the stored hosts one per line', async () => {
		http.get.mockResolvedValue({ data: { hosts: ['example.com', 'cdn.example.org'] } })
		const wrapper = mountSettings()
		await flushPromises()

		expect(http.get).toHaveBeenCalledWith(URL)
		expect(wrapper.findComponent(TextAreaStub).props('modelValue')).toBe('example.com\ncdn.example.org')
		expect(wrapper.findComponent(TextAreaStub).props('disabled')).toBe(false)
	})

	it('saves the non-blank lines and shows what the server stored', async () => {
		http.get.mockResolvedValue({ data: { hosts: [] } })
		http.put.mockResolvedValue({ data: { hosts: ['example.com', 'cdn.example.org'] } })
		const wrapper = mountSettings()
		await flushPromises()

		wrapper.findComponent(TextAreaStub).vm.$emit('update:modelValue', ' Example.com \n\ncdn.example.org\n')
		await wrapper.find('.button-stub').trigger('click')
		await flushPromises()

		expect(http.put).toHaveBeenCalledWith(URL, { hosts: ['Example.com', 'cdn.example.org'] })
		expect(wrapper.findComponent(TextAreaStub).props('modelValue')).toBe('example.com\ncdn.example.org')
		expect(dialogs.showSuccess).toHaveBeenCalled()
		expect(wrapper.find('[data-test="image-host-error"]').exists()).toBe(false)
	})

	it('shows the server refusal under the list and keeps what was typed', async () => {
		http.get.mockResolvedValue({ data: { hosts: [] } })
		http.put.mockRejectedValue({ response: { status: 400, data: { message: '"https://example.com" is not a host name.' } } })
		const wrapper = mountSettings()
		await flushPromises()

		wrapper.findComponent(TextAreaStub).vm.$emit('update:modelValue', 'https://example.com')
		await wrapper.find('.button-stub').trigger('click')
		await flushPromises()

		const error = wrapper.find('[data-test="image-host-error"]')
		expect(error.attributes('role')).toBe('alert')
		expect(error.text()).toBe('"https://example.com" is not a host name.')
		expect(wrapper.findComponent(TextAreaStub).props('modelValue')).toBe('https://example.com')
	})
})
