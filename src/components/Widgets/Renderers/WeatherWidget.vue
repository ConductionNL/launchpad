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

		<div v-else-if="errorMessage" class="weather-widget__state weather-widget__state--error">
			<AlertCircleOutline :size="32" />
			<span>{{ errorMessage }}</span>
			<button type="button" class="weather-widget__retry" @click="load">
				{{ t('launchpad', 'Retry') }}
			</button>
		</div>

		<div v-else-if="reading" class="weather-widget__reading">
			<span
				v-if="reading.stale"
				class="weather-widget__badge"
				role="status">
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
import { NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import WeatherSunny from 'vue-material-design-icons/WeatherSunny.vue'
import WeatherPartlyCloudy from 'vue-material-design-icons/WeatherPartlyCloudy.vue'
import WeatherCloudy from 'vue-material-design-icons/WeatherCloudy.vue'
import WeatherFog from 'vue-material-design-icons/WeatherFog.vue'
import WeatherPouring from 'vue-material-design-icons/WeatherPouring.vue'
import WeatherRainy from 'vue-material-design-icons/WeatherRainy.vue'
import WeatherSnowy from 'vue-material-design-icons/WeatherSnowy.vue'
import WeatherLightning from 'vue-material-design-icons/WeatherLightning.vue'
import WeatherWindy from 'vue-material-design-icons/WeatherWindy.vue'
import { fetchWeatherReading } from '../../../services/weatherClient.js'

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
		 * Persisted widget content blob: `{location, unitsOverride}`.
		 *
		 * @type {{location?: string, unitsOverride?: string}}
		 */
		content: {
			type: Object,
			default: () => ({}),
		},
		/**
		 * The widget placement — only `placement.id` is used, to call the
		 * per-placement weather endpoint.
		 *
		 * @type {{id?: number|string}|null}
		 */
		placement: {
			type: Object,
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
		 * @return {string}
		 */
		temperatureAriaLabel() {
			if (!this.reading || typeof this.reading.tempValue !== 'number') {
				return ''
			}
			const unitWord = this.reading.units === 'imperial'
				? t('launchpad', 'degrees Fahrenheit')
				: t('launchpad', 'degrees Celsius')
			return t('launchpad', '{value} {unit}', {
				value: Math.round(this.reading.tempValue),
				unit: unitWord,
			})
		},
	},

	mounted() {
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
		 * @return {Promise<void>}
		 */
		async load() {
			const placementId = this.placement?.id
			if (placementId === undefined || placementId === null) {
				this.loading = false
				this.errorMessage = t('launchpad', 'Weather is not available until this tile is saved.')
				return
			}

			this.loading = true
			this.errorMessage = ''
			try {
				const data = await fetchWeatherReading(placementId)
				if (data && data.error) {
					this.reading = null
					this.errorMessage = t('launchpad', 'Weather is currently unavailable.')
					return
				}
				this.reading = data
			} catch (e) {
				this.reading = null
				this.errorMessage = t('launchpad', 'Failed to load weather.')
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.weather-widget {
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
}

.weather-widget__state {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	color: var(--color-text-maxcontrast, var(--color-main-text));
	text-align: center;
}

.weather-widget__retry {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: transparent;
	padding: 4px 12px;
	cursor: pointer;
	color: var(--color-main-text);
}

.weather-widget__reading {
	position: relative;
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
}

.weather-widget__badge {
	position: absolute;
	top: -8px;
	right: -8px;
	font-size: 0.7rem;
	padding: 2px 6px;
	border-radius: var(--border-radius-pill, 8px);
	background: var(--color-warning, #ffcc00);
	color: var(--color-main-text);
}

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
