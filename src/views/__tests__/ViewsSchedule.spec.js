/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page schedules the active dashboard through the store and
 * explains a refusal (sharing-dashboard-schedule-screen REQ-SCHEDUI-001,
 * DashboardService::ERR_* messages), and says when it goes live or comes
 * down.
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'

vi.mock('../../services/api.js', () => ({
	api: {
		scheduleDashboard: vi.fn(),
		publishDashboard: vi.fn(),
		unpublishDashboard: vi.fn(),
	},
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

let Views

/**
 * @param {object} dashboard Active dashboard.
 * @param {boolean} isAdmin Admin flag.
 * @return {object} host
 */
function makeHost(dashboard, isAdmin = false) {
	const host = {
		activeDashboard: dashboard,
		isAdmin,
		scheduleDialogOpen: true,
		scheduleError: '',
	}
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	for (const name of ['canManagePublication', 'publicationNote']) {
		Object.defineProperty(host, name, {
			get: () => Views.computed[name].call(host),
		})
	}
	return host
}

beforeEach(async () => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views schedule', () => {
	it('REQ-SCHEDUI-001: the owner or an administrator manages publication, others do not', () => {
		expect(makeHost({ uuid: 'd', isOwner: true }).canManagePublication).toBe(
			true,
		)
		expect(
			makeHost({ uuid: 'd', isOwner: false }, true).canManagePublication,
		).toBe(true)
		expect(makeHost({ uuid: 'd', isOwner: false }).canManagePublication).toBe(
			false,
		)
	})

	it('REQ-SCHEDUI-001: schedules through the store and closes the dialog', async () => {
		api.scheduleDashboard.mockResolvedValue({
			data: {
				dashboard: {
					uuid: 'd',
					publicationStatus: 'scheduled',
					publishAt: '2026-10-01 09:00:00',
					unpublishAt: '2026-10-02 17:00:00',
				},
			},
		})
		const host = makeHost({ uuid: 'd', isOwner: true })
		await host.onScheduleActive({
			publishAt: '2026-10-01T07:00:00.000Z',
			unpublishAt: '2026-10-02T15:00:00.000Z',
		})
		expect(api.scheduleDashboard).toHaveBeenCalledWith(
			'd',
			'2026-10-01T07:00:00.000Z',
			'2026-10-02T15:00:00.000Z',
		)
		expect(host.scheduleDialogOpen).toBe(false)
	})

	it('REQ-SCHEDUI-001: a past time is explained and the dialog stays', async () => {
		api.scheduleDashboard.mockRejectedValue(
			Object.assign(new Error('x'), {
				response: {
					status: 400,
					data: {
						status: 'error',
						error: 'invalid_argument',
						message: 'publishAt must be a future timestamp',
					},
				},
			}),
		)
		const host = makeHost({ uuid: 'd', isOwner: true })
		await host.onScheduleActive({
			publishAt: '2020-01-01T00:00:00.000Z',
			unpublishAt: null,
		})
		expect(host.scheduleError).toBe('Choose a time in the future.')
		expect(host.scheduleDialogOpen).toBe(true)
	})

	it('REQ-SCHEDUI-001: says when it goes live and comes down', () => {
		const host = makeHost({
			uuid: 'd',
			isOwner: true,
			publicationStatus: 'scheduled',
			publishAt: '2026-10-01 09:00:00',
			unpublishAt: '2026-10-02 17:00:00',
		})
		expect(host.publicationNote).toContain('Goes live on')
		expect(host.publicationNote).toContain('Comes down on')
	})
})
