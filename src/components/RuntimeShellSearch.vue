<!-- SPDX-License-Identifier: EUPL-1.2 -->

<template>
	<div class="runtime-shell-search">
		<div class="runtime-shell-search__field" role="search">
			<label :for="inputId" class="hidden-visually">
				{{ t('launchpad', 'Search dashboard tiles') }}
			</label>
			<MagnifyIcon :size="18" class="runtime-shell-search__icon" aria-hidden="true" />
			<input
				:id="inputId"
				ref="inputEl"
				type="text"
				class="runtime-shell-search__input"
				role="combobox"
				aria-autocomplete="list"
				aria-haspopup="listbox"
				:aria-expanded="hasResults"
				:aria-controls="ariaControls"
				:aria-activedescendant="activeOptionId"
				:placeholder="placeholderText"
				autocomplete="off"
				data-test="quick-search-input"
				:value="search.state.query"
				@input="onInput"
				@keydown="onInputKeydown">
		</div>

		<!-- Ranked matches (REQ-QSEARCH-002/003). Non-matching tiles are dimmed
		     in the grid itself via the `filter` event — this listbox only
		     lists the current matches for keyboard/mouse selection. -->
		<ul
			v-if="hasResults"
			:id="listboxId"
			class="runtime-shell-search__results"
			role="listbox"
			:aria-label="t('launchpad', 'Matching tiles')">
			<li
				v-for="(result, index) in search.state.results"
				:id="optionId(index)"
				:key="result.id"
				role="option"
				:aria-selected="index === search.state.activeIndex ? 'true' : 'false'"
				class="runtime-shell-search__option"
				:class="{ 'runtime-shell-search__option--active': index === search.state.activeIndex }"
				data-test="quick-search-option"
				@mousedown.prevent="onOptionMouseDown(index)">
				<!-- Active match is marked with an icon (not colour alone),
				     satisfying the WCAG "not by colour alone" requirement
				     alongside the background/font-weight styling below. -->
				<CheckIcon
					:size="16"
					class="runtime-shell-search__option-marker"
					:class="{ 'runtime-shell-search__option-marker--hidden': index !== search.state.activeIndex }"
					aria-hidden="true" />
				<span>{{ result.label }}</span>
			</li>
		</ul>

		<!-- Screen-reader-only live announcement of the result count / no-match
		     state (REQ-QSEARCH-003 WCAG AA requirement). -->
		<p
			class="hidden-visually"
			aria-live="polite"
			data-test="quick-search-status">
			{{ statusMessage }}
		</p>

		<!-- Visible no-results affordance (REQ-QSEARCH-004 "No fallback
		     configured" scenario). Mirrors the sr-only status above, so it is
		     marked aria-hidden to avoid a duplicate announcement. -->
		<p
			v-if="showNoResultsMessage"
			class="runtime-shell-search__empty"
			aria-hidden="true"
			data-test="quick-search-empty">
			{{ noResultsMessage }}
		</p>
	</div>
</template>

<script>
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

import { useTileSearch, isCtrlKFocusShortcut, isSlashFocusShortcut } from '../composables/useTileSearch.js'

let instanceCounter = 0

/**
 * RuntimeShellSearch — the on-dashboard quick-search / launcher bar
 * (tile-quick-search: REQ-QSEARCH-001..004).
 *
 * A self-contained, WCAG AA combobox: labelled `role="search"` input,
 * `role="listbox"` results with `aria-activedescendant` tracking the
 * keyboard-selected match, and an `aria-live` count/no-match announcement.
 * All filter/rank/selection/fallback logic lives in the `useTileSearch`
 * composable — this component is the DOM + keyboard-event binding layer.
 *
 * The owning page (`WorkspaceApp.vue`) supplies the current dashboard's
 * searchable `items` and the `fallbackTarget` setting, and reacts to the
 * emitted events to dim non-matching tiles in the grid, activate the
 * chosen tile, and return focus to the grid on Esc — all of that is a DOM
 * concern outside this component's own tree, hence the event-based
 * contract rather than direct DOM reach-through.
 *
 * @spec openspec/specs/tile-quick-search/spec.md
 */
export default {
	name: 'RuntimeShellSearch',

	components: {
		MagnifyIcon,
		CheckIcon,
	},

	props: {
		/**
		 * The current dashboard's searchable items, `{id, label}` pairs.
		 * Re-filtered live whenever this changes (e.g. after a dashboard
		 * switch or a placement add/remove).
		 */
		items: {
			type: Array,
			default: () => [],
		},
		/**
		 * The `quicksearch_fallback_target` setting value: `'none'`,
		 * `'unified-search'`, or a validated `https` URL template
		 * containing `{query}` (REQ-QSEARCH-004).
		 */
		fallbackTarget: {
			type: String,
			default: 'none',
		},
	},

	emits: ['open', 'filter', 'fallback', 'clear'],

	data() {
		return {
			// One `useTileSearch()` instance per mounted bar. `onOpen` /
			// `onFallback` are thin `$emit` forwarders — this component owns
			// no filter/rank/fallback logic of its own (REQ-QSEARCH-002..004
			// all live in the composable).
			search: useTileSearch({
				onOpen: (item) => {
					this.$emit('open', item)
				},
				onFallback: (action) => {
					this.$emit('fallback', action)
				},
				getFallbackTarget: () => this.fallbackTarget,
			}),
			instanceId: `runtime-shell-search-${(instanceCounter += 1)}`,
		}
	},

	computed: {
		inputId() {
			return `${this.instanceId}-input`
		},

		listboxId() {
			return `${this.instanceId}-listbox`
		},

		hasResults() {
			return this.search.state.results.length > 0
		},

		ariaControls() {
			return this.hasResults ? this.listboxId : null
		},

		activeOptionId() {
			return this.search.state.activeIndex >= 0
				? this.optionId(this.search.state.activeIndex)
				: null
		},

		placeholderText() {
			return this.t('launchpad', 'Search tiles… (/ or Ctrl+K)')
		},

		/**
		 * REQ-QSEARCH-003 WCAG AA requirement: the result count (or
		 * no-match state) is announced via `aria-live`.
		 *
		 * @return {string}
		 */
		statusMessage() {
			const query = this.search.state.query
			if (!query) {
				return ''
			}
			const count = this.search.state.results.length
			if (count === 0) {
				return this.noResultsMessage
			}
			return this.n('launchpad', '%n matching tile', '%n matching tiles', count)
		},

		noResultsMessage() {
			return this.t('launchpad', 'No matching tiles for "{query}"', { query: this.search.state.query })
		},

		showNoResultsMessage() {
			return this.search.state.query !== '' && this.search.state.results.length === 0
		},
	},

	watch: {
		items: {
			immediate: true,
			handler(newItems) {
				this.search.setItems(newItems)
			},
		},
	},

	/** @spec openspec/specs/tile-quick-search/spec.md */
	mounted() {
		window.addEventListener('keydown', this.onWindowKeydown)
	},

	/** @spec openspec/specs/tile-quick-search/spec.md */
	beforeUnmount() {
		window.removeEventListener('keydown', this.onWindowKeydown)
	},

	methods: {
		t,
		n,

		optionId(index) {
			return `${this.listboxId}-option-${index}`
		},

		/**
		 * Focus the search input from anywhere on the page (REQ-QSEARCH-001).
		 * `/` is ignored while the user is already typing elsewhere; `Ctrl+K`
		 * / `Cmd+K` always focuses and prevents the browser default.
		 *
		 * @param {KeyboardEvent} event the window-level keydown event.
		 * @return {void}
		 */
		onWindowKeydown(event) {
			if (isSlashFocusShortcut(event) || isCtrlKFocusShortcut(event)) {
				event.preventDefault()
				this.focusInput()
			}
		},

		focusInput() {
			const el = this.$refs.inputEl
			if (el && typeof el.focus === 'function') {
				el.focus()
			}
		},

		/**
		 * REQ-QSEARCH-002 — live, client-side filtering as the user types.
		 * Also forwards the current match-id set so the owning page can dim
		 * non-matching tiles in the grid (`null` = no active query, i.e.
		 * every tile is undimmed).
		 *
		 * @param {InputEvent} event the native input event.
		 * @return {void}
		 */
		onInput(event) {
			this.search.setQuery(event.target.value)
			this.emitFilter()
		},

		emitFilter() {
			const query = this.search.state.query
			if (!query) {
				this.$emit('filter', null)
				return
			}
			this.$emit('filter', this.search.state.results.map((r) => r.id))
		},

		/**
		 * REQ-QSEARCH-003 keyboard contract: ArrowUp/ArrowDown move the
		 * selection, Enter opens the active match (or dispatches the
		 * no-match fallback per REQ-QSEARCH-004), Escape clears.
		 *
		 * @param {KeyboardEvent} event the input's keydown event.
		 * @return {void}
		 */
		onInputKeydown(event) {
			if (event.key === 'ArrowDown') {
				event.preventDefault()
				this.search.moveSelection(1)
				return
			}
			if (event.key === 'ArrowUp') {
				event.preventDefault()
				this.search.moveSelection(-1)
				return
			}
			if (event.key === 'Enter') {
				event.preventDefault()
				this.search.pressEnter()
				return
			}
			if (event.key === 'Escape') {
				event.preventDefault()
				this.onEscape()
			}
		},

		/**
		 * Mouse/touch selection of a listed match — jumps the active-
		 * selection cursor to the clicked option, then opens it exactly as
		 * Enter would (single code path for "activate a match").
		 *
		 * @param {number} index the clicked option's index in `results`.
		 * @return {void}
		 */
		onOptionMouseDown(index) {
			this.search.state.activeIndex = index
			this.search.pressEnter()
		},

		/**
		 * REQ-QSEARCH-003 "Escape clears and returns focus" scenario. Clears
		 * the composable state, tells the owning page to undim every tile,
		 * and asks it to move focus back to the grid — this component's own
		 * tree doesn't contain the grid, so focus-return is delegated via
		 * the `clear` event.
		 *
		 * @return {void}
		 */
		onEscape() {
			this.search.clear()
			this.$emit('filter', null)
			this.$emit('clear')
		},
	},
}
</script>

<style scoped>
.runtime-shell-search {
	position: relative;
	width: min(420px, 100%);
}

.runtime-shell-search__field {
	position: relative;
	display: flex;
	align-items: center;
}

.runtime-shell-search__icon {
	position: absolute;
	left: 10px;
	color: var(--color-text-maxcontrast, #767676);
	pointer-events: none;
}

.runtime-shell-search__input {
	width: 100%;
	padding: 8px 12px 8px 36px;
	border: 1px solid var(--color-border, #ddd);
	border-radius: var(--border-radius-large, 20px);
	background: var(--color-main-background, #fff);
	color: var(--color-main-text, #222);
	font: inherit;
}

.runtime-shell-search__input:focus-visible {
	outline: 2px solid var(--color-primary-element, #0082c9);
	outline-offset: 2px;
}

.runtime-shell-search__results {
	position: absolute;
	top: calc(100% + 4px);
	left: 0;
	right: 0;
	z-index: 1000;
	margin: 0;
	padding: 4px;
	list-style: none;
	max-height: 320px;
	overflow-y: auto;
	background: var(--color-main-background, #fff);
	border: 1px solid var(--color-border, #ddd);
	border-radius: var(--border-radius-large, 12px);
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.runtime-shell-search__option {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 10px;
	border-radius: var(--border-radius, 6px);
	cursor: pointer;
	color: var(--color-main-text, #222);
}

.runtime-shell-search__option:hover {
	background: var(--color-background-hover, #f5f5f5);
}

/* Active match: background + bold text + the CheckIcon rendered in the
   template together satisfy "not indicated by colour alone" — an icon and
   a font-weight change carry the state independent of the background hue. */
.runtime-shell-search__option--active {
	background: var(--color-primary-element-light, #e4f0fa);
	font-weight: 600;
}

.runtime-shell-search__option-marker {
	flex: 0 0 auto;
	color: var(--color-primary-element, #0082c9);
}

.runtime-shell-search__option-marker--hidden {
	visibility: hidden;
}

.runtime-shell-search__empty {
	margin: 4px 0 0;
	padding: 0 4px;
	color: var(--color-text-maxcontrast, #767676);
	font-size: 0.9em;
}
</style>
