/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the dashboard store's mandatory-read acknowledgement
 * state (dashboard-acknowledgements REQ-ACK-002 / REQ-ACK-003):
 *  - `fetchPendingAcknowledgements` populates the outstanding set + count
 *  - `outstandingAcknowledgementCount` reflects the number of unacknowledged items
 *  - `isPlacementOutstanding` gates on requirement + pending membership
 *  - `acknowledgePlacement` records the receipt and drops the item optimistically
 *
 * The api module is mocked so the store logic is exercised in isolation.
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { useDashboardStore } from '../dashboard.js'

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
		getPendingAcknowledgements: vi.fn(),
		acknowledge: vi.fn(),
	},
}))

function pendingItem(key) {
	return {
		placementId: 1,
		dashboardUuid: 'dash-1',
		announcementKey: key,
		prompt: 'Read this',
		deadline: null,
		contentVersion: 1,
	}
}

describe('dashboard store — acknowledgements', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useDashboardStore()
		vi.clearAllMocks()
	})

	it('starts with an empty outstanding set (no regression when unused)', () => {
		expect(store.outstandingAcknowledgementCount).toBe(0)
		expect(store.pendingAcknowledgements).toEqual([])
	})

	it('fetchPendingAcknowledgements populates the outstanding set and count', async () => {
		api.getPendingAcknowledgements.mockResolvedValue({
			data: { count: 2, items: [pendingItem('ak-1'), pendingItem('ak-2')] },
		})

		await store.fetchPendingAcknowledgements()

		expect(store.outstandingAcknowledgementCount).toBe(2)
		expect([...store.pendingAnnouncementKeys]).toEqual(['ak-1', 'ak-2'])
	})

	it('fetch failure leaves the set empty (non-fatal)', async () => {
		api.getPendingAcknowledgements.mockRejectedValue(new Error('boom'))

		await store.fetchPendingAcknowledgements()

		expect(store.outstandingAcknowledgementCount).toBe(0)
	})

	it('isPlacementOutstanding is true only for a required + pending placement', async () => {
		api.getPendingAcknowledgements.mockResolvedValue({
			data: { items: [pendingItem('ak-1')] },
		})
		await store.fetchPendingAcknowledgements()

		expect(
			store.isPlacementOutstanding({
				requiresAcknowledgement: 1,
				announcementKey: 'ak-1',
			}),
		).toBe(true)
		// Not pending → false.
		expect(
			store.isPlacementOutstanding({
				requiresAcknowledgement: 1,
				announcementKey: 'ak-9',
			}),
		).toBe(false)
		// Requirement off → false.
		expect(
			store.isPlacementOutstanding({
				requiresAcknowledgement: 0,
				announcementKey: 'ak-1',
			}),
		).toBe(false)
	})

	it('acknowledgePlacement records the receipt and drops the item from the count', async () => {
		api.getPendingAcknowledgements.mockResolvedValue({
			data: { items: [pendingItem('ak-1'), pendingItem('ak-2')] },
		})
		await store.fetchPendingAcknowledgements()
		api.acknowledge.mockResolvedValue({ data: {} })

		await store.acknowledgePlacement({
			announcementKey: 'ak-1',
			acknowledgementContentVersion: 1,
		})

		expect(api.acknowledge).toHaveBeenCalledWith('ak-1', 1)
		expect(store.outstandingAcknowledgementCount).toBe(1)
		expect([...store.pendingAnnouncementKeys]).toEqual(['ak-2'])
	})
})
