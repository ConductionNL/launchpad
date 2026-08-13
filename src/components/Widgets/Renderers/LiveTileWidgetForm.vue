<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="live-tile-widget-form">
		<p class="live-tile-widget-form__hint">
			{{
				t(
					'launchpad',
					'Bind this tile to a live value from a data source: a count, a status, a KPI number.',
				)
			}}
		</p>

		<!-- @nextcloud/vue@9 renamed the two-way prop of every form control
		     from `value`/`checked` to `modelValue`, and the paired event from
		     `update:value`/`update:checked` to `update:modelValue`. The old
		     names fail silently — `:value` falls through as a plain attribute
		     and the old event is simply never emitted, so the field renders
		     but never writes back. The listener must stay camelCase:
		     `useModel()` decides controlled-vs-local by looking for a literal
		     `onUpdate:modelValue` in the vnode props, and a kebab-case
		     `@update:model-value` does not match. -->
		<NcTextField
			:model-value="label"
			:label="t('launchpad', 'Label')"
			:placeholder="t('launchpad', 'e.g. Open tickets')"
			@update:modelValue="updateField('label', $event)" />

		<NcSelect
			:model-value="sourceMode"
			:options="sourceModeOptions"
			:input-label="t('launchpad', 'Source')"
			:reduce="(option) => option.value"
			label="label"
			@update:modelValue="onSourceModeChange" />

		<template v-if="sourceMode === 'connector'">
			<p v-if="!connectorAvailable" class="live-tile-widget-form__warning">
				{{
					t(
						'launchpad',
						'OpenConnector is not installed — direct-URL mode only.',
					)
				}}
			</p>
			<NcTextField
				:model-value="sourceId"
				:label="t('launchpad', 'OpenConnector source id')"
				:disabled="!connectorAvailable"
				@update:modelValue="updateField('sourceId', $event)" />
		</template>

		<template v-else>
			<NcTextField
				:model-value="url"
				:label="t('launchpad', 'URL')"
				:placeholder="t('launchpad', 'https://…')"
				@update:modelValue="onUrlChange"
				@blur="checkUrlAllowed" />
			<p v-if="urlAllowListError" class="live-tile-widget-form__warning">
				{{ urlAllowListError }}
			</p>
		</template>

		<NcTextField
			:model-value="valueExpr"
			:label="t('launchpad', 'Value expression')"
			placeholder="$.data.count"
			@update:modelValue="updateField('valueExpr', $event)" />

		<NcTextField
			:model-value="String(refresh)"
			type="number"
			:label="t('launchpad', 'Refresh interval (seconds)')"
			@update:modelValue="onRefreshChange" />
		<p class="live-tile-widget-form__hint-small">
			{{
				t('launchpad', 'Minimum {min} seconds.', {
					min: MIN_REFRESH_SECONDS,
				})
			}}
		</p>

		<div class="live-tile-widget-form__row">
			<NcTextField
				:model-value="formatPrefix"
				:label="t('launchpad', 'Prefix')"
				@update:modelValue="updateFormat('prefix', $event)" />
			<NcTextField
				:model-value="formatSuffix"
				:label="t('launchpad', 'Suffix')"
				@update:modelValue="updateFormat('suffix', $event)" />
		</div>
		<NcCheckboxRadioSwitch
			:model-value="formatThousands"
			type="switch"
			@update:modelValue="updateFormat('thousands', $event)">
			{{ t('launchpad', 'Thousands separator') }}
		</NcCheckboxRadioSwitch>

		<NcTextField
			:model-value="linkUrl"
			:label="t('launchpad', 'Click-through link (optional)')"
			@update:modelValue="updateField('linkUrl', $event)" />
		<NcSelect
			:model-value="linkTarget"
			:options="linkTargetOptions"
			:input-label="t('launchpad', 'Link target')"
			:reduce="(option) => option.value"
			label="label"
			@update:modelValue="
				(val) => updateField('linkTarget', val || 'same-tab')
			" />
	</div>
</template>

<script>
import { NcTextField, NcSelect, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import {
	fetchConnectorAvailability,
	validateLiveTileSource,
} from '../../../services/liveTileClient.js'

const MIN_REFRESH_SECONDS = 30
const DEFAULT_REFRESH_SECONDS = 300

const DEFAULT_CONTENT = Object.freeze({
	label: '',
	sourceMode: 'url',
	url: '',
	sourceId: '',
	valueExpr: '',
	refresh: DEFAULT_REFRESH_SECONDS,
	format: { prefix: '', suffix: '', thousands: false },
	badge: { thresholds: [] },
	linkUrl: '',
	linkTarget: 'same-tab',
})

/**
 * LiveTileWidgetForm — `CnAddWidgetModal` sub-form for creating or editing a
 * `livetile` placement (REQ-LIVETILE-002/004/005).
 *
 * Offers two source modes: an OpenConnector-backed source picker (only when
 * the `connector-status` capability probe reports OpenConnector present —
 * REQ-LIVETILE-005 "hidden or disabled" when absent) or a direct allow-
 * listed URL. Host allow-list enforcement is authoritative server-side
 * (fail-closed); this form performs a best-effort async check
 * (`validateLiveTileSource`) on blur so authors get fast feedback, surfaced
 * through the synchronous `validate()` the modal's submit gate calls.
 */
export default {
	name: 'LiveTileWidgetForm',

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
		const format = { ...DEFAULT_CONTENT.format, ...(initial.format || {}) }
		const badge = { ...DEFAULT_CONTENT.badge, ...(initial.badge || {}) }
		return {
			label: initial.label ?? DEFAULT_CONTENT.label,
			sourceMode: initial.sourceMode ?? DEFAULT_CONTENT.sourceMode,
			url: initial.url ?? DEFAULT_CONTENT.url,
			sourceId: initial.sourceId ?? DEFAULT_CONTENT.sourceId,
			valueExpr: initial.valueExpr ?? DEFAULT_CONTENT.valueExpr,
			refresh: initial.refresh ?? DEFAULT_CONTENT.refresh,
			formatPrefix: format.prefix,
			formatSuffix: format.suffix,
			formatThousands: !!format.thousands,
			badge,
			linkUrl: initial.linkUrl ?? DEFAULT_CONTENT.linkUrl,
			linkTarget: initial.linkTarget ?? DEFAULT_CONTENT.linkTarget,
			connectorAvailable: false,
			urlAllowListError: '',
			MIN_REFRESH_SECONDS,
		}
	},

	computed: {
		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		sourceModeOptions() {
			const options = [{ value: 'url', label: t('launchpad', 'Direct URL') }]
			// REQ-LIVETILE-005: only offer `connector` mode when the
			// capability probe confirms OpenConnector is present.
			if (this.connectorAvailable) {
				options.push({
					value: 'connector',
					label: t('launchpad', 'OpenConnector source'),
				})
			}
			return options
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		linkTargetOptions() {
			return [
				{ value: 'same-tab', label: t('launchpad', 'Same tab') },
				{ value: 'new-tab', label: t('launchpad', 'New tab') },
			]
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		assembledContent() {
			return {
				label: this.label,
				sourceMode: this.sourceMode,
				url: this.url,
				sourceId: this.sourceId,
				valueExpr: this.valueExpr,
				refresh: this.clampRefresh(this.refresh),
				format: {
					prefix: this.formatPrefix,
					suffix: this.formatSuffix,
					thousands: this.formatThousands,
				},
				badge: this.badge,
				linkUrl: this.linkUrl,
				linkTarget: this.linkTarget,
			}
		},
	},

	created() {
		this.refreshConnectorAvailability()
	},

	methods: {
		t,

		/**
		 * Clamp a refresh interval: values `<= 0` default to
		 * {@link DEFAULT_REFRESH_SECONDS}; any positive value below
		 * {@link MIN_REFRESH_SECONDS} is raised to that minimum
		 * (REQ-LIVETILE-002 "Refresh interval bounds").
		 *
		 * @param {number|string} raw the raw entered value.
		 * @return {number} the clamped refresh interval in seconds.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		clampRefresh(raw) {
			const num = Number(raw)
			if (!Number.isFinite(num) || num <= 0) {
				return DEFAULT_REFRESH_SECONDS
			}
			return Math.max(num, MIN_REFRESH_SECONDS)
		},

		/**
		 * Probe OpenConnector's `dashboard-http-datasource` capability. When
		 * absent (or already selected as `connector` with no availability),
		 * falls back to `url` mode so the form never offers a dead option
		 * (REQ-LIVETILE-005).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		async refreshConnectorAvailability() {
			this.connectorAvailable = await fetchConnectorAvailability()
			if (
				this.connectorAvailable === false
				&& this.sourceMode === 'connector'
			) {
				this.sourceMode = 'url'
				this.emitUpdate()
			}
		},

		/**
		 * Switch where the tile reads its value from.
		 *
		 * @param {string} val Source mode; falsy falls back to `url`.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		onSourceModeChange(val) {
			this.sourceMode = val || 'url'
			this.emitUpdate()
		},

		/**
		 * Set the data-source URL, clearing any stale allow-list error.
		 *
		 * @param {string} val The new URL.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		onUrlChange(val) {
			this.url = val
			this.urlAllowListError = ''
			this.emitUpdate()
		},

		/**
		 * Set how often the tile re-polls its source.
		 *
		 * @param {number} val Refresh interval in seconds.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		onRefreshChange(val) {
			this.refresh = val
			this.emitUpdate()
		},

		/**
		 * Set one top-level form field.
		 *
		 * @param {string} field Name of the data property to write.
		 * @param {*} val New value for that field.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		updateField(field, val) {
			this[field] = val
			this.emitUpdate()
		},

		/**
		 * Set one of the number-formatting options.
		 *
		 * @param {string} field Which option to write — `prefix`, `suffix`
		 *   or `thousands`.
		 * @param {*} val New value for that option.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		updateFormat(field, val) {
			if (field === 'prefix') {
				this.formatPrefix = val
			} else if (field === 'suffix') {
				this.formatSuffix = val
			} else if (field === 'thousands') {
				this.formatThousands = val
			}
			this.emitUpdate()
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		emitUpdate() {
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Best-effort async save-time check (REQ-LIVETILE-002 "rejected at
		 * save time") — the modal's submit gate calls `validate()`
		 * synchronously, so this only pre-populates `urlAllowListError` for
		 * the NEXT reactivity tick; the authoritative fail-closed
		 * enforcement always happens server-side at fetch time regardless.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		async checkUrlAllowed() {
			if (this.sourceMode !== 'url' || this.url.trim() === '') {
				this.urlAllowListError = ''
				return
			}
			const result = await validateLiveTileSource(this.assembledContent)
			if (
				result.valid === false
				&& result.errors.includes('host_not_allowed')
			) {
				this.urlAllowListError = t(
					'launchpad',
					'This host is not on the allow-list.',
				)
			} else if (
				result.valid === false
				&& result.errors.includes('invalid_url')
			) {
				this.urlAllowListError = t('launchpad', 'Enter a valid http(s) URL.')
			} else {
				this.urlAllowListError = ''
			}
		},

		/**
		 * Validate the form. `url` mode requires a syntactically valid URL;
		 * `connector` mode requires a source id and OpenConnector actually
		 * being available. Any async allow-list error already surfaced via
		 * `checkUrlAllowed()` also blocks submission.
		 *
		 * @return {string[]} the validation errors.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		validate() {
			const errors = []

			if (this.sourceMode === 'url') {
				if (typeof this.url !== 'string' || this.url.trim() === '') {
					errors.push(t('launchpad', 'URL is required'))
				} else if (!/^https?:\/\/.+/i.test(this.url.trim())) {
					errors.push(t('launchpad', 'Enter a valid http(s) URL.'))
				}
				if (this.urlAllowListError) {
					errors.push(this.urlAllowListError)
				}
			} else if (this.sourceMode === 'connector') {
				if (!this.connectorAvailable) {
					errors.push(t('launchpad', 'OpenConnector is not available.'))
				}
				if (
					typeof this.sourceId !== 'string'
					|| this.sourceId.trim() === ''
				) {
					errors.push(t('launchpad', 'Source id is required'))
				}
			}

			if (typeof this.valueExpr !== 'string' || this.valueExpr.trim() === '') {
				errors.push(t('launchpad', 'Value expression is required'))
			}

			return errors
		},
	},
}
</script>

<style scoped>
.live-tile-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.live-tile-widget-form__hint {
	margin: 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.live-tile-widget-form__hint-small {
	margin: -8px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.live-tile-widget-form__warning {
	margin: 0;
	font-size: 13px;
	color: var(--color-warning-text, var(--color-text-maxcontrast));
}

.live-tile-widget-form__row {
	display: flex;
	gap: 12px;
}

.live-tile-widget-form__row > * {
	flex: 1;
}
</style>
