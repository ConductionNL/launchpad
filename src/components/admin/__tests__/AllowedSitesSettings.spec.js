/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * AllowedSitesSettings: the General tab's "Allowed websites" block lists the
 * hosts with their purpose, adds and removes one, and saves the list through
 * PUT /api/admin/allowed-sites; a refusal shows the server's message
 * (REQ-ASET-021).
 *
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../../services/api.js'

vi.mock('../../../services/api.js', () => ({
	api: { getAllowedSites: vi.fn(), updateAllowedSites: vi.fn() },
}))
vi.mock('@nextcloud/dialogs', () => ({ showSuccess: vi.fn() }))

const { default: AllowedSitesSettings } = await import('../AllowedSitesSettings.vue')

const stubs = {
	CnSettingsSection: { name: 'CnSettingsSection', props: ['name'], template: '<section :data-name="name"><slot /></section>' },
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input class="text-field" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options'],
		emits: ['update:modelValue'],
		template: '<select class="select" :value="modelValue && modelValue.id" @change="$emit(\'update:modelValue\', options.find((o) => o.id === $event.target.value))"><option v-for="o in options" :key="o.id" :value="o.id">{{ o.label }}</option></select>',
	},
	NcButton: {
		name: 'NcButton',
		props: ['type', 'disabled', 'ariaLabel'],
		emits: ['click'],
		template: '<button class="button" :type="type || \'button\'" :disabled="disabled" :aria-label="ariaLabel" @click="$emit(\'click\')"><slot /></button>',
	},
}

const board = {
	data: {
		sites: [
			{ host: 'www.zuiddrecht.nl', embed: true, fetch: true },
			{ host: 'app.powerbi.com', embed: true, fetch: false },
		],
	},
}

async function mountSettings() {
	const wrapper = mount(AllowedSitesSettings, { global: { stubs } })
	await flushPromises()
	return wrapper
}

describe('AllowedSitesSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		api.getAllowedSites.mockResolvedValue(board)
		api.updateAllowedSites.mockImplementation(async (sites) => ({ data: { sites } }))
	})

	it('lists every host with its purpose', async () => {
		const wrapper = await mountSettings()
		const rows = wrapper.findAll('[data-testid="admin-allowed-site"]')
		expect(rows).toHaveLength(2)
		expect(rows[0].text()).toContain('www.zuiddrecht.nl')
		expect(rows[0].find('select').element.value).toBe('both')
		expect(rows[1].find('select').element.value).toBe('embed')
	})

	it('adds a website for Fetch, changes a purpose, removes one and saves the list', async () => {
		const wrapper = await mountSettings()
		await wrapper.find('[data-testid="admin-allowed-site-input"]').setValue('kcc-api.zuiddrecht.nl')
		await wrapper.find('[data-testid="admin-allowed-site-purpose"]').setValue('fetch')
		await wrapper.find('form').trigger('submit')
		await wrapper.findAll('[data-testid="admin-allowed-site"]')[1].find('select').setValue('both')
		await wrapper.findAll('[data-testid="admin-allowed-site"]')[0].find('button').trigger('click')
		await wrapper.find('[data-testid="admin-allowed-sites-save"]').trigger('click')
		await flushPromises()

		expect(api.updateAllowedSites).toHaveBeenCalledWith([
			{ host: 'app.powerbi.com', embed: true, fetch: true },
			{ host: 'kcc-api.zuiddrecht.nl', embed: false, fetch: true },
		])
		expect(wrapper.findAll('[data-testid="admin-allowed-site"]')).toHaveLength(2)
		expect(wrapper.find('[data-testid="admin-allowed-site-input"]').element.value).toBe('')
	})

	it('shows the server refusal and keeps the rows', async () => {
		api.updateAllowedSites.mockRejectedValue({ response: { data: { message: '"*.zuiddrecht.nl" is not a host name.' } } })
		const wrapper = await mountSettings()
		await wrapper.find('[data-testid="admin-allowed-site-input"]').setValue('*.zuiddrecht.nl')
		await wrapper.find('form').trigger('submit')
		await wrapper.find('[data-testid="admin-allowed-sites-save"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="admin-allowed-sites-error"]').text()).toBe('"*.zuiddrecht.nl" is not a host name.')
		expect(wrapper.findAll('[data-testid="admin-allowed-site"]')).toHaveLength(3)
	})

	it('says when the list is empty, also when it cannot be read', async () => {
		api.getAllowedSites.mockRejectedValue(new Error('offline'))
		const wrapper = await mountSettings()
		expect(wrapper.find('[data-testid="admin-allowed-sites-empty"]').exists()).toBe(true)
	})
})
