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

/**
 * Nextcloud's top bar search field, across the core versions LaunchPad runs on.
 *
 * @type {string}
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
 * (`search-placeholder.js`), so this component does not render or replace
 * it. It finds the field, gives it the combobox attributes (WAI-ARIA 1.2:
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
		 */
		shortcuts: LIST_PROP,

		/**
		 * The CSS selector of the field to attach to.
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
			timer: null,
			controller: null,
		}
	},

	computed: {
		/**
		 * The "Show all results" row, as an option.
		 *
		 * @return {{key: string}} The row.
		 */
		showAllOption() {
			return { key: SHOW_ALL_KEY }
		},

		/**
		 * Every selectable row in list order: the results, then "Show all".
		 *
		 * @return {Array<object>} The options.
		 */
		options() {
			const results = this.groups.flatMap((group) => group.results)
			return [...results, this.showAllOption]
		},

		/**
		 * The shortcut the tip names, or null.
		 *
		 * @return {{prefix: string, name: string}|null} The shortcut.
		 */
		shortcut() {
			return firstShortcut(this.shortcuts)
		},
	},

	watch: {
		/**
		 * Keep the field's combobox state in step with the list.
		 */
		open() {
			this.syncFieldAria()
		},

		/**
		 * Keep `aria-activedescendant` on the selected row.
		 */
		activeIndex() {
			this.syncFieldAria()
		},
	},

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

	beforeUnmount() {
		this.observer?.disconnect()
		clearTimeout(this.timer)
		this.controller?.abort()
		this.detach()
	},

	methods: {
		t,

		/**
		 * Find the field and bind to it.
		 *
		 * @return {boolean} Whether the field was found.
		 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
		 */
		attach() {
			const field = document.querySelector(this.fieldSelector)
			if (!field) {
				return false
			}
			this.field = field
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
			this.field = null
		},

		/**
		 * Mirror the list's state on the field.
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
		 */
		optionId(option) {
			return `${this.listboxId}-${option.key}`
		},

		/**
		 * Whether a row is the selected one.
		 *
		 * @param {{key: string}} option The row.
		 * @return {boolean} True when selected.
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
