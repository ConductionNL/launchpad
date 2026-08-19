/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the tileSearch store (tile-quick-search
 * REQ-QSEARCH-002/003) — the single decision point for whether a tile
 * renders de-emphasised.
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useTileSearchStore } from '../tileSearch.js'

describe('tileSearch store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('dims nothing before a query runs', () => {
		const store = useTileSearchStore()
		expect(store.matchIds).toBe(null)
		expect(store.hasActiveQuery).toBe(false)
		expect(store.isDimmed(1)).toBe(false)
		expect(store.isDimmed('anything')).toBe(false)
	})

	it('dims every non-matching tile and leaves matches alone', () => {
		const store = useTileSearchStore()
		store.setMatches(['match'])
		expect(store.isDimmed('match')).toBe(false)
		expect(
			store.isDimmed('other'),
			'CONTROL: a non-matching tile must dim, or the assertion above is satisfied by "nothing is ever dimmed"',
		).toBe(true)
	})

	it('treats an EMPTY match set as "dim everything", not as "no query"', () => {
		const store = useTileSearchStore()
		store.setMatches([])
		expect(store.hasActiveQuery).toBe(true)
		expect(store.isDimmed('anything')).toBe(true)
	})

	it('undims everything when the query is cleared', () => {
		const store = useTileSearchStore()
		store.setMatches(['match'])
		expect(store.isDimmed('other')).toBe(true)

		store.clear()
		expect(store.hasActiveQuery).toBe(false)
		expect(store.isDimmed('other')).toBe(false)

		// `setMatches(null)` is the same instruction by another name.
		store.setMatches(['match'])
		store.setMatches(null)
		expect(store.isDimmed('other')).toBe(false)
	})

	/*
	 * launchpad#95 — A PLACEMENT ID IS AN INTEGER; A DOM ATTRIBUTE IS A STRING.
	 *
	 * The original defect compared `getAttribute('data-placement-id')` (always
	 * a string) against ids copied straight off the API row (integers, the
	 * column being an auto-increment primary key) using `Array.includes`,
	 * which compares with SameValueZero and does not coerce. `[7].includes('7')`
	 * is `false`, so EVERY tile dimmed on every query — including the matches
	 * the user was searching for. It survived a green suite because every
	 * fixture in the old spec seeded string ids (`'p1'`, `'match'`), so no test
	 * had ever exercised the type the product actually handles.
	 *
	 * Dimming is now decided here, from store ids on both sides, so the
	 * mismatch cannot arise at all. This test pins the normalisation anyway:
	 * it asserts the getter answers correctly whichever type a caller holds,
	 * which is what stops the bug returning if someone later feeds it a
	 * value of the other type.
	 */
	it('answers identically for INTEGER and STRING ids (launchpad#95)', () => {
		const store = useTileSearchStore()
		store.setMatches([7])

		expect(store.isDimmed(7), 'integer id, integer match').toBe(false)
		expect(store.isDimmed('7'), 'string id, integer match').toBe(false)
		expect(
			store.isDimmed(8),
			'CONTROL: a genuinely non-matching id must still dim',
		).toBe(true)

		// And the same with the match set supplied as strings.
		store.setMatches(['7'])
		expect(store.isDimmed(7)).toBe(false)
		expect(store.isDimmed('7')).toBe(false)
		expect(store.isDimmed(8)).toBe(true)
	})
})
