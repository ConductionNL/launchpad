// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The text colour of an App or link tile and its contrast check
 * (app-link-tile-widget REQ-APPTILE-005).
 */

import { describe, expect, it } from 'vitest'
import {
	contrastRatio,
	DEFAULT_TEXT_COLOUR,
	isTextColour,
	parseColour,
	resolveColour,
	TEXT_COLOURS,
	textColourValue,
	textContrast,
	tileTextColour,
} from '../tileTextColour.js'

describe('text colour', () => {
	it('offers the eight tile tokens, white and black, white by default', () => {
		expect(TEXT_COLOURS).toHaveLength(10)
		expect(TEXT_COLOURS.slice(-2)).toEqual(['white', 'black'])
		expect(DEFAULT_TEXT_COLOUR).toBe('white')
	})

	it('reads the stored text colour and falls back to white', () => {
		expect(tileTextColour({ textColour: '--sc-primary-deep' })).toBe('--sc-primary-deep')
		expect(tileTextColour({ textColour: 'black' })).toBe('black')
		expect(tileTextColour({ textColour: 'red' })).toBe('white')
		expect(tileTextColour({})).toBe('white')
		expect(tileTextColour(null)).toBe('white')
		expect(isTextColour('--sc-accent')).toBe(true)
		expect(isTextColour('#fff')).toBe(false)
	})

	it('draws a token through the theme and white and black as they are', () => {
		expect(textColourValue('--sc-primary-deep')).toBe('var(--sc-primary-deep, #234a78)')
		expect(textColourValue('--sc-new')).toBe('var(--sc-new, #ffffff)')
		expect(textColourValue('black')).toBe('#000000')
		expect(textColourValue('white')).toBe('#ffffff')
		expect(textColourValue('nonsense')).toBe('#ffffff')
	})
})

describe('contrast', () => {
	it('parses hex and rgb colours', () => {
		expect(parseColour('#fff')).toEqual([255, 255, 255])
		expect(parseColour('#0082C9')).toEqual([0, 130, 201])
		expect(parseColour('rgb(1, 2, 3)')).toEqual([1, 2, 3])
		expect(parseColour('rgba(10 20 30 / 50%)')).toEqual([10, 20, 30])
		expect(parseColour('var(--x)')).toBeNull()
	})

	it('computes the WCAG ratio either way round', () => {
		expect(contrastRatio('#ffffff', '#000000')).toBeCloseTo(21, 5)
		expect(contrastRatio('#000000', '#ffffff')).toBeCloseTo(21, 5)
		expect(contrastRatio('#3669a5', '#ffffff')).toBeCloseTo(5.65, 1)
		expect(contrastRatio('nope', '#fff')).toBeNull()
	})

	it('passes white on primary and warns for black on a dark tile', () => {
		const resolve = (name) => resolveColour(name, null)
		expect(textContrast({ colourToken: '--sc-primary' }, resolve)).toEqual({ ratio: 5.6, passes: true })
		const dark = textContrast({ colourToken: '--sc-work-text', textColour: 'black' }, resolve)
		expect(dark.passes).toBe(false)
		expect(dark.ratio).toBeLessThan(4.5)
	})

	it('puts the threshold at 4.5:1', () => {
		// #767676 on white is 4.54:1, #777777 is 4.48:1.
		expect(textContrast({ backgroundColor: '#ffffff', textColour: '#767676' }, (n) => n === 'white' ? '#767676' : n).passes).toBe(true)
		expect(textContrast({ backgroundColor: '#ffffff' }, (n) => n === 'white' ? '#777777' : n).passes).toBe(false)
	})

	it('reads a token from the current theme', () => {
		document.documentElement.style.setProperty('--sc-primary', '#000000')
		expect(resolveColour('--sc-primary')).toBe('#000000')
		document.documentElement.style.removeProperty('--sc-primary')
		expect(resolveColour('--sc-work-ink', document.createElement('div'))).toBe('#1b1c1d')
		expect(resolveColour('#123456')).toBe('#123456')
	})

	it('uses an own hex tile colour', () => {
		expect(textContrast({ backgroundColor: '#ffffff' }).passes).toBe(false)
		expect(textContrast({ backgroundColor: '#0082C9' }).passes).toBe(false)
		expect(textContrast({ backgroundColor: 'var(--x)' })).toEqual({ ratio: null, passes: true })
	})
})
