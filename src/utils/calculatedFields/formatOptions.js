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
const UNITS = ['d', 'h', 'm', 's']
const UNIT_SECONDS = { d: 86400, h: 3600, m: 60, s: 1 }

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
	return new Intl.NumberFormat(localeOf(options, locale), intl).format(value)
}

/**
 * The locale the options name, else the fallback.
 *
 * @param {{locale?: string}} options The options.
 * @param {string} [locale] The fallback locale.
 * @return {string|undefined} The locale.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
function localeOf(options, locale) {
	return options.locale || locale
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
	return new Intl.DateTimeFormat(localeOf(options, locale), dateIntl(options)).format(date)
}

/**
 * The `Intl.DateTimeFormat` options: UTC unless a zone is set, a medium date
 * when neither style is.
 *
 * @param {DateFormatOptions} options The options.
 * @return {object} The Intl options.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
function dateIntl(options) {
	const intl = { timeZone: 'UTC', ...pick(options, DATE_KEYS) }
	if (!intl.dateStyle && !intl.timeStyle) {
		intl.dateStyle = 'medium'
	}
	return intl
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
	const parts = tokenise(durationPattern(options))
	const amounts = durationAmounts(Math.round(Math.abs(seconds)), parts)
	const sign = seconds < 0 ? '-' : ''
	return sign + parts.map((part) => renderPart(part, amounts)).join('')
}

/**
 * The duration pattern of the options, `h:mm:ss` without one.
 *
 * @param {DurationFormatOptions} options The options.
 * @return {string} The pattern.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
function durationPattern(options) {
	return typeof options.pattern === 'string' && options.pattern !== '' ? options.pattern : 'h:mm:ss'
}

/**
 * The amount of each unit the pattern uses; the largest carries the rest.
 *
 * @param {number} total The whole seconds.
 * @param {Array<{unit?: string}>} parts The pattern parts.
 * @return {Record<string, number>} The amounts by unit.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
function durationAmounts(total, parts) {
	const amounts = {}
	let rest = total
	for (const unit of UNITS.filter((candidate) => parts.some((part) => part.unit === candidate))) {
		amounts[unit] = Math.floor(rest / UNIT_SECONDS[unit])
		rest -= amounts[unit] * UNIT_SECONDS[unit]
	}
	return amounts
}

/**
 * One pattern part as text: a literal as is, a unit zero-padded to its width.
 *
 * @param {{unit?: string, width?: number, literal?: string}} part The part.
 * @param {Record<string, number>} amounts The amounts by unit.
 * @return {string} The text.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
function renderPart(part, amounts) {
	if (part.literal !== undefined) {
		return part.literal
	}
	return String(amounts[part.unit]).padStart(part.width, '0')
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
		const { part, next } = readPart(pattern, at)
		parts.push(part)
		at = next
	}
	return parts
}

/**
 * Read the pattern part starting at a position: quoted literal text, a run
 * of one unit token, or a single literal character.
 *
 * @param {string} pattern The pattern.
 * @param {number} at The position.
 * @return {{part: object, next: number}} The part and the position after it.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
function readPart(pattern, at) {
	const char = pattern[at]
	if (char === "'") {
		const end = pattern.indexOf("'", at + 1)
		const stop = end === -1 ? pattern.length : end
		return { part: { literal: pattern.slice(at + 1, stop) }, next: stop + 1 }
	}
	if (!'dhms'.includes(char)) {
		return { part: { literal: char }, next: at + 1 }
	}
	let width = 1
	while (pattern[at + width] === char) {
		width++
	}
	return { part: { unit: char, width }, next: at + width }
}

/**
 * The formatters by return type, first match wins: `null` shows as an
 * empty string, anything unmatched as its string (`true`/`false` for booleans).
 *
 * @type {Array<{applies: function(unknown, object): boolean, format: function(unknown, object, string=): string}>}
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */
const FORMATTERS = [
	{ applies: (value) => value === null || value === undefined, format: () => '' },
	{ applies: (value, field) => typeof value === 'number' && field.returnType === 'duration', format: (value, options) => formatDuration(value, options) },
	{ applies: (value) => typeof value === 'number', format: formatNumber },
	{ applies: (value, field) => typeof value === 'string' && field.returnType === 'date', format: formatDate },
]

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
export function formatCalculatedValue(value, field = {}, locale) {
	const formatter = FORMATTERS.find((candidate) => candidate.applies(value, field))
	return formatter ? formatter.format(value, field.formatOptions || {}, locale) : String(value)
}
