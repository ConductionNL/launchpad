/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The news widget on a public link or kiosk screen (REQ-PSHR-011).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import PublicNewsWidget from '../PublicNewsWidget.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text) => text,
	t: (_app, text) => text,
}))

async function flushPromises() {
	for (let i = 0; i < 10; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

describe('PublicNewsWidget', () => {
	it('lists the news items the token-scoped read answers, without likes or comments', async () => {
		const readWidget = vi.fn().mockResolvedValue({
			widget: 'announcements',
			data: {
				announcements: [
					{ uuid: 'a', title: 'Canteen closed Friday', body: 'Renovation', category: 'Facilities', publishAt: '2026-11-20T09:00:00Z' },
					{ uuid: 'b', title: 'New desk hours', body: '' },
				],
			},
		})
		const wrapper = mount(PublicNewsWidget, { props: { placementId: 5, readWidget } })
		await flushPromises()

		expect(readWidget).toHaveBeenCalledWith(5)
		const items = wrapper.findAll('[data-test="public-news-item"]')
		expect(items).toHaveLength(2)
		expect(items[0].text()).toContain('Canteen closed Friday')
		expect(items[0].text()).toContain('Renovation')
		expect(items[0].text()).toContain('Facilities')
		expect(items[1].find('.public-news-widget__body').exists()).toBe(false)
		expect(wrapper.find('[data-test="announcement-like"]').exists()).toBe(false)
	})

	it('says there is no news when the list is empty', async () => {
		const readWidget = vi.fn().mockResolvedValue({ widget: 'announcements', data: { announcements: [] } })
		const wrapper = mount(PublicNewsWidget, { props: { placementId: 5, readWidget } })
		await flushPromises()

		expect(wrapper.text()).toContain('No news yet.')
	})

	it('says news is unavailable when the read fails', async () => {
		const readWidget = vi.fn().mockRejectedValue(new Error('404'))
		const wrapper = mount(PublicNewsWidget, { props: { placementId: 5, readWidget } })
		await flushPromises()

		expect(wrapper.text()).toContain('News is currently unavailable.')
		expect(wrapper.findAll('[data-test="public-news-item"]')).toHaveLength(0)
	})

	it('formats a publish date and leaves an unparsable one empty', () => {
		const wrapper = mount(PublicNewsWidget, { props: { placementId: 5, readWidget: vi.fn().mockResolvedValue({}) } })
		expect(wrapper.vm.formatDate('2026-11-20T09:00:00Z')).not.toBe('')
		expect(wrapper.vm.formatDate('not a date')).toBe('')
	})
})
