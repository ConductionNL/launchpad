/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `NewsWidget.vue` beyond summary truncation: the
 * items request, the failed-feeds badge, the loading / error / empty
 * states, the content options with their defaults, item clicks and the
 * relative / absolute dates.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import NewsWidget from '../NewsWidget.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) =>
		'/index.php' + path.replace(/\{(\w+)\}/g, (_m, k) => String(params[k])),
}))

const NOW = Date.UTC(2026, 2, 11, 12, 0, 0)

const ITEMS = [
	{ guid: 'a', title: 'Linked', link: 'https://news.test/a', summary: '<p>Body</p>', thumbnailUrl: 'https://news.test/a.png', pubDate: '2026-03-11T11:58:00Z' },
	{ guid: 'b', title: 'Plain', summary: '', pubDate: '' },
]

function mountWidget(content = {}, placement = { id: 4 }) {
	return mount(NewsWidget, { props: { content, placement } })
}

beforeEach(() => {
	vi.useFakeTimers({ toFake: ['Date'] })
	vi.setSystemTime(NOW)
	globalThis.t = (_app, key) => key
	axios.get.mockReset()
	axios.get.mockResolvedValue({ data: { items: ITEMS, feedsFailed: 0, failedUrls: [] } })
})

afterEach(() => {
	vi.useRealTimers()
})

describe('NewsWidget items', () => {
	it('requests the placement items with the item limit and renders them', async () => {
		const wrapper = mountWidget({ itemLimit: 5 })
		await flushPromises()

		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/widgets/news/4/items',
			{ params: { limit: 5 } },
		)
		const rows = wrapper.findAll('.news-widget__item')
		expect(rows.map((r) => r.find('.news-widget__title').text())).toEqual(['Linked', 'Plain'])
		expect(rows[0].find('a.news-widget__item-link').attributes('href')).toBe('https://news.test/a')
		expect(rows[0].find('.news-widget__thumb').exists()).toBe(true)
		expect(rows[0].find('.news-widget__summary').exists()).toBe(true)
		expect(rows[1].find('.news-widget__item-link--inert').exists()).toBe(true)
	})

	it.each([[0], [51], ['5'], [undefined]])('uses the default limit of 10 for %s', async (itemLimit) => {
		mountWidget({ itemLimit })
		await flushPromises()
		expect(axios.get.mock.calls[0][1]).toEqual({ params: { limit: 10 } })
	})

	it('does not load without a placement id', async () => {
		const wrapper = mountWidget({}, {})
		await flushPromises()
		await wrapper.vm.loadItems()
		expect(axios.get).not.toHaveBeenCalled()
	})

	it('hides thumbnails and summaries only when explicitly off', async () => {
		const wrapper = mountWidget({ showThumbnails: false, showSummary: 'yes' })
		await flushPromises()
		expect(wrapper.find('.news-widget__thumb').exists()).toBe(false)
		expect(wrapper.find('.news-widget__summary').exists()).toBe(false)
	})

	it('applies an allowed layout and falls back to list', () => {
		expect(mountWidget({ layout: 'grid' }).classes()).toContain('news-widget--grid')
		expect(mountWidget({ layout: 'masonry' }).classes()).toContain('news-widget--list')
	})

	it('badges one failed feed in the singular with the URL as tooltip', async () => {
		axios.get.mockResolvedValue({ data: { items: ITEMS, feedsFailed: 1, failedUrls: ['https://bad.test/rss'] } })
		const wrapper = mountWidget()
		await flushPromises()

		const badge = wrapper.find('.news-widget__badge')
		expect(badge.text()).toBe('1 feed failed')
		expect(badge.attributes('title')).toBe('Failed: https://bad.test/rss')
	})

	it('badges several failed feeds with their count and no tooltip without URLs', async () => {
		axios.get.mockResolvedValue({ data: { items: [], feedsFailed: 3, failedUrls: 'x' } })
		const wrapper = mountWidget()
		await flushPromises()

		expect(wrapper.find('.news-widget__badge').text()).toBe('3 feeds failed')
		expect(wrapper.vm.failedTooltip).toBe('')
		expect(wrapper.find('.news-widget__state--empty').exists()).toBe(true)
	})

	it('shows the error state when loading fails', async () => {
		axios.get.mockRejectedValue(new Error('502'))
		const wrapper = mountWidget()
		await flushPromises()

		expect(wrapper.find('.news-widget__state--error').exists()).toBe(true)
		expect(wrapper.vm.items).toEqual([])
		expect(wrapper.find('.news-widget__state--loading').exists()).toBe(false)
	})

	it('treats a response without data as no items and no failures', async () => {
		axios.get.mockResolvedValue(undefined)
		const wrapper = mountWidget()
		await flushPromises()
		expect(wrapper.vm.items).toEqual([])
		expect(wrapper.vm.failedCount).toBe(0)
	})

	it('reloads when the placement changes', async () => {
		const wrapper = mountWidget()
		await flushPromises()
		await wrapper.setProps({ placement: { id: 9 } })
		await flushPromises()
		expect(axios.get.mock.calls.map((c) => c[0])).toEqual([
			'/index.php/apps/launchpad/api/widgets/news/4/items',
			'/index.php/apps/launchpad/api/widgets/news/9/items',
		])
	})
})

describe('NewsWidget clicks and dates', () => {
	it('opens the link in a new tab only when the anchor was prevented', () => {
		const open = vi.spyOn(window, 'open').mockImplementation(() => null)
		const wrapper = mountWidget()

		wrapper.vm.onItemClick({ defaultPrevented: false }, ITEMS[0])
		wrapper.vm.onItemClick({ defaultPrevented: true }, ITEMS[1])
		wrapper.vm.onItemClick({ defaultPrevented: true }, null)
		expect(open).not.toHaveBeenCalled()

		wrapper.vm.onItemClick({ defaultPrevented: true }, ITEMS[0])
		expect(open).toHaveBeenCalledWith('https://news.test/a', '_blank', 'noopener,noreferrer')
		open.mockRestore()
	})

	it.each([
		['2026-03-11T11:59:30Z', 'just now'],
		['2026-03-11T12:00:30Z', 'just now'],
		['2026-03-11T11:15:00Z', '45 minutes ago'],
		['2026-03-11T07:00:00Z', '5 hours ago'],
		['2026-03-08T12:00:00Z', '3 days ago'],
		['', ''],
		['not a date', ''],
		[undefined, ''],
	])('formats %s relatively as "%s"', (pubDate, expected) => {
		expect(mountWidget().vm.formatDate(pubDate)).toBe(expected)
	})

	it('formats absolute dates as UTC "YYYY-MM-DD HH:MM"', () => {
		const wrapper = mountWidget({ dateFormat: 'absolute' })
		expect(wrapper.vm.formatDate('2026-03-08T09:05:00Z')).toBe('2026-03-08 09:05')
	})

	it('renders the item date relative to now', async () => {
		const wrapper = mountWidget()
		await flushPromises()
		expect(wrapper.find('.news-widget__date').text()).toBe('2 minutes ago')
	})
})
