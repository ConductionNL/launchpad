/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 */

import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/launchpad')

/** @spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md */
export const usePublicShareStore = defineStore('publicShares', {
	state: () => ({
		/** @type {Record<string, object[]>} shares indexed by dashboardUuid */
		sharesByDashboard: {},
		loading: false,
		/** @type {Record<string, boolean>} token -> unlocked flag */
		unlockedTokens: JSON.parse(localStorage.getItem('mydash_unlocked_tokens') ?? '{}'),
	}),

	getters: {
		/** Return active shares for a given dashboard UUID. */
		sharesFor: (state) => (uuid) => state.sharesByDashboard[uuid] ?? [],

		/** True if the given token has been unlocked this session. */
		isUnlocked: (state) => (token) => state.unlockedTokens[token] === true,
	},

	actions: {
		/** Create a public share for a dashboard. */
		async createShare(dashboardUuid, { password = null, expiresAt = null } = {}) {
			this.loading = true
			try {
				const body = {}
				if (password) body.password = password
				if (expiresAt) body.expiresAt = expiresAt

				const { data } = await axios.post(
					`${baseUrl}/api/dashboards/${encodeURIComponent(dashboardUuid)}/public-share`,
					body
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

		/** Fetch active shares for a dashboard. */
		async fetchShares(dashboardUuid) {
			this.loading = true
			try {
				const { data } = await axios.get(
					`${baseUrl}/api/dashboards/${encodeURIComponent(dashboardUuid)}/public-shares`
				)
				this.sharesByDashboard[dashboardUuid] = data
				return data
			} finally {
				this.loading = false
			}
		},

		/** Soft-revoke a share. */
		async revokeShare(dashboardUuid, shareId) {
			await axios.delete(
				`${baseUrl}/api/dashboards/${encodeURIComponent(dashboardUuid)}/public-shares/${shareId}`
			)
			if (this.sharesByDashboard[dashboardUuid]) {
				this.sharesByDashboard[dashboardUuid] = this.sharesByDashboard[dashboardUuid]
					.filter((s) => s.id !== shareId)
			}
		},

		/** Mark a token as unlocked and persist to localStorage. */
		markUnlocked(token) {
			this.unlockedTokens = { ...this.unlockedTokens, [token]: true }
			localStorage.setItem('mydash_unlocked_tokens', JSON.stringify(this.unlockedTokens))
		},

		/** Remove an unlocked token (e.g. on session end). */
		clearUnlocked(token) {
			const updated = { ...this.unlockedTokens }
			delete updated[token]
			this.unlockedTokens = updated
			localStorage.setItem('mydash_unlocked_tokens', JSON.stringify(updated))
		},
	},
})
