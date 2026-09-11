/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `DashboardRegistrySettings.vue` (dashboard-store
 * spec, REQ-STORE-009). These pin the token handling, which is the part a
 * browser test cannot see go wrong: an e2e run can check that the field is
 * empty, but not that a save without a typed token leaves the stored one
 * alone. The server keeps an omitted key and clears an empty string, so
 * sending `registryToken: ''` on every save would wipe the token silently.
 *
 * The `api` module is mocked at the import boundary, and the field stubs
 * below bind `modelValue` so v-model round-trips through them.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DashboardRegistrySettings from '../DashboardRegistrySettings.vue'
import { api } from '../../../services/api.js'

vi.mock('../../../services/api.js', () => ({
	api: {
		getStoreConfig: vi.fn(),
		updateStoreConfig: vi.fn(),
	},
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

/**
 * A field stub that round-trips `v-model` through a real `<input>`.
 *
 * @param {string} name The component name it stands in for.
 * @return {object} The stub component.
 */
function fieldStub(name) {
	return {
		name,
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template:
			'<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	}
}

const stubs = {
	NcTextField: fieldStub('NcTextField'),
	NcPasswordField: fieldStub('NcPasswordField'),
	NcNoteCard: { name: 'NcNoteCard', template: '<div><slot /></div>' },
	NcButton: {
		name: 'NcButton',
		props: ['type', 'variant', 'disabled'],
		emits: ['click'],
		template:
			'<button type="button" @click="$emit(\'click\')"><slot /></button>',
	},
}

const STORED = {
	registryUrl: 'https://registry.example.org/',
	registryRegister: 'launchpad',
	tokenConfigured: true,
	available: true,
}

/**
 * Mount the component against a given server answer and let `load()` settle.
 *
 * @param {object} config The redacted connection `getStoreConfig` returns.
 * @return {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function mountWith(config) {
	api.getStoreConfig.mockResolvedValue({ data: config })
	const wrapper = mount(DashboardRegistrySettings, { global: { stubs } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	api.getStoreConfig.mockReset()
	api.updateStoreConfig.mockReset().mockImplementation((payload) =>
		Promise.resolve({
			data: {
				...STORED,
				...('registryUrl' in payload
					? { registryUrl: payload.registryUrl }
					: {}),
				tokenConfigured:
					'registryToken' in payload ? payload.registryToken !== '' : true,
			},
		}),
	)
})

describe('DashboardRegistrySettings', () => {
	it('fills the URL and register but never the token', async () => {
		const wrapper = await mountWith(STORED)

		expect(wrapper.find('[data-test="registry-url"]').element.value).toBe(
			STORED.registryUrl,
		)
		expect(wrapper.find('[data-test="registry-register"]').element.value).toBe(
			'launchpad',
		)
		expect(wrapper.find('[data-test="registry-token"]').element.value).toBe('')
		expect(wrapper.find('[data-test="registry-token-status"]').text()).toBe(
			'A token is set. Enter a new one to replace it.',
		)
	})

	it('leaves the stored token alone when the field is empty on save', async () => {
		const wrapper = await mountWith(STORED)

		await wrapper
			.find('[data-test="registry-url"]')
			.setValue('https://other.example.org/')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.updateStoreConfig).toHaveBeenCalledTimes(1)
		const payload = api.updateStoreConfig.mock.calls[0][0]
		expect(payload).not.toHaveProperty('registryToken')
		expect(payload.registryUrl).toBe('https://other.example.org/')
	})

	it('sends a typed token once and then empties the field', async () => {
		const wrapper = await mountWith({ ...STORED, tokenConfigured: false })

		await wrapper.find('[data-test="registry-token"]').setValue('new-secret')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.updateStoreConfig.mock.calls[0][0].registryToken).toBe(
			'new-secret',
		)
		expect(wrapper.find('[data-test="registry-token"]').element.value).toBe('')
		expect(wrapper.find('[data-test="registry-token-status"]').text()).toBe(
			'A token is set. Enter a new one to replace it.',
		)
	})

	it('clears only the token when the administrator removes it', async () => {
		const wrapper = await mountWith(STORED)

		await wrapper.find('[data-test="registry-remove-token"]').trigger('click')
		await flushPromises()

		expect(api.updateStoreConfig).toHaveBeenCalledWith({ registryToken: '' })
		expect(wrapper.find('[data-test="registry-remove-token"]').exists()).toBe(
			false,
		)
	})

	it('says so when OpenRegister is not available', async () => {
		const wrapper = await mountWith({ ...STORED, available: false })

		expect(wrapper.find('[data-test="registry-engine-missing"]').exists()).toBe(
			true,
		)
	})
})
