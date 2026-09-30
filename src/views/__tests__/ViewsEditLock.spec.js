/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page enters edit mode only with the lock, and gives it back
 * on every way out (dashboard-locking REQ-LOCKUI-001..003). The real
 * Views.vue methods run against a small host object, with the lock
 * composable driven through the real api module shapes.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useDashboardLock } from '../../composables/useDashboardLock.js'
import { api } from '../../services/api.js'

vi.mock('../../services/api.js', () => ({
	api: {
		acquireLock: vi.fn(),
		heartbeatLock: vi.fn(),
		releaseLock: vi.fn(() => Promise.resolve({ status: 204 })),
		releaseLockOnPageHide: vi.fn(() => Promise.resolve({ status: 204 })),
		forceReleaseLock: vi.fn(),
	},
}))

let Views
function conflict(status, data) {
	return Object.assign(new Error('http'), { response: { status, data } })
}

/**
 * A host with the Views methods bound, as the component would have them.
 *
 * @return {object} host
 */
function makeHost() {
	const host = {
		isEditMode: false,
		isWidgetModalOpen: false,
		isTileEditorOpen: false,
		forceReleaseDialogOpen: false,
		activeDashboard: { id: 7, uuid: 'dash-uuid', name: 'Finance' },
		closeWidgetModal: vi.fn(),
		closeStyleEditor: vi.fn(),
		grid: { closeContextMenu: vi.fn() },
	}
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	// The mapped store action, replaced after binding.
	host.switchDashboard = vi.fn()
	host.editLock = useDashboardLock({ onLost: () => host.onEditLockLost() })
	return host
}

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views edit lock', () => {
	it('REQ-LOCKUI-001: Edit enters edit mode when the lock is granted', async () => {
		api.acquireLock.mockResolvedValue({
			data: { displayName: 'Pieter', expiresIn: 900 },
		})
		const host = makeHost()
		await host.toggleEditMode()
		expect(api.acquireLock).toHaveBeenCalledWith('dash-uuid')
		expect(host.isEditMode).toBe(true)
		host.editLock.stop()
	})

	it("REQ-LOCKUI-001: a colleague's lock keeps the page read-only", async () => {
		api.acquireLock.mockRejectedValue(
			conflict(409, {
				code: 'lock_conflict',
				lock: { displayName: 'Sanne', expiresIn: 900 },
			}),
		)
		const host = makeHost()
		await host.toggleEditMode()
		expect(host.isEditMode).toBe(false)
		expect(host.editLock.state.status).toBe('blocked')

		await host.openWidgetModal()
		expect(host.isWidgetModalOpen).toBe(false)
		await host.openTileEditor()
		expect(host.isTileEditorOpen).toBe(false)
	})

	it('REQ-LOCKUI-001: 403 and a failed request keep the page read-only', async () => {
		const host = makeHost()
		api.acquireLock.mockRejectedValue(conflict(403, { code: 'lock_forbidden' }))
		await host.toggleEditMode()
		expect(host.isEditMode).toBe(false)
		api.acquireLock.mockRejectedValue(new Error('Network Error'))
		await host.toggleEditMode()
		expect(host.isEditMode).toBe(false)
	})

	it('REQ-LOCKUI-001: leaving edit mode and switching dashboards release the lock', async () => {
		api.acquireLock.mockResolvedValue({ data: {} })
		const host = makeHost()
		await host.toggleEditMode()
		await host.toggleEditMode()
		expect(host.isEditMode).toBe(false)
		expect(api.releaseLock).toHaveBeenCalledTimes(1)

		await host.toggleEditMode()
		await host.onSidebarSwitch(8, 'group')
		expect(host.isEditMode).toBe(false)
		expect(api.releaseLock).toHaveBeenCalledTimes(2)
		expect(host.switchDashboard).toHaveBeenCalledWith(8)
	})

	it('REQ-LOCKUI-002: a lost lock drops the page to view mode', async () => {
		vi.useFakeTimers()
		api.acquireLock.mockResolvedValue({ data: {} })
		api.heartbeatLock.mockRejectedValue(
			conflict(404, { code: 'lock_not_found' }),
		)
		const host = makeHost()
		await host.toggleEditMode()
		await vi.advanceTimersByTimeAsync(5 * 60 * 1000)
		expect(host.isEditMode).toBe(false)
		expect(host.editLock.state.status).toBe('lost')
		vi.useRealTimers()
	})

	it('REQ-LOCKUI-003: a confirmed take-over edits', async () => {
		api.acquireLock.mockRejectedValueOnce(
			conflict(409, { code: 'lock_conflict', lock: { displayName: 'Sanne' } }),
		)
		api.forceReleaseLock.mockResolvedValue({ data: { status: 'ok' } })
		const host = makeHost()
		await host.toggleEditMode()
		api.acquireLock.mockResolvedValue({ data: {} })
		host.forceReleaseDialogOpen = true
		await host.onTakeOverConfirmed()
		expect(host.forceReleaseDialogOpen).toBe(false)
		expect(api.forceReleaseLock).toHaveBeenCalledWith('dash-uuid')
		expect(host.isEditMode).toBe(true)
		host.editLock.stop()
	})
})
