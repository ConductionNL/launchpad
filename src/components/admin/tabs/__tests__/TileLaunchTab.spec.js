/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `TileLaunchTab.vue` (REQ-TLT-001, REQ-TLT-003): the
 * allowed program addresses load one per line, launch templates are added
 * and removed, a save sends both lists, and a refusal shows the server's
 * message.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TileLaunchTab from '../TileLaunchTab.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

function settle() {
	return new Promise((resolve) => setTimeout(resolve, 0))
}

const ENTRA = {
	key: 'microsoft-entra-id',
	name: 'Microsoft Entra ID',
	urlTemplate: 'https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso',
}

describe('TileLaunchTab', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.put.mockReset()
	})

	it('loads the settings, adds a template and saves both lists', async () => {
		axios.get.mockResolvedValue({ data: { allowedSchemes: ['ms-word'], ssoTemplates: [] } })
		axios.put.mockResolvedValue({
			data: { allowedSchemes: ['ms-word', 'vscode'], ssoTemplates: [ENTRA] },
		})
		const wrapper = mount(TileLaunchTab)
		await settle()
		await settle()
		expect(wrapper.find('textarea').element.value).toBe('ms-word')

		await wrapper.find('textarea').setValue('ms-word\nvscode\n')
		await wrapper.find('[data-testid="tile-launch-add-template"]').trigger('click')
		expect(wrapper.findAll('[data-testid="tile-launch-template"]')).toHaveLength(1)
		wrapper.vm.templates[0].name = ' Microsoft Entra ID '
		wrapper.vm.templates[0].urlTemplate = ENTRA.urlTemplate
		await wrapper.find('form').trigger('submit')
		await settle()

		expect(axios.put).toHaveBeenCalledWith('/apps/launchpad/api/admin/tile-launch', {
			allowedSchemes: ['ms-word', 'vscode'],
			ssoTemplates: [{ name: 'Microsoft Entra ID', urlTemplate: ENTRA.urlTemplate }],
		})
		expect(wrapper.vm.templates).toEqual([ENTRA])
		expect(wrapper.text()).toContain('Program and sign-on settings saved.')
	})

	it('keeps the key of a stored template, drops a removed one and an empty one', async () => {
		axios.get.mockResolvedValue({
			data: {
				allowedSchemes: [],
				ssoTemplates: [ENTRA, { ...ENTRA, key: 'old', name: 'Old' }],
			},
		})
		axios.put.mockResolvedValue({ data: { allowedSchemes: [], ssoTemplates: [ENTRA] } })
		const wrapper = mount(TileLaunchTab)
		await settle()
		await settle()

		wrapper.vm.removeTemplate(1)
		wrapper.vm.addTemplate()
		await wrapper.find('form').trigger('submit')
		await settle()

		expect(axios.put.mock.calls[0][1].ssoTemplates).toEqual([ENTRA])
	})

	it('shows the server refusal', async () => {
		axios.get.mockResolvedValue({ data: { allowedSchemes: [], ssoTemplates: [] } })
		axios.put.mockRejectedValue({
			response: { data: { error: 'This scheme cannot be allowed: javascript' } },
		})
		const wrapper = mount(TileLaunchTab)
		await settle()
		await settle()

		await wrapper.find('textarea').setValue('javascript')
		await wrapper.find('form').trigger('submit')
		await settle()

		expect(wrapper.find('[role="alert"]').text()).toBe(
			'This scheme cannot be allowed: javascript',
		)
	})
})
