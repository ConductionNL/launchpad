/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page enters edit mode while colleagues edit the same
 * dashboard, shows them, and withdraws its presence on every way out
 * (building-edit-a-dashboard-together, tasks 1.1 and 1.4). The real
 * Views.vue methods run against a small host object, with the presence
 * composable driven through the real api module shapes.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useDashboardPresence } from '../../composables/useDashboardPresence.js'
import { api } from '../../services/api.js'

vi.mock('../../services/api.js', () => ({
	api: {
		joinPresence: vi.fn(),
		leavePresence: vi.fn(() => Promise.resolve({ status: 204 })),
		acquireLock: vi.fn(),
	},
}))

let Views
function failure(status) {
	return Object.assign(new Error('http'), { response: { status, data: {} } })
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
	host.presence = useDashboardPresence({ onRefused: () => host.onPresenceRefused() })
	return host
}

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views co-editing', () => {
	it('a second editor edits instead of waiting, and sees the first one', async () => {
		const marieke = { displayName: 'Marieke Jansen', since: '2026-11-14T10:42:00+01:00' }
		api.joinPresence.mockResolvedValue({ data: { editors: [marieke] } })
		const host = makeHost()
		await host.toggleEditMode()
		expect(api.joinPresence).toHaveBeenCalledWith('dash-uuid')
		expect(api.acquireLock).not.toHaveBeenCalled()
		expect(host.isEditMode).toBe(true)
		expect(host.presence.state.editors).toEqual([marieke])
		await host.presence.stop()
	})

	it('no edit rights (403) or an unknown dashboard (404) keep the page read-only', async () => {
		const host = makeHost()
		api.joinPresence.mockRejectedValue(failure(403))
		await host.toggleEditMode()
		expect(host.isEditMode).toBe(false)
		expect(host.presence.state.status).toBe('forbidden')

		await host.openWidgetModal()
		expect(host.isWidgetModalOpen).toBe(false)

		api.joinPresence.mockRejectedValue(failure(404))
		await host.toggleEditMode()
		expect(host.isEditMode).toBe(false)
	})

	it('a failed request does not stop the editor', async () => {
		api.joinPresence.mockRejectedValue(new Error('Network Error'))
		const host = makeHost()
		await host.toggleEditMode()
		expect(host.isEditMode).toBe(true)
		await host.presence.stop()
	})

	it('leaving edit mode and switching dashboards withdraw the presence', async () => {
		api.joinPresence.mockResolvedValue({ data: { editors: [] } })
		const host = makeHost()
		await host.toggleEditMode()
		await host.toggleEditMode()
		expect(host.isEditMode).toBe(false)
		expect(api.leavePresence).toHaveBeenCalledTimes(1)
		expect(api.leavePresence).toHaveBeenLastCalledWith('dash-uuid')

		await host.toggleEditMode()
		await host.onSidebarSwitch(8, 'group')
		expect(host.isEditMode).toBe(false)
		expect(api.leavePresence).toHaveBeenCalledTimes(2)
		expect(host.switchDashboard).toHaveBeenCalledWith(8)
	})

	it('a refused renewal drops the page back to view mode', async () => {
		vi.useFakeTimers()
		try {
			api.joinPresence.mockResolvedValueOnce({ data: { editors: [] } })
			const host = makeHost()
			await host.toggleEditMode()
			expect(host.isEditMode).toBe(true)

			api.joinPresence.mockRejectedValue(failure(403))
			await vi.advanceTimersByTimeAsync(30 * 1000)
			expect(host.isEditMode).toBe(false)
			expect(host.presence.state.status).toBe('forbidden')
		} finally {
			vi.useRealTimers()
		}
	})
})
