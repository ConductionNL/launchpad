/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'

const baseUrl = generateUrl('/apps/launchpad')

/** @spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md */
export const usePublicShareStore = defineStore('publicShares', {
	state: () => ({
		/** @type {Record<string, object[]>} shares indexed by dashboardUuid */
		sharesByDashboard: {},
		loading: false,
		/** @type {Record<string, boolean>} token -> unlocked flag */
		unlockedTokens: JSON.parse(
			localStorage.getItem('mydash_unlocked_tokens') ?? '{}',
		),
	}),

	getters: {
		/**
		 * Return active shares for a given dashboard UUID.
		 *
		 * @param {object} state The store state.
		 * @return {(uuid: string) => object[]} Lookup function; `[]` when the
		 *   dashboard has no shares loaded.
		 */
		sharesFor: (state) => (uuid) => state.sharesByDashboard[uuid] ?? [],

		/**
		 * True if the given token has been unlocked this session.
		 *
		 * @param {object} state The store state.
		 * @return {(token: string) => boolean} Lookup function.
		 */
		isUnlocked: (state) => (token) => state.unlockedTokens[token] === true,
	},

	actions: {
		/**
		 * Create a public share for a dashboard.
		 *
		 * @param {string} dashboardUuid UUID of the dashboard to share.
		 * @param {object} [options] Share options.
		 * @param {string|null} [options.password] Password required to open
		 *   the link; null leaves the share unprotected.
		 * @param {string|null} [options.expiresAt] ISO-8601 expiry; null
		 *   creates a share that does not expire.
		 * @spec openspec/specs/dashboard-public-share/spec.md#req-pshr-001
		 * @return {Promise<object>} The created share record.
		 */
		async createShare(
			dashboardUuid,
			{ password = null, expiresAt = null } = {},
		) {
			this.loading = true
			try {
				const body = {}
				if (password) body.password = password
				if (expiresAt) body.expiresAt = expiresAt

				const { data } = await axios.post(
					`${baseUrl}/api/dashboards/${encodeURIComponent(dashboardUuid)}/public-share`,
					body,
				)
				if (this.sharesByDashboard[dashboardUuid] === undefined) {
					this.sharesByDashboard[dashboardUuid] = []
				}
				this.sharesByDashboard[dashboardUuid].push(data)
				return data
			} finally {
				this.loading = false
			}
		},

		/**
		 * Fetch active shares for a dashboard.
		 *
		 * @param {string} dashboardUuid UUID of the dashboard to inspect.
		 * @spec openspec/specs/dashboard-public-share/spec.md#req-pshr-002
		 * @return {Promise<object[]>} The dashboard's active shares.
		 */
		async fetchShares(dashboardUuid) {
			this.loading = true
			try {
				const { data } = await axios.get(
					`${baseUrl}/api/dashboards/${encodeURIComponent(dashboardUuid)}/public-shares`,
				)
				this.sharesByDashboard[dashboardUuid] = data
				return data
			} finally {
				this.loading = false
			}
		},

		/**
		 * Soft-revoke a share and drop it from the local list.
		 *
		 * @param {string} dashboardUuid UUID of the dashboard that owns it.
		 * @param {number|string} shareId Id of the share to revoke.
		 * @spec openspec/specs/dashboard-public-share/spec.md#req-pshr-003
		 */
		async revokeShare(dashboardUuid, shareId) {
			await axios.delete(
				`${baseUrl}/api/dashboards/${encodeURIComponent(dashboardUuid)}/public-shares/${shareId}`,
			)
			if (this.sharesByDashboard[dashboardUuid]) {
				this.sharesByDashboard[dashboardUuid] = this.sharesByDashboard[
					dashboardUuid
				].filter((s) => s.id !== shareId)
			}
		},

		/**
		 * Mark a token as unlocked and persist to localStorage, so a correct
		 * password is not re-demanded on every subsequent render of the same
		 * share within the session (REQ-PSHR-005 "Unlock with correct
		 * password"). This is a UX cache only — the server still gates every
		 * render on the unlock header/param.
		 *
		 * @param {string} token Public share token the user just unlocked.
		 * @spec openspec/specs/dashboard-public-share/spec.md#req-pshr-005
		 */
		markUnlocked(token) {
			this.unlockedTokens = { ...this.unlockedTokens, [token]: true }
			localStorage.setItem(
				'mydash_unlocked_tokens',
				JSON.stringify(this.unlockedTokens),
			)
		},

		/**
		 * Remove an unlocked token (e.g. on session end), returning the share
		 * to the REQ-PSHR-005 password gate on its next render.
		 *
		 * @param {string} token Public share token to re-lock.
		 * @spec openspec/specs/dashboard-public-share/spec.md#req-pshr-005
		 */
		clearUnlocked(token) {
			const updated = { ...this.unlockedTokens }
			delete updated[token]
			this.unlockedTokens = updated
			localStorage.setItem('mydash_unlocked_tokens', JSON.stringify(updated))
		},
	},
})
