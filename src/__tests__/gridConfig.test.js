/**
 * SPDX-FileCopyrightText: 2024 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/* eslint-disable n/no-unpublished-import */
import { describe, it, expect } from 'vitest'
/* eslint-enable n/no-unpublished-import */
import {
	CELL_HEIGHT,
	GRID_MARGIN,
	GRID_COLUMNS,
	BREAKPOINTS,
	COLUMN_LAYOUT,
} from '../constants/gridConfig.js'

describe('gridConfig constants', () => {
	it('CELL_HEIGHT should be 60 pixels (REQ-GRID-012)', () => {
		expect(CELL_HEIGHT).toBe(60)
	})

	it('GRID_MARGIN should be 8 pixels (REQ-GRID-012)', () => {
		expect(GRID_MARGIN).toBe(8)
	})

	it('GRID_COLUMNS should be 12 (default)', () => {
		expect(GRID_COLUMNS).toBe(12)
	})

	it('COLUMN_LAYOUT should be moveScale for proportional rescaling', () => {
		expect(COLUMN_LAYOUT).toBe('moveScale')
	})

	describe('BREAKPOINTS structure and values', () => {
		it('should have exactly 4 entries', () => {
			expect(BREAKPOINTS).toHaveLength(4)
		})

		it('should have entries with correct structure {w, c}', () => {
			BREAKPOINTS.forEach(bp => {
				expect(bp).toHaveProperty('w')
				expect(bp).toHaveProperty('c')
				expect(typeof bp.w).toBe('number')
				expect(typeof bp.c).toBe('number')
			})
		})

		it('should have the documented breakpoint values per REQ-GRID-007', () => {
			expect(BREAKPOINTS[0]).toEqual({ w: 1400, c: 12 })
			expect(BREAKPOINTS[1]).toEqual({ w: 1100, c: 8 })
			expect(BREAKPOINTS[2]).toEqual({ w: 768, c: 4 })
			expect(BREAKPOINTS[3]).toEqual({ w: 480, c: 1 })
		})

		it('viewport widths should be monotonically descending', () => {
			for (let i = 1; i < BREAKPOINTS.length; i++) {
				expect(BREAKPOINTS[i - 1].w).toBeGreaterThan(BREAKPOINTS[i].w)
			}
		})

		it('column counts should be monotonically descending', () => {
			for (let i = 1; i < BREAKPOINTS.length; i++) {
				expect(BREAKPOINTS[i - 1].c).toBeGreaterThan(BREAKPOINTS[i].c)
			}
		})
	})

	describe('height math per REQ-GRID-012', () => {
		it('should calculate correct DOM height for 4-row widget at 60px cell / 8px margin', () => {
			const gridHeight = 4
			const domHeight = gridHeight * CELL_HEIGHT + (gridHeight - 1) * GRID_MARGIN
			// (4 * 60) + (3 * 8) = 240 + 24 = 264 px
			expect(domHeight).toBe(264)
		})

		it('should calculate correct DOM height for 1-row widget', () => {
			const gridHeight = 1
			const domHeight = gridHeight * CELL_HEIGHT + Math.max(0, gridHeight - 1) * GRID_MARGIN
			// (1 * 60) + (0 * 8) = 60 px
			expect(domHeight).toBe(60)
		})
	})
})
