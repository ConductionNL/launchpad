<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="search-widget">
		<RuntimeShellSearch
			:items="searchableTiles"
			:placeholder="content.placeholder || ''"
			:fallbackTarget="effectiveFallbackTarget"
			@open="onSearchOpen"
			@filter="onSearchFilter"
			@fallback="onSearchFallback"
			@clear="onSearchClear" />
	</div>
</template>

<script>
import { mapState } from 'pinia'
import RuntimeShellSearch from '../../RuntimeShellSearch.vue'
import {
	activateSearchResult,
	buildSearchableTiles,
	focusGrid,
	performFallback,
} from '../../../composables/useTileSearchHost.js'
import { useDashboardStore } from '../../../stores/dashboard.js'
import { useTileSearchStore } from '../../../stores/tileSearch.js'
import { useWidgetStore } from '../../../stores/widgets.js'

/**
 * SearchWidget — the `search` widget type's renderer (tile-quick-search
 * REQ-QSEARCH-001, REQ-QSEARCH-005).
 *
 * The quick-search bar used to be page chrome in `WorkspaceApp.vue`, present
 * on every dashboard whether or not anyone wanted it. It is now a widget the
 * author places on the grid like any other, which is why this component
 * exists: it is the host that turns `RuntimeShellSearch`'s emissions into
 * effects, exactly as `WorkspaceApp.vue` used to.
 *
 * It owns no search logic. Ranking and filtering live in `useTileSearch.js`,
 * the DOM/keyboard binding lives in `RuntimeShellSearch.vue`, the page-level
 * effects live in `useTileSearchHost.js`, and dimming is reactive state in
 * `stores/tileSearch.js`. This component wires those four together and
 * resolves the two config settings.
 *
 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-001
 */
export default {
	name: 'SearchWidget',

	components: {
		RuntimeShellSearch,
	},

	inject: {
		/**
		 * The admin-configured `quicksearch_fallback_target` setting, which a
		 * widget with no override of its own inherits (REQ-QSEARCH-004).
		 * `none` is the default, so an unset server never navigates.
		 */
		injectedQuicksearchFallbackTarget: {
			from: 'quicksearchFallbackTarget',
			default: 'none',
		},
	},

	props: {
		/**
		 * Persisted widget content blob (REQ-QSEARCH-005).
		 *
		 * @type {{placeholder?: string, fallbackTarget?: string}}
		 */
		content: {
			type: Object,
			default: () => ({}),
		},

		/**
		 * The placement row this widget renders as. Deliberately declared and
		 * deliberately unread: the searchable set comes from the store so it
		 * stays live, but `WidgetRenderer` binds `placement` on every registry
		 * renderer, and an undeclared binding falls through to the root
		 * element as the literal attribute `placement="[object Object]"`.
		 * Declaring it absorbs the binding instead.
		 */
		// eslint-disable-next-line vue/no-unused-properties
		placement: {
			type: Object,
			default: () => ({}),
		},
	},

	computed: {
		...mapState(useDashboardStore, ['widgetPlacements']),
		...mapState(useWidgetStore, ['availableWidgets']),

		/**
		 * The current dashboard's searchable items (REQ-QSEARCH-002).
		 *
		 * Read from the live Pinia store rather than the initial-state
		 * snapshot, so results re-filter correctly after a placement is added
		 * or removed, or the dashboard is switched, with no page reload.
		 *
		 * @return {Array<{id: (string|number), label: string, placement: object}>}
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
		 */
		searchableTiles() {
			return buildSearchableTiles(this.widgetPlacements, this.availableWidgets)
		},

		/**
		 * The fallback target actually in force, resolving the two layers
		 * (REQ-QSEARCH-004): this widget's own override wins when set, and an
		 * empty override falls through to the admin setting.
		 *
		 * Empty string is the "inherit" marker rather than a dedicated
		 * sentinel, so the widget's value space is exactly the admin
		 * setting's — no third vocabulary for a reader to learn.
		 *
		 * @return {string}
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-004
		 */
		effectiveFallbackTarget() {
			return (
				this.content.fallbackTarget || this.injectedQuicksearchFallbackTarget
			)
		},
	},

	beforeUnmount() {
		// A widget removed mid-query must not leave the tiles it dimmed stuck
		// that way — nothing else would ever clear them.
		useTileSearchStore().clear()
	},

	methods: {
		/**
		 * REQ-QSEARCH-003 "Enter opens the selected tile".
		 *
		 * @param {{id: (string|number), label: string, placement: object}} item
		 *   the opened search result.
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-003
		 */
		onSearchOpen(item) {
			activateSearchResult(item)
		},

		/**
		 * REQ-QSEARCH-002 "Typing filters tiles by label". Non-matching tiles
		 * are de-emphasised, never removed from the grid layout — this widget
		 * only records which ids matched; `Views.vue` renders the consequence.
		 *
		 * @param {Array<string|number>|null} matchIds the matching ids, or
		 *   `null` when the query is empty.
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
		 */
		onSearchFilter(matchIds) {
			useTileSearchStore().setMatches(matchIds)
		},

		/**
		 * REQ-QSEARCH-004 no-match fallback.
		 *
		 * @param {{type: string, url?: string, query?: string}} action the
		 *   resolved fallback action.
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-004
		 */
		onSearchFallback(action) {
			performFallback(action)
		},

		/**
		 * REQ-QSEARCH-003 "Escape clears and returns focus".
		 *
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-003
		 */
		onSearchClear() {
			useTileSearchStore().clear()
			focusGrid()
		},
	},
}
</script>

<style scoped>
.search-widget {
	width: 100%;
	/* The results listbox is absolutely positioned inside RuntimeShellSearch
	   and must escape the widget cell rather than be clipped by it. */
	overflow: visible;
}
</style>
