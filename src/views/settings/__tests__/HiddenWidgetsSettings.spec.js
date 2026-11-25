/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `HiddenWidgetsSettings.vue` (personal-settings
 * REQ-PSET-003): the section lists every widget the person hid with its
 * dashboard, shows one or all of them again through the caller's own
 * endpoints, and says so when nothing is hidden.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import HiddenWidgetsSettings from '../HiddenWidgetsSettings.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), delete: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

const stubs = {
	NcAppSettingsSection: { props: ['name'], template: '<section class="section" :data-name="name"><slot /></section>' },
	NcNoteCard: { template: '<div class="note"><slot /></div>' },
	NcButton: {
		props: ['disabled'],
		emits: ['click'],
		template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}

const twoHidden = [
	{ placementId: 101, dashboardId: 10, dashboardName: 'Intranet Zuiddrecht', placement: { id: 101, widgetId: 'text', customTitle: 'Verjaardagen' } },
	{ placementId: 201, dashboardId: 20, dashboardName: 'Team Vergunningen', placement: { id: 201, widgetId: 'text', customTitle: 'Kantinemenu' } },
]

/**
 * Mount the section and let its load settle.
 *
 * @return {Promise<object>} The wrapper.
 */
async function mounted() {
	const wrapper = mount(HiddenWidgetsSettings, {
		global: {
			stubs,
			mocks: {
				t: (app, text) => text,
				n: (app, one, many, count) => (count === 1 ? one : many).replace('%n', String(count)),
			},
		},
	})
	await settle()
	await settle()
	return wrapper
}

describe('HiddenWidgetsSettings', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.delete.mockReset()
	})

	it('lists every hidden widget with its dashboard and the count', async () => {
		axios.get.mockResolvedValue({ data: { hidden: twoHidden } })

		const wrapper = await mounted()

		expect(axios.get).toHaveBeenCalledWith('/apps/launchpad/api/personal-layer/hidden')
		expect(wrapper.find('[data-testid="launchpad-hidden-widgets-count"]').text())
			.toBe('You have hidden 2 widgets on shared dashboards')
		const items = wrapper.findAll('[data-testid="launchpad-hidden-widget"]')
		expect(items).toHaveLength(2)
		expect(items[0].text()).toContain('Verjaardagen')
		expect(items[0].text()).toContain('Intranet Zuiddrecht')
		expect(items[1].text()).toContain('Kantinemenu')
	})

	it('Show again removes that one widget through its own route', async () => {
		axios.get.mockResolvedValue({ data: { hidden: twoHidden } })
		axios.delete.mockResolvedValue({ data: { shown: true } })
		const wrapper = await mounted()

		await wrapper.findAll('[data-testid="launchpad-hidden-widget-show"]')[0].trigger('click')
		await settle()

		expect(axios.delete).toHaveBeenCalledWith('/apps/launchpad/api/personal-layer/hidden/101')
		const items = wrapper.findAll('[data-testid="launchpad-hidden-widget"]')
		expect(items).toHaveLength(1)
		expect(items[0].text()).toContain('Kantinemenu')
		expect(wrapper.find('[data-testid="launchpad-hidden-widgets-count"]').text())
			.toBe('You have hidden 1 widget on shared dashboards')
	})

	it('Show all again empties the list and says nothing is hidden, with no button', async () => {
		axios.get.mockResolvedValue({ data: { hidden: twoHidden } })
		axios.delete.mockResolvedValue({ data: { shown: 2 } })
		const wrapper = await mounted()

		await wrapper.find('[data-testid="launchpad-hidden-widgets-show-all"]').trigger('click')
		await settle()

		expect(axios.delete).toHaveBeenCalledWith('/apps/launchpad/api/personal-layer/hidden')
		expect(wrapper.find('[data-testid="launchpad-hidden-widgets-none"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="launchpad-hidden-widgets-show-all"]').exists()).toBe(false)
	})

	it('with nothing hidden it says so and offers no button', async () => {
		axios.get.mockResolvedValue({ data: { hidden: [] } })

		const wrapper = await mounted()

		expect(wrapper.find('[data-testid="launchpad-hidden-widgets-none"]').exists()).toBe(true)
		expect(wrapper.findAll('button')).toHaveLength(0)
	})

	it('a failed change keeps the list and shows an error', async () => {
		axios.get.mockResolvedValue({ data: { hidden: twoHidden } })
		axios.delete.mockRejectedValue(new Error('500'))
		const wrapper = await mounted()

		await wrapper.findAll('[data-testid="launchpad-hidden-widget-show"]')[0].trigger('click')
		await settle()

		expect(wrapper.findAll('[data-testid="launchpad-hidden-widget"]')).toHaveLength(2)
		expect(wrapper.find('.note').exists()).toBe(true)
	})

	it('a failed load says so instead of claiming nothing is hidden', async () => {
		axios.get.mockRejectedValue(new Error('500'))

		const wrapper = await mounted()

		expect(wrapper.find('[data-testid="launchpad-hidden-widgets-none"]').exists()).toBe(false)
		expect(wrapper.text()).toContain('could not be loaded')
	})
})
