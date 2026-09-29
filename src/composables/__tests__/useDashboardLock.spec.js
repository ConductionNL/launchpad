/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useDashboardLock: the workspace page acquires, refreshes, releases and
 * loses the dashboard editing lock (dashboard-locking REQ-LOCKUI-001..003).
 * The error bodies are the shapes DashboardLockApiController returns.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { HEARTBEAT_MS, useDashboardLock } from '../useDashboardLock.js'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

vi.mock('../../services/api.js', () => ({
	api: {
		acquireLock: vi.fn(),
		heartbeatLock: vi.fn(),
		releaseLock: vi.fn(),
		releaseLockOnPageHide: vi.fn(() => Promise.resolve({ status: 204 })),
		forceReleaseLock: vi.fn(),
	},
}))

// The exact bodies DashboardLockApiController sends (DashboardLock::jsonSerialize
// and jsonSerializeConflict, which strips userId).
const heldLock = {
	id: 3,
	dashboardUuid: 'dash-uuid',
	userId: 'sanne',
	displayName: 'Sanne',
	acquiredAt: '2026-09-29 09:00:00',
	lastHeartbeat: '2026-09-29 09:00:00',
	expiresAt: '2026-09-29 09:15:00',
	expiresIn: 900,
	lockTimeoutSec: 900,
}
function withoutUserId(lock) {
	const copy = { ...lock }
	delete copy.userId
	return copy
}
function conflict(status, data) {
	return Object.assign(new Error('http'), { response: { status, data } })
}

beforeEach(() => {
	vi.useFakeTimers()
	vi.clearAllMocks()
	api.releaseLockOnPageHide.mockResolvedValue({ status: 204 })
})

afterEach(() => {
	vi.useRealTimers()
})

describe('useDashboardLock', () => {
	it('REQ-LOCKUI-001: a granted lock is held', async () => {
		api.acquireLock.mockResolvedValue({ data: heldLock })
		const lock = useDashboardLock()
		expect(await lock.acquire('dash-uuid')).toBe(true)
		expect(lock.state.status).toBe('held')
		lock.stop()
	})

	it('REQ-LOCKUI-001: a 409 blocks and names the holder', async () => {
		const conflictLock = withoutUserId(heldLock)
		api.acquireLock.mockRejectedValue(
			conflict(409, {
				error: 'Lock held by another user',
				code: 'lock_conflict',
				lock: conflictLock,
			}),
		)
		const lock = useDashboardLock()
		expect(await lock.acquire('dash-uuid')).toBe(false)
		expect(lock.state.status).toBe('blocked')
		expect(lock.state.holderName).toBe('Sanne')
		expect(lock.state.since).toBe('2026-09-29 09:00:00')
		expect(lock.state.expiresIn).toBe(900)
	})

	it('REQ-LOCKUI-001: a 403 or a network failure never grants editing', async () => {
		api.acquireLock.mockRejectedValue(
			conflict(403, { error: 'Forbidden', code: 'lock_forbidden' }),
		)
		const lock = useDashboardLock()
		expect(await lock.acquire('dash-uuid')).toBe(false)
		expect(lock.state.status).toBe('forbidden')

		api.acquireLock.mockRejectedValue(new Error('Network Error'))
		expect(await lock.acquire('dash-uuid')).toBe(false)
		expect(lock.state.status).toBe('unavailable')
	})

	it('REQ-LOCKUI-002: refreshes every five minutes while held', async () => {
		api.acquireLock.mockResolvedValue({ data: heldLock })
		api.heartbeatLock.mockResolvedValue({ data: heldLock })
		const lock = useDashboardLock()
		await lock.acquire('dash-uuid')
		expect(HEARTBEAT_MS).toBeLessThanOrEqual(5 * 60 * 1000)
		await vi.advanceTimersByTimeAsync(HEARTBEAT_MS)
		expect(api.heartbeatLock).toHaveBeenCalledWith('dash-uuid')
		await vi.advanceTimersByTimeAsync(HEARTBEAT_MS)
		expect(api.heartbeatLock).toHaveBeenCalledTimes(2)
		lock.stop()
	})

	it('REQ-LOCKUI-002: a 404 on refresh loses the lock and tells the page', async () => {
		api.acquireLock.mockResolvedValue({ data: heldLock })
		api.heartbeatLock.mockRejectedValue(
			conflict(404, { error: 'Lock not found', code: 'lock_not_found' }),
		)
		const onLost = vi.fn()
		const lock = useDashboardLock({ onLost })
		await lock.acquire('dash-uuid')
		await vi.advanceTimersByTimeAsync(HEARTBEAT_MS)
		expect(lock.state.status).toBe('lost')
		expect(onLost).toHaveBeenCalledTimes(1)
		await vi.advanceTimersByTimeAsync(HEARTBEAT_MS)
		expect(api.heartbeatLock).toHaveBeenCalledTimes(1)
	})

	it('REQ-LOCKUI-002: a network hiccup on refresh keeps the lock', async () => {
		api.acquireLock.mockResolvedValue({ data: heldLock })
		api.heartbeatLock.mockRejectedValue(new Error('Network Error'))
		const lock = useDashboardLock()
		await lock.acquire('dash-uuid')
		await vi.advanceTimersByTimeAsync(HEARTBEAT_MS)
		expect(lock.state.status).toBe('held')
		lock.stop()
	})

	it('REQ-LOCKUI-001: release sends DELETE and stops refreshing', async () => {
		api.acquireLock.mockResolvedValue({ data: heldLock })
		api.releaseLock.mockResolvedValue({ status: 204 })
		const lock = useDashboardLock()
		await lock.acquire('dash-uuid')
		await lock.release()
		expect(api.releaseLock).toHaveBeenCalledWith('dash-uuid')
		expect(lock.state.status).toBe('none')
		await vi.advanceTimersByTimeAsync(HEARTBEAT_MS)
		expect(api.heartbeatLock).not.toHaveBeenCalled()
	})

	it('REQ-LOCKUI-001: closing the page releases with a keepalive DELETE', async () => {
		api.acquireLock.mockResolvedValue({ data: heldLock })
		const lock = useDashboardLock()
		await lock.acquire('dash-uuid')
		window.dispatchEvent(new Event('pagehide'))
		expect(api.releaseLockOnPageHide).toHaveBeenCalledWith('dash-uuid')
		lock.stop()
	})

	it('REQ-LOCKUI-003: take over force-releases and then acquires', async () => {
		const conflictLock = withoutUserId(heldLock)
		api.acquireLock.mockRejectedValueOnce(
			conflict(409, { code: 'lock_conflict', lock: conflictLock }),
		)
		api.forceReleaseLock.mockResolvedValue({ data: { released: true } })
		const lock = useDashboardLock()
		await lock.acquire('dash-uuid')
		api.acquireLock.mockResolvedValue({
			data: { ...heldLock, userId: 'admin', displayName: 'Admin' },
		})
		expect(await lock.takeOver()).toBe(true)
		expect(api.forceReleaseLock).toHaveBeenCalledWith('dash-uuid')
		expect(lock.state.status).toBe('held')
		lock.stop()
	})
})
