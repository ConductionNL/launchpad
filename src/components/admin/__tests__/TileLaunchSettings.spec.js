/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * TileLaunchSettings: the admin lists behind program and single sign-on
 * tiles (launcher-tile-launch-types task 6, REQ-ASET-020). Each change saves
 * the whole list through PUT /api/admin/settings; a refusal leaves the list
 * as it was and shows the server's message.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../../services/api.js'

vi.mock('../../../services/api.js', () => ({
	api: { getAdminSettings: vi.fn(), updateAdminSettings: vi.fn() },
}))

const { default: TileLaunchSettings } = await import('../TileLaunchSettings.vue')

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

const entra = { key: 'entra', name: 'Microsoft Entra ID', urlTemplate: 'https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso' }

function settings(fields) {
	return { data: { tileAllowedSchemes: [], ssoLaunchTemplates: [], ...fields } }
}

async function mountSettings() {
	const wrapper = mount(TileLaunchSettings, { global: { stubs } })
	await flushPromises()
	return wrapper
}

function field(wrapper, label) {
	return wrapper.find(`input.text-field[aria-label="${label}"]`)
}

describe('TileLaunchSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		api.updateAdminSettings.mockResolvedValue({ data: { status: 'ok' } })
	})

	it('shows both lists as stored', async () => {
		api.getAdminSettings.mockResolvedValue(settings({ tileAllowedSchemes: ['ms-word'], ssoLaunchTemplates: [entra] }))
		const wrapper = await mountSettings()

		expect(wrapper.find('[data-testid="admin-tile-schemes"]').text()).toContain('ms-word')
		expect(wrapper.find('[data-testid="admin-sso-templates"]').text()).toContain('Microsoft Entra ID')
		expect(wrapper.find('[data-testid="admin-sso-templates"]').text()).toContain(entra.urlTemplate)
	})

	it('says when nothing is allowed yet', async () => {
		api.getAdminSettings.mockResolvedValue(settings({}))
		const wrapper = await mountSettings()

		expect(wrapper.text()).toContain('No schemes allowed yet, so program tiles do nothing.')
		expect(wrapper.text()).toContain('No sign-on templates yet.')
	})

	it('allows a scheme by saving the whole list', async () => {
		api.getAdminSettings.mockResolvedValueOnce(settings({ tileAllowedSchemes: ['ms-word'] }))
			.mockResolvedValueOnce(settings({ tileAllowedSchemes: ['ms-word', 'vscode'] }))
		const wrapper = await mountSettings()

		await field(wrapper, 'Scheme').setValue(' vscode ')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.updateAdminSettings).toHaveBeenCalledWith({ tileAllowedSchemes: ['ms-word', 'vscode'] })
		expect(wrapper.find('[data-testid="admin-tile-schemes"]').text()).toContain('vscode')
		expect(field(wrapper, 'Scheme').element.value).toBe('')
	})

	it('keeps the list and shows the refusal when the server refuses a scheme', async () => {
		api.getAdminSettings.mockResolvedValue(settings({}))
		api.updateAdminSettings.mockRejectedValue({ response: { status: 400, data: { error: 'The scheme "javascript" is never allowed on a tile.' } } })
		const wrapper = await mountSettings()

		await field(wrapper, 'Scheme').setValue('javascript')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(wrapper.find('[role="alert"]').text()).toBe('The scheme "javascript" is never allowed on a tile.')
		expect(wrapper.find('[data-testid="admin-tile-schemes"]').exists()).toBe(false)
		expect(field(wrapper, 'Scheme').element.value).toBe('javascript')
	})

	it('removes a scheme', async () => {
		api.getAdminSettings.mockResolvedValue(settings({ tileAllowedSchemes: ['ms-word', 'vscode'] }))
		const wrapper = await mountSettings()

		await wrapper.findAll('[data-testid="admin-tile-schemes"] button')[0].trigger('click')
		await flushPromises()

		expect(api.updateAdminSettings).toHaveBeenCalledWith({ tileAllowedSchemes: ['vscode'] })
	})

	it('adds a template once every field is filled in', async () => {
		api.getAdminSettings.mockResolvedValue(settings({}))
		const wrapper = await mountSettings()
		const submit = wrapper.findAll('form')[1].find('button[type="submit"]')
		expect(submit.attributes('disabled')).toBeDefined()

		await field(wrapper, 'Template name').setValue('Microsoft Entra ID')
		await field(wrapper, 'Key').setValue('entra')
		await field(wrapper, 'Sign-on address').setValue(entra.urlTemplate)
		expect(submit.attributes('disabled')).toBeUndefined()
		await wrapper.findAll('form')[1].trigger('submit')
		await flushPromises()

		expect(api.updateAdminSettings).toHaveBeenCalledWith({ ssoLaunchTemplates: [entra] })
	})

	it('removes a template by its key', async () => {
		api.getAdminSettings.mockResolvedValue(settings({ ssoLaunchTemplates: [entra, { key: 'kc', name: 'Keycloak', urlTemplate: 'https://sso.example.nl/{appId}' }] }))
		const wrapper = await mountSettings()

		await wrapper.find('[data-testid="admin-sso-templates"] button').trigger('click')
		await flushPromises()

		expect(api.updateAdminSettings).toHaveBeenCalledWith({ ssoLaunchTemplates: [{ key: 'kc', name: 'Keycloak', urlTemplate: 'https://sso.example.nl/{appId}' }] })
	})

	it('names what each remove button removes, for screen readers', async () => {
		api.getAdminSettings.mockResolvedValue(settings({ tileAllowedSchemes: ['ms-word'], ssoLaunchTemplates: [entra] }))
		const wrapper = await mountSettings()

		const labels = wrapper.findAll('li button').map((b) => b.attributes('aria-label'))
		expect(labels).toEqual(['Remove {scheme}', 'Remove {name}'])
	})
})
