<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="iframe-widget-form">
		<p class="iframe-widget-form__hint">
			{{ t('launchpad', 'Embed an external page — only hosts your administrator has allow-listed can be embedded.') }}
		</p>

		<!-- @nextcloud/vue@9: `value`/`checked` + `update:value`/`update:checked`
		     were renamed to `modelValue` + `update:modelValue`. Both old names
		     fail silently under Vue 3. Listener stays camelCase — `useModel()`
		     matches a literal `onUpdate:modelValue`, not the kebab spelling. -->
		<NcTextField
			:model-value="url"
			:label="t('launchpad', 'URL')"
			:placeholder="t('launchpad', 'https://…')"
			@update:modelValue="onUrlChange"
			@blur="checkUrlAllowed" />
		<p v-if="urlAllowListError" class="iframe-widget-form__warning">
			{{ urlAllowListError }}
		</p>
		<p v-else-if="allowListChecked && url" class="iframe-widget-form__hint-small">
			{{ t('launchpad', 'This host is allow-listed.') }}
		</p>

		<NcTextField
			:model-value="title"
			:label="t('launchpad', 'Title')"
			:placeholder="t('launchpad', 'e.g. Status page')"
			@update:modelValue="updateField('title', $event)" />
		<p class="iframe-widget-form__hint-small">
			{{ t('launchpad', 'Read by screen readers — required for accessibility.') }}
		</p>

		<div class="iframe-widget-form__row">
			<NcTextField
				:model-value="String(height)"
				type="number"
				:label="t('launchpad', 'Height (px)')"
				:disabled="aspect !== 'none'"
				@update:modelValue="onHeightChange" />
			<NcSelect
				:model-value="aspect"
				:options="aspectOptions"
				:input-label="t('launchpad', 'Aspect ratio')"
				:reduce="(option) => option.value"
				label="label"
				@update:modelValue="(val) => updateField('aspect', val || 'none')" />
		</div>

		<fieldset class="iframe-widget-form__sandbox">
			<legend>{{ t('launchpad', 'Sandbox permissions') }}</legend>
			<NcCheckboxRadioSwitch
				v-for="token in SANDBOX_TOKEN_OPTIONS"
				:key="token.value"
				:model-value="sandbox.includes(token.value)"
				type="switch"
				@update:modelValue="(checked) => toggleSandboxToken(token.value, checked)">
				{{ token.label }}
			</NcCheckboxRadioSwitch>
			<p class="iframe-widget-form__hint-small">
				{{ t('launchpad', 'The frame can never navigate this page away — that permission is not offered.') }}
			</p>
		</fieldset>
	</div>
</template>

<script>
import { NcTextField, NcSelect, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { validateIframeUrl } from '../../../services/iframeClient.js'

const DEFAULT_HEIGHT = 400

const DEFAULT_CONTENT = Object.freeze({
	url: '',
	title: '',
	height: DEFAULT_HEIGHT,
	aspect: 'none',
	sandbox: ['allow-scripts', 'allow-same-origin'],
	allowListChecked: false,
})

/**
 * The sandbox tokens an author may toggle. `allow-top-navigation` is
 * deliberately absent from this list — it is never offered, and would be
 * stripped even if present (REQ-IFRAME-004).
 *
 * @type {Array<{value: string, label: string}>}
 */
function sandboxTokenOptions() {
	return [
		{ value: 'allow-scripts', label: t('launchpad', 'Allow scripts') },
		{ value: 'allow-same-origin', label: t('launchpad', 'Allow same-origin') },
		{ value: 'allow-forms', label: t('launchpad', 'Allow forms') },
		{ value: 'allow-popups', label: t('launchpad', 'Allow popups') },
	]
}

/**
 * IframeWidgetForm — `CnAddWidgetModal` sub-form for creating or editing an
 * `iframe` placement (REQ-IFRAME-001/002/004).
 *
 * URL, title, height/aspect-ratio, and a constrained sandbox toggle set.
 * Host allow-list enforcement is authoritative server-side (fail-closed);
 * this form performs a best-effort async check (`validateIframeUrl`) on
 * blur so authors get fast feedback, surfaced through the synchronous
 * `validate()` the modal's submit gate calls.
 */
export default {
	name: 'IframeWidgetForm',

	components: {
		NcTextField,
		NcSelect,
		NcCheckboxRadioSwitch,
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
		return {
			url: initial.url ?? DEFAULT_CONTENT.url,
			title: initial.title ?? DEFAULT_CONTENT.title,
			height: initial.height ?? DEFAULT_CONTENT.height,
			aspect: initial.aspect ?? DEFAULT_CONTENT.aspect,
			sandbox: this.sanitiseInitialSandbox(initial.sandbox),
			allowListChecked: initial.allowListChecked === true,
			urlAllowListError: '',
			SANDBOX_TOKEN_OPTIONS: sandboxTokenOptions(),
		}
	},

	computed: {
		/** @spec openspec/specs/iframe-embed-widget/spec.md */
		aspectOptions() {
			return [
				{ value: 'none', label: t('launchpad', 'Fixed height') },
				{ value: '16:9', label: '16:9' },
				{ value: '4:3', label: '4:3' },
				{ value: '1:1', label: '1:1' },
				{ value: '9:16', label: '9:16' },
			]
		},

		/** @spec openspec/specs/iframe-embed-widget/spec.md */
		assembledContent() {
			return {
				url: this.url,
				title: this.title,
				height: this.clampHeight(this.height),
				aspect: this.aspect,
				// Defence-in-depth — never assemble a payload carrying the
				// forbidden token, even though it is never offered as a
				// toggle (REQ-IFRAME-004).
				sandbox: this.sandbox.filter((token) => !token.startsWith('allow-top-navigation')),
				allowListChecked: this.allowListChecked,
			}
		},
	},

	methods: {
		t,

		/**
		 * Strip any forbidden/unknown token from a persisted sandbox list
		 * at load time (defence-in-depth for placements saved before this
		 * client existed, or via direct API access).
		 *
		 * @param {*} raw the persisted sandbox list.
		 * @return {string[]} the sanitised token list.
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		sanitiseInitialSandbox(raw) {
			if (!Array.isArray(raw)) {
				return [...DEFAULT_CONTENT.sandbox]
			}
			const permitted = sandboxTokenOptions().map((option) => option.value)
			const clean = raw.filter((token) => typeof token === 'string' && permitted.includes(token))
			return clean.length > 0 ? clean : [...DEFAULT_CONTENT.sandbox]
		},

		/**
		 * Clamp a configured height to a sane positive pixel value.
		 *
		 * @param {number|string} raw the raw entered value.
		 * @return {number} the clamped height in pixels.
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		clampHeight(raw) {
			const num = Number(raw)
			if (!Number.isFinite(num) || num <= 0) {
				return DEFAULT_HEIGHT
			}
			return Math.round(num)
		},

		/**
		 * Set the embed URL, clearing the allow-list verdict so the new
		 * host is re-checked before the frame renders.
		 *
		 * @param {string} val The new URL.
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		onUrlChange(val) {
			this.url = val
			this.urlAllowListError = ''
			this.allowListChecked = false
			this.emitUpdate()
		},

		/**
		 * Set the embed height.
		 *
		 * @param {number} val Height in pixels.
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		onHeightChange(val) {
			this.height = val
			this.emitUpdate()
		},

		/**
		 * Set one top-level form field.
		 *
		 * @param {string} field Name of the data property to write.
		 * @param {*} val New value for that field.
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		updateField(field, val) {
			this[field] = val
			this.emitUpdate()
		},

		/**
		 * Toggle one sandbox token on/off. `allow-top-navigation` can never
		 * be added — it is not in `SANDBOX_TOKEN_OPTIONS`, so this method
		 * can only ever toggle a permitted token (REQ-IFRAME-004).
		 *
		 * @param {string} token the sandbox token.
		 * @param {boolean} checked whether the toggle is now on.
		 * @return {void}
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		toggleSandboxToken(token, checked) {
			if (checked) {
				if (!this.sandbox.includes(token)) {
					this.sandbox = [...this.sandbox, token]
				}
			} else {
				this.sandbox = this.sandbox.filter((existing) => existing !== token)
			}
			this.emitUpdate()
		},

		/** @spec openspec/specs/iframe-embed-widget/spec.md */
		emitUpdate() {
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Best-effort async save-time check (REQ-IFRAME-002 "rejected at
		 * save time") — the modal's submit gate calls `validate()`
		 * synchronously, so this only pre-populates `urlAllowListError` for
		 * the NEXT reactivity tick; the authoritative fail-closed
		 * enforcement always happens server-side regardless.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		async checkUrlAllowed() {
			if (this.url.trim() === '') {
				this.urlAllowListError = ''
				this.allowListChecked = false
				return
			}
			const result = await validateIframeUrl(this.assembledContent)
			this.allowListChecked = result.valid === true
			if (result.valid === false && result.errors.includes('host_not_allowed')) {
				this.urlAllowListError = t('launchpad', 'This host is not on the allow-list.')
			} else if (result.valid === false && result.errors.includes('invalid_url')) {
				this.urlAllowListError = t('launchpad', 'Enter a valid http(s) URL.')
			} else {
				this.urlAllowListError = ''
			}
			this.emitUpdate()
		},

		/**
		 * Validate the form. Requires a syntactically valid URL and a
		 * non-empty title (REQ-IFRAME-004 "Accessible frame title"). Any
		 * async allow-list error already surfaced via `checkUrlAllowed()`
		 * also blocks submission.
		 *
		 * @return {string[]} the validation errors.
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		validate() {
			const errors = []

			if (typeof this.url !== 'string' || this.url.trim() === '') {
				errors.push(t('launchpad', 'URL is required'))
			} else if (!/^https?:\/\/.+/i.test(this.url.trim())) {
				errors.push(t('launchpad', 'Enter a valid http(s) URL.'))
			}
			if (this.urlAllowListError) {
				errors.push(this.urlAllowListError)
			}

			if (typeof this.title !== 'string' || this.title.trim() === '') {
				errors.push(t('launchpad', 'Title is required'))
			}

			return errors
		},
	},
}
</script>

<style scoped>
.iframe-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.iframe-widget-form__hint {
	margin: 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.iframe-widget-form__hint-small {
	margin: -8px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.iframe-widget-form__warning {
	margin: 0;
	font-size: 13px;
	color: var(--color-warning-text, var(--color-text-maxcontrast));
}

.iframe-widget-form__row {
	display: flex;
	gap: 12px;
}

.iframe-widget-form__row > * {
	flex: 1;
}

.iframe-widget-form__sandbox {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 8px 12px;
	margin: 0;
}

.iframe-widget-form__sandbox legend {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	padding: 0 4px;
}
</style>
