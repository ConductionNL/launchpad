/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Characterisation tests pinning `placeNewWidget`'s size fallbacks and
 * bottom-edge computation for malformed input, so it can be split into
 * named steps without changing behaviour.
 */

import { describe, expect, it } from 'vitest'
import { DEFAULT_H, DEFAULT_W, placeNewWidget } from '../useGridManager.js'

describe('placeNewWidget fallbacks', () => {
	it.each([
		[null, DEFAULT_W, DEFAULT_H],
		[undefined, DEFAULT_W, DEFAULT_H],
		[0, DEFAULT_W, DEFAULT_H],
		[{ w: 0, h: -1 }, DEFAULT_W, DEFAULT_H],
		[{ w: NaN, h: Infinity }, DEFAULT_W, DEFAULT_H],
		[{ w: '3', h: '2' }, DEFAULT_W, DEFAULT_H],
		[{ w: 0.5, h: 7 }, 0.5, 7],
	])('sizes %j', (spec, w, h) => {
		expect(placeNewWidget(spec, [])).toEqual({ x: 0, y: 0, w, h, pushed: [] })
	})

	it.each([
		[null, 0],
		['x', 0],
		[[{}], 1],
		[[{ gridY: 'a', gridHeight: 'b' }], 1],
		[[{ gridY: 3 }, { gridHeight: 5 }], 5],
		[[{ gridY: -4, gridHeight: 2 }], 0],
		[[{ gridY: 2, gridHeight: 0 }, { gridY: 1, gridHeight: 1 }], 2],
	])('appends below %j', (placements, y) => {
		expect(placeNewWidget({ w: 1, h: 1 }, placements)).toEqual({ x: 0, y, w: 1, h: 1, pushed: [] })
	})
})
