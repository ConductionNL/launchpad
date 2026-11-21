/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the dashboard store's refresh of the widgets the
 * visibility rules hide for viewers (REQ-VIS-013): the flag is copied onto
 * the loaded placements and the layout itself is left alone.
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'
import { useDashboardStore } from '../dashboard.js'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('../../utils/logger.js', () => ({
	logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn(), debug: vi.fn() },
}))

vi.mock('../../services/api.js', () => ({
	api: {
		getDashboardById: vi.fn(),
	},
}))

beforeEach(() => {
	setActivePinia(createPinia())
	vi.resetAllMocks()
})

describe('dashboard store widget visibility', () => {
	it('copies hiddenForViewers onto the loaded placements and keeps their layout', async () => {
		const store = useDashboardStore()
		store.activeDashboard = { id: 7 }
		store.widgetPlacements = [
			{ id: 1, gridX: 4, hiddenForViewers: false },
			{ id: 2, gridX: 0, hiddenForViewers: true },
			{ id: 3, gridX: 8 },
		]
		api.getDashboardById.mockResolvedValue({
			data: {
				placements: [
					{ id: 1, gridX: 0, hiddenForViewers: true },
					{ id: 2, gridX: 0, hiddenForViewers: false },
				],
			},
		})

		await store.refreshPlacementVisibility()

		expect(api.getDashboardById).toHaveBeenCalledWith(7)
		expect(store.widgetPlacements).toEqual([
			{ id: 1, gridX: 4, hiddenForViewers: true },
			{ id: 2, gridX: 0, hiddenForViewers: false },
			{ id: 3, gridX: 8 },
		])
	})

	it('does nothing without an active dashboard', async () => {
		const store = useDashboardStore()

		await store.refreshPlacementVisibility()

		expect(api.getDashboardById).not.toHaveBeenCalled()
	})

	it('logs a failed read and leaves the placements as they were', async () => {
		const store = useDashboardStore()
		store.activeDashboard = { id: 7 }
		store.widgetPlacements = [{ id: 1, hiddenForViewers: true }]
		api.getDashboardById.mockRejectedValue(new Error('down'))

		await store.refreshPlacementVisibility()

		expect(logger.error).toHaveBeenCalled()
		expect(store.widgetPlacements).toEqual([{ id: 1, hiddenForViewers: true }])
	})

	it('reloads the active placements in place, and keeps them when the read fails', async () => {
		const store = useDashboardStore()
		store.activeDashboard = { id: 7 }
		store.widgetPlacements = [{ id: 1, content: { text: 'old' } }]
		api.getDashboardById.mockResolvedValueOnce({ data: { placements: [{ id: 1, content: { text: 'new' } }] } })

		await store.reloadPlacements()
		expect(store.widgetPlacements).toEqual([{ id: 1, content: { text: 'new' } }])
		expect(store.loading).toBe(false)

		api.getDashboardById.mockRejectedValueOnce(new Error('down'))
		await store.reloadPlacements()
		expect(store.widgetPlacements).toEqual([{ id: 1, content: { text: 'new' } }])
		expect(logger.error).toHaveBeenCalled()
	})
})
