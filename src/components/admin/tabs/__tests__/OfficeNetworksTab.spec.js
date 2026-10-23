/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `OfficeNetworksTab.vue` (REQ-TIA-001): the ranges
 * load one per line, the administrator's own address is named with whether
 * it is on the office network, a save sends the lines, and a refused range
 * shows the server's message.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import OfficeNetworksTab from '../OfficeNetworksTab.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

function settle() {
	return new Promise((resolve) => setTimeout(resolve, 0))
}
const stubs = { NcButton: { template: '<button type="submit"><slot /></button>' } }

describe('OfficeNetworksTab', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.put.mockReset()
	})

	it('adds the office range and says whether the admin is on it', async () => {
		axios.get.mockResolvedValue({
			data: {
				ranges: [],
				currentAddress: '10.20.4.7',
				onOfficeNetwork: false,
			},
		})
		axios.put.mockResolvedValue({
			data: {
				ranges: ['10.20.0.0/16'],
				currentAddress: '10.20.4.7',
				onOfficeNetwork: true,
			},
		})
		const wrapper = mount(OfficeNetworksTab, { global: { stubs } })
		await settle()
		await settle()
		expect(wrapper.find('[data-testid="office-current-address"]').text()).toBe(
			'Your current address 10.20.4.7 is not on the office network.',
		)

		await wrapper.find('textarea').setValue(' 10.20.0.0/16 \n\n')
		await wrapper.find('form').trigger('submit')
		await settle()

		expect(axios.put).toHaveBeenCalledWith(
			'/apps/launchpad/api/admin/office-networks',
			{ ranges: ['10.20.0.0/16'] },
		)
		expect(wrapper.find('textarea').element.value).toBe('10.20.0.0/16')
		expect(wrapper.find('[data-testid="office-current-address"]').text()).toBe(
			'Your current address 10.20.4.7 is on the office network.',
		)
	})

	it('shows the refusal of a malformed range', async () => {
		axios.get.mockResolvedValue({
			data: {
				ranges: ['10.20.0.0/16'],
				currentAddress: '84.1.2.3',
				onOfficeNetwork: false,
			},
		})
		axios.put.mockRejectedValue({
			response: {
				data: { error: 'This is not a valid network range: 10.20.0.0/40' },
			},
		})
		const wrapper = mount(OfficeNetworksTab, { global: { stubs } })
		await settle()
		await settle()

		await wrapper.find('textarea').setValue('10.20.0.0/40')
		await wrapper.find('form').trigger('submit')
		await settle()

		expect(wrapper.find('[role="alert"]').text()).toBe(
			'This is not a valid network range: 10.20.0.0/40',
		)
	})
})
