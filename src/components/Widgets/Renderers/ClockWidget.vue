<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="clock-widget" :class="`clock-widget--${style}`">
		<div v-if="style === 'analog'" class="clock-widget__analog" role="img" :aria-label="accessibleLabel">
			<svg viewBox="0 0 100 100" class="clock-widget__face" aria-hidden="true">
				<circle cx="50" cy="50" r="48" class="clock-widget__rim" />
				<line
					v-for="tick in 12"
					:key="`tick-${tick}`"
					class="clock-widget__tick"
					v-bind="tickCoords(tick)" />
				<line class="clock-widget__hand clock-widget__hand--hour" x1="50" y1="50" v-bind="handCoords(hourAngle, 26)" />
				<line class="clock-widget__hand clock-widget__hand--minute" x1="50" y1="50" v-bind="handCoords(minuteAngle, 38)" />
				<line class="clock-widget__hand clock-widget__hand--second" x1="50" y1="50" v-bind="handCoords(secondAngle, 42)" />
				<circle cx="50" cy="50" r="2.2" class="clock-widget__pivot" />
			</svg>
		</div>

		<div v-else class="clock-widget__digital" :aria-label="accessibleLabel">
			<div class="clock-widget__time" aria-hidden="true">
				{{ timeText }}
			</div>
			<div v-if="showDate" class="clock-widget__date" aria-hidden="true">
				{{ dateText }}
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t, getCanonicalLocale } from '@nextcloud/l10n'

/**
 * Persisted-content field defaults (REQ-CLOCK-002). `timezone: ''` means
 * "follow the browser/device timezone" — the widget never fetches the
 * server-held Nextcloud user timezone, keeping the clock fully
 * client-side (REQ-CLOCK-001: no backend endpoint, no data fetch).
 */
const DEFAULT_CONTENT = Object.freeze({
	style: 'digital',
	hourFormat: 'auto',
	timezone: '',
	showDate: true,
})

/**
 * ClockWidget — the `clock` dashboard widget type (REQ-CLOCK-001..003).
 *
 * Fully client-side: renders the current time from the device clock only,
 * re-rendering once per second. No network call is ever made — timezone
 * conversion and locale-aware date/time formatting are done in-browser via
 * `Intl`. Mirrors the `divider` widget's zero-backend pattern.
 *
 * Persisted shape: `{style, hourFormat, timezone, showDate}` for a digital
 * clock; `{style, timezone}` for an analog clock (REQ-CLOCK-002 — the
 * analog face has no format/date fields to persist).
 */
export default {
	name: 'ClockWidget',

	props: {
		/**
		 * Persisted widget content blob.
		 *
		 * @type {{style?: string, hourFormat?: string, timezone?: string, showDate?: boolean}}
		 */
		content: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			/** Ticks every second; drives all time-derived computed properties. */
			now: new Date(),
			intervalId: null,
		}
	},

	computed: {
		/** Resolved style — `digital` or `analog`. @spec openspec/changes/clock-weather-widgets/specs/clock-weather-widgets/spec.md */
		style() {
			return this.content?.style === 'analog' ? 'analog' : DEFAULT_CONTENT.style
		},

		/** Resolved hour format — `12h`, `24h`, or `auto` (follows locale). @spec openspec/changes/clock-weather-widgets/specs/clock-weather-widgets/spec.md */
		hourFormat() {
			const value = this.content?.hourFormat
			if (value === '12h' || value === '24h') {
				return value
			}
			return DEFAULT_CONTENT.hourFormat
		},

		/** Whether the resolved hour format renders 12-hour clock with AM/PM. */
		hour12() {
			if (this.hourFormat === '12h') {
				return true
			}
			if (this.hourFormat === '24h') {
				return false
			}
			// 'auto' — let Intl decide from the locale's own convention.
			return undefined
		},

		/** Resolved IANA timezone, or '' to use the browser/device timezone. @spec openspec/changes/clock-weather-widgets/specs/clock-weather-widgets/spec.md */
		timezone() {
			return typeof this.content?.timezone === 'string' ? this.content.timezone : DEFAULT_CONTENT.timezone
		},

		/** Whether to render the locale-aware date line beneath a digital clock. */
		showDate() {
			if (this.style === 'analog') {
				return false
			}
			return this.content?.showDate !== false
		},

		/** Canonical BCP-47 locale, e.g. `en-US` or `nl-NL`. */
		locale() {
			return getCanonicalLocale()
		},

		/** Intl.DateTimeFormat options shared by the time formatter. */
		timeFormatOptions() {
			const options = {
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
			}
			if (this.hour12 !== undefined) {
				options.hour12 = this.hour12
			}
			if (this.timezone !== '') {
				options.timeZone = this.timezone
			}
			return options
		},

		/** Locale-aware, timezone-aware time string (REQ-CLOCK-003). */
		timeText() {
			try {
				return new Intl.DateTimeFormat(this.locale, this.timeFormatOptions).format(this.now)
			} catch (e) {
				// Invalid/unknown IANA timezone — fall back to the device zone
				// rather than crashing the widget.
				const { timeZone, ...fallbackOptions } = this.timeFormatOptions
				return new Intl.DateTimeFormat(this.locale, fallbackOptions).format(this.now)
			}
		},

		/** Intl.DateTimeFormat options for the locale-aware date line. */
		dateFormatOptions() {
			const options = {
				weekday: 'long',
				year: 'numeric',
				month: 'long',
				day: 'numeric',
			}
			if (this.timezone !== '') {
				options.timeZone = this.timezone
			}
			return options
		},

		/** Locale-aware date string, e.g. Dutch weekday/month names (REQ-CLOCK-003). */
		dateText() {
			try {
				return new Intl.DateTimeFormat(this.locale, this.dateFormatOptions).format(this.now)
			} catch (e) {
				const { timeZone, ...fallbackOptions } = this.dateFormatOptions
				return new Intl.DateTimeFormat(this.locale, fallbackOptions).format(this.now)
			}
		},

		/**
		 * Textual time exposed to assistive technology — required for the
		 * analog face (which conveys time visually only) and reused as the
		 * digital wrapper's aria-label so the value is announced once, not
		 * digit-by-digit (REQ-CLOCK-003 "Analog clock is accessible").
		 */
		accessibleLabel() {
			if (this.showDate) {
				return t('launchpad', '{date}, {time}', { date: this.dateText, time: this.timeText })
			}
			return this.timeText
		},

		/** Seconds hand angle in degrees (0 = 12 o'clock). */
		secondAngle() {
			return this.now.getSeconds() * 6
		},

		/** Minutes hand angle in degrees, creeping smoothly with seconds. */
		minuteAngle() {
			return (this.now.getMinutes() + (this.now.getSeconds() / 60)) * 6
		},

		/** Hours hand angle in degrees, creeping smoothly with minutes. */
		hourAngle() {
			return ((this.now.getHours() % 12) + (this.now.getMinutes() / 60)) * 30
		},
	},

	mounted() {
		// Update at least once per second (REQ-CLOCK-003).
		this.intervalId = setInterval(() => {
			this.now = new Date()
		}, 1000)
	},

	beforeDestroy() {
		if (this.intervalId) {
			clearInterval(this.intervalId)
			this.intervalId = null
		}
	},

	methods: {
		/**
		 * SVG line coordinates for one of the 12 clock-face tick marks.
		 *
		 * @param {number} tick tick index 1..12.
		 * @return {{x1: number, y1: number, x2: number, y2: number}}
		 */
		tickCoords(tick) {
			const angle = ((tick % 12) * 30)
			const outer = this.pointOnCircle(angle, 44)
			const inner = this.pointOnCircle(angle, 38)
			return { x1: inner.x, y1: inner.y, x2: outer.x, y2: outer.y }
		},

		/**
		 * SVG line-end coordinates for a clock hand of the given length,
		 * pointing at the given angle (degrees, 0 = 12 o'clock).
		 *
		 * @param {number} angleDegrees the hand's angle in degrees.
		 * @param {number} length the hand length in SVG units.
		 * @return {{x2: number, y2: number}}
		 */
		handCoords(angleDegrees, length) {
			const point = this.pointOnCircle(angleDegrees, length)
			return { x2: point.x, y2: point.y }
		},

		/**
		 * Resolve a point at the given angle (0 = 12 o'clock, clockwise) and
		 * radius from the SVG center (50, 50).
		 *
		 * @param {number} angleDegrees the angle in degrees.
		 * @param {number} radius the radius in SVG units.
		 * @return {{x: number, y: number}}
		 */
		pointOnCircle(angleDegrees, radius) {
			const radians = ((angleDegrees - 90) * Math.PI) / 180
			return {
				x: 50 + (radius * Math.cos(radians)),
				y: 50 + (radius * Math.sin(radians)),
			}
		},
	},
}
</script>

<style scoped>
.clock-widget {
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
}

.clock-widget__analog {
	width: 100%;
	height: 100%;
	max-width: 220px;
	max-height: 220px;
}

.clock-widget__face {
	width: 100%;
	height: 100%;
}

.clock-widget__rim {
	fill: var(--color-main-background, #fff);
	stroke: var(--color-border, #ccc);
	stroke-width: 2;
}

.clock-widget__tick {
	stroke: var(--color-text-maxcontrast, var(--color-main-text));
	stroke-width: 1.5;
}

.clock-widget__hand {
	stroke: var(--color-main-text);
	stroke-linecap: round;
}

.clock-widget__hand--hour {
	stroke-width: 4;
}

.clock-widget__hand--minute {
	stroke-width: 3;
}

.clock-widget__hand--second {
	stroke: var(--color-primary-element, var(--color-primary));
	stroke-width: 1.5;
}

.clock-widget__pivot {
	fill: var(--color-primary-element, var(--color-primary));
}

.clock-widget__digital {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 4px;
	text-align: center;
}

.clock-widget__time {
	font-size: 2rem;
	font-weight: 600;
	font-variant-numeric: tabular-nums;
	color: var(--color-main-text);
	line-height: 1.1;
}

.clock-widget__date {
	font-size: 0.9rem;
	color: var(--color-text-maxcontrast, var(--color-main-text));
}
</style>
