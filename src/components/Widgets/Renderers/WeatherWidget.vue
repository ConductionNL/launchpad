<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="weather-widget">
		<div v-if="loading" class="weather-widget__state">
			<NcLoadingIcon :size="32" />
			<span>{{ t('launchpad', 'Loading weather…') }}</span>
		</div>

		<div
			v-else-if="errorMessage"
			class="weather-widget__state weather-widget__state--error">
			<AlertCircleOutline :size="32" />
			<span>{{ errorMessage }}</span>
			<button type="button" class="weather-widget__retry" @click="load">
				{{ t('launchpad', 'Retry') }}
			</button>
		</div>

		<div v-else-if="reading" class="weather-widget__reading">
			<span v-if="reading.stale" class="weather-widget__badge" role="status">
				{{ t('launchpad', 'Showing last known reading') }}
			</span>

			<div class="weather-widget__location">
				{{ reading.location }}
			</div>

			<div class="weather-widget__body">
				<!-- Condition conveyed by icon AND text (WCAG AA — never colour/icon
				     alone), REQ-WEATHER-003. -->
				<component
					:is="conditionIcon"
					:size="48"
					:title="reading.conditionText"
					class="weather-widget__icon" />
				<div class="weather-widget__details">
					<div
						class="weather-widget__temp"
						:aria-label="temperatureAriaLabel">
						{{ temperatureText }}
					</div>
					<div class="weather-widget__condition-text">
						{{ reading.conditionText }}
					</div>
				</div>
			</div>
		</div>

		<div v-else class="weather-widget__state">
			{{ t('launchpad', 'No weather data yet.') }}
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcLoadingIcon } from '@nextcloud/vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import WeatherCloudy from 'vue-material-design-icons/WeatherCloudy.vue'
import WeatherFog from 'vue-material-design-icons/WeatherFog.vue'
import WeatherLightning from 'vue-material-design-icons/WeatherLightning.vue'
import WeatherPartlyCloudy from 'vue-material-design-icons/WeatherPartlyCloudy.vue'
import WeatherPouring from 'vue-material-design-icons/WeatherPouring.vue'
import WeatherRainy from 'vue-material-design-icons/WeatherRainy.vue'
import WeatherSnowy from 'vue-material-design-icons/WeatherSnowy.vue'
import WeatherSunny from 'vue-material-design-icons/WeatherSunny.vue'
import WeatherWindy from 'vue-material-design-icons/WeatherWindy.vue'
import {
	NULLABLE_OBJECT_PROP,
	OBJECT_PROP,
} from '../../../constants/propDefinitions.js'
import { fetchWeatherReading } from '../../../services/weatherClient.js'
import { loadPlacementReading } from '../../../utils/placementReading.js'

/**
 * Maps a normalised `condition` code (returned by `WeatherService`) to an
 * icon component. Falls back to `WeatherPartlyCloudy` for any unrecognised
 * code so the widget never renders a blank icon slot.
 *
 * @type {Record<string, object>}
 */
const CONDITION_ICONS = {
	clear: WeatherSunny,
	sunny: WeatherSunny,
	'partly-cloudy': WeatherPartlyCloudy,
	cloudy: WeatherCloudy,
	overcast: WeatherCloudy,
	fog: WeatherFog,
	mist: WeatherFog,
	drizzle: WeatherRainy,
	rain: WeatherRainy,
	'heavy-rain': WeatherPouring,
	showers: WeatherPouring,
	snow: WeatherSnowy,
	thunderstorm: WeatherLightning,
	windy: WeatherWindy,
}

/**
 * WeatherWidget — the `weather` dashboard widget type (REQ-WEATHER-001..003).
 *
 * Calls LaunchPad's own `GET /api/weather/{placementId}` endpoint (via
 * `weatherClient.js`, mockable in tests) on mount. Renders one of four
 * states: loading, error (no cached reading available), stale-but-cached
 * (a `weather-widget__badge` overlays the last-known reading), or a fresh
 * reading. The condition is always conveyed by an icon AND a text label
 * (WCAG AA — REQ-WEATHER-003), and the temperature carries an
 * `aria-label` including its units.
 */
export default {
	name: 'WeatherWidget',

	components: {
		NcLoadingIcon,
		AlertCircleOutline,
	},

	props: {
		/**
		 * The widget is a miniature in Add widget (REQ-WDG-048): it draws
		 * its sample content and sends no request.
		 */
		preview: { type: Boolean, default: false },

		/**
		 * Persisted widget content blob: `{location, unitsOverride}`.
		 *
		 * The reading is resolved SERVER-side by `GET /api/weather/{placementId}`,
		 * which loads this same blob off the placement row; here only a
		 * miniature reads its `sampleReading` (REQ-WDG-048).
		 *
		 * @type {{location?: string, unitsOverride?: string, sampleReading?: object}}
		 */
		content: OBJECT_PROP,

		/**
		 * The widget placement — only `placement.id` is used, to call the
		 * per-placement weather endpoint.
		 *
		 * @type {{id?: number|string}|null}
		 */
		placement: NULLABLE_OBJECT_PROP,

		/**
		 * Where the reading comes from instead of LaunchPad's per-placement
		 * endpoint: a function of the placement id that resolves the reading.
		 * A public link or kiosk screen passes its token-scoped read here
		 * (REQ-PSHR-011); null (the default) is the signed-in dashboard.
		 *
		 * @type {(function((number|string)): Promise<object>)|null}
		 */
		readingSource: {
			type: Function,
			default: null,
		},
	},

	data() {
		return {
			loading: true,
			errorMessage: '',
			reading: null,
		}
	},

	computed: {
		/**
		 * Icon component for the current reading's condition code. The icon
		 * is only ever half of the signal — the template renders
		 * `conditionText` beside it, because REQ-WEATHER-003 forbids
		 * conveying the condition by icon or colour alone.
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-weather-003
		 * @return {object} the icon component.
		 */
		conditionIcon() {
			const code = this.reading?.condition
			return CONDITION_ICONS[code] || WeatherPartlyCloudy
		},

		/**
		 * Unit suffix (°C / °F) driven by the `units` the server resolved
		 * from the viewer's locale or the author's override — never a
		 * hardcoded unit.
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-weather-003
		 * @return {string}
		 */
		unitSuffix() {
			return this.reading?.units === 'imperial' ? '°F' : '°C'
		},

		/**
		 * Formatted temperature, e.g. "18°C" — the visible value, always
		 * stated together with its units.
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-weather-003
		 * @return {string}
		 */
		temperatureText() {
			if (!this.reading || typeof this.reading.tempValue !== 'number') {
				return ''
			}
			return `${Math.round(this.reading.tempValue)}${this.unitSuffix}`
		},

		/**
		 * Accessible label for the temperature, spelling the units out in
		 * words — REQ-WEATHER-003 requires the temperature to carry an
		 * accessible label including its units, which "18°C" read aloud
		 * does not reliably give.
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-weather-003
		 * @return {string}
		 */
		temperatureAriaLabel() {
			if (!this.reading || typeof this.reading.tempValue !== 'number') {
				return ''
			}
			const unitWord =
				this.reading.units === 'imperial'
					? t('launchpad', 'degrees Fahrenheit')
					: t('launchpad', 'degrees Celsius')
			return t('launchpad', '{value} {unit}', {
				value: Math.round(this.reading.tempValue),
				unit: unitWord,
			})
		},
	},

	/** @spec openspec/changes/archive/2026-11-27-add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048 */
	mounted() {
		if (this.preview) {
			this.reading = this.content?.sampleReading || null
			this.loading = false
			return
		}
		this.load()
	},

	methods: {
		t,

		/**
		 * Load (or reload) the weather reading for this placement. Any
		 * failure — network error, 403, 5xx, missing placement id — renders
		 * the error state; it never throws into the parent.
		 *
		 * The browser only ever calls LaunchPad's own per-placement endpoint
		 * (via `weatherClient.js`), so no provider URL or API key is ever
		 * reachable from here.
		 *
		 * @spec openspec/specs/clock-weather-widgets/spec.md#req-weather-001
		 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
		 * @return {Promise<void>}
		 */
		async load() {
			await loadPlacementReading(this, {
				placementId: this.placement?.id,
				fetch: this.readingSource ?? fetchWeatherReading,
				unsaved: t(
					'launchpad',
					'Weather is not available until this tile is saved.',
				),
				unavailable: t('launchpad', 'Weather is currently unavailable.'),
				failed: t('launchpad', 'Failed to load weather.'),
			})
		},
	},
}
</script>

<style scoped src="./readingTile.css"></style>

<style scoped>
.weather-widget__location {
	font-weight: 600;
	color: var(--color-main-text);
}

.weather-widget__body {
	display: flex;
	align-items: center;
	gap: 12px;
}

.weather-widget__details {
	display: flex;
	flex-direction: column;
}

.weather-widget__temp {
	font-size: 1.8rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.weather-widget__condition-text {
	color: var(--color-text-maxcontrast, var(--color-main-text));
}
</style>
