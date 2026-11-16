// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Format options of a calculated field, applied at display time only
 * (REQ-CALC-010).
 *
 * A calculated value stays in its native type (number, ISO date string,
 * seconds for a duration) for alerting, export and drill-down; these helpers
 * only turn it into the string a renderer shows. They never change the value
 * itself.
 *
 * @typedef {object} NumberFormatOptions
 * @property {'decimal'|'currency'|'percent'} [style] The number style.
 * @property {number} [minimumFractionDigits] Fewest fraction digits.
 * @property {number} [maximumFractionDigits] Most fraction digits.
 * @property {string} [currency] ISO 4217 code, for the currency style.
 * @property {string} [locale] BCP 47 locale, else the viewer's.
 *
 * @typedef {object} DateFormatOptions
 * @property {'short'|'medium'|'long'|'full'} [dateStyle] The date style.
 * @property {'short'|'medium'|'long'|'full'} [timeStyle] The time style.
 * @property {string} [timeZone] IANA time zone, default UTC (dates are UTC instants).
 * @property {string} [locale] BCP 47 locale, else the viewer's.
 *
 * @typedef {object} DurationFormatOptions
 * @property {string} pattern Tokens `d`, `h`, `m`, `s` (zero-padded `hh`, `mm`, `ss`); text in single quotes is literal.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */

const NUMBER_KEYS = ['style', 'minimumFractionDigits', 'maximumFractionDigits', 'currency']
const DATE_KEYS = ['dateStyle', 'timeStyle', 'timeZone']

/**
 * The listed keys of an options object that are set.
 *
 * @param {object} options The options.
 * @param {string[]} keys The keys to keep.
 * @return {object} The picked options.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
function pick(options, keys) {
	const picked = {}
	for (const key of keys) {
		if (options[key] !== undefined && options[key] !== null && options[key] !== '') {
			picked[key] = options[key]
		}
	}
	return picked
}

/**
 * Format a number with `Intl.NumberFormat`.
 *
 * @param {number} value The value.
 * @param {NumberFormatOptions} options The options.
 * @param {string} [locale] The fallback locale.
 * @return {string} The display string.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
export function formatNumber(value, options = {}, locale = undefined) {
	const intl = pick(options, NUMBER_KEYS)
	if (intl.style === 'currency' && !intl.currency) {
		delete intl.style
	}
	return new Intl.NumberFormat(options.locale || locale, intl).format(value)
}

/**
 * Format an ISO date string with `Intl.DateTimeFormat`, in UTC unless a time
 * zone is set.
 *
 * @param {string} value The ISO date.
 * @param {DateFormatOptions} options The options.
 * @param {string} [locale] The fallback locale.
 * @return {string} The display string; the value itself when it is no date.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
export function formatDate(value, options = {}, locale = undefined) {
	const date = new Date(value)
	if (Number.isNaN(date.getTime())) {
		return String(value)
	}
	const intl = { timeZone: 'UTC', ...pick(options, DATE_KEYS) }
	if (!intl.dateStyle && !intl.timeStyle) {
		intl.dateStyle = 'medium'
	}
	return new Intl.DateTimeFormat(options.locale || locale, intl).format(date)
}

/**
 * Format a duration in seconds with a token pattern: `d` days, `h` hours,
 * `m` minutes, `s` seconds; a doubled token is zero-padded; text between
 * single quotes is literal. The largest unit in the pattern carries the
 * remainder of the units the pattern leaves out.
 *
 * @param {number} seconds The duration.
 * @param {DurationFormatOptions} options The options.
 * @return {string} The display string.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
export function formatDuration(seconds, options = {}) {
	const pattern = typeof options.pattern === 'string' && options.pattern !== '' ? options.pattern : 'h:mm:ss'
	const parts = tokenise(pattern)
	const units = { d: 86400, h: 3600, m: 60, s: 1 }
	const sign = seconds < 0 ? '-' : ''
	let rest = Math.round(Math.abs(seconds))
	const amounts = {}
	for (const unit of ['d', 'h', 'm', 's']) {
		if (parts.some((part) => part.unit === unit)) {
			amounts[unit] = Math.floor(rest / units[unit])
			rest -= amounts[unit] * units[unit]
		}
	}
	return sign + parts.map((part) => {
		if (part.literal !== undefined) {
			return part.literal
		}
		return String(amounts[part.unit]).padStart(part.width, '0')
	}).join('')
}

/**
 * Split a duration pattern into unit tokens and literal text.
 *
 * @param {string} pattern The pattern.
 * @return {Array<{unit?: string, width?: number, literal?: string}>} The parts.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
function tokenise(pattern) {
	const parts = []
	let at = 0
	while (at < pattern.length) {
		const char = pattern[at]
		if (char === "'") {
			const end = pattern.indexOf("'", at + 1)
			const stop = end === -1 ? pattern.length : end
			parts.push({ literal: pattern.slice(at + 1, stop) })
			at = stop + 1
		} else if ('dhms'.includes(char)) {
			let width = 1
			while (pattern[at + width] === char) {
				width++
			}
			parts.push({ unit: char, width })
			at += width
		} else {
			parts.push({ literal: char })
			at++
		}
	}
	return parts
}

/**
 * The display string of a calculated value: its format options applied by
 * its return type. `null` shows as an empty string. The value passed in is
 * never changed, so alerting and export keep the native value.
 *
 * @param {unknown} value The evaluated value.
 * @param {{returnType?: string, formatOptions?: object}} field The calculated field.
 * @param {string} [locale] The viewer's locale, used when the options name none.
 * @return {string} The display string.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
export function formatCalculatedValue(value, field = {}, locale = undefined) {
	if (value === null || value === undefined) {
		return ''
	}
	const options = field.formatOptions || {}
	if (typeof value === 'number' && field.returnType === 'duration') {
		return formatDuration(value, options)
	}
	if (typeof value === 'number') {
		return formatNumber(value, options, locale)
	}
	if (typeof value === 'string' && field.returnType === 'date') {
		return formatDate(value, options, locale)
	}
	if (typeof value === 'boolean') {
		return value ? 'true' : 'false'
	}
	return String(value)
}
