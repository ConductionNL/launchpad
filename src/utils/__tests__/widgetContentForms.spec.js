/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The pure helpers behind the Live data, Image and Calendar content forms
 * (widget-content-forms-board-sync: REQ-LIVETILE-020, REQ-IMG-020, REQ-CAL-020).
 */

import { describe, expect, it } from 'vitest'
import { contentForPeriod, isIcsAddress, periodOf } from '../calendarPeriod.js'
import { describeImageFile, formatBytes, isUploadableImage, MAX_IMAGE_BYTES } from '../imageFile.js'
import {
	knownColour,
	previewValue,
	refreshLabel,
	refreshOptions,
	suffixToUnit,
	unitToSuffix,
} from '../liveTilePreview.js'

describe('live tile unit', () => {
	it('puts a space before a word and none before a symbol', () => {
		expect(unitToSuffix('minutes')).toBe(' minutes')
		expect(unitToSuffix('  minuten ')).toBe(' minuten')
		expect(unitToSuffix('%')).toBe('%')
		expect(unitToSuffix('')).toBe('')
		expect(unitToSuffix(null)).toBe('')
	})

	it('shows the stored suffix without its space', () => {
		expect(suffixToUnit(' minutes')).toBe('minutes')
		expect(suffixToUnit(undefined)).toBe('')
	})
})

describe('live tile refresh', () => {
	it('names whole minutes from five up in minutes, the rest in seconds', () => {
		expect(refreshLabel(300)).toBe('Every 5 minutes')
		expect(refreshLabel(300, true)).toBe('Refreshes every 5 minutes')
		expect(refreshLabel(90)).toBe('Every 90 seconds')
		expect(refreshLabel(30, true)).toBe('Refreshes every 30 seconds')
	})

	it('offers the four choices and keeps a stored interval that is not one of them', () => {
		expect(refreshOptions(300).map((o) => o.value)).toEqual([300, 900, 1800, 3600])
		expect(refreshOptions(45).map((o) => o.value)).toEqual([45, 300, 900, 1800, 3600])
		expect(refreshOptions(0).map((o) => o.value)).toEqual([300, 900, 1800, 3600])
	})
})

describe('live tile colour and preview', () => {
	it('accepts the theme colours only', () => {
		expect(knownColour('primary')).toBe('primary')
		expect(knownColour('#ff0000')).toBe('')
		expect(knownColour(undefined)).toBe('')
	})

	it('shows the reading with prefix and unit, a dash without one', () => {
		const format = { prefix: '', suffix: ' minutes', thousands: false }
		expect(previewValue({ value: 12 }, format)).toBe('12 minutes')
		expect(previewValue(null, format)).toBe('— minutes')
		expect(previewValue({ value: 'high' }, { prefix: '> ', suffix: '', thousands: false })).toBe('> high')
		expect(previewValue({ value: 1234567 }, { prefix: '', suffix: '', thousands: true })).toBe((1234567).toLocaleString())
	})
})

describe('image file', () => {
	it('takes images up to 5 MB', () => {
		expect(isUploadableImage({ type: 'image/png', size: 1000 })).toBeNull()
		expect(isUploadableImage({ type: 'image/png', size: MAX_IMAGE_BYTES })).toBeNull()
		expect(isUploadableImage({ type: 'image/png', size: MAX_IMAGE_BYTES + 1 })).toBe('size')
		expect(isUploadableImage({ type: 'application/pdf', size: 10 })).toBe('type')
		expect(isUploadableImage(null)).toBe('type')
	})

	it('describes size and dimensions, leaving out what is not known', () => {
		expect(formatBytes(512)).toBe('512 B')
		expect(formatBytes(820 * 1024)).toBe('820 KB')
		expect(formatBytes(1.5 * 1024 * 1024)).toBe('1.5 MB')
		expect(describeImageFile({ size: 1.5 * 1024 * 1024, width: 1600, height: 900 })).toBe('1.5 MB · 1600 × 900')
		expect(describeImageFile({ size: null, width: null, height: null })).toBe('')
		expect(describeImageFile(null)).toBe('')
	})
})

describe('calendar period', () => {
	it('maps 7 days to the agenda over seven days and Month to the month view', () => {
		expect(contentForPeriod('week')).toEqual({ viewMode: 'agenda', daysAhead: 7 })
		expect(contentForPeriod('month')).toEqual({ viewMode: 'month', daysAhead: 31 })
		expect(periodOf({ viewMode: 'month' })).toBe('month')
		expect(periodOf({ viewMode: 'week' })).toBe('week')
		expect(periodOf(null)).toBe('week')
	})

	it('accepts https ICS addresses only', () => {
		expect(isIcsAddress('https://example.com/a.ics')).toBe(true)
		expect(isIcsAddress('http://example.com/a.ics')).toBe(false)
		expect(isIcsAddress('webcal://example.com')).toBe(false)
		expect(isIcsAddress(42)).toBe(false)
	})
})
