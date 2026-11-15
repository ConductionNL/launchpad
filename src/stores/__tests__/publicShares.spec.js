/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the public-share store: creating, listing and
 * revoking a dashboard's public shares through the API, and remembering
 * which share tokens the viewer unlocked in localStorage.
 */

import axios from '@nextcloud/axios'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { usePublicShareStore } from '../publicShares.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

const BASE = '/index.php/apps/launchpad/api/dashboards'

describe('publicShares store', () => {
	beforeEach(() => {
		localStorage.clear()
		vi.clearAllMocks()
		setActivePinia(createPinia())
	})

	it('creates a share with only the options given and lists it under its dashboard', async () => {
		axios.post.mockResolvedValueOnce({ data: { id: 1, token: 't1' } })
		axios.post.mockResolvedValueOnce({ data: { id: 2, token: 't2' } })
		const store = usePublicShareStore()

		const created = await store.createShare('dash/1')
		await store.createShare('dash/1', { password: 'secret', expiresAt: '2027-01-01' })

		expect(created).toEqual({ id: 1, token: 't1' })
		expect(axios.post.mock.calls).toEqual([
			[`${BASE}/dash%2F1/public-share`, {}],
			[`${BASE}/dash%2F1/public-share`, { password: 'secret', expiresAt: '2027-01-01' }],
		])
		expect(store.sharesFor('dash/1').map((s) => s.id)).toEqual([1, 2])
		expect(store.loading).toBe(false)
	})

	it('stops loading when creating fails', async () => {
		axios.post.mockRejectedValueOnce(new Error('403'))
		const store = usePublicShareStore()

		await expect(store.createShare('d')).rejects.toThrow('403')
		expect(store.loading).toBe(false)
		expect(store.sharesFor('d')).toEqual([])
	})

	it('fetches the shares of a dashboard and replaces what was known', async () => {
		axios.get.mockResolvedValueOnce({ data: [{ id: 5 }] })
		const store = usePublicShareStore()
		store.sharesByDashboard.d = [{ id: 1 }]

		expect(await store.fetchShares('d')).toEqual([{ id: 5 }])
		expect(axios.get).toHaveBeenCalledWith(`${BASE}/d/public-shares`)
		expect(store.sharesFor('d')).toEqual([{ id: 5 }])
		expect(store.loading).toBe(false)
	})

	it('revokes a share and drops it from the list', async () => {
		axios.delete.mockResolvedValue({})
		const store = usePublicShareStore()
		store.sharesByDashboard.d = [{ id: 1 }, { id: 2 }]

		await store.revokeShare('d', 1)
		await store.revokeShare('unknown', 9)

		expect(axios.delete.mock.calls).toEqual([[`${BASE}/d/public-shares/1`], [`${BASE}/unknown/public-shares/9`]])
		expect(store.sharesFor('d')).toEqual([{ id: 2 }])
		expect(store.sharesFor('unknown')).toEqual([])
	})

	it('remembers unlocked tokens in localStorage and forgets them again', () => {
		localStorage.setItem('mydash_unlocked_tokens', JSON.stringify({ old: true }))
		const store = usePublicShareStore()

		expect(store.isUnlocked('old')).toBe(true)
		store.markUnlocked('new')
		expect(store.isUnlocked('new')).toBe(true)
		expect(JSON.parse(localStorage.getItem('mydash_unlocked_tokens'))).toEqual({ old: true, new: true })

		store.clearUnlocked('old')
		expect(store.isUnlocked('old')).toBe(false)
		expect(JSON.parse(localStorage.getItem('mydash_unlocked_tokens'))).toEqual({ new: true })
	})
})
