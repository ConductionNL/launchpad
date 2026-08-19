<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="search-widget-form">
		<!-- @nextcloud/vue@9: `value` + `update:value` were renamed to
		     `modelValue` + `update:modelValue`. The old names fail silently
		     under Vue 3. Listener stays camelCase — `useModel()` matches a
		     literal `onUpdate:modelValue`, not the kebab spelling. -->
		<NcTextField
			:modelValue="placeholder"
			:label="t('launchpad', 'Placeholder text')"
			:placeholder="defaultPlaceholder"
			@update:modelValue="onPlaceholderChange" />
		<p class="search-widget-form__hint">
			{{
				t(
					'launchpad',
					'Leave empty to use the default text, which advertises the / and Ctrl+K shortcuts.',
				)
			}}
		</p>

		<!-- inputLabel, never a manual <label> — a hand-rolled label breaks
		     NcSelect's internal accessibility wiring (ADR-004, WCAG 1.3.1). -->
		<NcSelect
			:modelValue="fallbackMode"
			:options="fallbackModeOptions"
			:inputLabel="t('launchpad', 'When nothing matches')"
			:reduce="(option) => option.value"
			:clearable="false"
			label="label"
			@update:modelValue="onFallbackModeChange" />
		<p class="search-widget-form__hint">
			{{ fallbackModeHint }}
		</p>

		<NcTextField
			v-if="fallbackMode === 'web-search'"
			:modelValue="fallbackTemplate"
			:label="t('launchpad', 'Search URL')"
			placeholder="https://example.com/search?q={query}"
			:error="Boolean(templateError)"
			:helperText="templateError"
			@update:modelValue="onTemplateChange" />
	</div>
</template>

<script>
import { NcSelect, NcTextField } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import {
	FALLBACK_TARGET_NONE,
	FALLBACK_TARGET_UNIFIED_SEARCH,
	isValidFallbackTemplate,
} from '../../../composables/useTileSearch.js'

/**
 * The "inherit the admin setting" marker (REQ-QSEARCH-005).
 *
 * Empty string rather than a dedicated `'inherit'` sentinel, so a widget's
 * `fallbackTarget` value space is exactly the admin setting's — `''`,
 * `'none'`, `'unified-search'`, or an `https` template. A fourth vocabulary
 * word would have to be understood by every reader of the value for no gain.
 *
 * @type {string}
 */
const FALLBACK_INHERIT = ''

const DEFAULT_CONTENT = Object.freeze({
	placeholder: '',
	fallbackTarget: FALLBACK_INHERIT,
})

/**
 * Classify a persisted `fallbackTarget` into the mode the select shows.
 *
 * The stored value is a single string carrying two things at once: which kind
 * of fallback it is, and — for the web-search kind — the template itself. The
 * form splits them apart for editing and rejoins them on save.
 *
 * @param {string} fallbackTarget the persisted value.
 * @return {string} one of `''`, `'none'`, `'unified-search'`, `'web-search'`.
 */
function modeOf(fallbackTarget) {
	if (!fallbackTarget) {
		return FALLBACK_INHERIT
	}
	if (fallbackTarget === FALLBACK_TARGET_NONE) {
		return FALLBACK_TARGET_NONE
	}
	if (fallbackTarget === FALLBACK_TARGET_UNIFIED_SEARCH) {
		return FALLBACK_TARGET_UNIFIED_SEARCH
	}
	return 'web-search'
}

/**
 * SearchWidgetForm — `CnAddWidgetModal` sub-form for creating or editing a
 * `search` placement (REQ-QSEARCH-005).
 *
 * Two settings: the input's placeholder text, and an optional override of the
 * admin-configured no-match fallback. Both default to "inherit / built-in", so
 * a freshly-added search widget behaves exactly like the old shell bar did
 * without the author configuring anything.
 *
 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-005
 */
export default {
	name: 'SearchWidgetForm',

	components: {
		NcTextField,
		NcSelect,
	},

	props: {
		/**
		 * The placement being edited, or `null` in create mode.
		 *
		 * @type {{content: object}|null}
		 */
		editingWidget: {
			type: Object,
			default: null,
		},

		/**
		 * Initial content values — used when not editing.
		 *
		 * @type {object}
		 */
		value: {
			type: Object,
			default: () => ({ ...DEFAULT_CONTENT }),
		},
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		const fallbackTarget =
			initial.fallbackTarget ?? DEFAULT_CONTENT.fallbackTarget
		const mode = modeOf(fallbackTarget)
		return {
			placeholder: initial.placeholder ?? DEFAULT_CONTENT.placeholder,
			fallbackMode: mode,
			// Only populated for the web-search mode; kept in its own field so
			// switching modes and back does not lose what the author typed.
			fallbackTemplate: mode === 'web-search' ? fallbackTarget : '',
		}
	},

	computed: {
		/**
		 * Shown as the placeholder-field's own placeholder, so the author can
		 * see what they get by leaving it empty.
		 *
		 * @return {string}
		 */
		defaultPlaceholder() {
			return t('launchpad', 'Search tiles… (/ or Ctrl+K)')
		},

		/**
		 * @return {Array<{value: string, label: string}>} the fallback modes.
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-005
		 */
		fallbackModeOptions() {
			return [
				{
					value: FALLBACK_INHERIT,
					label: t('launchpad', 'Use the administrator setting'),
				},
				{
					value: FALLBACK_TARGET_NONE,
					label: t('launchpad', 'Show "no results" only'),
				},
				{
					value: FALLBACK_TARGET_UNIFIED_SEARCH,
					label: t('launchpad', 'Hand off to Nextcloud search'),
				},
				{
					value: 'web-search',
					label: t('launchpad', 'Open a web search'),
				},
			]
		},

		/**
		 * @return {string} explanatory text for the selected mode.
		 */
		fallbackModeHint() {
			if (this.fallbackMode === FALLBACK_INHERIT) {
				return t(
					'launchpad',
					'Follows whatever your administrator configured for this instance.',
				)
			}
			if (this.fallbackMode === FALLBACK_TARGET_NONE) {
				return t(
					'launchpad',
					'The dashboard stays put and shows an accessible "no results" message.',
				)
			}
			if (this.fallbackMode === FALLBACK_TARGET_UNIFIED_SEARCH) {
				return t('launchpad', 'Passes the query to Nextcloud’s own search.')
			}
			return t(
				'launchpad',
				'Opens a new tab. The URL must be https and contain {query}.',
			)
		},

		/**
		 * Live validation message for the template field, or the empty string
		 * when it is acceptable (or not in play).
		 *
		 * @return {string}
		 */
		templateError() {
			if (this.fallbackMode !== 'web-search') {
				return ''
			}
			if (this.fallbackTemplate.trim() === '') {
				return ''
			}
			if (isValidFallbackTemplate(this.fallbackTemplate)) {
				return ''
			}
			return t('launchpad', 'Enter an https URL containing {query}.')
		},

		/**
		 * The `content` blob as currently edited.
		 *
		 * @return {{placeholder: string, fallbackTarget: string}}
		 */
		assembledContent() {
			return {
				placeholder: this.placeholder,
				fallbackTarget:
					this.fallbackMode === 'web-search'
						? this.fallbackTemplate
						: this.fallbackMode,
			}
		},
	},

	methods: {
		t,

		/**
		 * @param {string} val the new placeholder text.
		 * @return {void}
		 */
		onPlaceholderChange(val) {
			this.placeholder = val ?? ''
			this.emitContent()
		},

		/**
		 * @param {string|null} val the newly selected mode.
		 * @return {void}
		 */
		onFallbackModeChange(val) {
			this.fallbackMode = val ?? FALLBACK_INHERIT
			this.emitContent()
		},

		/**
		 * @param {string} val the new URL template.
		 * @return {void}
		 */
		onTemplateChange(val) {
			this.fallbackTemplate = val ?? ''
			this.emitContent()
		},

		/**
		 * @return {void}
		 */
		emitContent() {
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Synchronous submit gate called by the add/edit modal.
		 *
		 * Only the web-search mode can be invalid — the other three carry no
		 * free text. An empty placeholder is legitimate (it means "use the
		 * default"), so it is never an error.
		 *
		 * @return {string[]} the validation errors.
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-005
		 */
		validate() {
			const errors = []
			if (this.fallbackMode !== 'web-search') {
				return errors
			}
			if (this.fallbackTemplate.trim() === '') {
				errors.push(t('launchpad', 'Search URL is required'))
			} else if (!isValidFallbackTemplate(this.fallbackTemplate)) {
				errors.push(t('launchpad', 'Enter an https URL containing {query}.'))
			}
			return errors
		},
	},
}
</script>

<style scoped>
.search-widget-form {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.search-widget-form__hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
	margin: 0;
}
</style>
