/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { defineStore } from 'pinia'
import { api } from '../services/api.js'

export const useTileStore = defineStore('tiles', {
	state: () => ({
		tiles: [],
		loading: false,
	}),

	actions: {
		/** @spec openspec/specs/tiles/spec.md */
		async loadTiles() {
			this.loading = true
			try {
				const response = await api.getTiles()
				this.tiles = response.data
			} catch (error) {
				console.error('Failed to load tiles:', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Create a reusable launcher tile and append it to the local list.
		 *
		 * @param {object} tileData Tile attributes (title, icon, colours,
		 *   link target).
		 * @return {Promise<object>} The created tile.
		 * @spec openspec/specs/tiles/spec.md
		 */
		async createTile(tileData) {
			try {
				const response = await api.createTile(tileData)
				this.tiles.push(response.data)
				return response.data
			} catch (error) {
				console.error('Failed to create tile:', error)
				throw error
			}
		},

		/**
		 * Update a launcher tile and patch it into the local list.
		 *
		 * @param {number|string} id Id of the tile to update.
		 * @param {object} tileData Changed tile attributes.
		 * @return {Promise<object>} The updated tile.
		 * @spec openspec/specs/tiles/spec.md
		 */
		async updateTile(id, tileData) {
			try {
				const response = await api.updateTile(id, tileData)
				const index = this.tiles.findIndex(t => t.id === id)
				if (index !== -1) {
					this.tiles[index] = response.data
				}
				return response.data
			} catch (error) {
				console.error('Failed to update tile:', error)
				throw error
			}
		},

		/**
		 * Delete a launcher tile and drop it from the local list.
		 *
		 * @param {number|string} id Id of the tile to delete.
		 * @spec openspec/specs/tiles/spec.md
		 */
		async deleteTile(id) {
			try {
				await api.deleteTile(id)
				this.tiles = this.tiles.filter(t => t.id !== id)
			} catch (error) {
				console.error('Failed to delete tile:', error)
				throw error
			}
		},
	},
})
