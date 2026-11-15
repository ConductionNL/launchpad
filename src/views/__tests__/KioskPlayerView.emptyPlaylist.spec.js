/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * KioskPlayerView with a playlist that holds no dashboards: it says so,
 * starts no rotation and only waits for the next refresh
 * (dashboard-kiosk-mode REQ-KIOSKUI-002).
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import KioskPlayerView from '../KioskPlayerView.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))

beforeEach(() => {
	vi.useFakeTimers()
	vi.clearAllMocks()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('KioskPlayerView empty playlist', () => {
	it('REQ-KIOSKUI-002: shows the empty message and schedules only the next read, no rotation', async () => {
		axios.get.mockResolvedValue({
			data: { playlist: { id: 1, name: 'Lobby', token: 'tok', refreshSeconds: 60 }, entries: [] },
		})
		const wrapper = mount(KioskPlayerView, {
			props: { token: 'tok' },
			global: { stubs: { PublicDashboardGrid: { template: '<div class="grid" />' } } },
		})
		await flushPromises()

		expect(wrapper.text()).toContain('This playlist has no dashboards to show.')
		expect(wrapper.find('[data-testid="kiosk-dashboard"]').exists()).toBe(false)
		expect(wrapper.vm.dwellTimer).toBeNull()
		expect(vi.getTimerCount()).toBe(1)

		await vi.advanceTimersByTimeAsync(59_000)
		expect(axios.get).toHaveBeenCalledTimes(1)
		await vi.advanceTimersByTimeAsync(1_000)
		expect(axios.get).toHaveBeenCalledTimes(2)
		wrapper.unmount()
	})
})
