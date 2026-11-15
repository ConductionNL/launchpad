/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the dashboard store's persistence actions:
 * publish / unpublish, placement saves routed per dashboard source,
 * push-down merges, removal of (compulsory) placements, single-placement
 * patches, the dashboard tree, view events and metadata fields, plus the
 * acknowledgement and lookup getters.
 *
 * The api module, dialogs, l10n and logger are mocked; nothing else is.
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, str) => str,
	translatePlural: (_app, sing, plur, n) => (n === 1 ? sing : plur),
}))

vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))

vi.mock('../../services/api.js', () => ({
	api: {
		publishDashboard: vi.fn(),
		unpublishDashboard: vi.fn(),
		updateDashboard: vi.fn(),
		updateGroupDashboard: vi.fn(),
		removeWidget: vi.fn(),
		updateWidgetPlacement: vi.fn(),
		getDashboardTree: vi.fn(),
		recordDashboardViewEvent: vi.fn(),
		getMetadataFields: vi.fn(),
	},
}))

const { showError } = await import('@nextcloud/dialogs')
const { logger } = await import('../../utils/logger.js')
const { api } = await import('../../services/api.js')
const { useDashboardStore } = await import('../dashboard.js')

describe('dashboard store persistence', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
		store = useDashboardStore()
	})

	describe('publishDashboard / unpublishDashboard', () => {
		it('patches the listed and active copy with the publication fields only', async () => {
			store.dashboards = [{ uuid: 'd1', name: 'Home', publicationStatus: 'draft' }, { uuid: 'd2', name: 'Other' }]
			store.activeDashboard = { uuid: 'd1', name: 'Home', publicationStatus: 'draft' }
			const returned = { uuid: 'd1', name: 'Renamed', publicationStatus: 'published', publishAt: null, publishedAt: '2026-11-01T10:00:00Z' }
			api.publishDashboard.mockResolvedValue({ data: { dashboard: returned } })

			await expect(store.publishDashboard('d1')).resolves.toBe(returned)

			expect(api.publishDashboard).toHaveBeenCalledWith('d1')
			const patched = { uuid: 'd1', name: 'Home', publicationStatus: 'published', publishAt: null, publishedAt: '2026-11-01T10:00:00Z', unpublishAt: null }
			expect(store.dashboards).toEqual([patched, { uuid: 'd2', name: 'Other' }])
			expect(store.activeDashboard).toEqual(patched)
		})

		it('answers null without patching when the response carries no dashboard', async () => {
			store.dashboards = [{ uuid: 'd1', publicationStatus: 'published' }]
			api.unpublishDashboard.mockResolvedValue({ data: {} })

			await expect(store.unpublishDashboard('d1')).resolves.toBeNull()
			expect(store.dashboards).toEqual([{ uuid: 'd1', publicationStatus: 'published' }])
		})

		it('logs, shows an error and answers null when the call fails', async () => {
			const failure = new Error('403')
			api.publishDashboard.mockRejectedValue(failure)
			api.unpublishDashboard.mockRejectedValue(failure)

			await expect(store.publishDashboard('d1')).resolves.toBeNull()
			await expect(store.unpublishDashboard('d1')).resolves.toBeNull()

			expect(logger.error).toHaveBeenCalledWith('Failed to publish dashboard:', failure)
			expect(logger.error).toHaveBeenCalledWith('Failed to unpublish dashboard:', failure)
			expect(showError).toHaveBeenNthCalledWith(1, 'Publish dashboard')
			expect(showError).toHaveBeenNthCalledWith(2, 'Unpublish dashboard')
		})
	})

	describe('updatePlacements', () => {
		const placements = [
			{ id: 1, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2, title: 'A' },
			{ id: 2, gridX: 4, gridY: 0, gridWidth: 2, gridHeight: 3, title: 'B' },
		]
		const geometry = [
			{ id: 1, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2 },
			{ id: 2, gridX: 4, gridY: 0, gridWidth: 2, gridHeight: 3 },
		]

		it('saves a personal dashboard through its own endpoint with geometry only', async () => {
			store.activeDashboard = { id: 7, uuid: 'u7', source: 'user' }
			api.updateDashboard.mockResolvedValue({ data: {} })

			await store.updatePlacements(placements)

			expect(store.widgetPlacements).toStrictEqual(placements)
			expect(api.updateDashboard).toHaveBeenCalledWith(7, { placements: geometry })
			expect(api.updateGroupDashboard).not.toHaveBeenCalled()
			expect(store.saving).toBe(false)
		})

		it.each(['group', 'default'])('saves a %s dashboard through the group endpoint', async (source) => {
			store.activeDashboard = { id: 7, uuid: 'u7', groupId: 'staff', source }
			api.updateGroupDashboard.mockResolvedValue({ data: {} })

			await store.updatePlacements(placements)

			expect(api.updateGroupDashboard).toHaveBeenCalledWith('staff', 'u7', { placements: geometry })
			expect(api.updateDashboard).not.toHaveBeenCalled()
		})

		it('keeps the local layout and clears saving when the save fails', async () => {
			store.activeDashboard = { id: 7, source: 'user' }
			const failure = new Error('500')
			api.updateDashboard.mockRejectedValue(failure)

			await store.updatePlacements(placements)

			expect(store.widgetPlacements).toStrictEqual(placements)
			expect(store.saving).toBe(false)
			expect(logger.error).toHaveBeenCalledWith('Failed to save placements:', failure)
		})
	})

	describe('applyPushedPlacements', () => {
		it('does nothing for an empty or missing push list', async () => {
			await store.applyPushedPlacements([])
			await store.applyPushedPlacements(undefined)

			expect(api.updateDashboard).not.toHaveBeenCalled()
		})

		it('moves only the pushed placements down, matching ids as strings, in one save', async () => {
			store.activeDashboard = { id: 3, source: 'user' }
			store.widgetPlacements = [
				{ id: 1, gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2 },
				{ id: 2, gridX: 0, gridY: 2, gridWidth: 2, gridHeight: 2 },
			]
			api.updateDashboard.mockResolvedValue({ data: {} })

			await store.applyPushedPlacements([{ id: '2', gridY: 5 }])

			expect(store.widgetPlacements.map((p) => p.gridY)).toEqual([0, 5])
			expect(api.updateDashboard).toHaveBeenCalledTimes(1)
			expect(api.updateDashboard.mock.calls[0][1].placements[1]).toEqual({ id: 2, gridX: 0, gridY: 5, gridWidth: 2, gridHeight: 2 })
		})
	})

	describe('removeWidgetFromDashboard', () => {
		it('refuses a compulsory placement without full permission', async () => {
			store.widgetPlacements = [{ id: 1, isCompulsory: true }]
			store.permissionLevel = 'add_only'

			await store.removeWidgetFromDashboard(1)

			expect(api.removeWidget).not.toHaveBeenCalled()
			expect(logger.warn).toHaveBeenCalledWith('Cannot remove compulsory widget')
			expect(store.widgetPlacements).toHaveLength(1)
		})

		it('removes a compulsory placement with full permission', async () => {
			store.widgetPlacements = [{ id: 1, isCompulsory: true }, { id: 2 }]
			api.removeWidget.mockResolvedValue({})

			await store.removeWidgetFromDashboard(1)

			expect(api.removeWidget).toHaveBeenCalledWith(1)
			expect(store.widgetPlacements).toEqual([{ id: 2 }])
		})

		it('keeps the placement when the delete fails', async () => {
			store.widgetPlacements = [{ id: 2 }]
			const failure = new Error('gone')
			api.removeWidget.mockRejectedValue(failure)

			await store.removeWidgetFromDashboard(2)

			expect(store.widgetPlacements).toEqual([{ id: 2 }])
			expect(logger.error).toHaveBeenCalledWith('Failed to remove widget:', failure)
		})
	})

	describe('updateWidgetPlacement', () => {
		it('replaces the placement with the server copy', async () => {
			store.widgetPlacements = [{ id: 1, title: 'old' }, { id: 2, title: 'x' }]
			api.updateWidgetPlacement.mockResolvedValue({ data: { id: 2, title: 'new' } })

			await store.updateWidgetPlacement(2, { title: 'new' })

			expect(api.updateWidgetPlacement).toHaveBeenCalledWith(2, { title: 'new' })
			expect(store.widgetPlacements).toEqual([{ id: 1, title: 'old' }, { id: 2, title: 'new' }])
		})

		it('leaves the list alone for an unknown placement', async () => {
			store.widgetPlacements = [{ id: 1 }]
			api.updateWidgetPlacement.mockResolvedValue({ data: { id: 9 } })

			await store.updateWidgetPlacement(9, {})

			expect(store.widgetPlacements).toEqual([{ id: 1 }])
		})

		it('logs the failure and its response body', async () => {
			const failure = Object.assign(new Error('422'), { response: { data: { message: 'bad' } } })
			api.updateWidgetPlacement.mockRejectedValue(failure)

			await store.updateWidgetPlacement(1, {})

			expect(logger.error).toHaveBeenCalledWith('Failed to update widget placement:', failure)
			expect(logger.error).toHaveBeenCalledWith('Error details:', { message: 'bad' })
		})
	})

	describe('loadDashboardTree', () => {
		it('stores an array answer and anything else as empty', async () => {
			api.getDashboardTree.mockResolvedValueOnce({ data: [{ uuid: 'root', children: [] }] })
			await store.loadDashboardTree()
			expect(store.dashboardTree).toEqual([{ uuid: 'root', children: [] }])

			api.getDashboardTree.mockResolvedValueOnce({ data: { tree: [] } })
			await store.loadDashboardTree()
			expect(store.dashboardTree).toEqual([])
		})

		it('empties the tree when loading fails', async () => {
			store.dashboardTree = [{ uuid: 'stale' }]
			const failure = new Error('down')
			api.getDashboardTree.mockRejectedValue(failure)

			await store.loadDashboardTree()

			expect(store.dashboardTree).toEqual([])
			expect(logger.error).toHaveBeenCalledWith('Failed to load dashboard tree:', failure)
		})
	})

	describe('recordViewEvent', () => {
		it('skips an empty uuid and records a given one', async () => {
			api.recordDashboardViewEvent.mockResolvedValue({})

			await store.recordViewEvent('')
			await store.recordViewEvent('d1')

			expect(api.recordDashboardViewEvent).toHaveBeenCalledTimes(1)
			expect(api.recordDashboardViewEvent).toHaveBeenCalledWith('d1')
		})

		it('only warns when recording fails', async () => {
			const failure = new Error('offline')
			api.recordDashboardViewEvent.mockRejectedValue(failure)

			await expect(store.recordViewEvent('d1')).resolves.toBeUndefined()
			expect(logger.warn).toHaveBeenCalledWith('Failed to record dashboard view event:', failure)
		})
	})

	describe('fetchMetadataFields', () => {
		it('accepts a wrapped list, a bare list, and treats anything else as empty', async () => {
			api.getMetadataFields.mockResolvedValueOnce({ data: { fields: [{ key: 'owner' }] } })
			await store.fetchMetadataFields()
			expect(store.metadataFields).toEqual([{ key: 'owner' }])

			api.getMetadataFields.mockResolvedValueOnce({ data: [{ key: 'topic' }] })
			await store.fetchMetadataFields()
			expect(store.metadataFields).toEqual([{ key: 'topic' }])

			api.getMetadataFields.mockResolvedValueOnce({ data: 'nope' })
			await store.fetchMetadataFields()
			expect(store.metadataFields).toEqual([])
		})

		it('is silent for a forbidden answer and logs any other failure', async () => {
			api.getMetadataFields.mockRejectedValueOnce({ response: { status: 403 } })
			await store.fetchMetadataFields()
			expect(logger.error).not.toHaveBeenCalled()

			const failure = { response: { status: 500 } }
			api.getMetadataFields.mockRejectedValueOnce(failure)
			store.metadataFields = [{ key: 'stale' }]
			await store.fetchMetadataFields()
			expect(logger.error).toHaveBeenCalledWith('Failed to load metadata fields:', failure)
			expect(store.metadataFields).toEqual([])
		})
	})

	describe('getters', () => {
		it('finds placements by id and lists the compulsory ones', () => {
			store.widgetPlacements = [{ id: 1, isCompulsory: true }, { id: 2 }]

			expect(store.getPlacementById(2)).toEqual({ id: 2 })
			expect(store.getPlacementById(3)).toBeUndefined()
			expect(store.compulsoryPlacements).toEqual([{ id: 1, isCompulsory: true }])
		})

		it('counts outstanding acknowledgements and collects their keys', () => {
			store.pendingAcknowledgements = [{ announcementKey: 'a' }, { announcementKey: '' }, { announcementKey: 'b' }]

			expect(store.outstandingAcknowledgementCount).toBe(3)
			expect([...store.pendingAnnouncementKeys]).toEqual(['a', 'b'])
		})

		it('marks a placement outstanding only when it requires acknowledgement of a pending key', () => {
			store.pendingAcknowledgements = [{ announcementKey: 'a' }]

			expect(store.isPlacementOutstanding({ requiresAcknowledgement: '1', announcementKey: 'a' })).toBe(true)
			expect(store.isPlacementOutstanding({ requiresAcknowledgement: 1, announcementKey: 'b' })).toBe(false)
			expect(store.isPlacementOutstanding({ requiresAcknowledgement: 1 })).toBe(false)
			expect(store.isPlacementOutstanding({ requiresAcknowledgement: 0, announcementKey: 'a' })).toBe(false)
			expect(store.isPlacementOutstanding(null)).toBe(false)
		})

		it('answers cached metadata or an empty object, and sorts fields by order then label', () => {
			store.metadataByDashboard = { d1: { owner: 'x' } }
			store.metadataFields = [
				{ label: 'Zeta', sortOrder: 1 },
				{ label: 'beta', sortOrder: 2 },
				{ label: 'Alpha', sortOrder: 1 },
				{ label: 'Gamma' },
			]

			expect(store.metadataFor('d1')).toEqual({ owner: 'x' })
			expect(store.metadataFor('d2')).toEqual({})
			expect(store.metadataFieldsSorted.map((f) => f.label)).toEqual(['Gamma', 'Alpha', 'Zeta', 'beta'])
		})
	})
})
