/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pinia store for the tile quick-search's dimming state
 * (tile-quick-search REQ-QSEARCH-002).
 *
 * WHY A STORE RATHER THAN A DOM WALK
 * ----------------------------------
 * Dimming used to be imperative: `WorkspaceApp.vue` queried every rendered
 * `.launchpad-grid-item[data-placement-id]`, read each tile's id back out of
 * the markup with `getAttribute()`, and toggled a class on it. That reads
 * application state out of the DOM, which ADR-004 forbids — the placement id
 * originates in `useDashboardStore`, is written into the markup by
 * `Views.vue`, and was then being read back out of the markup to decide
 * behaviour.
 *
 * Now the search widget writes match ids here and `Views.vue` binds the
 * `--dimmed` class reactively on the grid item it already renders. The tile
 * that owns the markup owns the class; nothing reads the DOM.
 *
 * This also retires launchpad#95 by construction. That bug was a comparison
 * between an INTEGER placement id (off the API row) and the STRING a DOM
 * attribute always returns: `[7].includes('7')` is `false` under
 * SameValueZero, so every tile dimmed on every query — including the matches
 * the user was looking for. Both sides of the comparison now originate in the
 * same store, so the type mismatch can no longer arise. The `String(...)`
 * normalisation below is kept as defence in depth, not as the fix.
 */

import { defineStore } from 'pinia'

export const useTileSearchStore = defineStore('tileSearch', {
	state: () => ({
		/**
		 * Ids of the placements matching the current query, normalised to
		 * strings.
		 *
		 * `null` means "no active query" and is NOT the same as `[]`:
		 *  - `null` → nothing is dimmed (the user has cleared the search)
		 *  - `[]`   → EVERY tile is dimmed (the query matched nothing)
		 *
		 * @type {string[]|null}
		 */
		matchIds: null,
	}),

	getters: {
		/**
		 * Whether a given placement should render de-emphasised
		 * (REQ-QSEARCH-002 — non-matching tiles are dimmed, never removed
		 * from the grid layout).
		 *
		 * Accepts an integer or a string id so callers never have to think
		 * about which one they hold.
		 *
		 * @param {object} state the store state.
		 * @return {(placementId: string|number) => boolean} the predicate.
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
		 */
		isDimmed: (state) => (placementId) => {
			if (state.matchIds === null) {
				return false
			}
			return state.matchIds.includes(String(placementId)) === false
		},

		/**
		 * Whether a query is currently narrowing the grid. Lets a consumer
		 * distinguish "no search running" from "search matched nothing".
		 *
		 * @param {object} state the store state.
		 * @return {boolean} true while a query is active.
		 */
		hasActiveQuery: (state) => state.matchIds !== null,
	},

	actions: {
		/**
		 * Record the current query's matching placement ids.
		 *
		 * @param {Array<string|number>|null} ids matching placement ids, or
		 *   `null` to clear the query (undim everything).
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
		 */
		setMatches(ids) {
			this.matchIds = ids === null ? null : ids.map((id) => String(id))
		},

		/**
		 * Clear the active query so every tile renders at full emphasis
		 * (REQ-QSEARCH-003 "Escape clears and returns focus"). Also called
		 * when a search widget unmounts, so a deleted widget never leaves
		 * tiles dimmed behind it.
		 *
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-003
		 */
		clear() {
			this.matchIds = null
		},
	},
})
