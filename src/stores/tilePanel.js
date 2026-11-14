/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The one panel beside the tiles that a `side-panel` tile opens in
 * (launcher-tile-open-behaviour D3). A tile writes here; `WorkspaceApp`
 * renders `TileSidePanel` from it. Opening another tile replaces the panel.
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */

import { defineStore } from 'pinia'

export const useTilePanelStore = defineStore('launchpad-tile-panel', {
	state: () => ({
		/** The address shown, or null when the panel is closed. */
		url: null,
		/** The tile's title, shown in the panel header. */
		title: '',
		/** Whether the address is on this Nextcloud (no framing check needed). */
		sameOrigin: false,
		/** The element focus returns to on close. */
		opener: null,
	}),

	getters: {
		isOpen: (state) => state.url !== null,
	},

	actions: {
		/**
		 * Open the panel, replacing whatever it showed.
		 *
		 * @param {object} tile The tile being opened.
		 * @param {string} tile.url The address to show.
		 * @param {string} [tile.title] The tile's title.
		 * @param {boolean} [tile.sameOrigin] Whether the address is on this Nextcloud.
		 * @param {HTMLElement|null} [tile.opener] The element focus returns to on close.
		 * @return {void}
		 */
		open({ url, title = '', sameOrigin = false, opener = null }) {
			this.url = url
			this.title = title
			this.sameOrigin = sameOrigin
			this.opener = opener
		},

		/**
		 * Close the panel and return focus to the tile that opened it.
		 *
		 * @return {void}
		 */
		close() {
			const opener = this.opener
			this.url = null
			this.title = ''
			this.sameOrigin = false
			this.opener = null
			if (opener && typeof opener.focus === 'function') {
				opener.focus()
			}
		},
	},
})
