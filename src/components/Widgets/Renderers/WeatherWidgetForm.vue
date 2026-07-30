<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="weather-widget-form">
		<p class="weather-widget-form__hint">
			{{ t('launchpad', 'Show current conditions for a location. Units and language follow your Nextcloud locale unless overridden below.') }}
		</p>

		<!-- @nextcloud/vue@9: `value` + `update:value` were renamed to
		     `modelValue` + `update:modelValue`; the old pair fails silently. -->
		<NcTextField
			:model-value="location"
			:label="t('launchpad', 'Location')"
			:placeholder="t('launchpad', 'e.g. Amsterdam, NL')"
			required
			@update:modelValue="updateField('location', $event)" />

		<NcSelect
			:model-value="unitsOverride"
			:options="unitsOptions"
			:input-label="t('launchpad', 'Units')"
			:placeholder="t('launchpad', 'Follow my locale')"
			:reduce="(option) => option.value"
			label="label"
			@update:modelValue="updateField('unitsOverride', $event || '')" />
	</div>
</template>

<script>
import { NcTextField, NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

const DEFAULT_CONTENT = Object.freeze({
	location: '',
	unitsOverride: '',
})

/**
 * WeatherWidgetForm — `CnAddWidgetModal` sub-form for creating or editing a
 * `weather` placement (REQ-WEATHER-002/003).
 *
 * `location` is a free-text location string resolved server-side by
 * `WeatherService`. `unitsOverride` lets the author pin `metric`/`imperial`
 * regardless of the viewer's locale (REQ-WEATHER-003 "Author override of
 * units"); left blank (default), units follow each viewer's own locale.
 */
export default {
	name: 'WeatherWidgetForm',

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
		return {
			location: initial.location ?? DEFAULT_CONTENT.location,
			unitsOverride: initial.unitsOverride ?? DEFAULT_CONTENT.unitsOverride,
		}
	},

	computed: {
		unitsOptions() {
			return [
				{ value: 'metric', label: t('launchpad', 'Metric (°C, km/h)') },
				{ value: 'imperial', label: t('launchpad', 'Imperial (°F, mph)') },
			]
		},

		assembledContent() {
			return {
				location: this.location,
				unitsOverride: this.unitsOverride,
			}
		},
	},

	methods: {
		t,

		/**
		 * Set a field and notify the parent via `update:content`.
		 *
		 * @param {string} field one of: location, unitsOverride.
		 * @param {string} value the new value.
		 * @return {void}
		 */
		updateField(field, value) {
			this[field] = value
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Validate the form — a weather tile with no location can never
		 * resolve a reading, so `location` is required.
		 *
		 * @return {string[]} the validation errors.
		 */
		validate() {
			if (typeof this.location !== 'string' || this.location.trim() === '') {
				return [t('launchpad', 'Location is required')]
			}
			return []
		},
	},
}
</script>

<style scoped>
.weather-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.weather-widget-form__hint {
	margin: 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}
</style>
