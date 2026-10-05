/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * NoticeRegion and NoticeBanner (openspec/specs/announcements REQ-ANN-005):
 * notices above the grid, the level as icon and word, a close button only on
 * a dismissible notice, and a dismissal remembered per person.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

import axios from '@nextcloud/axios'
import NoticeRegion from '../NoticeRegion.vue'

const MAINTENANCE = {
	uuid: 'n1',
	kind: 'notice',
	level: 'warning',
	dismissible: false,
	title: 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00',
	body: '',
}
const INFO = { uuid: 'n2', kind: 'notice', level: 'info', dismissible: true, title: 'Nieuwe huisstijl', body: '' }

async function mountWith(notices, dismissed = []) {
	axios.get.mockImplementation((url) => Promise.resolve({
		data: url.includes('/preferences/')
			? { value: dismissed.length ? JSON.stringify(dismissed) : null }
			: { announcements: notices },
	}))
	const wrapper = mount(NoticeRegion)
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	axios.get.mockReset()
	axios.put.mockReset()
})

describe('NoticeRegion', () => {
	it('asks for notices and shows a warning by icon and word, without a close button', async () => {
		const wrapper = await mountWith([MAINTENANCE])

		expect(axios.get).toHaveBeenCalledWith('/apps/launchpad/api/announcements', { params: { kind: 'notice' } })
		const banner = wrapper.find('.notice-banner')
		expect(banner.text()).toContain('Warning:')
		expect(banner.text()).toContain('Onderhoud zaaksysteem zaterdag 08:00 tot 12:00')
		expect(banner.attributes('role')).toBe('alert')
		expect(banner.find('svg').exists()).toBe(true)
		expect(banner.find('button').exists()).toBe(false)
	})

	it('hides a dismissible notice when closed and remembers it', async () => {
		const wrapper = await mountWith([MAINTENANCE, INFO])
		axios.put.mockResolvedValue({ data: {} })

		const close = wrapper.findAll('.notice-banner')[1].find('button')
		expect(close.attributes('aria-label')).toBe('Close this notice')
		await close.trigger('click')
		await flushPromises()

		expect(wrapper.findAll('.notice-banner')).toHaveLength(1)
		expect(axios.put).toHaveBeenCalledWith('/apps/launchpad/api/preferences/dismissed-notices', { value: JSON.stringify(['n2']) })
	})

	it('keeps a dismissed notice hidden on the next visit, but never a non-dismissible one', async () => {
		const wrapper = await mountWith([MAINTENANCE, INFO], ['n1', 'n2'])
		const titles = wrapper.findAll('.notice-banner__title').map((node) => node.text())
		expect(titles).toEqual(['Warning: Onderhoud zaaksysteem zaterdag 08:00 tot 12:00'])
	})

	it('renders nothing when no notice targets the reader', async () => {
		const wrapper = await mountWith([])
		expect(wrapper.find('.notice-region').exists()).toBe(false)
	})
})
