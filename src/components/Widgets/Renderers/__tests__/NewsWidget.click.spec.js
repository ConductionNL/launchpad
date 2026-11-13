/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `NewsWidget.vue` (REQ-NEWS-011): a click on a
 * rendered item link keeps the native anchor behaviour, and falls back to
 * `window.open(..., 'noopener,noreferrer')` only when something inside the
 * anchor has prevented the default navigation.
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

const ITEMS = [
	{ guid: 'a', title: 'First', link: 'https://news.test/a', summary: '', pubDate: '' },
	{ guid: 'b', title: 'Second', link: 'https://news.test/b', summary: '', pubDate: '' },
]

let open

beforeEach(() => {
	globalThis.t = (_app, key) => key
	axios.get.mockReset()
	axios.get.mockResolvedValue({ data: { items: ITEMS, feedsFailed: 0, failedUrls: [] } })
	open = vi.spyOn(window, 'open').mockImplementation(() => null)
})

afterEach(() => {
	open.mockRestore()
})

async function mountWidget() {
	const wrapper = mount(NewsWidget, {
		props: { content: {}, placement: { id: 4 } },
		attachTo: document.body,
	})
	await flushPromises()
	return wrapper
}

describe('NewsWidget item link clicks', () => {
	it('leaves an ordinary click to the native anchor (no window.open)', async () => {
		const wrapper = await mountWidget()
		const anchors = wrapper.findAll('a.news-widget__item-link')
		expect(anchors).toHaveLength(2)
		const event = new MouseEvent('click', { bubbles: true, cancelable: true })
		// Stop jsdom from attempting a real navigation after the handler ran.
		document.addEventListener('click', (e) => e.preventDefault(), { once: true })
		anchors[0].element.dispatchEvent(event)
		expect(open).not.toHaveBeenCalled()
		wrapper.unmount()
	})

	it('opens the clicked item in a new tab when the anchor default was prevented', async () => {
		const wrapper = await mountWidget()
		const title = wrapper.findAll('.news-widget__title')[1]
		// A listener inside the anchor prevents the default before the
		// anchor's own handler runs as the event bubbles.
		title.element.addEventListener('click', (e) => e.preventDefault())
		await title.trigger('click')
		expect(open).toHaveBeenCalledTimes(1)
		expect(open).toHaveBeenCalledWith(
			'https://news.test/b',
			'_blank',
			'noopener,noreferrer',
		)
		wrapper.unmount()
	})
})
