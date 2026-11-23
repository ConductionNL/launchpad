/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * KioskPlayerView: the position line "2 of 4 · next in 18 s" on a playlist
 * of more than one dashboard, and the polite announcement when it moves on
 * (dashboard-kiosk-mode REQ-KIOSKUI-003, board LpKiosk).
 *
 * @spec openspec/changes/kiosk-position-indicator/specs/dashboard-kiosk-mode/spec.md#requirement-the-kiosk-screen-shows-where-the-playlist-is-req-kioskui-003
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import KioskPlayerView from '../KioskPlayerView.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => String(vars[k] ?? `{${k}}`)),
}))

const PLAYLIST_URL = '/index.php/apps/launchpad/kiosk/tok'

const entry = (uuid, name) => ({ dwellSeconds: 30, dashboard: { uuid, name }, placements: [] })

/**
 * Serve a playlist: the playlist read and each entry read.
 *
 * @param {Array<object>} entries The playlist's entries.
 */
function serve(entries) {
	axios.get.mockImplementation((url) => {
		if (url === PLAYLIST_URL) {
			return Promise.resolve({ data: { playlist: { refreshSeconds: 300 }, entries } })
		}
		const uuid = url.split('/').pop()
		return Promise.resolve({ data: entries.find((e) => e.dashboard.uuid === uuid) })
	})
}

function mountPlayer() {
	return mount(KioskPlayerView, {
		props: { token: 'tok' },
		global: { stubs: { PublicDashboardGrid: { template: '<div class="grid" />' } } },
	})
}

const CANTEEN = [entry('a', 'Nieuws'), entry('b', 'Kantine'), entry('c', 'Agenda'), entry('d', 'Weer')]

beforeEach(() => {
	vi.useFakeTimers()
	vi.clearAllMocks()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('KioskPlayerView position line (REQ-KIOSKUI-003)', () => {
	it('reads "2 of 4 · next in 18 s" 12 seconds into the second dashboard', async () => {
		serve(CANTEEN)
		const wrapper = mountPlayer()
		await flushPromises()
		expect(wrapper.find('[data-testid="kiosk-position"]').text()).toBe('1 of 4 · next in 30 s')

		await vi.advanceTimersByTimeAsync(30_000)
		expect(wrapper.text()).toContain('Kantine')
		expect(wrapper.find('[data-testid="kiosk-position"]').text()).toBe('2 of 4 · next in 30 s')

		await vi.advanceTimersByTimeAsync(12_000)
		expect(wrapper.find('[data-testid="kiosk-position"]').text()).toBe('2 of 4 · next in 18 s')
		wrapper.unmount()
	})

	it('counts down every second', async () => {
		serve(CANTEEN)
		const wrapper = mountPlayer()
		await flushPromises()
		await vi.advanceTimersByTimeAsync(1_000)
		expect(wrapper.find('[data-testid="kiosk-position"]').text()).toBe('1 of 4 · next in 29 s')
		await vi.advanceTimersByTimeAsync(1_000)
		expect(wrapper.find('[data-testid="kiosk-position"]').text()).toBe('1 of 4 · next in 28 s')
		wrapper.unmount()
	})

	it('shows no position line for a playlist of one dashboard', async () => {
		serve([entry('a', 'Nieuws')])
		const wrapper = mountPlayer()
		await flushPromises()
		expect(wrapper.text()).toContain('Nieuws')
		expect(wrapper.find('[data-testid="kiosk-position"]').exists()).toBe(false)
		wrapper.unmount()
	})

	it('is text hidden from screen readers, not a control', async () => {
		serve(CANTEEN)
		const wrapper = mountPlayer()
		await flushPromises()
		const line = wrapper.find('[data-testid="kiosk-position"]')
		expect(line.element.tagName).toBe('P')
		expect(line.attributes('aria-hidden')).toBe('true')
		expect(line.attributes('tabindex')).toBeUndefined()
		wrapper.unmount()
	})

	it('politely names the new dashboard when the playlist moves on', async () => {
		serve(CANTEEN)
		const wrapper = mountPlayer()
		await flushPromises()
		const live = wrapper.find('[data-testid="kiosk-announcement"]')
		expect(live.attributes('aria-live')).toBe('polite')
		expect(live.text()).toBe('')

		await vi.advanceTimersByTimeAsync(30_000)
		expect(wrapper.find('[data-testid="kiosk-announcement"]').text()).toBe('Now showing Kantine')
		wrapper.unmount()
	})

	it('announces nothing when a one-dashboard playlist starts over', async () => {
		serve([entry('a', 'Nieuws')])
		const wrapper = mountPlayer()
		await flushPromises()
		await vi.advanceTimersByTimeAsync(30_000)
		expect(wrapper.find('[data-testid="kiosk-announcement"]').text()).toBe('')
		wrapper.unmount()
	})

	it('moves the reconnect note above the position line', async () => {
		let reads = 0
		axios.get.mockImplementation((url) => {
			if (url === PLAYLIST_URL) {
				reads += 1
				return reads === 1
					? Promise.resolve({ data: { playlist: { refreshSeconds: 30 }, entries: CANTEEN } })
					: Promise.reject(Object.assign(new Error('net'), { response: { status: 502 } }))
			}
			const uuid = url.split('/').pop()
			return Promise.resolve({ data: CANTEEN.find((e) => e.dashboard.uuid === uuid) })
		})
		const wrapper = mountPlayer()
		await flushPromises()
		await vi.advanceTimersByTimeAsync(30_000)
		const note = wrapper.find('[data-testid="kiosk-reconnecting"]')
		expect(note.exists()).toBe(true)
		expect(note.classes()).toContain('kiosk-player__reconnect--above')
		wrapper.unmount()
	})
})

describe('KioskPlayerView position clock', () => {
	it('runs no clock for a single dashboard, and stops it on unmount', async () => {
		serve([entry('a', 'Nieuws')])
		const single = mountPlayer()
		await flushPromises()
		expect(single.vm.tickTimer).toBeNull()
		single.unmount()

		serve(CANTEEN)
		const many = mountPlayer()
		await flushPromises()
		expect(many.vm.tickTimer).not.toBeNull()
		const clear = vi.spyOn(globalThis, 'clearInterval')
		many.unmount()
		expect(clear).toHaveBeenCalled()
	})
})
