/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `tileSort.js` (launcher-tile-sorting): every sort
 * mode with its tie rules, the once-per-load shuffle, the row-by-row reflow,
 * and the browser-only usage counts with storage available and blocked.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
	__resetTileSortForTest,
	childTitle,
	forgetTileUse,
	normaliseSortMode,
	readTileUse,
	recordTileUse,
	reflowChildren,
	sortChildren,
	TILE_USE_KEY,
} from '../tileSort.js'

const tile = (id, title, w = 1, h = 1) => ({ id, type: 'link', content: { title }, gridX: 0, gridY: 0, gridWidth: w, gridHeight: h })
const titles = (list) => list.map(childTitle)

// "Zaaksysteem", "Agenda" and "Mail", placed in that order.
const applicaties = () => [tile(1, 'Zaaksysteem'), tile(2, 'Agenda'), tile(3, 'Mail')]

beforeEach(() => {
	localStorage.clear()
	__resetTileSortForTest()
})

afterEach(() => {
	vi.restoreAllMocks()
})

describe('sortChildren', () => {
	it('keeps the stored order by hand, and for an unknown mode', () => {
		expect(titles(sortChildren(applicaties(), 'manual'))).toEqual(['Zaaksysteem', 'Agenda', 'Mail'])
		expect(titles(sortChildren(applicaties(), 'by-colour'))).toEqual(['Zaaksysteem', 'Agenda', 'Mail'])
		expect(normaliseSortMode('by-colour')).toBe('manual')
		expect(normaliseSortMode('most-used')).toBe('most-used')
	})

	it('sorts alphabetically in the viewer\'s locale, case and accents aside, numbers numerically', () => {
		expect(titles(sortChildren(applicaties(), 'alphabetical', { locale: 'nl' }))).toEqual(['Agenda', 'Mail', 'Zaaksysteem'])
		const mixed = [tile(1, 'zaak 10'), tile(2, 'Émail'), tile(3, 'zaak 9'), tile(4, 'agenda')]
		expect(titles(sortChildren(mixed, 'alphabetical', { locale: 'nl' }))).toEqual(['agenda', 'Émail', 'zaak 9', 'zaak 10'])
	})

	it('puts the most used first, equal counts and unused tiles alphabetically', () => {
		const use = { 1: { count: 3, lastUsedAt: 10 }, 3: { count: 1, lastUsedAt: 50 } }
		expect(titles(sortChildren(applicaties(), 'most-used', { use, locale: 'nl' }))).toEqual(['Zaaksysteem', 'Mail', 'Agenda'])
		expect(titles(sortChildren(applicaties(), 'most-used', { use: {}, locale: 'nl' }))).toEqual(['Agenda', 'Mail', 'Zaaksysteem'])
	})

	it('puts the last used first', () => {
		const use = { 1: { count: 3, lastUsedAt: 10 }, 3: { count: 1, lastUsedAt: 50 } }
		expect(titles(sortChildren(applicaties(), 'last-used', { use, locale: 'nl' }))).toEqual(['Mail', 'Zaaksysteem', 'Agenda'])
	})

	it('shuffles once per page load and keeps that order', () => {
		const draws = [0.9, 0.1, 0.5, 0.3, 0.7, 0.2]
		vi.spyOn(Math, 'random').mockImplementation(() => draws.shift())

		const first = titles(sortChildren(applicaties(), 'random'))
		expect(first).toEqual(['Agenda', 'Mail', 'Zaaksysteem'])
		expect(titles(sortChildren(applicaties(), 'random'))).toEqual(first)

		__resetTileSortForTest()
		expect(titles(sortChildren(applicaties(), 'random'))).toEqual(['Mail', 'Zaaksysteem', 'Agenda'])
	})

	it('never changes the array it is given', () => {
		const children = applicaties()
		sortChildren(children, 'alphabetical')
		expect(titles(children)).toEqual(['Zaaksysteem', 'Agenda', 'Mail'])
	})
})

describe('childTitle', () => {
	it('reads the title, a label, or a tile title, in that order', () => {
		expect(childTitle({ content: { title: ' Mail ' } })).toBe('Mail')
		expect(childTitle({ content: { label: 'Agenda' } })).toBe('Agenda')
		expect(childTitle({ tileTitle: 'Zaaksysteem' })).toBe('Zaaksysteem')
		expect(childTitle({})).toBe('')
	})
})

describe('reflowChildren', () => {
	it('fills rows from the top left, keeping each size and wrapping when a tile does not fit', () => {
		const placed = reflowChildren([tile(1, 'a', 2, 1), tile(2, 'b', 1, 2), tile(3, 'c', 2, 1), tile(4, 'd', 4, 1)], 4)
		expect(placed.map((c) => [c.gridX, c.gridY, c.gridWidth, c.gridHeight])).toEqual([
			[0, 0, 2, 1],
			[2, 0, 1, 2],
			[0, 2, 2, 1],
			[0, 3, 4, 1],
		])
	})

	it('treats a missing size as two by two, and caps a width at the grid', () => {
		const placed = reflowChildren([{ id: 1 }, { id: 2, gridWidth: 9 }], 4)
		expect(placed.map((c) => [c.gridX, c.gridY])).toEqual([[0, 0], [0, 2]])
	})
})

describe('usage in this browser', () => {
	it('counts uses and remembers the last one', () => {
		recordTileUse(7, 1000)
		recordTileUse(7, 2000)
		recordTileUse('9', 1500)
		expect(readTileUse()).toEqual({ 7: { count: 2, lastUsedAt: 2000 }, 9: { count: 1, lastUsedAt: 1500 } })
		recordTileUse(null)
		expect(Object.keys(readTileUse())).toEqual(['7', '9'])
	})

	it('forgets the given placements only', () => {
		recordTileUse(7, 1000)
		recordTileUse(9, 1000)
		forgetTileUse([7, 'nope'])
		expect(Object.keys(readTileUse())).toEqual(['9'])
	})

	it('reads nothing from unusable storage contents', () => {
		localStorage.setItem(TILE_USE_KEY, '{broken')
		expect(readTileUse()).toEqual({})
		localStorage.setItem(TILE_USE_KEY, '[1,2]')
		expect(readTileUse()).toEqual({})
	})

	it('does not throw when storage is blocked', () => {
		vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
			throw new Error('SecurityError')
		})
		vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
			throw new Error('QuotaExceededError')
		})

		expect(() => recordTileUse(7)).not.toThrow()
		expect(() => forgetTileUse([7])).not.toThrow()
		expect(readTileUse()).toEqual({})
	})
})
