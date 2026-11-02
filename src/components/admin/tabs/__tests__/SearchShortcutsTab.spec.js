/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `SearchShortcutsTab.vue` (REQ-SPX-001): the list
 * loads, a shortcut is added and saved, and the server's refusal of a plain
 * http address is shown.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SearchShortcutsTab from '../SearchShortcutsTab.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

function settle() {
	return new Promise((resolve) => setTimeout(resolve, 0))
}
const stubs = {
	NcButton: {
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :type="$attrs.type || \'button\'" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}

describe('SearchShortcutsTab', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.put.mockReset()
	})

	it('adds the TOPdesk shortcut and saves the list', async () => {
		axios.get.mockResolvedValue({ data: { shortcuts: [] } })
		axios.put.mockImplementation((url, body) =>
			Promise.resolve({ data: { shortcuts: body.shortcuts } }),
		)
		const wrapper = mount(SearchShortcutsTab, { global: { stubs } })
		await settle()
		await settle()

		await wrapper
			.findAll('button')
			.find((button) => button.text() === 'Add shortcut')
			.trigger('click')
		const inputs = wrapper.findAll('tbody input')
		await inputs[0].setValue('!t')
		await inputs[1].setValue('TOPdesk')
		await inputs[2].setValue(
			'https://topdesk.gemeente.nl/tas/secure/search?q={query}',
		)
		await wrapper.find('form').trigger('submit')
		await settle()

		expect(axios.put).toHaveBeenCalledWith(
			'/apps/launchpad/api/admin/search-shortcuts',
			{
				shortcuts: [
					{
						prefix: '!t',
						name: 'TOPdesk',
						urlTemplate:
							'https://topdesk.gemeente.nl/tas/secure/search?q={query}',
					},
				],
			},
		)
		expect(wrapper.text()).toContain('Search shortcuts saved.')
	})

	it('shows the server refusal of a plain http address', async () => {
		axios.get.mockResolvedValue({
			data: {
				shortcuts: [
					{
						prefix: '!i',
						name: 'Intranet',
						urlTemplate: 'http://intranet.gemeente.nl/zoek?q={query}',
					},
				],
			},
		})
		axios.put.mockRejectedValue({
			response: {
				data: { error: 'Use an https address that contains {query}: !i' },
			},
		})
		const wrapper = mount(SearchShortcutsTab, { global: { stubs } })
		await settle()
		await settle()

		await wrapper.find('form').trigger('submit')
		await settle()

		expect(wrapper.find('[role="alert"]').text()).toBe(
			'Use an https address that contains {query}: !i',
		)
	})
})
