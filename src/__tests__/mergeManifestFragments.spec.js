/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ADR-037: verifies that disjoint manifest fragments union cleanly so concurrent
 * OpenSpec change builds never collide on the shared src/manifest.json.
 */

import { describe, expect, it } from 'vitest'
import { applyManifestFragments } from '../utils/mergeManifestFragments.js'

describe('applyManifestFragments (ADR-037)', () => {
	it('concatenates pages and menu from disjoint fragments in order', () => {
		const base = { version: '1.0.0', pages: [], menu: [] }
		const merged = applyManifestFragments(base, [
			{ pages: [{ id: 'alpha' }], menu: [{ id: 'm-alpha' }] },
			{ pages: [{ id: 'beta' }], menu: [{ id: 'm-beta' }] },
		])

		expect(merged.pages.map((p) => p.id)).toEqual(['alpha', 'beta'])
		expect(merged.menu.map((m) => m.id)).toEqual(['m-alpha', 'm-beta'])
	})

	it('preserves base pages/menu and appends fragment entries', () => {
		const base = { pages: [{ id: 'home' }], menu: [{ id: 'm-home' }] }
		const merged = applyManifestFragments(base, [{ pages: [{ id: 'extra' }] }])

		expect(merged.pages.map((p) => p.id)).toEqual(['home', 'extra'])
		expect(merged.menu.map((m) => m.id)).toEqual(['m-home'])
	})

	it('does not mutate the base manifest', () => {
		const base = { pages: [], menu: [] }
		applyManifestFragments(base, [{ pages: [{ id: 'x' }], menu: [{ id: 'y' }] }])

		expect(base.pages).toEqual([])
		expect(base.menu).toEqual([])
	})

	it('is a no-op for an empty / placeholder fragment set', () => {
		const base = {
			version: '1.0.0',
			pages: [],
			menu: [],
			runtime: { loadFrom: 'x' },
		}
		const merged = applyManifestFragments(base, [{ pages: [], menu: [] }])

		expect(merged.pages).toEqual([])
		expect(merged.menu).toEqual([])
		expect(merged.runtime).toEqual({ loadFrom: 'x' })
	})

	it('ignores fragments without pages/menu arrays', () => {
		const base = { pages: [{ id: 'home' }], menu: [] }
		const merged = applyManifestFragments(base, [
			{},
			{ pages: 'not-an-array' },
			null,
		])

		expect(merged.pages.map((p) => p.id)).toEqual(['home'])
		expect(merged.menu).toEqual([])
	})
})
