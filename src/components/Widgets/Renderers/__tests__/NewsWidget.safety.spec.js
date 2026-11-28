/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `NewsWidget.vue`: attribute stripping in summaries,
 * unsafe item links, the all-feeds-failed badge, the empty-state icon and
 * the carousel's previous/next navigation (REQ-NEWS-005, -008, -009, -010,
 * -011).
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import NewsWidget from '../NewsWidget.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => `/index.php${p}` }))

/**
 * Mount the widget on a placement whose endpoint answers this payload.
 *
 * @param {object} payload The items response.
 * @param {object} content The placement content.
 * @return {Promise<object>} The mounted wrapper, after the load.
 */
async function render(payload, content = {}) {
	axios.get.mockResolvedValue({ data: { feedsFailed: 0, failedUrls: [], ...payload } })
	const wrapper = mount(NewsWidget, { props: { content, placement: { id: 3 } } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	axios.get.mockReset()
})

describe('NewsWidget safety and layout', () => {
	it('strips data- and aria- attributes from summary markup', async () => {
		const wrapper = await render({ items: [] })
		const out = wrapper.vm.formattedSummary({
			summary: '<p><strong data-track="1" aria-label="x">bold</strong></p>',
		})
		expect(out).toBe('<p><strong>bold</strong></p>')
	})

	it.each([
		["javascript:alert('xss')"],
		['data:text/html,<script>alert(1)</script>'],
		['  JavaScript:alert(1)'],
	])('renders an item linking to %s as an inert card', async (link) => {
		const wrapper = await render({ items: [{ guid: 'x', title: 'Bad', link }] })
		expect(wrapper.find('a').exists()).toBe(false)
		expect(wrapper.find('.news-widget__item-link--inert').exists()).toBe(true)
	})

	it('never opens an unsafe link from the click fallback', async () => {
		const open = vi.spyOn(window, 'open').mockImplementation(() => null)
		const wrapper = await render({ items: [] })
		wrapper.vm.onItemClick({ defaultPrevented: true }, { link: 'javascript:alert(1)' })
		expect(open).not.toHaveBeenCalled()
		open.mockRestore()
	})

	it('badges "All N feeds failed" when every configured feed failed', async () => {
		const urls = ['https://a.test/rss', 'https://b.test/rss', 'https://c.test/rss']
		const wrapper = await render({ items: [], feedsFailed: 3, failedUrls: urls }, { feedUrls: urls })
		expect(wrapper.find('.news-widget__badge').text()).toBe('All 3 feeds failed')
	})

	it('keeps the plain count when some feeds still answered', async () => {
		const urls = ['https://a.test/rss', 'https://b.test/rss', 'https://c.test/rss']
		const wrapper = await render(
			{ items: [{ guid: 'ok', title: 'Ok', link: 'https://a.test/1' }], feedsFailed: 2, failedUrls: urls.slice(1) },
			{ feedUrls: urls },
		)
		expect(wrapper.find('.news-widget__badge').text()).toBe('2 feeds failed')
	})

	it('shows an RSS icon in the empty state', async () => {
		const wrapper = await render({ items: [] })
		expect(wrapper.find('.news-widget__state--empty .rss-icon svg').exists()).toBe(true)
	})

	it('gives the carousel previous and next buttons that move the shown item', async () => {
		const items = [1, 2, 3].map((n) => ({ guid: `s${n}`, title: `Slide ${n}`, link: `https://a.test/${n}` }))
		const wrapper = await render({ items }, { layout: 'carousel' })
		const list = wrapper.find('.news-widget__list').element
		list.scrollTo = vi.fn()

		const [previous, next] = wrapper.findAll('.news-widget__carousel-nav button')
		expect(previous.attributes('aria-label')).toBe('Previous item')
		expect(next.attributes('aria-label')).toBe('Next item')
		expect(previous.attributes('disabled')).toBeDefined()

		await next.trigger('click')
		expect(wrapper.vm.carouselIndex).toBe(1)
		expect(list.scrollTo).toHaveBeenCalled()
		await previous.trigger('click')
		expect(wrapper.vm.carouselIndex).toBe(0)
	})

	it('draws no carousel buttons in the list layout', async () => {
		const items = [1, 2].map((n) => ({ guid: `l${n}`, title: `Row ${n}` }))
		const wrapper = await render({ items })
		expect(wrapper.find('.news-widget__carousel-nav').exists()).toBe(false)
	})
})
