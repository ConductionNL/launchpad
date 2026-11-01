/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for nudgePlacement(): every action against a set of placements,
 * neighbours and column counts, compared with
 * fixtures/nudgePlacement.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { nudgePlacement } from '../useGridManager.js'

const FIXTURE = path.resolve(__dirname, 'fixtures/nudgePlacement.golden.json')

const ACTIONS = [
	'move-up',
	'move-down',
	'move-left',
	'move-right',
	'grow-width',
	'shrink-width',
	'grow-height',
	'shrink-height',
	'unknown',
]

const PLACEMENTS = {
	'top left': { id: 1, gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2 },
	'right edge': { id: 1, gridX: 10, gridY: 3, gridWidth: 2, gridHeight: 3 },
	'too wide': { id: 1, gridX: 8, gridY: 1, gridWidth: 6, gridHeight: 2 },
	'missing fields': { id: 1 },
	'string fields': {
		id: 1,
		gridX: '2',
		gridY: '1',
		gridWidth: '3',
		gridHeight: '3',
	},
	'no placement': undefined,
}

const NEIGHBOURS = {
	none: [],
	'not an array': 'nope',
	mixed: [
		null,
		undefined,
		{ id: 1, gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 12 },
		{ id: 2, gridX: 0, gridY: 2, gridWidth: 2, gridHeight: 2 },
		{ id: 3, gridX: 2, gridY: 0, gridWidth: 1, gridHeight: 1 },
		{ id: 4, gridX: 9, gridY: 4, gridWidth: 3, gridHeight: 2 },
		{ id: 5 },
		{ id: 6, gridX: 'x', gridY: 3, gridWidth: Infinity, gridHeight: 2 },
		{ id: 7, gridX: 11, gridY: 6, gridWidth: 1, gridHeight: 1 },
		{ gridX: 1, gridY: 1, gridWidth: 1, gridHeight: 1 },
		{ id: 8, gridX: 0, gridY: 1, gridWidth: 2, gridHeight: NaN },
		{ id: 9, gridX: 0, gridY: 5, gridWidth: '2', gridHeight: '9' },
	],
}

const OPTIONS = {
	default: undefined,
	'six columns': { gridColumns: 6 },
	'zero columns': { gridColumns: 0 },
}

describe('nudgePlacement (golden)', () => {
	it('matches the recorded results', () => {
		const actual = {}
		for (const [p, placement] of Object.entries(PLACEMENTS)) {
			for (const [n, neighbours] of Object.entries(NEIGHBOURS)) {
				for (const [o, options] of Object.entries(OPTIONS)) {
					for (const action of ACTIONS) {
						actual[`${p} | ${n} | ${o} | ${action}`] = nudgePlacement(
							placement,
							action,
							neighbours,
							options,
						)
					}
				}
			}
		}
		if (process.env.RECORD_GOLDEN === '1') {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		expect(actual).toEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')))
	})
})
