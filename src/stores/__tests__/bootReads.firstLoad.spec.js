/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The tiles and the pending acknowledgements: the first read takes what the
 * page carries, every later read (and a page without them) asks the API.
 *
 * @spec openspec/changes/archive/2026-11-25-workspace-boot-reads-from-initial-state/tasks.md#task-4
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { takeBootRead } from '../../utils/loadInitialState.js'
import { useDashboardStore } from '../dashboard.js'
import { useTileStore } from '../tiles.js'

vi.mock('../../services/api.js', () => ({
	api: {
		getTiles: vi.fn(),
		getPendingAcknowledgements: vi.fn(),
	},
}))
vi.mock('../../utils/loadInitialState.js', () => ({
	takeBootRead: vi.fn(),
	takeDashboardFirstLoad: vi.fn(() => null),
}))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))

beforeEach(() => {
	setActivePinia(createPinia())
	vi.resetAllMocks()
})

describe('tile store first load', () => {
	it('takes the tiles the page carries without asking the API', async () => {
		takeBootRead.mockReturnValue([{ id: 7, title: 'Mail' }])
		const store = useTileStore()

		await store.loadTiles()

		expect(takeBootRead).toHaveBeenCalledWith('tiles')
		expect(store.tiles).toEqual([{ id: 7, title: 'Mail' }])
		expect(api.getTiles).not.toHaveBeenCalled()
	})

	it('asks the API when the page carries none', async () => {
		takeBootRead.mockReturnValue(undefined)
		api.getTiles.mockResolvedValue({ data: [{ id: 8 }] })
		const store = useTileStore()

		await store.loadTiles()

		expect(api.getTiles).toHaveBeenCalledTimes(1)
		expect(store.tiles).toEqual([{ id: 8 }])
	})
})

describe('pending acknowledgements first load', () => {
	it('takes the items the page carries without asking the API', async () => {
		takeBootRead.mockReturnValue({ count: 1, items: [{ announcementKey: 'a1' }] })
		const store = useDashboardStore()

		await store.fetchPendingAcknowledgements()

		expect(takeBootRead).toHaveBeenCalledWith('pendingAcknowledgements')
		expect(store.pendingAcknowledgements).toEqual([{ announcementKey: 'a1' }])
		expect(api.getPendingAcknowledgements).not.toHaveBeenCalled()
	})

	it('asks the API when the page carries none, or a malformed part', async () => {
		takeBootRead.mockReturnValue({ count: 1 })
		api.getPendingAcknowledgements.mockResolvedValue({ data: { items: [{ announcementKey: 'a2' }] } })
		const store = useDashboardStore()

		await store.fetchPendingAcknowledgements()

		expect(api.getPendingAcknowledgements).toHaveBeenCalledTimes(1)
		expect(store.pendingAcknowledgements).toEqual([{ announcementKey: 'a2' }])
	})
})
