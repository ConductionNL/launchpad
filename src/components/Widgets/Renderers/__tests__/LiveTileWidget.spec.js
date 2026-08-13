/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `LiveTileWidget.vue` (REQ-LIVETILE-001..005). The
 * browser client (`liveTileClient.js`) is mocked so these tests never
 * perform a real network call — REQ-LIVETILE-003 "upstream failure
 * degrades gracefully" and the loading/stale/error/unavailable states are
 * exercised deterministically.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import LiveTileWidget from '../LiveTileWidget.vue'
import { fetchLiveTileValue } from '../../../../services/liveTileClient.js'

vi.mock('../../../../services/liveTileClient.js', () => ({
	fetchLiveTileValue: vi.fn(),
}))

// Vue 2's @vue/test-utils does not export `flushPromises`.
async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeEach(() => {
	globalThis.t = (_app, key, vars) => {
		if (vars && typeof key === 'string') {
			return key.replace(/\{(\w+)\}/g, (_, name) =>
				Object.prototype.hasOwnProperty.call(vars, name)
					? vars[name]
					: `{${name}}`,
			)
		}
		return key
	}
	fetchLiveTileValue.mockReset()
})

describe('LiveTileWidget — REQ-LIVETILE-003 fetches via the placement endpoint only', () => {
	it('calls fetchLiveTileValue(placement.id) on mount', async () => {
		fetchLiveTileValue.mockResolvedValue({
			value: 42,
			formatted: '42',
			badge: null,
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		mount(LiveTileWidget, { propsData: { content: {}, placement: { id: 7 } } })
		await flushPromises()
		expect(fetchLiveTileValue).toHaveBeenCalledWith(7)
	})

	it('renders an error state (never crashes) when the placement has no id yet', async () => {
		const wrapper = mount(LiveTileWidget, {
			propsData: { content: {}, placement: null },
		})
		await flushPromises()
		expect(fetchLiveTileValue).not.toHaveBeenCalled()
		expect(wrapper.find('.live-tile-widget__state--error').exists()).toBe(true)
	})
})

describe('LiveTileWidget — REQ-LIVETILE-004 formatting + badge', () => {
	it('renders the formatted value and label with an accessible aria-label', async () => {
		fetchLiveTileValue.mockResolvedValue({
			value: 1234,
			formatted: '€1,234 open',
			badge: null,
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		const wrapper = mount(LiveTileWidget, {
			propsData: { content: { label: 'Open tickets' }, placement: { id: 1 } },
		})
		await flushPromises()
		expect(wrapper.find('.live-tile-widget__value').text()).toBe('€1,234 open')
		expect(
			wrapper.find('.live-tile-widget__value').attributes('aria-label'),
		).toContain('Open tickets')
	})

	it('renders the threshold badge with an icon AND a text label (never colour alone)', async () => {
		fetchLiveTileValue.mockResolvedValue({
			value: 99,
			formatted: '99',
			badge: { state: 'alert', label: 'Critical' },
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		const wrapper = mount(LiveTileWidget, {
			propsData: { content: {}, placement: { id: 2 } },
		})
		await flushPromises()
		const badge = wrapper.find('.live-tile-widget__badge')
		expect(badge.exists()).toBe(true)
		expect(badge.classes()).toContain('live-tile-widget__badge--alert')
		// Icon present.
		expect(badge.find('svg').exists()).toBe(true)
		// Text label present (not colour/icon alone).
		expect(badge.text()).toContain('Critical')
	})
})

describe('LiveTileWidget — REQ-LIVETILE-003 stale + error degradation', () => {
	it('shows a stale badge when the reading is marked stale, without erroring', async () => {
		fetchLiveTileValue.mockResolvedValue({
			value: 5,
			formatted: '5',
			badge: null,
			fetchedAt: '2024-06-15T10:00:00Z',
			stale: true,
		})
		const wrapper = mount(LiveTileWidget, {
			propsData: { content: {}, placement: { id: 3 } },
		})
		await flushPromises()
		expect(wrapper.find('.live-tile-widget__stale-badge').exists()).toBe(true)
	})

	it('renders the error state — never throws — when the endpoint call rejects', async () => {
		fetchLiveTileValue.mockRejectedValue(new Error('network error'))
		const wrapper = mount(LiveTileWidget, {
			propsData: { content: {}, placement: { id: 4 } },
		})
		await flushPromises()
		expect(wrapper.find('.live-tile-widget__state--error').exists()).toBe(true)
	})

	it('retry button re-invokes fetchLiveTileValue', async () => {
		fetchLiveTileValue.mockRejectedValueOnce(new Error('boom'))
		const wrapper = mount(LiveTileWidget, {
			propsData: { content: {}, placement: { id: 6 } },
		})
		await flushPromises()
		expect(wrapper.find('.live-tile-widget__state--error').exists()).toBe(true)

		fetchLiveTileValue.mockResolvedValueOnce({
			value: 1,
			formatted: '1',
			badge: null,
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		await wrapper.find('.live-tile-widget__retry').trigger('click')
		await flushPromises()
		expect(fetchLiveTileValue).toHaveBeenCalledTimes(2)
		expect(wrapper.find('.live-tile-widget__state--error').exists()).toBe(false)
	})
})

describe('LiveTileWidget — REQ-LIVETILE-005 connector-absent leaf consumption', () => {
	it('renders an informative "data source unavailable" state (not a crash) for a connector-mode tile with no cached value', async () => {
		fetchLiveTileValue.mockResolvedValue({
			value: null,
			formatted: null,
			badge: null,
			fetchedAt: null,
			stale: true,
		})
		const wrapper = mount(LiveTileWidget, {
			propsData: {
				content: { sourceMode: 'connector' },
				placement: { id: 8 },
			},
		})
		await flushPromises()
		expect(wrapper.find('.live-tile-widget__state--unavailable').exists()).toBe(
			true,
		)
		expect(wrapper.find('.live-tile-widget__state--error').exists()).toBe(false)
	})

	it('does NOT render the unavailable state for a url-mode tile with the same null/stale shape (generic error instead)', async () => {
		fetchLiveTileValue.mockResolvedValue({
			value: null,
			formatted: null,
			badge: null,
			fetchedAt: null,
			stale: true,
		})
		const wrapper = mount(LiveTileWidget, {
			propsData: { content: { sourceMode: 'url' }, placement: { id: 9 } },
		})
		await flushPromises()
		expect(wrapper.find('.live-tile-widget__state--unavailable').exists()).toBe(
			false,
		)
	})
})

describe('LiveTileWidget — REQ-LIVETILE-004 click-through', () => {
	it('renders as an anchor honouring the configured link + same-tab target', async () => {
		fetchLiveTileValue.mockResolvedValue({
			value: 1,
			formatted: '1',
			badge: null,
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		const wrapper = mount(LiveTileWidget, {
			propsData: {
				content: {
					linkUrl: 'https://example.com/tickets',
					linkTarget: 'same-tab',
				},
				placement: { id: 10 },
			},
		})
		await flushPromises()
		expect(wrapper.element.tagName).toBe('A')
		expect(wrapper.attributes('href')).toBe('https://example.com/tickets')
		expect(wrapper.attributes('target')).toBeUndefined()
	})

	it('opens a new tab (with rel=noopener) when link-target=new-tab', async () => {
		fetchLiveTileValue.mockResolvedValue({
			value: 1,
			formatted: '1',
			badge: null,
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		const wrapper = mount(LiveTileWidget, {
			propsData: {
				content: {
					linkUrl: 'https://example.com/tickets',
					linkTarget: 'new-tab',
				},
				placement: { id: 11 },
			},
		})
		await flushPromises()
		expect(wrapper.attributes('target')).toBe('_blank')
		expect(wrapper.attributes('rel')).toContain('noopener')
	})

	it('renders as a plain div (no navigation) when no link is configured', async () => {
		fetchLiveTileValue.mockResolvedValue({
			value: 1,
			formatted: '1',
			badge: null,
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		const wrapper = mount(LiveTileWidget, {
			propsData: { content: {}, placement: { id: 12 } },
		})
		await flushPromises()
		expect(wrapper.element.tagName).toBe('DIV')
	})
})
