/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * DiscoverableServicesSettings: the General tab's "Services to discover"
 * block lists each service with its address, adds and removes one, and saves
 * the list through PUT /api/admin/discoverable-services; a refusal shows the
 * server's message (REQ-TILE-044).
 *
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../../services/api.js'

vi.mock('../../../services/api.js', () => ({
	api: { getDiscoverableServices: vi.fn(), updateDiscoverableServices: vi.fn() },
}))
vi.mock('@nextcloud/dialogs', () => ({ showSuccess: vi.fn() }))

const { default: DiscoverableServicesSettings } = await import('../DiscoverableServicesSettings.vue')

const stubs = {
	CnSettingsSection: { name: 'CnSettingsSection', props: ['name'], template: '<section :data-name="name"><slot /></section>' },
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input class="text-field" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcButton: {
		name: 'NcButton',
		props: ['type', 'disabled', 'ariaLabel'],
		emits: ['click'],
		template: '<button class="button" :type="type || \'button\'" :disabled="disabled" :aria-label="ariaLabel" @click="$emit(\'click\')"><slot /></button>',
	},
}

const grafana = { name: 'Grafana', url: 'http://localhost:3000' }

async function mountSettings() {
	const wrapper = mount(DiscoverableServicesSettings, { global: { stubs } })
	await flushPromises()
	return wrapper
}

describe('DiscoverableServicesSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		api.getDiscoverableServices.mockResolvedValue({ data: { services: [grafana] } })
	})

	it('lists the stored services with their address', async () => {
		const wrapper = await mountSettings()
		const rows = wrapper.findAll('[data-testid="admin-discoverable-service"]')
		expect(rows).toHaveLength(1)
		expect(rows[0].text()).toContain('Grafana')
		expect(rows[0].text()).toContain('http://localhost:3000')
	})

	it('says so when the list is empty', async () => {
		api.getDiscoverableServices.mockResolvedValue({ data: { services: [] } })
		const wrapper = await mountSettings()
		expect(wrapper.find('[data-testid="admin-discoverable-services-empty"]').exists()).toBe(true)
	})

	it('adds a service, removes one and saves the list', async () => {
		api.updateDiscoverableServices.mockResolvedValue({ data: { services: [{ name: 'Uptime Kuma', url: 'http://localhost:3001' }] } })
		const wrapper = await mountSettings()

		await wrapper.find('[data-testid="admin-discoverable-service-name"]').setValue('Uptime Kuma')
		await wrapper.find('[data-testid="admin-discoverable-service-url"]').setValue('http://localhost:3001')
		await wrapper.find('form').trigger('submit')
		expect(wrapper.findAll('[data-testid="admin-discoverable-service"]')).toHaveLength(2)

		await wrapper.find('[aria-label="Remove Grafana"]').trigger('click')
		await wrapper.find('[data-testid="admin-discoverable-services-save"]').trigger('click')
		await flushPromises()

		expect(api.updateDiscoverableServices).toHaveBeenCalledWith([{ name: 'Uptime Kuma', url: 'http://localhost:3001' }])
		expect(wrapper.findAll('[data-testid="admin-discoverable-service"]').map((row) => row.text())).toEqual([expect.stringContaining('Uptime Kuma')])
	})

	it('shows the server refusal', async () => {
		api.updateDiscoverableServices.mockRejectedValue({ response: { data: { message: '"ftp://a" is not a web address starting with http:// or https://.' } } })
		const wrapper = await mountSettings()

		await wrapper.find('[data-testid="admin-discoverable-services-save"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="admin-discoverable-services-error"]').text()).toContain('is not a web address')
	})
})
