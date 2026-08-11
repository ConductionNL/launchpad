<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="clock-widget-form">
		<p class="clock-widget-form__hint">
			{{ t('launchpad', 'Show the current time and date, entirely in the browser — no data leaves your device.') }}
		</p>

		<!-- @nextcloud/vue@9: `value`/`checked` + `update:value`/`update:checked`
		     were renamed to `modelValue` + `update:modelValue`; the old names
		     fail silently under Vue 3. -->
		<NcSelect
			:model-value="style"
			:options="styleOptions"
			:input-label="t('launchpad', 'Style')"
			:reduce="(option) => option.value"
			label="label"
			:clearable="false"
			@update:modelValue="updateField('style', $event)" />

		<template v-if="style === 'digital'">
			<NcSelect
				:model-value="hourFormat"
				:options="hourFormatOptions"
				:input-label="t('launchpad', 'Hour format')"
				:reduce="(option) => option.value"
				label="label"
				:clearable="false"
				@update:modelValue="updateField('hourFormat', $event)" />

			<NcCheckboxRadioSwitch
				:model-value="showDate"
				type="switch"
				@update:modelValue="updateField('showDate', $event)">
				{{ t('launchpad', 'Show date') }}
			</NcCheckboxRadioSwitch>
		</template>

		<NcSelect
			:model-value="timezone"
			:options="timezoneOptions"
			:input-label="t('launchpad', 'Timezone')"
			:placeholder="t('launchpad', 'Follow device timezone')"
			:reduce="(option) => option.value"
			label="label"
			@update:modelValue="updateField('timezone', $event || '')" />
	</div>
</template>

<script>
import { NcSelect, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

const DEFAULT_CONTENT = Object.freeze({
	style: 'digital',
	hourFormat: 'auto',
	timezone: '',
	showDate: true,
})

/**
 * Resolve the list of IANA timezone identifiers offered by the timezone
 * picker (REQ-CLOCK-002 "config UI MUST offer a timezone picker listing
 * IANA timezone identifiers"). Uses `Intl.supportedValuesOf('timeZone')`
 * where available (modern browsers); falls back to a small curated list
 * of major zones so the picker degrades gracefully rather than crashing
 * on older runtimes.
 *
 * @return {string[]} the offered IANA timezone identifiers.
 */
function resolveTimezones() {
	if (typeof Intl.supportedValuesOf === 'function') {
		try {
			return Intl.supportedValuesOf('timeZone')
		} catch (e) {
			// fall through to the curated fallback below.
		}
	}
	return [
		'UTC',
		'Europe/Amsterdam',
		'Europe/London',
		'Europe/Berlin',
		'Europe/Paris',
		'Europe/Madrid',
		'Europe/Rome',
		'America/New_York',
		'America/Chicago',
		'America/Denver',
		'America/Los_Angeles',
		'America/Sao_Paulo',
		'Asia/Tokyo',
		'Asia/Shanghai',
		'Asia/Kolkata',
		'Asia/Dubai',
		'Australia/Sydney',
		'Pacific/Auckland',
	]
}

/**
 * ClockWidgetForm — `CnAddWidgetModal` sub-form for creating or editing a
 * `clock` placement (REQ-CLOCK-002).
 *
 * Digital fields: style, hour format (12h/24h/auto-from-locale), show-date
 * toggle, timezone. Analog fields: style, timezone only — matching the
 * persisted-shape scenarios in the spec (no hourFormat/showDate on an
 * analog clock).
 */
export default {
	name: 'ClockWidgetForm',

	components: {
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
			style: initial.style ?? DEFAULT_CONTENT.style,
			hourFormat: initial.hourFormat ?? DEFAULT_CONTENT.hourFormat,
			timezone: initial.timezone ?? DEFAULT_CONTENT.timezone,
			showDate: initial.showDate ?? DEFAULT_CONTENT.showDate,
		}
	},

	computed: {
		/**
		 * The two style choices the author picks between — `digital` and
		 * `analog` are the only values REQ-CLOCK-002 defines a persisted
		 * shape for.
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-clock-002
		 * @return {Array<{value: string, label: string}>}
		 */
		styleOptions() {
			return [
				{ value: 'digital', label: t('launchpad', 'Digital') },
				{ value: 'analog', label: t('launchpad', 'Analog') },
			]
		},

		/**
		 * The hour-format choices: 12h, 24h, and `auto` — the last is what
		 * makes "hourFormat following the user locale" (REQ-CLOCK-002
		 * "Defaults when unset") selectable rather than implicit.
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-clock-002
		 * @return {Array<{value: string, label: string}>}
		 */
		hourFormatOptions() {
			return [
				{ value: 'auto', label: t('launchpad', 'Follow language (automatic)') },
				{ value: '12h', label: t('launchpad', '12-hour (AM/PM)') },
				{ value: '24h', label: t('launchpad', '24-hour') },
			]
		},

		/**
		 * The IANA timezone picker required by the REQ-CLOCK-002 "Analog
		 * style configuration" scenario ("the config UI MUST offer a timezone
		 * picker listing IANA timezone identifiers").
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-clock-002
		 * @return {Array<{value: string, label: string}>}
		 */
		timezoneOptions() {
			return resolveTimezones().map((zone) => ({ value: zone, label: zone }))
		},

		/**
		 * The persisted content blob. An analog clock only persists
		 * `{style, timezone}` — hourFormat/showDate are digital-only
		 * concerns (REQ-CLOCK-002 "Analog style configuration").
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-clock-002
		 * @return {object}
		 */
		assembledContent() {
			if (this.style === 'analog') {
				return {
					style: this.style,
					timezone: this.timezone,
				}
			}
			return {
				style: this.style,
				hourFormat: this.hourFormat,
				timezone: this.timezone,
				showDate: this.showDate,
			}
		},
	},

	methods: {
		t,

		/**
		 * Set a field and notify the parent via `update:content`, re-deriving
		 * the persisted blob so switching to `analog` immediately drops the
		 * digital-only keys (REQ-CLOCK-002).
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-clock-002
		 * @param {string} field one of: style, hourFormat, timezone, showDate.
		 * @param {string|boolean} value the new value.
		 * @return {void}
		 */
		updateField(field, value) {
			this[field] = value
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Validate the form. The clock widget has no required fields — every
		 * REQ-CLOCK-002 field has a documented default, so an empty array
		 * always means valid.
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-clock-002
		 * @return {string[]} the validation errors (always empty).
		 */
		validate() {
			return []
		},
	},
}
</script>

<style scoped>
.clock-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.clock-widget-form__hint {
	margin: 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}
</style>
