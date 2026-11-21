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
function httpError(status) {
	return Object.assign(new Error('http'), { response: { status } })
}

const PLAYLIST_URL = '/index.php/apps/launchpad/kiosk/tok'

/**
 * Answer the player's reads: the playlist read takes the next of
 * `playlistAnswers` (the last one repeats), an entry read asks `entry`.
 *
 * @param {Array<Function>} playlistAnswers Each returns a promise.
 * @param {(uuid: string) => Promise<object>} entry Answers an entry read.
 */
function serve(playlistAnswers, entry = (uuid) => Promise.resolve({ data: payload.entries.find((e) => e.dashboard.uuid === uuid) })) {
	let reads = 0
	axios.get.mockImplementation((url) => {
		if (url === PLAYLIST_URL) {
			const answer = playlistAnswers[Math.min(reads, playlistAnswers.length - 1)]
			reads += 1
			return answer()
		}
		return entry(url.split('/').pop())
	})
}

/** @return {number} How often the playlist itself was read. */
function playlistReads() {
	return axios.get.mock.calls.filter(([url]) => url === PLAYLIST_URL).length
}

const ok = () => Promise.resolve({ data: payload })

function mountPlayer() {
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
		serve([ok])
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
		serve([ok])
		const wrapper = mountPlayer()
		await flushPromises()
		await vi.advanceTimersByTimeAsync(300_000)
		expect(playlistReads()).toBe(2)
		wrapper.unmount()
	})

	it('REQ-KIOSKUI-002: a revoked link shows that the playlist is gone', async () => {
		serve([ok, () => Promise.reject(httpError(404))])
		const wrapper = mountPlayer()
		await flushPromises()
		await vi.advanceTimersByTimeAsync(300_000)
		expect(wrapper.find('[data-testid="kiosk-unavailable"]').exists()).toBe(true)
		expect(wrapper.text()).not.toContain('Nieuws')
		wrapper.unmount()
	})

	it('REQ-KIOSKUI-002: a network blip keeps the last playlist and tries again', async () => {
		serve([ok, () => Promise.reject(new Error('Network Error')), ok])
		const wrapper = mountPlayer()
		await flushPromises()
		await vi.advanceTimersByTimeAsync(300_000)
		expect(wrapper.find('[data-testid="kiosk-unavailable"]').exists()).toBe(
			false,
		)
		expect(wrapper.find('[data-testid="kiosk-dashboard"]').exists()).toBe(true)
		await vi.advanceTimersByTimeAsync(30_000)
		expect(playlistReads()).toBe(3)
		wrapper.unmount()
	})

	it('REQ-KIOSK-005: a failed read shows a reconnect indicator until a read succeeds', async () => {
		serve([ok, () => Promise.reject(new Error('Network Error')), ok])
		const wrapper = mountPlayer()
		await flushPromises()
		const indicator = () => wrapper.find('[data-testid="kiosk-reconnecting"]')
		expect(indicator().exists()).toBe(false)
		await vi.advanceTimersByTimeAsync(300_000)
		expect(indicator().exists()).toBe(true)
		expect(indicator().attributes('role')).toBe('status')
		expect(wrapper.text()).toContain('Nieuws')
		await vi.advanceTimersByTimeAsync(30_000)
		expect(indicator().exists()).toBe(false)
		wrapper.unmount()
	})

	it('shows the unavailable message when the first read is refused', async () => {
		serve([() => Promise.reject(httpError(404))])
		const wrapper = mountPlayer()
		await flushPromises()
		expect(wrapper.find('[data-testid="kiosk-unavailable"]').exists()).toBe(true)
		wrapper.unmount()
	})

	it('REQ-KIOSK-004: fetches the next entry 5 seconds before its turn and shows that fresh data', async () => {
		const fresh = { dwellSeconds: 20, dashboard: { uuid: 'k', name: 'Kantine vers' }, placements: [] }
		serve([ok], () => Promise.resolve({ data: fresh }))
		const wrapper = mountPlayer()
		await flushPromises()
		const entryReads = () => axios.get.mock.calls.filter(([url]) => url !== PLAYLIST_URL).map(([url]) => url)

		await vi.advanceTimersByTimeAsync(24_000)
		expect(entryReads()).toEqual([])
		await vi.advanceTimersByTimeAsync(1_000)
		expect(entryReads()).toEqual(['/index.php/apps/launchpad/kiosk/tok/dashboards/k'])
		expect(wrapper.text()).toContain('Nieuws')

		await vi.advanceTimersByTimeAsync(5_000)
		expect(wrapper.text()).toContain('Kantine vers')
		expect(entryReads()).toHaveLength(1)
		wrapper.unmount()
	})

	it('REQ-KIOSK-005: skips an entry that fails and tries it again on the next loop', async () => {
		const three = {
			...payload,
			entries: [...payload.entries, { dwellSeconds: 30, dashboard: { uuid: 'p', name: 'Parkeren' }, placements: [] }],
		}
		const attempts = { k: 0 }
		serve([() => Promise.resolve({ data: three })], (uuid) => {
			if (uuid === 'k') {
				attempts.k += 1
				return Promise.reject(httpError(500))
			}
			return Promise.resolve({ data: three.entries.find((e) => e.dashboard.uuid === uuid) })
		})
		const wrapper = mountPlayer()
		await flushPromises()

		await vi.advanceTimersByTimeAsync(30_000)
		expect(wrapper.text()).toContain('Parkeren')
		expect(wrapper.text()).not.toContain('500')
		expect(attempts.k).toBe(1)

		// Parkeren 30 s, Nieuws 30 s: the next loop reaches Kantine again.
		await vi.advanceTimersByTimeAsync(60_000)
		expect(attempts.k).toBe(2)
		wrapper.unmount()
	})

	it('REQ-KIOSK-005: skips an entry that does not answer within 10 seconds', async () => {
		serve([ok], (uuid) => (uuid === 'k' ? new Promise(() => {}) : Promise.resolve({ data: payload.entries[0] })))
		const wrapper = mountPlayer()
		await flushPromises()

		await vi.advanceTimersByTimeAsync(30_000)
		expect(wrapper.text()).toContain('Nieuws')
		await vi.advanceTimersByTimeAsync(5_000)
		expect(wrapper.text()).toContain('Nieuws')
		expect(wrapper.find('[data-testid="kiosk-dashboard"]').exists()).toBe(true)
		wrapper.unmount()
	})

	it('REQ-KIOSK-001: hides the cursor after 10 seconds without movement', async () => {
		serve([ok])
		const wrapper = mountPlayer()
		await flushPromises()
		await vi.advanceTimersByTimeAsync(10_000)
		expect(document.documentElement.classList.contains('launchpad-kiosk--idle')).toBe(true)
		document.dispatchEvent(new Event('mousemove'))
		expect(document.documentElement.classList.contains('launchpad-kiosk--idle')).toBe(false)
		wrapper.unmount()
		expect(document.documentElement.classList.contains('launchpad-kiosk--idle')).toBe(false)
	})
})
