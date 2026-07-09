/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the dashboard-quota-limits frontend logic in the
 * Pinia dashboard store (REQ-QUOTA-006):
 *  - the `{items, quota}` list envelope is unwrapped into state (and a
 *    bare-array legacy response still populates the list),
 *  - the `dashboardQuotaReached` / `widgetQuotaReached` getters reflect the
 *    effective limits (0 = unlimited),
 *  - the tooltip getters render the localised message at the limit only.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

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

// Interpolate {placeholders} so the tooltip assertions are meaningful.
vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, str, params = {}) => str.replace(/\{(\w+)\}/g, (_, k) => params[k] ?? `{${k}}`),
	translatePlural: (_app, sing, plur, n) => (n === 1 ? sing : plur),
}))

vi.mock('../../services/api.js', () => ({
	api: {
		getDashboards: vi.fn(),
		getVisibleDashboards: vi.fn(),
		getActiveDashboard: vi.fn(),
	},
}))

let mockApi

beforeEach(async () => {
	setActivePinia(createPinia())
	const mod = await import('../../services/api.js')
	mockApi = mod.api
	for (const fn of Object.values(mockApi)) {
		if (typeof fn?.mockReset === 'function') {
			fn.mockReset()
		}
	}
})

describe('dashboard store — quota envelope', () => {
	it('unwraps the {items, quota} list envelope into state', async () => {
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()

		mockApi.getVisibleDashboards.mockResolvedValue({
			data: {
				items: [{ id: 1, uuid: 'a', source: 'user' }],
				quota: { maxDashboards: 5, dashboardsUsed: 3, maxWidgetsPerDashboard: 40 },
			},
		})
		mockApi.getActiveDashboard.mockResolvedValue({ data: null })

		await store.loadDashboards()

		expect(store.dashboards).toHaveLength(1)
		expect(store.quota).toEqual({ maxDashboards: 5, dashboardsUsed: 3, maxWidgetsPerDashboard: 40 })
	})

	it('still populates the list from a bare-array legacy response', async () => {
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()

		mockApi.getVisibleDashboards.mockResolvedValue({
			data: [{ id: 9, uuid: 'z', source: 'user' }],
		})
		mockApi.getActiveDashboard.mockResolvedValue({ data: null })

		await store.loadDashboards()

		expect(store.dashboards).toHaveLength(1)
		// Quota stays at the unlimited default — no quota UI.
		expect(store.quota.maxDashboards).toBe(0)
	})
})

describe('dashboard store — quota getters', () => {
	it('dashboardQuotaReached is false when unlimited (max 0)', async () => {
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()
		store.quota = { maxDashboards: 0, dashboardsUsed: 99, maxWidgetsPerDashboard: 0 }
		expect(store.dashboardQuotaReached).toBe(false)
		expect(store.dashboardQuotaTooltip).toBe('')
	})

	it('dashboardQuotaReached is true at the limit, with a localised tooltip', async () => {
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()
		store.quota = { maxDashboards: 5, dashboardsUsed: 5, maxWidgetsPerDashboard: 0 }
		expect(store.dashboardQuotaReached).toBe(true)
		expect(store.dashboardQuotaTooltip).toBe('You have reached the limit of 5 dashboards')
	})

	it('dashboardQuotaReached is false below the limit', async () => {
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()
		store.quota = { maxDashboards: 5, dashboardsUsed: 4, maxWidgetsPerDashboard: 0 }
		expect(store.dashboardQuotaReached).toBe(false)
	})

	it('widgetQuotaReached reflects the active dashboard placement count', async () => {
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()
		store.quota = { maxDashboards: 0, dashboardsUsed: 0, maxWidgetsPerDashboard: 2 }
		store.widgetPlacements = [{ id: 1 }, { id: 2 }]
		expect(store.widgetQuotaReached).toBe(true)
		expect(store.widgetQuotaTooltip).toBe('You have reached the limit of 2 widgets on this dashboard')

		store.widgetPlacements = [{ id: 1 }]
		expect(store.widgetQuotaReached).toBe(false)
		expect(store.widgetQuotaTooltip).toBe('')
	})

	it('widgetQuotaReached is false when unlimited', async () => {
		const { useDashboardStore } = await import('../dashboard.js')
		const store = useDashboardStore()
		store.quota = { maxDashboards: 0, dashboardsUsed: 0, maxWidgetsPerDashboard: 0 }
		store.widgetPlacements = [{ id: 1 }, { id: 2 }, { id: 3 }]
		expect(store.widgetQuotaReached).toBe(false)
	})
})
