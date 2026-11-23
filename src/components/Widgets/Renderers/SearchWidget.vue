<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="search-widget">
		<!-- An internet search as LpWerkplek draws it: a field, the engine,
		     a Search button, and a line that the results open in a new tab. -->
		<form
			v-if="webLayout"
			class="search-widget__web"
			role="search"
			data-testid="search-widget-web"
			@submit.prevent="searchWeb">
			<span class="search-widget__row">
				<label :for="inputId" class="hidden-visually">{{ t('launchpad', 'Internet search') }}</label>
				<input
					:id="inputId"
					v-model="webQuery"
					type="search"
					class="search-widget__input"
					:placeholder="content.placeholder || t('launchpad', 'Search the internet')">
				<span class="search-widget__engine" data-testid="search-widget-engine">{{ engine.name }}</span>
				<button type="submit" class="search-widget__submit">
					{{ t('launchpad', 'Search') }}
				</button>
			</span>
			<span class="search-widget__note">{{ t('launchpad', 'Opens the results in a new tab') }}</span>
		</form>
		<RuntimeShellSearch
			v-else
			:items="searchableTiles"
			:placeholder="content.placeholder || ''"
			:fallbackTarget="effectiveFallbackTarget"
			:shortcuts="injectedSearchShortcuts"
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
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
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

		/**
		 * The admin's search shortcuts (REQ-SPX-002). An empty list keeps
		 * the box as it was.
		 */
		injectedSearchShortcuts: {
			from: 'searchShortcuts',
			default: () => [],
		},
	},

	props: {
		/**
		 * Persisted widget content blob (REQ-QSEARCH-005).
		 *
		 * @type {{placeholder?: string, fallbackTarget?: string}}
		 */
		content: OBJECT_PROP,

		/**
		 * The placement row this widget renders as. Deliberately declared and
		 * deliberately unread: the searchable set comes from the store so it
		 * stays live, but `WidgetRenderer` binds `placement` on every registry
		 * renderer, and an undeclared binding falls through to the root
		 * element as the literal attribute `placement="[object Object]"`.
		 * Declaring it absorbs the binding instead.
		 */
		// eslint-disable-next-line vue/no-unused-properties
		placement: OBJECT_PROP,
	},

	data() {
		return {
			webQuery: '',
			inputId: `search-widget-${Math.random().toString(36).slice(2, 10)}`,
		}
	},

	computed: {
		/**
		 * Whether the widget is an internet search form (`content.layout:
		 * 'web'`) rather than the tile search.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/widgets/spec.md
		 */
		webLayout() {
			return this.content?.layout === 'web'
		},

		/**
		 * The search engine: `content.engine` `{name, url}` with `{q}` in the
		 * url, else DuckDuckGo.
		 *
		 * @return {{name: string, url: string}}
		 * @spec openspec/specs/widgets/spec.md
		 */
		engine() {
			const engine = this.content?.engine
			if (engine && typeof engine.url === 'string' && engine.url.includes('{q}')) {
				return { name: String(engine.name || ''), url: engine.url }
			}
			return { name: 'DuckDuckGo', url: 'https://duckduckgo.com/?q={q}' }
		},

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

	/**
	 * A widget removed mid-query must not leave the tiles it dimmed stuck
	 * that way — nothing else would ever clear them.
	 *
	 * @return {void}
	 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
	 */
	beforeUnmount() {
		useTileSearchStore().clear()
	},

	methods: {
		/**
		 * Open the engine's results for the typed words in a new tab.
		 *
		 * @return {void}
		 * @spec openspec/specs/widgets/spec.md
		 */
		searchWeb() {
			const query = this.webQuery.trim()
			if (query === '') {
				return
			}
			window.open(this.engine.url.replace('{q}', encodeURIComponent(query)), '_blank', 'noopener,noreferrer')
		},

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
.search-widget__web {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.search-widget__row {
	display: flex;
	gap: 8px;
}

.search-widget__input {
	flex: 1;
	box-sizing: border-box;
	min-width: 0;
	height: 40px;
	margin: 0;
	padding: 0 12px;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: 8px;
	font-size: 14px;
}

.search-widget__engine {
	display: inline-flex;
	align-items: center;
	box-sizing: border-box;
	height: 40px;
	padding: 0 12px;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: 8px;
	font-size: 14px;
}

.search-widget__submit {
	height: 40px;
	margin: 0;
	padding: 0 16px;
	border: 0;
	border-radius: 8px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 14px;
	font-weight: 600;
}

.search-widget__note {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.search-widget {
	width: 100%;
	/* The results listbox is absolutely positioned inside RuntimeShellSearch
	   and must escape the widget cell rather than be clipped by it. */
	overflow: visible;
}
</style>
