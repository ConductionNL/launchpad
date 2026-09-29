/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'

const baseUrl = generateUrl('/apps/launchpad')

/**
 * @param {number|string} dashboardId Dashboard id.
 * @return {string} The personal-layer URL of that dashboard.
 */
function layerUrl(dashboardId) {
	return `${baseUrl}/api/dashboards/${encodeURIComponent(dashboardId)}/personal-layer`
}

/**
 * The reader's own layer over a shared dashboard: what they hid and what
 * they adjusted. The server applies it when the dashboard is read, so after
 * a change the page re-reads the dashboard rather than filtering itself.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
export const usePersonalLayerStore = defineStore('personalLayer', {
	state: () => ({
		dashboardId: null,
		hidden: [],
		overrides: {},
		hiddenPlacements: [],
	}),

	actions: {
		/**
		 * Read the layer of one dashboard.
		 *
		 * @param {number} dashboardId Dashboard id.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async load(dashboardId) {
			const { data } = await axios.get(layerUrl(dashboardId))
			this.dashboardId = dashboardId
			this.hidden = Array.isArray(data?.hidden) ? data.hidden.map(Number) : []
			this.overrides =
				data?.overrides && typeof data.overrides === 'object'
					? data.overrides
					: {}
			this.hiddenPlacements = Array.isArray(data?.hiddenPlacements)
				? data.hiddenPlacements
				: []
		},

		/**
		 * Save a new hidden set with the overrides unchanged. A compulsory
		 * refusal rejects with `{compulsory: true, placementId}`.
		 *
		 * @param {number} dashboardId Dashboard id.
		 * @param {Array<number>} hidden The whole hidden set.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async saveHidden(dashboardId, hidden) {
			try {
				await axios.put(layerUrl(dashboardId), {
					overrides: this.overrides,
					hidden,
				})
			} catch (error) {
				const data = error?.response?.data
				if (
					error?.response?.status === 403
					&& data?.error === 'placement_compulsory'
				) {
					throw Object.assign(new Error('placement_compulsory'), {
						compulsory: true,
						placementId: data.placementId,
					})
				}
				throw error
			}
			await this.load(dashboardId)
		},

		/**
		 * Hide one placement for this person only.
		 *
		 * @param {number} dashboardId Dashboard id.
		 * @param {number} placementId Placement to hide.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async hide(dashboardId, placementId) {
			const id = Number(placementId)
			const next = this.hidden.includes(id)
				? [...this.hidden]
				: [...this.hidden, id]
			await this.saveHidden(dashboardId, next)
		},

		/**
		 * Bring one hidden placement back.
		 *
		 * @param {number} dashboardId Dashboard id.
		 * @param {number} placementId Placement to show again.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async showAgain(dashboardId, placementId) {
			await this.saveHidden(
				dashboardId,
				this.hidden.filter((id) => id !== Number(placementId)),
			)
		},

		/**
		 * Delete the whole layer: the person sees the dashboard as composed.
		 *
		 * @param {number} dashboardId Dashboard id.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async reset(dashboardId) {
			await axios.delete(layerUrl(dashboardId))
			await this.load(dashboardId)
		},
	},
})
