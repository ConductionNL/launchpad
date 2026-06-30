/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect } from 'vitest'
import * as mdi from '@mdi/js'
import { ICON_CATALOGUE, normaliseIconValue } from '../iconCatalogue.js'

describe('ICON_CATALOGUE', () => {
	it('is a frozen, non-empty array', () => {
		expect(Array.isArray(ICON_CATALOGUE)).toBe(true)
		expect(ICON_CATALOGUE.length).toBeGreaterThan(0)
		expect(Object.isFrozen(ICON_CATALOGUE)).toBe(true)
	})

	it('produces entries shaped { key, label, value, search, path }', () => {
		for (const entry of ICON_CATALOGUE) {
			expect(entry).toMatchObject({
				key: expect.any(String),
				label: expect.any(String),
				value: expect.any(String),
				search: expect.any(String),
				path: expect.any(String),
			})
			// Keys are MDI export names; value/path are the SVG path the picker
			// indexes by, and search is the lower-cased key.
			expect(entry.key.startsWith('mdi')).toBe(true)
			expect(entry.value).toBe(entry.path)
			expect(entry.search).toBe(entry.key.toLowerCase())
		}
	})
})

describe('normaliseIconValue', () => {
	it('maps a lowercase legacy name to its MDI path', () => {
		expect(normaliseIconValue('star')).toBe(mdi.mdiStar)
		expect(normaliseIconValue('folder')).toBe(mdi.mdiFolder)
		expect(normaliseIconValue('home')).toBe(mdi.mdiHome)
	})

	it('maps separated multi-word names (space, dash, underscore)', () => {
		expect(normaliseIconValue('chart-bar')).toBe(mdi.mdiChartBar)
		expect(normaliseIconValue('account group')).toBe(mdi.mdiAccountGroup)
		expect(normaliseIconValue('chart_bar')).toBe(mdi.mdiChartBar)
	})

	it('accepts names already in PascalCase', () => {
		expect(normaliseIconValue('Star')).toBe(mdi.mdiStar)
		expect(normaliseIconValue('ChartBar')).toBe(mdi.mdiChartBar)
	})

	it('leaves an SVG path string untouched', () => {
		expect(normaliseIconValue(mdi.mdiStar)).toBe(mdi.mdiStar)
	})

	it('leaves custom URLs untouched', () => {
		expect(normaliseIconValue('/apps/launchpad/resource/42.svg'))
			.toBe('/apps/launchpad/resource/42.svg')
		expect(normaliseIconValue('https://example.com/icon.png'))
			.toBe('https://example.com/icon.png')
	})

	it('returns an unknown bare name unchanged (falls back to default downstream)', () => {
		expect(normaliseIconValue('totallynotanicon')).toBe('totallynotanicon')
	})

	it('passes through empty and non-string values', () => {
		expect(normaliseIconValue('')).toBe('')
		expect(normaliseIconValue(null)).toBe(null)
		expect(normaliseIconValue(undefined)).toBe(undefined)
	})
})
