/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard store's first load comes from the page's initial state
 * (dashboard-first-load-from-initial-state): the visible list and the
 * active dashboard the page already computed, without asking the API
 * again. Every later load, and a page without the key, fetches both.
 *
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-4
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

let pushedState = {}

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (_app, key, fallback) => (Object.hasOwn(pushedState, key) ? pushedState[key] : fallback),
}))

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, str) => str,
	translatePlural: (_app, sing, plur, n) => (n === 1 ? sing : plur),
}))

vi.mock('../../services/api.js', () => ({
	api: {
		getDashboards: vi.fn(),
		getVisibleDashboards: vi.fn(),
		getActiveDashboard: vi.fn(),
	},
}))

const firstLoad = {
	visible: {
		items: [{ id: 1, uuid: 'a', name: 'Team', source: 'group', isOwner: false }],
		quota: { maxDashboards: 5, dashboardsUsed: 1, maxWidgetsPerDashboard: 40 },
	},
	active: {
		dashboard: { id: 1, uuid: 'a', name: 'Team' },
		placements: [{ id: 7, widgetId: 'clock' }],
		permissionLevel: 'view_only',
		isOwner: false,
		sharedBy: null,
		hasVariants: false,
	},
}

let mockApi

beforeEach(async () => {
	vi.resetModules()
	setActivePinia(createPinia())
	pushedState = {}
	const mod = await import('../../services/api.js')
	mockApi = mod.api
	for (const fn of Object.values(mockApi)) {
		fn.mockReset()
	}
})

describe('dashboard store — first load from the initial state', () => {
	it('fills the list and the active dashboard without asking the API', async () => {
		pushedState = { dashboardFirstLoad: firstLoad }
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()

		await store.loadDashboards()

		expect(mockApi.getVisibleDashboards).not.toHaveBeenCalled()
		expect(mockApi.getActiveDashboard).not.toHaveBeenCalled()
		expect(store.dashboards).toHaveLength(1)
		expect(store.quota.maxDashboards).toBe(5)
		expect(store.activeDashboard.uuid).toBe('a')
		expect(store.widgetPlacements).toEqual([{ id: 7, widgetId: 'clock' }])
		expect(store.permissionLevel).toBe('view_only')
		expect(store.loadedOnce).toBe(true)
	})

	it('asks the API on every load after the first', async () => {
		pushedState = { dashboardFirstLoad: firstLoad }
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()
		mockApi.getVisibleDashboards.mockResolvedValue({ data: { items: [], quota: null } })
		mockApi.getActiveDashboard.mockResolvedValue({ data: null })

		await store.loadDashboards()
		await store.loadDashboards()

		expect(mockApi.getVisibleDashboards).toHaveBeenCalledTimes(1)
		expect(mockApi.getActiveDashboard).toHaveBeenCalledTimes(1)
		expect(store.dashboards).toEqual([])
	})

	it('keeps the list when the page had no active dashboard', async () => {
		pushedState = { dashboardFirstLoad: { visible: firstLoad.visible, active: null } }
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()

		await store.loadDashboards()

		expect(mockApi.getActiveDashboard).not.toHaveBeenCalled()
		expect(store.dashboards).toHaveLength(1)
		expect(store.activeDashboard).toBeNull()
	})

	it('fetches both when the page did not carry them', async () => {
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()
		mockApi.getVisibleDashboards.mockResolvedValue({ data: [{ id: 2, uuid: 'b', source: 'user' }] })
		mockApi.getActiveDashboard.mockResolvedValue({ data: null })

		await store.loadDashboards()

		expect(mockApi.getVisibleDashboards).toHaveBeenCalledTimes(1)
		expect(mockApi.getActiveDashboard).toHaveBeenCalledTimes(1)
		expect(store.dashboards).toHaveLength(1)
	})
})
