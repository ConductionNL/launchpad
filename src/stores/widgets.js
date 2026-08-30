/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { defineStore } from 'pinia'
import { api } from '../services/api.js'
import { widgetBridge } from '../services/widgetBridge.js'
import { logger } from '../utils/logger.js'

export const useWidgetStore = defineStore('widgets', {
	state: () => ({
		availableWidgets: [],
		widgetItems: {},
		loading: false,
	}),

	getters: {
		getWidgetById: (state) => (id) => {
			return state.availableWidgets.find((w) => w.id === id)
		},

		getWidgetItems: (state) => (widgetId) => {
			return state.widgetItems[widgetId] || { items: [], loading: false }
		},
	},

	actions: {
		/** @spec openspec/specs/widgets/spec.md */
		async loadAvailableWidgets() {
			this.loading = true
			try {
				const response = await api.getAvailableWidgets()
				this.availableWidgets = response.data
				// Feed widget titles/icons into OCA.Dashboard so CnNcWidgetWidget
				// renders a human header instead of the raw widget id.
				widgetBridge.setWidgetMetadata(this.availableWidgets)
			} catch (error) {
				logger.error('Failed to load available widgets:', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Fetch items for a batch of Nextcloud dashboard widgets, marking
		 * each as loading first so the UI can show per-widget spinners.
		 *
		 * @param {string[]} widgetIds Widget ids to fetch items for.
		 * @spec openspec/specs/widgets/spec.md
		 */
		async loadWidgetItems(widgetIds) {
			logger.debug('[WidgetStore] loadWidgetItems called:', widgetIds)
			// Mark widgets as loading
			for (const id of widgetIds) {
				this.widgetItems[id] = { ...this.widgetItems[id], loading: true }
			}

			try {
				const response = await api.getWidgetItems(widgetIds)
				logger.debug('[WidgetStore] API response:', response.data)
				for (const [widgetId, data] of Object.entries(response.data)) {
					logger.debug(
						'[WidgetStore] Setting items for widget:',
						widgetId,
						'Items count:',
						data.items?.length,
						'Items:',
						data.items,
					)
					this.widgetItems[widgetId] = {
						items: data.items || [],
						emptyContentMessage: data.emptyContentMessage || '',
						halfEmptyContentMessage: data.halfEmptyContentMessage || '',
						loading: false,
					}
				}
			} catch (error) {
				logger.error('Failed to load widget items:', error)
				for (const id of widgetIds) {
					this.widgetItems[id] = {
						...this.widgetItems[id],
						loading: false,
					}
				}
			}
		},

		/**
		 * Re-fetch items for a single widget.
		 *
		 * @param {string} widgetId Widget id to refresh.
		 * @spec openspec/specs/widgets/spec.md
		 */
		async refreshWidgetItems(widgetId) {
			await this.loadWidgetItems([widgetId])
		},
	},
})
