/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the tile store (load, create, update, delete, and
 * the logged failures) and the widget store's catalogue load, item lookups
 * and refresh.
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { widgetBridge } from '../../services/widgetBridge.js'
import { logger } from '../../utils/logger.js'
import { useTileStore } from '../tiles.js'
import { useWidgetStore } from '../widgets.js'

vi.mock('../../utils/logger.js', () => ({
	logger: { error: vi.fn(), debug: vi.fn() },
}))

vi.mock('../../services/api.js', () => ({
	api: {
		getTiles: vi.fn(),
		createTile: vi.fn(),
		updateTile: vi.fn(),
		deleteTile: vi.fn(),
		getAvailableWidgets: vi.fn(),
		getWidgetItems: vi.fn(),
	},
}))

vi.mock('../../services/widgetBridge.js', () => ({
	widgetBridge: { setWidgetMetadata: vi.fn() },
}))

beforeEach(() => {
	vi.clearAllMocks()
	setActivePinia(createPinia())
})

describe('tiles store', () => {
	it('loads tiles and logs a failed load without throwing', async () => {
		const store = useTileStore()
		api.getTiles.mockResolvedValueOnce({ data: [{ id: 1 }] })
		await store.loadTiles()
		expect([store.tiles, store.loading]).toEqual([[{ id: 1 }], false])

		const failure = new Error('down')
		api.getTiles.mockRejectedValueOnce(failure)
		await store.loadTiles()
		expect(logger.error).toHaveBeenCalledWith('Failed to load tiles:', failure)
		expect([store.tiles, store.loading]).toEqual([[{ id: 1 }], false])
	})

	it('creates, updates and deletes tiles in the list', async () => {
		const store = useTileStore()
		store.tiles = [{ id: 1, title: 'A' }]
		api.createTile.mockResolvedValueOnce({ data: { id: 2, title: 'B' } })
		api.updateTile.mockResolvedValue({ data: { id: 1, title: 'A2' } })
		api.deleteTile.mockResolvedValueOnce({})

		expect(await store.createTile({ title: 'B' })).toEqual({ id: 2, title: 'B' })
		expect(await store.updateTile(1, { title: 'A2' })).toEqual({ id: 1, title: 'A2' })
		await store.updateTile(99, { title: 'ghost' })
		await store.deleteTile(2)

		expect(api.createTile).toHaveBeenCalledWith({ title: 'B' })
		expect(api.updateTile).toHaveBeenCalledWith(1, { title: 'A2' })
		expect(api.deleteTile).toHaveBeenCalledWith(2)
		expect(store.tiles).toEqual([{ id: 1, title: 'A2' }])
	})

	it('logs and rethrows a failed write, leaving the list alone', async () => {
		const store = useTileStore()
		store.tiles = [{ id: 1 }]
		const cases = [
			[() => store.createTile({}), api.createTile, 'Failed to create tile:'],
			[() => store.updateTile(1, {}), api.updateTile, 'Failed to update tile:'],
			[() => store.deleteTile(1), api.deleteTile, 'Failed to delete tile:'],
		]

		for (const [call, method, message] of cases) {
			const failure = new Error(message)
			method.mockRejectedValueOnce(failure)
			await expect(call()).rejects.toBe(failure)
			expect(logger.error).toHaveBeenLastCalledWith(message, failure)
		}
		expect(store.tiles).toEqual([{ id: 1 }])
	})
})

describe('widgets store', () => {
	it('loads the widget catalogue and hands it to the dashboard bridge', async () => {
		const store = useWidgetStore()
		const widgets = [{ id: 'weather', title: 'Weather' }]
		api.getAvailableWidgets.mockResolvedValueOnce({ data: widgets })

		await store.loadAvailableWidgets()

		expect(store.availableWidgets).toEqual(widgets)
		expect(widgetBridge.setWidgetMetadata).toHaveBeenCalledWith(widgets)
		expect(store.getWidgetById('weather')).toEqual(widgets[0])
		expect(store.getWidgetById('nope')).toBeUndefined()
		expect(store.loading).toBe(false)
	})

	it('logs a failed catalogue load', async () => {
		const store = useWidgetStore()
		const failure = new Error('500')
		api.getAvailableWidgets.mockRejectedValueOnce(failure)

		await store.loadAvailableWidgets()

		expect(logger.error).toHaveBeenCalledWith('Failed to load available widgets:', failure)
		expect(widgetBridge.setWidgetMetadata).not.toHaveBeenCalled()
		expect(store.loading).toBe(false)
	})

	it('answers an empty entry for unknown widgets and refreshes one widget', async () => {
		const store = useWidgetStore()
		expect(store.getWidgetItems('x')).toEqual({ items: [], loading: false })

		api.getWidgetItems.mockResolvedValueOnce({ data: { x: { items: [1] } } })
		await store.refreshWidgetItems('x')

		expect(api.getWidgetItems).toHaveBeenCalledWith(['x'])
		expect(store.getWidgetItems('x')).toEqual({ items: [1], emptyContentMessage: '', halfEmptyContentMessage: '', loading: false })
	})
})
