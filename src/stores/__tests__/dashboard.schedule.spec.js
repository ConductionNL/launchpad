/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard store's schedule action (REQ-SCHEDUI-001, REQ-SCHEDUI-002):
 * it sends both times, patches the local copy, and hands a refusal back to
 * the dialog instead of swallowing it.
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
	api: { scheduleDashboard: vi.fn() },
}))

describe('dashboard store: schedule', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useDashboardStore()
		store.dashboards = [
			{ uuid: 'd-1', name: 'Open day', publicationStatus: 'published' },
		]
		store.activeDashboard = { ...store.dashboards[0] }
		api.scheduleDashboard.mockReset()
	})

	it('sends both times and patches the local copy', async () => {
		api.scheduleDashboard.mockResolvedValue({
			data: {
				dashboard: {
					uuid: 'd-1',
					publicationStatus: 'scheduled',
					publishAt: '2030-05-01 07:00:00',
					unpublishAt: '2030-05-02 15:00:00',
					publishedAt: null,
				},
			},
		})

		await store.scheduleDashboard(
			'd-1',
			'2030-05-01T07:00:00.000Z',
			'2030-05-02T15:00:00.000Z',
		)

		expect(api.scheduleDashboard).toHaveBeenCalledWith(
			'd-1',
			'2030-05-01T07:00:00.000Z',
			'2030-05-02T15:00:00.000Z',
		)
		expect(store.activeDashboard.unpublishAt).toBe('2030-05-02 15:00:00')
		expect(store.dashboards[0].publicationStatus).toBe('scheduled')
	})

	it('hands a refusal back to the caller', async () => {
		const refusal = Object.assign(new Error('400'), {
			response: { data: { message: 'publishAt must be a future timestamp' } },
		})
		api.scheduleDashboard.mockRejectedValue(refusal)

		await expect(
			store.scheduleDashboard('d-1', '2020-01-01T00:00:00.000Z', null),
		).rejects.toBe(refusal)
		expect(store.activeDashboard.publicationStatus).toBe('published')
	})
})
