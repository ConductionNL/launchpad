/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import { api } from '../services/api.js'

/**
 * Pinia store for the template-discovery gallery, save-as-template flow
 * and admin preview-image upload (REQ-TMPL-014..017).
 *
 * Surfaces a flat list of gallery entries plus the currently active
 * category / sort filters so the gallery view can render straight off
 * the store getters without re-fetching.
 */
export const useTemplatesStore = defineStore('templates', {
	state: () => ({
		// Gallery entries:
		// {uuid, name, description, category, previewImage,
		//  gridColumns, widgetCount, lastUpdatedAt}.
		galleryTemplates: [],
		selectedCategory: null,
		sortBy: 'name',
		loading: false,
		saving: false,
	}),

	getters: {
		/**
		 * Distinct, alphabetically-sorted list of categories present in the
		 * current gallery payload. Drives the category-filter dropdown.
		 *
		 * @param {object} state The store state.
		 * @return {string[]} Distinct category names (NULL excluded).
		 */
		categories: (state) => {
			const seen = new Set()
			for (const tpl of state.galleryTemplates) {
				if (tpl.category) {
					seen.add(tpl.category)
				}
			}
			return Array.from(seen).sort((a, b) => a.localeCompare(b))
		},
	},

	actions: {
		/**
		 * Fetch the gallery list. Stores the response under
		 * `galleryTemplates` and updates the active filter state so the
		 * UI affordances stay in sync.
		 *
		 * @param {object} [options] Filter options.
		 * @param {string|null} [options.category] Category filter.
		 * @param {string} [options.sort] Sort key (`name`/`updatedAt`).
		 * @return {Promise<Array>} The gallery entries.
		 */
		/** @spec openspec/specs/admin-templates/spec.md */
		async fetchGallery({ category = null, sort = 'name' } = {}) {
			this.loading = true
			this.selectedCategory = category
			this.sortBy = sort
			try {
				const { data } = await api.getTemplateGallery({ category, sort })
				this.galleryTemplates = Array.isArray(data?.templates)
					? data.templates
					: []
				return this.galleryTemplates
			} catch (err) {
				showError(t('launchpad', 'Could not load template gallery'))
				throw err
			} finally {
				this.loading = false
			}
		},

		/**
		 * Admin-only preview image upload (REQ-TMPL-017). Accepts a
		 * base64 data URL — caller is responsible for reading the file
		 * via `FileReader` first.
		 *
		 * @param {string} templateUuid Template UUID.
		 * @param {string} base64DataUrl `data:image/...;base64,...` URL.
		 * @return {Promise<string>} The persisted image URL.
		 */
		/** @spec openspec/specs/admin-templates/spec.md */
		async uploadPreviewImage(templateUuid, base64DataUrl) {
			try {
				const { data } = await api.uploadTemplatePreviewImage(
					templateUuid,
					base64DataUrl,
				)
				const url = data?.previewImage ?? ''
				// Patch the gallery entry in-place so the card reflects
				// the new image without a full refetch.
				const idx = this.galleryTemplates.findIndex(
					(tpl) => tpl.uuid === templateUuid,
				)
				if (idx !== -1) {
					this.galleryTemplates[idx] = {
						...this.galleryTemplates[idx],
						previewImage: url,
					}
				}
				return url
			} catch (err) {
				showError(t('launchpad', 'Invalid image format'))
				throw err
			}
		},
	},
})
