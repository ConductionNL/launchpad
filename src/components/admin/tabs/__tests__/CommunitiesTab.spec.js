/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * CommunitiesTab: an administrator pins up to 20 Talk conversations
 * (openspec/changes/engagement-communities REQ-COMM-003).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const getAdminSettings = vi.fn()
const updateAdminSettings = vi.fn()
vi.mock('../../../../services/api.js', () => ({
	api: {
		getAdminSettings: (...args) => getAdminSettings(...args),
		updateAdminSettings: (...args) => updateAdminSettings(...args),
	},
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

import CommunitiesTab, { tokensOf } from '../CommunitiesTab.vue'

const NcButton = {
	name: 'NcButton',
	props: ['disabled', 'variant'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

/**
 * Mount the tab over the stored pinned list.
 *
 * @param {Array<string>} pinned The stored tokens.
 * @return {Promise<object>} The wrapper.
 */
async function mountTab(pinned) {
	getAdminSettings.mockResolvedValue({ data: { data: { pinnedCommunities: pinned } } })
	const wrapper = mount(CommunitiesTab, { global: { stubs: { NcButton } } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	getAdminSettings.mockReset()
	updateAdminSettings.mockReset()
})

describe('CommunitiesTab', () => {
	it('shows the stored tokens one per line', async () => {
		const wrapper = await mountTab(['vraagict', 'duurzaam42'])

		expect(wrapper.find('textarea').element.value).toBe('vraagict\nduurzaam42')
	})

	it('saves the tokens as a list, blanks and duplicates dropped', async () => {
		updateAdminSettings.mockResolvedValue({ data: { status: 'ok' } })
		const wrapper = await mountTab([])

		await wrapper.find('textarea').setValue(' vraagict \n\nduurzaam42\nvraagict\n')
		await wrapper.find('[data-testid="communities-save"]').trigger('click')
		await flushPromises()

		expect(updateAdminSettings).toHaveBeenCalledWith({ pinnedCommunities: ['vraagict', 'duurzaam42'] })
		expect(wrapper.find('[role="status"]').text()).toBe('Pinned communities saved.')
	})

	it('shows the server refusal on the field', async () => {
		updateAdminSettings.mockRejectedValue({
			response: { status: 400, data: { error: "Invalid value for pinnedCommunities: 'Vraag' is not a Talk conversation token (4 to 30 lowercase letters and digits)." } },
		})
		const wrapper = await mountTab([])

		await wrapper.find('textarea').setValue('Vraag')
		await wrapper.find('[data-testid="communities-save"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[role="status"]').text()).toContain("'Vraag' is not a Talk conversation token")
		expect(wrapper.find('textarea').attributes('aria-invalid')).toBe('true')
	})

	it('refuses more than twenty before asking the server', async () => {
		const wrapper = await mountTab([])

		await wrapper.find('textarea').setValue(Array.from({ length: 21 }, (_v, i) => `room${i}`).join('\n'))
		await wrapper.find('[data-testid="communities-save"]').trigger('click')

		expect(updateAdminSettings).not.toHaveBeenCalled()
		expect(wrapper.find('[role="status"]').text()).toBe('At most 20 conversations can be pinned.')
	})

	it('reads tokens split by lines, spaces or commas', () => {
		expect(tokensOf('a1b2, c3d4\ne5f6  a1b2')).toEqual(['a1b2', 'c3d4', 'e5f6'])
		expect(tokensOf('')).toEqual([])
	})
})
