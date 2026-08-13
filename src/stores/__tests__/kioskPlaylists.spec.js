/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the kioskPlaylists Pinia store (REQ-KIOSK-002/003).
 * axios is mocked at the import boundary so no HTTP traffic is generated.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

let axios
let useKioskPlaylistStore

beforeEach(async () => {
	setActivePinia(createPinia())
	axios = (await import('@nextcloud/axios')).default
	;({ useKioskPlaylistStore } = await import('../kioskPlaylists.js'))
	vi.clearAllMocks()
})

describe('kioskPlaylists store', () => {
	it('fetchPlaylists populates state from the API', async () => {
		axios.get.mockResolvedValue({ data: [{ id: 1, name: 'Lobby' }] })
		const store = useKioskPlaylistStore()
		await store.fetchPlaylists()
		expect(store.playlists).toEqual([{ id: 1, name: 'Lobby' }])
		expect(store.loading).toBe(false)
	})

	it('createPlaylist appends the created playlist', async () => {
		axios.post.mockResolvedValue({
			data: { id: 5, name: 'Reception', entries: [] },
		})
		const store = useKioskPlaylistStore()
		const created = await store.createPlaylist({
			name: 'Reception',
			entries: [],
			refreshSeconds: 300,
		})
		expect(created.id).toBe(5)
		expect(store.playlists).toContainEqual(created)
		expect(axios.post).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/kiosk/playlists',
			{ name: 'Reception', entries: [], refreshSeconds: 300 },
		)
	})

	it('updatePlaylist replaces the existing entry', async () => {
		const store = useKioskPlaylistStore()
		store.playlists = [{ id: 7, name: 'Old' }]
		axios.put.mockResolvedValue({ data: { id: 7, name: 'New' } })
		await store.updatePlaylist(7, {
			name: 'New',
			entries: [],
			refreshSeconds: 60,
		})
		expect(store.playlists).toEqual([{ id: 7, name: 'New' }])
	})

	it('revokePlaylist removes it from local state', async () => {
		const store = useKioskPlaylistStore()
		store.playlists = [{ id: 3 }, { id: 4 }]
		axios.delete.mockResolvedValue({})
		await store.revokePlaylist(3)
		expect(store.playlists).toEqual([{ id: 4 }])
	})

	it('fetchRender returns the public render payload', async () => {
		axios.get.mockResolvedValue({ data: { playlist: { id: 1 }, entries: [] } })
		const store = useKioskPlaylistStore()
		const payload = await store.fetchRender('tok123')
		expect(payload).toEqual({ playlist: { id: 1 }, entries: [] })
		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/kiosk/tok123',
		)
	})
})
