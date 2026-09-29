/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * KioskPlayerView: the lobby screen plays the playlist in order for each
 * dwell time, re-reads it on the refresh interval, says so when the link is
 * revoked and keeps the last playlist through a network blip
 * (dashboard-kiosk-mode REQ-KIOSKUI-002). The payload is
 * KioskService::renderPlaylist's.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import KioskPlayerView from '../KioskPlayerView.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))

const payload = {
	playlist: {
		id: 1,
		name: 'Lobby',
		token: 'tok',
		refreshSeconds: 300,
		entries: [],
	},
	entries: [
		{
			dwellSeconds: 30,
			dashboard: { uuid: 'n', name: 'Nieuws' },
			placements: [],
		},
		{
			dwellSeconds: 20,
			dashboard: { uuid: 'k', name: 'Kantine' },
			placements: [],
		},
	],
}
function httpError (status) {
  return Object.assign(new Error('http'), { response: { status } })
}

function mountPlayer () {
  return mount(KioskPlayerView, {
		props: { token: 'tok' },
		global: {
			stubs: { PublicDashboardGrid: { template: '<div class="grid" />' } },
		},
	})
}

beforeEach(() => {
	vi.useFakeTimers()
	vi.clearAllMocks()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('KioskPlayerView', () => {
	it('REQ-KIOSKUI-002: rotates through the playlist and starts over', async () => {
		axios.get.mockResolvedValue({ data: payload })
		const wrapper = mountPlayer()
		await flushPromises()
		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/kiosk/tok',
			{ headers: { Accept: 'application/json' } },
		)
		expect(wrapper.text()).toContain('Nieuws')
		await vi.advanceTimersByTimeAsync(30_000)
		expect(wrapper.text()).toContain('Kantine')
		await vi.advanceTimersByTimeAsync(20_000)
		expect(wrapper.text()).toContain('Nieuws')
		wrapper.unmount()
	})

	it('REQ-KIOSKUI-002: re-reads the playlist on the refresh interval', async () => {
		axios.get.mockResolvedValue({ data: payload })
		const wrapper = mountPlayer()
		await flushPromises()
		await vi.advanceTimersByTimeAsync(300_000)
		expect(axios.get).toHaveBeenCalledTimes(2)
		wrapper.unmount()
	})

	it('REQ-KIOSKUI-002: a revoked link shows that the playlist is gone', async () => {
		axios.get.mockResolvedValueOnce({ data: payload })
		axios.get.mockRejectedValueOnce(httpError(404))
		const wrapper = mountPlayer()
		await flushPromises()
		await vi.advanceTimersByTimeAsync(300_000)
		expect(wrapper.find('[data-testid="kiosk-unavailable"]').exists()).toBe(true)
		expect(wrapper.text()).not.toContain('Nieuws')
		wrapper.unmount()
	})

	it('REQ-KIOSKUI-002: a network blip keeps the last playlist and tries again', async () => {
		axios.get.mockResolvedValueOnce({ data: payload })
		axios.get.mockRejectedValueOnce(new Error('Network Error'))
		axios.get.mockResolvedValue({ data: payload })
		const wrapper = mountPlayer()
		await flushPromises()
		await vi.advanceTimersByTimeAsync(300_000)
		expect(wrapper.find('[data-testid="kiosk-unavailable"]').exists()).toBe(
			false,
		)
		expect(wrapper.find('[data-testid="kiosk-dashboard"]').exists()).toBe(true)
		await vi.advanceTimersByTimeAsync(30_000)
		expect(axios.get).toHaveBeenCalledTimes(3)
		wrapper.unmount()
	})

	it('shows the unavailable message when the first read is refused', async () => {
		axios.get.mockRejectedValue(httpError(404))
		const wrapper = mountPlayer()
		await flushPromises()
		expect(wrapper.find('[data-testid="kiosk-unavailable"]').exists()).toBe(true)
		wrapper.unmount()
	})
})
