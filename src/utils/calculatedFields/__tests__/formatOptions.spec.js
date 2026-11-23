// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Format options applied at display time only (tasks 34-36).
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-010-format-options-applied-at-render-time
 */

import { describe, expect, it } from 'vitest'
import { formatCalculatedValue, formatDate, formatDuration, formatNumber } from '../formatOptions.js'

describe('formatNumber', () => {
	it('formats a currency in the field\'s locale', () => {
		expect(formatNumber(145000, { style: 'currency', currency: 'EUR', locale: 'nl-NL' })).toBe(
			new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(145000),
		)
	})

	it('applies the fraction digits', () => {
		expect(formatNumber(40, { minimumFractionDigits: 2, maximumFractionDigits: 2, locale: 'en-US' })).toBe('40.00')
		expect(formatNumber(1.23456, { maximumFractionDigits: 1, locale: 'en-US' })).toBe('1.2')
	})

	it('falls back to a decimal when a currency style names no currency', () => {
		expect(formatNumber(12.5, { style: 'currency', locale: 'en-US' })).toBe('12.5')
	})

	it('uses the viewer\'s locale when the options name none', () => {
		expect(formatNumber(1234.5, {}, 'de-DE')).toBe('1.234,5')
	})
})

describe('formatDate', () => {
	it('formats an ISO instant in UTC by default', () => {
		expect(formatDate('2026-04-21T23:30:00Z', { dateStyle: 'short', locale: 'en-GB' })).toBe('21/04/2026')
	})

	it('honours a time zone', () => {
		expect(formatDate('2026-04-21T23:30:00Z', { dateStyle: 'short', timeZone: 'Europe/Amsterdam', locale: 'en-GB' })).toBe('22/04/2026')
	})

	it('answers the value itself when it is no date', () => {
		expect(formatDate('not a date', {})).toBe('not a date')
	})
})

describe('formatDuration', () => {
	it('formats days with literal text', () => {
		expect(formatDuration(21 * 86400, { pattern: "d' dagen'" })).toBe('21 dagen')
	})

	it('pads doubled tokens and carries the remainder into the largest unit', () => {
		expect(formatDuration(90061, { pattern: 'h:mm:ss' })).toBe('25:01:01')
		expect(formatDuration(90061, { pattern: "d'd' hh'h'" })).toBe('1d 01h')
	})

	it('keeps the sign of a negative duration', () => {
		expect(formatDuration(-3600, { pattern: 'h:mm' })).toBe('-1:00')
	})

	it('uses h:mm:ss without a pattern', () => {
		expect(formatDuration(61, {})).toBe('0:01:01')
	})
})

describe('formatCalculatedValue', () => {
	it('formats by the return type and leaves the value untouched', () => {
		const value = 145000
		const field = { returnType: 'number', formatOptions: { style: 'currency', currency: 'EUR', locale: 'nl-NL' } }

		expect(formatCalculatedValue(value, field)).toContain('145.000,00')
		expect(value).toBe(145000)
	})

	it('formats a date and a duration', () => {
		expect(formatCalculatedValue('2026-04-21T00:00:00Z', { returnType: 'date', formatOptions: { dateStyle: 'short', locale: 'en-GB' } })).toBe('21/04/2026')
		expect(formatCalculatedValue(172800, { returnType: 'duration', formatOptions: { pattern: "d' dagen'" } })).toBe('2 dagen')
	})

	it('shows null as empty and other values as text', () => {
		expect(formatCalculatedValue(null, { returnType: 'number' })).toBe('')
		expect(formatCalculatedValue(true, { returnType: 'boolean' })).toBe('true')
		expect(formatCalculatedValue('High Value', { returnType: 'string' })).toBe('High Value')
	})
})
