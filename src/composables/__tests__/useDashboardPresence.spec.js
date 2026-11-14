/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useDashboardPresence: announces the editor, renews on a heartbeat, takes
 * the colleagues the server answers, and withdraws on stop and page hide
 * (building-edit-a-dashboard-together, task 1.1).
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { PRESENCE_BEAT_MS, useDashboardPresence } from '../useDashboardPresence.js'

vi.mock('../../services/api.js', () => ({
	api: {
		joinPresence: vi.fn(),
		leavePresence: vi.fn(() => Promise.resolve({ status: 204 })),
	},
}))

function failure(status) {
	return Object.assign(new Error('http'), { response: { status } })
}

beforeEach(() => {
	vi.clearAllMocks()
	vi.useFakeTimers()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('useDashboardPresence', () => {
	it('announces, renews every beat with the latest colleagues, and withdraws on stop', async () => {
		const marieke = { displayName: 'Marieke Jansen', since: '2026-11-14T10:42:00+01:00' }
		api.joinPresence
			.mockResolvedValueOnce({ data: { editors: [] } })
			.mockResolvedValueOnce({ data: { editors: [marieke] } })
		const presence = useDashboardPresence()

		expect(await presence.start('d-1')).toBe(true)
		expect(presence.state.status).toBe('editing')
		expect(presence.state.editors).toEqual([])

		await vi.advanceTimersByTimeAsync(PRESENCE_BEAT_MS)
		expect(api.joinPresence).toHaveBeenCalledTimes(2)
		expect(presence.state.editors).toEqual([marieke])

		await presence.stop()
		expect(api.leavePresence).toHaveBeenCalledWith('d-1')
		expect(presence.state).toEqual({ status: 'none', uuid: null, editors: [] })
		await vi.advanceTimersByTimeAsync(PRESENCE_BEAT_MS)
		expect(api.joinPresence).toHaveBeenCalledTimes(2)
	})

	it('a refused start reports false, starts no heartbeat and calls no onRefused', async () => {
		const onRefused = vi.fn()
		api.joinPresence.mockRejectedValue(failure(403))
		const presence = useDashboardPresence({ onRefused })

		expect(await presence.start('d-1')).toBe(false)
		expect(presence.state.status).toBe('forbidden')
		await vi.advanceTimersByTimeAsync(PRESENCE_BEAT_MS)
		expect(api.joinPresence).toHaveBeenCalledTimes(1)
		expect(onRefused).not.toHaveBeenCalled()

		await presence.stop()
		expect(api.leavePresence).not.toHaveBeenCalled()
	})

	it('a refused renewal stops the heartbeat and calls onRefused once', async () => {
		const onRefused = vi.fn()
		api.joinPresence.mockResolvedValueOnce({ data: { editors: [] } }).mockRejectedValue(failure(404))
		const presence = useDashboardPresence({ onRefused })
		await presence.start('d-1')

		await vi.advanceTimersByTimeAsync(PRESENCE_BEAT_MS * 2)
		expect(onRefused).toHaveBeenCalledTimes(1)
		expect(api.joinPresence).toHaveBeenCalledTimes(2)
		expect(presence.state.status).toBe('forbidden')
	})

	it('a network failure keeps the last colleagues and tries again', async () => {
		const marieke = { displayName: 'Marieke Jansen', since: 's' }
		api.joinPresence
			.mockResolvedValueOnce({ data: { editors: [marieke] } })
			.mockRejectedValueOnce(new Error('Network Error'))
			.mockResolvedValueOnce({ data: { editors: [] } })
		const presence = useDashboardPresence()
		await presence.start('d-1')

		await vi.advanceTimersByTimeAsync(PRESENCE_BEAT_MS)
		expect(presence.state.editors).toEqual([marieke])
		expect(presence.state.status).toBe('editing')
		await vi.advanceTimersByTimeAsync(PRESENCE_BEAT_MS)
		expect(presence.state.editors).toEqual([])
		await presence.stop()
	})

	it('withdraws with keepalive when the page is hidden while editing', async () => {
		api.joinPresence.mockResolvedValue({ data: { editors: [] } })
		const presence = useDashboardPresence()
		await presence.start('d-1')

		window.dispatchEvent(new Event('pagehide'))
		expect(api.leavePresence).toHaveBeenCalledWith('d-1', true)
		await presence.stop()
	})
})
