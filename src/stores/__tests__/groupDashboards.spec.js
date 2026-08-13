/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the groupDashboards Pinia store (Task 7 of the
 * admin-group-management change). Mocks the api module so the store
 * exercises its CRUD + cache logic without hitting the network.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, source) => source,
}))

vi.mock('../../services/api.js', () => ({
	api: {
		getAdminGroups: vi.fn(),
		listGroupDashboards: vi.fn(),
		createGroupDashboard: vi.fn(),
		updateGroupDashboard: vi.fn(),
		deleteGroupDashboard: vi.fn(),
		setGroupDashboardDefault: vi.fn(),
	},
}))

let mockApi
let showError

beforeEach(async () => {
	setActivePinia(createPinia())
	const apiMod = await import('../../services/api.js')
	mockApi = apiMod.api
	const dialogsMod = await import('@nextcloud/dialogs')
	showError = dialogsMod.showError
	for (const fn of Object.values(mockApi)) {
		if (typeof fn?.mockReset === 'function') {
			fn.mockReset()
		}
	}
	showError.mockReset?.()
})

describe('useGroupDashboardsStore — fetchGroups', () => {
	it('hydrates groups with the default sentinel first', async () => {
		mockApi.getAdminGroups.mockResolvedValueOnce({
			data: { data: [{ id: 'admins', displayName: 'Admins' }] },
		})
		const { useGroupDashboardsStore, DEFAULT_GROUP_ID } =
			await import('../groupDashboards.js')
		const store = useGroupDashboardsStore()
		await store.fetchGroups()
		expect(store.groups[0].id).toBe(DEFAULT_GROUP_ID)
		expect(store.groups[1]).toEqual({ id: 'admins', displayName: 'Admins' })
	})

	it('toasts and stores the error on failure', async () => {
		mockApi.getAdminGroups.mockRejectedValueOnce(new Error('boom'))
		const { useGroupDashboardsStore } = await import('../groupDashboards.js')
		const store = useGroupDashboardsStore()
		await store.fetchGroups()
		expect(store.error).toBeInstanceOf(Error)
		expect(showError).toHaveBeenCalled()
	})
})

describe('useGroupDashboardsStore — CRUD', () => {
	it('fetchGroupDashboards caches under the group id', async () => {
		mockApi.listGroupDashboards.mockResolvedValueOnce({
			data: { data: [{ uuid: 'u1', name: 'A' }] },
		})
		const { useGroupDashboardsStore } = await import('../groupDashboards.js')
		const store = useGroupDashboardsStore()
		await store.fetchGroupDashboards('admins')
		expect(store.dashboardsFor('admins')).toHaveLength(1)
		expect(store.countFor('admins')).toBe(1)
	})

	it('create appends to the cache', async () => {
		mockApi.createGroupDashboard.mockResolvedValueOnce({
			data: { data: { uuid: 'u2', name: 'New' } },
		})
		const { useGroupDashboardsStore } = await import('../groupDashboards.js')
		const store = useGroupDashboardsStore()
		store.dashboardsByGroup = { admins: [{ uuid: 'u1', name: 'A' }] }
		await store.create('admins', { name: 'New' })
		expect(store.dashboardsFor('admins')).toHaveLength(2)
	})

	it('update mutates the matching row', async () => {
		mockApi.updateGroupDashboard.mockResolvedValueOnce({
			data: { data: { uuid: 'u1', name: 'A renamed' } },
		})
		const { useGroupDashboardsStore } = await import('../groupDashboards.js')
		const store = useGroupDashboardsStore()
		store.dashboardsByGroup = {
			admins: [
				{ uuid: 'u1', name: 'A' },
				{ uuid: 'u2', name: 'B' },
			],
		}
		await store.update('admins', 'u1', { name: 'A renamed' })
		expect(store.dashboardsFor('admins')[0].name).toBe('A renamed')
		expect(store.dashboardsFor('admins')[1].name).toBe('B')
	})

	it('delete removes the row', async () => {
		mockApi.deleteGroupDashboard.mockResolvedValueOnce({ data: {} })
		const { useGroupDashboardsStore } = await import('../groupDashboards.js')
		const store = useGroupDashboardsStore()
		store.dashboardsByGroup = {
			admins: [
				{ uuid: 'u1', name: 'A' },
				{ uuid: 'u2', name: 'B' },
			],
		}
		await store.delete('admins', 'u1')
		expect(store.dashboardsFor('admins')).toHaveLength(1)
		expect(store.dashboardsFor('admins')[0].uuid).toBe('u2')
	})

	it('delete surfaces last_group_dashboard as the localized toast', async () => {
		const err = new Error('last')
		err.response = { data: { error: 'last_group_dashboard' } }
		mockApi.deleteGroupDashboard.mockRejectedValueOnce(err)
		const { useGroupDashboardsStore } = await import('../groupDashboards.js')
		const store = useGroupDashboardsStore()
		store.dashboardsByGroup = { admins: [{ uuid: 'u1', name: 'A' }] }
		await expect(store.delete('admins', 'u1')).rejects.toThrow()
		expect(showError).toHaveBeenCalledWith('Last dashboard cannot be deleted')
		// Row is preserved on failure.
		expect(store.dashboardsFor('admins')).toHaveLength(1)
	})

	it('setDefault flips the isDefault flag on the matching row', async () => {
		mockApi.setGroupDashboardDefault.mockResolvedValueOnce({ data: {} })
		const { useGroupDashboardsStore } = await import('../groupDashboards.js')
		const store = useGroupDashboardsStore()
		store.dashboardsByGroup = {
			admins: [
				{ uuid: 'u1', name: 'A', isDefault: true },
				{ uuid: 'u2', name: 'B', isDefault: false },
			],
		}
		await store.setDefault('admins', 'u2')
		expect(store.dashboardsFor('admins')[0].isDefault).toBe(false)
		expect(store.dashboardsFor('admins')[1].isDefault).toBe(true)
	})
})
