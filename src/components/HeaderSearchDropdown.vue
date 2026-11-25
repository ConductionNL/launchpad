<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->

<template>
	<Teleport to="body">
		<div
			v-if="open"
			class="lp-header-search"
			data-testid="header-search-dropdown"
			:style="panelStyle">
			<p class="lp-header-search__query" data-testid="header-search-query">
				{{ t('launchpad', 'You searched for {query}', { query: query.trim() }) }}
			</p>
			<ul
				:id="listboxId"
				class="lp-header-search__list"
				role="listbox"
				:aria-label="t('launchpad', 'Search results')">
				<template v-for="group in groups" :key="group.id">
					<li
						class="lp-header-search__group"
						role="presentation"
						data-testid="header-search-group">
						{{ group.name.toLocaleUpperCase() }}
					</li>
					<li
						v-for="result in group.results"
						:id="optionId(result)"
						:key="result.key"
						role="option"
						class="lp-header-search__option"
						:class="{ 'lp-header-search__option--active': isActive(result) }"
						:aria-selected="isActive(result) ? 'true' : 'false'"
						data-testid="header-search-option"
						@mousedown.prevent="choose(result)">
						<img
							v-if="result.icon"
							class="lp-header-search__icon"
							:src="result.icon"
							alt=""
							aria-hidden="true">
						<span class="lp-header-search__text">
							<span class="lp-header-search__title">{{ result.title }}</span>
							<span v-if="result.detail" class="lp-header-search__detail">{{ result.detail }}</span>
						</span>
					</li>
				</template>
				<li
					v-if="!loading && groups.length === 0"
					class="lp-header-search__empty"
					role="presentation">
					{{ t('launchpad', 'No results') }}
				</li>
				<li
					:id="optionId(showAllOption)"
					role="option"
					class="lp-header-search__option lp-header-search__show-all"
					:class="{ 'lp-header-search__option--active': isActive(showAllOption) }"
					:aria-selected="isActive(showAllOption) ? 'true' : 'false'"
					data-testid="header-search-show-all"
					@mousedown.prevent="choose(showAllOption)">
					{{ t('launchpad', 'Show all results') }}
				</li>
			</ul>
			<p
				v-if="shortcut"
				class="lp-header-search__tip"
				data-testid="header-search-tip">
				{{ t('launchpad', 'Tip: type {prefix} to search {name} directly', shortcut) }}
			</p>
		</div>
	</Teleport>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { performFallback } from '../composables/useTileSearchHost.js'
import { LIST_PROP } from '../constants/propDefinitions.js'
import {
	firstShortcut,
	isSearchable,
	moveSelection,
	searchAll,
} from '../services/headerSearch.js'
import {
	fieldWords,
	removeInlineField,
	resolveSearchField,
} from '../utils/headerSearchField.js'

/**
 * Nextcloud's top bar search field, across the core versions LaunchPad runs on.
 *
 * @type {string}
 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export const CORE_SEARCH_FIELD_SELECTOR = '.unified-search-input, #unified-search input, .unified-search__input, input.unified-search-menu__input'

/** Wait this long after the last keystroke before searching. */
const DEBOUNCE_MS = 250

/** The key of the "Show all results" row. */
const SHOW_ALL_KEY = 'show-all'

let instanceCounter = 0

/**
 * HeaderSearchDropdown: the results list under the top bar's search field
 * on LaunchPad pages (header-search-dropdown, REQ-SRCH-013).
 *
 * The field belongs to Nextcloud core and its words to thematiq
 * (`search-placeholder.js`). Where core's top bar has a field this component
 * does not render or replace it; where it has only a button (Nextcloud 31+
 * on a desktop), it places a field in the button's place that shows the
 * button's words (`utils/headerSearchField.js`). It finds the field, gives it the combobox attributes (WAI-ARIA 1.2:
 * `role="combobox"`, `aria-expanded`, `aria-controls`,
 * `aria-activedescendant`), listens to its input and keys, and draws a
 * listbox under it. It never writes the field's `placeholder` or value.
 *
 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export default {
	name: 'HeaderSearchDropdown',

	props: {
		/**
		 * The admin's search shortcuts `{prefix, name, urlTemplate}`
		 * (REQ-SPX-002); the first one gives the tip.
		 *
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		shortcuts: LIST_PROP,

		/**
		 * The CSS selector of the field to attach to.
		 *
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		fieldSelector: {
			type: String,
			default: CORE_SEARCH_FIELD_SELECTOR,
		},
	},

	data() {
		instanceCounter += 1
		return {
			field: null,
			query: '',
			groups: [],
			loading: false,
			open: false,
			activeIndex: -1,
			panelStyle: {},
			listboxId: `lp-header-search-${instanceCounter}`,
			observer: null,
			labelObserver: null,
			timer: null,
			controller: null,
		}
	},

	computed: {
		/**
		 * The "Show all results" row, as an option.
		 *
		 * @return {{key: string}} The row.
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		showAllOption() {
			return { key: SHOW_ALL_KEY }
		},

		/**
		 * Every selectable row in list order: the results, then "Show all".
		 *
		 * @return {Array<object>} The options.
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		options() {
			const results = this.groups.flatMap((group) => group.results)
			return [...results, this.showAllOption]
		},

		/**
		 * The shortcut the tip names, or null.
		 *
		 * @return {{prefix: string, name: string}|null} The shortcut.
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		shortcut() {
			return firstShortcut(this.shortcuts)
		},
	},

	watch: {
		/**
		 * Keep the field's combobox state in step with the list.
		 *
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		open() {
			this.syncFieldAria()
		},

		/**
		 * Keep `aria-activedescendant` on the selected row.
		 *
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		activeIndex() {
			this.syncFieldAria()
		},
	},

	/**
	 * Attach to the field, now or once the top bar renders it.
	 *
	 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
	 */
	mounted() {
		if (!this.attach()) {
			// The top bar can render after LaunchPad's bundle runs.
			this.observer = new MutationObserver(() => {
				if (this.attach()) {
					this.observer.disconnect()
					this.observer = null
				}
			})
			this.observer.observe(document.body, { childList: true, subtree: true })
		}
	},

	/**
	 * Let go of the field and cancel pending work.
	 *
	 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
	 */
	beforeUnmount() {
		this.observer?.disconnect()
		clearTimeout(this.timer)
		this.controller?.abort()
		this.detach()
	},

	methods: {
		t,

		/**
		 * Find the field and bind to it. On Nextcloud 31+ the desktop top bar
		 * has only a button, so LaunchPad places its own field there
		 * (`resolveSearchField()`), following the button's words.
		 *
		 * @return {boolean} Whether the field was found.
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		attach() {
			const resolved = resolveSearchField(
				document.querySelector(this.fieldSelector),
				t('launchpad', 'Search'),
			)
			if (!resolved) {
				return false
			}
			const { field, created, label } = resolved
			this.field = field
			if (created && label) {
				// thematiq can write its field words after LaunchPad mounts.
				this.labelObserver = new MutationObserver(() => {
					const words = fieldWords(label.parentElement, field.placeholder)
					field.placeholder = words
					field.setAttribute('aria-label', words)
				})
				this.labelObserver.observe(label, { childList: true, characterData: true, subtree: true })
			}
			field.setAttribute('role', 'combobox')
			field.setAttribute('aria-autocomplete', 'list')
			field.setAttribute('aria-controls', this.listboxId)
			field.setAttribute('autocomplete', 'off')
			field.addEventListener('input', this.onInput)
			field.addEventListener('keydown', this.onKeydown)
			field.addEventListener('blur', this.onBlur)
			this.syncFieldAria()
			return true
		},

		/**
		 * Unbind from the field.
		 *
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		detach() {
			const field = this.field
			if (!field) {
				return
			}
			field.removeEventListener('input', this.onInput)
			field.removeEventListener('keydown', this.onKeydown)
			field.removeEventListener('blur', this.onBlur)
			field.removeAttribute('aria-activedescendant')
			field.setAttribute('aria-expanded', 'false')
			this.labelObserver?.disconnect()
			this.labelObserver = null
			removeInlineField(field)
			this.field = null
		},

		/**
		 * Mirror the list's state on the field.
		 *
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		syncFieldAria() {
			const field = this.field
			if (!field) {
				return
			}
			field.setAttribute('aria-expanded', this.open ? 'true' : 'false')
			const active = this.open ? this.options[this.activeIndex] : null
			if (active) {
				field.setAttribute('aria-activedescendant', this.optionId(active))
			} else {
				field.removeAttribute('aria-activedescendant')
			}
		},

		/**
		 * The DOM id of a row.
		 *
		 * @param {{key: string}} option The row.
		 * @return {string} The id.
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		optionId(option) {
			return `${this.listboxId}-${option.key}`
		},

		/**
		 * Whether a row is the selected one.
		 *
		 * @param {{key: string}} option The row.
		 * @return {boolean} True when selected.
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		isActive(option) {
			return this.options[this.activeIndex]?.key === option.key
		},

		/**
		 * The field changed: search after a short pause, or close under two
		 * characters.
		 *
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		onInput() {
			this.query = this.field?.value ?? ''
			clearTimeout(this.timer)
			if (!isSearchable(this.query)) {
				this.close()
				return
			}
			this.timer = setTimeout(() => this.search(), DEBOUNCE_MS)
		},

		/**
		 * Ask the providers and open the list with the first result selected.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		async search() {
			this.controller?.abort()
			const controller = new AbortController()
			this.controller = controller
			const query = this.query
			this.loading = true
			this.position()
			this.open = true
			const groups = await searchAll(query.trim(), { signal: controller.signal })
			if (controller.signal.aborted || query !== this.query) {
				return
			}
			this.groups = groups
			this.loading = false
			this.activeIndex = 0
			this.syncFieldAria()
		},

		/**
		 * Place the panel under the field, as wide as the field.
		 *
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		position() {
			const rect = this.field?.getBoundingClientRect?.()
			if (!rect) {
				return
			}
			this.panelStyle = {
				top: `${rect.bottom}px`,
				left: `${rect.left}px`,
				width: `${rect.width}px`,
			}
		},

		/**
		 * Arrow keys move the selection, Enter opens it, Escape closes the
		 * list and keeps the query and the focus.
		 *
		 * @param {KeyboardEvent} event The key press on the field.
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		onKeydown(event) {
			if (!this.open) {
				return
			}
			switch (event.key) {
			case 'ArrowDown':
			case 'ArrowUp':
				event.preventDefault()
				this.activeIndex = moveSelection(
					this.activeIndex,
					event.key === 'ArrowDown' ? 1 : -1,
					this.options.length,
				)
				break
			case 'Enter': {
				const option = this.options[this.activeIndex]
				if (option) {
					event.preventDefault()
					event.stopPropagation()
					this.choose(option)
				}
				break
			}
			case 'Escape':
				event.preventDefault()
				event.stopPropagation()
				this.close()
				break
			default:
				break
			}
		},

		/**
		 * Close when the focus leaves the field (a row click is a mousedown
		 * with its default prevented, so it keeps the focus).
		 *
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		onBlur() {
			this.close()
		},

		/**
		 * Open a result, or hand the query to Nextcloud's own search dialog.
		 *
		 * @param {{key: string, url?: string}} option The chosen row.
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		choose(option) {
			if (option.key === SHOW_ALL_KEY) {
				const query = this.query.trim()
				this.close()
				performFallback({ type: 'unified-search', query })
				return
			}
			if (option.url) {
				this.close()
				window.location.assign(option.url)
			}
		},

		/**
		 * Close the list. The field keeps its value and its focus.
		 *
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		close() {
			clearTimeout(this.timer)
			this.controller?.abort()
			this.controller = null
			this.open = false
			this.loading = false
			this.activeIndex = -1
		},
	},
}
</script>

<style scoped>
.lp-header-search {
	position: fixed;
	z-index: 10000;
	min-width: 280px;
	max-height: 70vh;
	overflow-y: auto;
	margin-top: 4px;
	padding: 8px 0;
	background: var(--color-main-background);
	color: var(--color-main-text);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	box-shadow: 0 4px 16px var(--color-box-shadow);
}

.lp-header-search__query,
.lp-header-search__tip {
	margin: 0;
	padding: 6px 16px;
	color: var(--color-text-maxcontrast);
}

.lp-header-search__list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.lp-header-search__group {
	padding: 8px 16px 4px;
	font-size: 0.8em;
	font-weight: bold;
	letter-spacing: 0.04em;
	color: var(--color-text-maxcontrast);
}

.lp-header-search__option {
	display: flex;
	gap: 12px;
	align-items: center;
	padding: 6px 16px;
	cursor: pointer;
}

.lp-header-search__option--active {
	background: var(--color-primary-element-light);
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.lp-header-search__icon {
	width: 24px;
	height: 24px;
	flex-shrink: 0;
}

.lp-header-search__text {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.lp-header-search__title {
	font-weight: bold;
}

.lp-header-search__detail {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.lp-header-search__empty {
	padding: 6px 16px;
	color: var(--color-text-maxcontrast);
}

.lp-header-search__show-all {
	border-top: 1px solid var(--color-border);
	margin-top: 4px;
}

.lp-header-search__tip {
	border-top: 1px solid var(--color-border);
	font-size: 0.9em;
}
</style>

<style>
/* LaunchPad's field in Nextcloud 31+'s top bar (utils/headerSearchField.js); unscoped: the top bar is core's DOM.
   It takes the look of the core button it stands in for; the host has pointer-events: none in core. */
.lp-header-search-host .unified-search-input__button {
	display: none !important;
}

.lp-header-search-host input.lp-header-search-field {
	pointer-events: auto;
	width: 100%;
	height: calc(var(--default-clickable-area, 34px) - 8px);
	min-height: 0;
	margin: 0;
	padding: 0 12px;
	border: none;
	border-radius: var(--border-radius-element, 8px);
	background-color: rgba(0, 0, 0, 0.15);
	backdrop-filter: var(--filter-background-blur);
	box-shadow: inset 0 2px 0 rgba(0, 0, 0, 0.12);
	color: var(--color-background-plain-text, #fff);
	font: inherit;
	text-align: start;
}

.lp-header-search-host input.lp-header-search-field::placeholder {
	color: color-mix(in srgb, var(--color-background-plain-text, #fff) 70%, var(--color-background-plain, #00679e));
	opacity: 1;
}

.lp-header-search-host input.lp-header-search-field:hover,
.lp-header-search-host input.lp-header-search-field:focus {
	background-color: rgba(0, 0, 0, 0.22);
}

.lp-header-search-host input.lp-header-search-field:focus-visible {
	outline: 2px solid var(--color-background-plain-text, #fff);
	outline-offset: 2px;
}
</style>
