/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the dashboard store's reaction actions
 * (REQ-RXN-001..003): the cached summary per dashboard, the missing-input
 * guards, and how failures are reported.
 */

import { showError } from '@nextcloud/dialogs'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
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
		getDashboardReactions: vi.fn(),
		addDashboardReaction: vi.fn(),
		removeDashboardReaction: vi.fn(),
	},
}))

beforeEach(() => {
	setActivePinia(createPinia())
	vi.resetAllMocks()
})

describe('dashboard store reactions', () => {
	it('caches the fetched summary per dashboard', async () => {
		const store = useDashboardStore()
		store.reactionsSummary = { other: { '👍': 1 } }
		api.getDashboardReactions.mockResolvedValue({ data: { '🎉': 2 } })

		const summary = await store.fetchReactionsSummary('d-1')

		expect(api.getDashboardReactions).toHaveBeenCalledWith('d-1')
		expect(summary).toEqual({ '🎉': 2 })
		expect(store.reactionsSummary).toEqual({
			other: { '👍': 1 },
			'd-1': { '🎉': 2 },
		})
	})

	it('returns null without a dashboard or when the fetch fails', async () => {
		const store = useDashboardStore()

		expect(await store.fetchReactionsSummary('')).toBeNull()
		expect(api.getDashboardReactions).not.toHaveBeenCalled()

		api.getDashboardReactions.mockRejectedValue(new Error('down'))
		expect(await store.fetchReactionsSummary('d-1')).toBeNull()
		expect(store.reactionsSummary).toEqual({})
	})

	it('adding a reaction stores the returned summary', async () => {
		const store = useDashboardStore()
		api.addDashboardReaction.mockResolvedValue({ data: { '👍': 3 } })

		const summary = await store.addReaction('d-1', '👍')

		expect(api.addDashboardReaction).toHaveBeenCalledWith('d-1', '👍')
		expect(summary).toEqual({ '👍': 3 })
		expect(store.reactionsSummary['d-1']).toEqual({ '👍': 3 })
	})

	it('adding or removing needs both a dashboard and an emoji', async () => {
		const store = useDashboardStore()

		expect(await store.addReaction('d-1', '')).toBeNull()
		expect(await store.addReaction('', '👍')).toBeNull()
		expect(await store.removeReaction('d-1', '')).toBeNull()
		expect(api.addDashboardReaction).not.toHaveBeenCalled()
		expect(api.removeDashboardReaction).not.toHaveBeenCalled()
	})

	it('a failed add shows the server error, else a generic one', async () => {
		const store = useDashboardStore()
		api.addDashboardReaction.mockRejectedValueOnce({
			response: { data: { error: 'Unknown emoji' } },
		})
		expect(await store.addReaction('d-1', '🦄')).toBeNull()
		expect(showError).toHaveBeenLastCalledWith('Unknown emoji')

		api.addDashboardReaction.mockRejectedValueOnce(new Error('x'))
		expect(await store.addReaction('d-1', '🦄')).toBeNull()
		expect(showError).toHaveBeenLastCalledWith('Operation failed')
		expect(store.reactionsSummary).toEqual({})
	})

	it('removing a reaction refetches the summary', async () => {
		const store = useDashboardStore()
		api.removeDashboardReaction.mockResolvedValue({})
		api.getDashboardReactions.mockResolvedValue({ data: { '👍': 0 } })

		const summary = await store.removeReaction('d-1', '👍')

		expect(api.removeDashboardReaction).toHaveBeenCalledWith('d-1', '👍')
		expect(api.getDashboardReactions).toHaveBeenCalledWith('d-1')
		expect(summary).toEqual({ '👍': 0 })
		expect(store.reactionsSummary['d-1']).toEqual({ '👍': 0 })
	})

	it('a failed remove shows the error and does not refetch', async () => {
		const store = useDashboardStore()
		api.removeDashboardReaction.mockRejectedValue({
			response: { data: { error: 'Not yours' } },
		})

		expect(await store.removeReaction('d-1', '👍')).toBeNull()
		expect(showError).toHaveBeenCalledWith('Not yours')
		expect(api.getDashboardReactions).not.toHaveBeenCalled()
	})
})
