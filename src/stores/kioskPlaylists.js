/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pinia store for kiosk-playlist CRUD plus the anonymous public render fetch.
 * A playlist is a named, ordered list of dashboards addressed by a single
 * URL-safe token and rendered chrome-less on a wall display.
 *
 * @spec openspec/changes/dashboard-kiosk-mode/specs/dashboard-kiosk-mode/spec.md
 */

import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/launchpad')

/** @spec openspec/changes/dashboard-kiosk-mode/specs/dashboard-kiosk-mode/spec.md */
export const useKioskPlaylistStore = defineStore('kioskPlaylists', {
	state: () => ({
		/** @type {object[]} playlists visible to the current user */
		playlists: [],
		loading: false,
	}),

	getters: {
		/**
		 * Return a playlist by its primary key, or undefined.
		 *
		 * @param {object} state The store state.
		 * @return {(id: number|string) => (object|undefined)} Lookup function.
		 */
		playlistById: (state) => (id) => state.playlists.find((p) => p.id === id),
	},

	actions: {
		/**
		 * Fetch the playlists visible to the caller (own for users, all for admins).
		 *
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md#req-kiosk-002
		 */
		async fetchPlaylists() {
			this.loading = true
			try {
				const { data } = await axios.get(`${baseUrl}/api/kiosk/playlists`)
				this.playlists = Array.isArray(data) ? data : []
				return this.playlists
			} finally {
				this.loading = false
			}
		},

		/**
		 * Create a playlist.
		 *
		 * @param {object} payload The new playlist.
		 * @param {string} payload.name Display name.
		 * @param {Array<{dashboardUuid: string, dwellSeconds: number}>} payload.entries
		 *   Dashboards to cycle through, with how long each is shown.
		 * @param {number} [payload.refreshSeconds] How often the kiosk
		 *   re-fetches dashboard content.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md#req-kiosk-002
		 */
		async createPlaylist({ name, entries, refreshSeconds = 300 }) {
			this.loading = true
			try {
				const { data } = await axios.post(`${baseUrl}/api/kiosk/playlists`, {
					name,
					entries,
					refreshSeconds,
				})
				this.playlists.push(data)
				return data
			} finally {
				this.loading = false
			}
		},

		/**
		 * Update an existing playlist.
		 *
		 * @param {number|string} id Primary key of the playlist to update.
		 * @param {object} payload The replacement values.
		 * @param {string} payload.name Display name.
		 * @param {Array<{dashboardUuid: string, dwellSeconds: number}>} payload.entries
		 *   Dashboards to cycle through, with how long each is shown.
		 * @param {number} [payload.refreshSeconds] How often the kiosk
		 *   re-fetches dashboard content.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md#req-kiosk-002
		 */
		async updatePlaylist(id, { name, entries, refreshSeconds = 300 }) {
			this.loading = true
			try {
				const { data } = await axios.put(
					`${baseUrl}/api/kiosk/playlists/${encodeURIComponent(id)}`,
					{ name, entries, refreshSeconds },
				)
				const idx = this.playlists.findIndex((p) => p.id === id)
				if (idx !== -1) {
					this.playlists.splice(idx, 1, data)
				}
				return data
			} finally {
				this.loading = false
			}
		},

		/**
		 * Soft-revoke a playlist; removes it from the local list.
		 *
		 * @param {number|string} id Primary key of the playlist to revoke.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md#req-kiosk-002
		 */
		async revokePlaylist(id) {
			await axios.delete(`${baseUrl}/api/kiosk/playlists/${encodeURIComponent(id)}`)
			this.playlists = this.playlists.filter((p) => p.id !== id)
		},

		/**
		 * Anonymously fetch a playlist render payload by token. Used by the
		 * public KioskView; returns { playlist, entries } or throws on 404.
		 *
		 * @param {string} token Public share token identifying the playlist.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md#req-kiosk-003
		 */
		async fetchRender(token) {
			const { data } = await axios.get(
				`${baseUrl}/kiosk/${encodeURIComponent(token)}`,
			)
			return data
		},
	},
})
