/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `useGridManager.js` covering both halves of the
 * combined composable:
 *
 *   - REQ-GRID-007 (Responsive breakpoints): the BREAKPOINTS table has the
 *     four expected entries, monotonically descending column counts, and
 *     `getColumnOpts()` wires the `moveScale` layout + `breakpointForWindow`
 *     flag.
 *   - REQ-GRID-012 (Cell geometry constants): `CELL_HEIGHT === 60`,
 *     `GRID_MARGIN === 8`, and the height-math scenario.
 *   - syncCellHeightCssVar() writes the `--launchpad-cell-height` custom
 *     property on `:root` from the JS constant.
 *   - REQ-WDG-015..017 (Right-click context menu): edit-mode opens the
 *     popover, view-mode falls through, viewport clamping, swap-not-stack,
 *     outside-click closes, listener cleanup.
 *   - REQ-GRID-006 (Widget Auto-Layout) + REQ-GRID-014 (single placement
 *     authority): `placeNewWidget()` returns the auto-position slot when
 *     space exists, falls back to top-left + push-down when the top is
 *     full or the auto-position slot lands below the viewport, applies
 *     the 4×4 default size, and is the ONLY caller of `grid.addWidget(`
 *     in `src/` (architectural enforcement via grep test).
 *
 * These constants are the single source of truth referenced by
 * `DashboardGrid.vue` and `css/launchpad.css`; flipping any value here will
 * fail this spec and force an explicit downstream update.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { readFileSync, readdirSync, statSync } from 'fs'
import { join, relative } from 'path'
import {
	BREAKPOINTS,
	CELL_HEIGHT,
	CELL_HEIGHT_CSS_VAR,
	COLUMN_LAYOUT,
	DEFAULT_COLUMNS,
	DEFAULT_H,
	DEFAULT_W,
	GRID_MARGIN,
	getColumnOpts,
	placeNewWidget,
	syncCellHeightCssVar,
} from '../useGridManager.js'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

/**
 * Build a fake right-click event whose `preventDefault` we can spy on.
 *
 * @param {number} clientX cursor x coordinate
 * @param {number} clientY cursor y coordinate
 * @return {{clientX: number, clientY: number, preventDefault: () => void, target: object}}
 */
function makeEvent(clientX, clientY) {
	return {
		clientX,
		clientY,
		preventDefault: vi.fn(),
		target: { closest: () => null },
	}
}

describe('useGridManager — grid configuration', () => {
	describe('REQ-GRID-012 cell geometry constants', () => {
		it('CELL_HEIGHT is 60 px', () => {
			expect(CELL_HEIGHT).toBe(60)
		})

		it('GRID_MARGIN is 8 px', () => {
			expect(GRID_MARGIN).toBe(8)
		})

		it('DEFAULT_COLUMNS is 12', () => {
			expect(DEFAULT_COLUMNS).toBe(12)
		})

		it('height math: 4 rows + 3 inter-row margins = 264 px', () => {
			const rows = 4
			const innerMargins = rows - 1
			expect((rows * CELL_HEIGHT) + (innerMargins * GRID_MARGIN)).toBe(264)
		})
	})

	describe('REQ-GRID-007 responsive breakpoints', () => {
		it('BREAKPOINTS has exactly four entries', () => {
			expect(BREAKPOINTS).toHaveLength(4)
		})

		it('BREAKPOINTS entries match the spec table 1400/1100/768/480', () => {
			expect(BREAKPOINTS).toEqual([
				{ w: 1400, c: 12 },
				{ w: 1100, c: 8 },
				{ w: 768, c: 4 },
				{ w: 480, c: 1 },
			])
		})

		it('column counts descend monotonically as widths shrink', () => {
			for (let i = 1; i < BREAKPOINTS.length; i++) {
				expect(BREAKPOINTS[i].w).toBeLessThan(BREAKPOINTS[i - 1].w)
				expect(BREAKPOINTS[i].c).toBeLessThan(BREAKPOINTS[i - 1].c)
			}
		})

		it('BREAKPOINTS is frozen so callers cannot mutate the canonical table', () => {
			expect(Object.isFrozen(BREAKPOINTS)).toBe(true)
		})

		it('COLUMN_LAYOUT is "moveScale"', () => {
			expect(COLUMN_LAYOUT).toBe('moveScale')
		})

		it('getColumnOpts() returns a fresh deep copy of breakpoints + the moveScale layout + breakpointForWindow', () => {
			const opts = getColumnOpts()
			expect(opts.layout).toBe('moveScale')
			expect(opts.breakpointForWindow).toBe(true)
			expect(opts.breakpoints).toEqual([
				{ w: 1400, c: 12 },
				{ w: 1100, c: 8 },
				{ w: 768, c: 4 },
				{ w: 480, c: 1 },
			])
			opts.breakpoints[0].c = 99
			expect(BREAKPOINTS[0].c).toBe(12)
		})
	})

	describe('CSS custom-property sync', () => {
		it('syncCellHeightCssVar writes the cell height to documentElement', () => {
			document.documentElement.style.removeProperty(CELL_HEIGHT_CSS_VAR)
			syncCellHeightCssVar()
			const value = document.documentElement.style.getPropertyValue(CELL_HEIGHT_CSS_VAR)
			expect(value).toBe(`${CELL_HEIGHT}px`)
		})

		it('CELL_HEIGHT_CSS_VAR is the documented `--launchpad-cell-height` name', () => {
			expect(CELL_HEIGHT_CSS_VAR).toBe('--launchpad-cell-height')
		})
	})

	describe('REQ-GRID-014 placeNewWidget() — always appends at the bottom', () => {
		it('exports the documented default constants', () => {
			expect(DEFAULT_W).toBe(4)
			expect(DEFAULT_H).toBe(4)
		})

		it('appends below the only existing widget without moving it', () => {
			// Single existing widget at top-left occupying half the row.
			const placements = [
				{ id: 'a', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 4 },
			]
			const result = placeNewWidget({ w: 4, h: 4 }, placements, { gridColumns: 12 })
			// Free space remains at x=6,y=0, but we still drop the widget in a
			// fresh row below everything (bottom edge of `a` is y=4).
			expect(result).toMatchObject({ x: 0, y: 4, w: 4, h: 4, pushed: [] })
		})

		it('applies the 4×4 default size when the spec omits w/h', () => {
			const result = placeNewWidget({}, [], { gridColumns: 12 })
			expect(result).toMatchObject({ x: 0, y: 0, w: 4, h: 4, pushed: [] })
		})

		it('honours an explicit smaller size (e.g. tiles default to 2×2)', () => {
			const result = placeNewWidget({ w: 2, h: 2 }, [], { gridColumns: 12 })
			expect(result).toMatchObject({ w: 2, h: 2 })
		})

		it('places below the lowest widget and never pushes existing widgets', () => {
			// A full top region plus a widget that already lives lower down.
			const placements = [
				{ id: 'a', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 4 },
				{ id: 'b', gridX: 4, gridY: 0, gridWidth: 4, gridHeight: 4 },
				{ id: 'c', gridX: 8, gridY: 0, gridWidth: 4, gridHeight: 4 },
				{ id: 'd', gridX: 0, gridY: 6, gridWidth: 4, gridHeight: 2 },
			]
			const result = placeNewWidget({ w: 4, h: 4 }, placements, { gridColumns: 12 })
			// Lowest bottom edge is `d` at gridY 6 + gridHeight 2 = 8.
			expect(result).toMatchObject({ x: 0, y: 8, w: 4, h: 4, pushed: [] })
		})

		it('uses the bottom edge of the lowest widget even when it is not the last in the array', () => {
			const placements = [
				{ id: 'low', gridX: 0, gridY: 5, gridWidth: 6, gridHeight: 3 }, // bottom edge = 8
				{ id: 'high', gridX: 6, gridY: 0, gridWidth: 6, gridHeight: 2 }, // bottom edge = 2
			]
			const result = placeNewWidget({ w: 6, h: 3 }, placements, { gridColumns: 12 })
			expect(result).toMatchObject({ x: 0, y: 8, pushed: [] })
		})

		it('handles an empty grid by returning (0, 0)', () => {
			const result = placeNewWidget({ w: 4, h: 4 }, [], { gridColumns: 12 })
			expect(result).toMatchObject({ x: 0, y: 0, w: 4, h: 4, pushed: [] })
		})

		it('tolerates placements with missing grid fields (treats them as 1-row at y=0)', () => {
			const placements = [
				{ id: 'a' }, // no coords → contributes bottom edge 1
			]
			const result = placeNewWidget({ w: 4, h: 4 }, placements, { gridColumns: 12 })
			expect(result).toMatchObject({ x: 0, y: 1, pushed: [] })
		})

		it('does not mutate the input placements array', () => {
			const placements = [
				{ id: 'a', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 4 },
			]
			const snapshot = JSON.stringify(placements)
			placeNewWidget({ w: 4, h: 4 }, placements, { gridColumns: 12 })
			expect(JSON.stringify(placements)).toBe(snapshot)
		})
	})

	describe('REQ-GRID-014 architectural enforcement (grep guard)', () => {
		// Recursively walk a directory and return absolute paths of all
		// `.js` and `.vue` files. Skips `node_modules` and `__tests__`
		// (the test file itself documents the rule and references the
		// forbidden pattern in comments).
		function walkSrc(dir, acc = []) {
			for (const entry of readdirSync(dir)) {
				const full = join(dir, entry)
				const st = statSync(full)
				if (st.isDirectory()) {
					if (entry === 'node_modules') continue
					walkSrc(full, acc)
				} else if (st.isFile() && (entry.endsWith('.js') || entry.endsWith('.vue'))) {
					acc.push(full)
				}
			}
			return acc
		}

		it('only `useGridManager.js` (and its test) reference `grid.addWidget(`', () => {
			// Pattern: literal `grid.addWidget(`. Captures the canonical
			// GridStack API call across `.js` and `.vue` files. Variants
			// like `gridInstance.addWidget(` would slip past — that's
			// intentional, the rule polices the canonical name and code
			// review handles aliases.
			const PATTERN = /\bgrid\.addWidget\s*\(/
			const srcDir = join(__dirname, '..', '..')
			const files = walkSrc(srcDir)
			const offenders = []
			for (const file of files) {
				const rel = relative(srcDir, file).replace(/\\/g, '/')
				// Skip the helper file itself and this test file.
				if (rel === 'composables/useGridManager.js') continue
				if (rel === 'composables/__tests__/useGridManager.spec.js') continue
				const text = readFileSync(file, 'utf8')
				if (PATTERN.test(text)) {
					offenders.push(rel)
				}
			}
			expect(offenders).toEqual([])
		})
	})
})

describe('useGridManager — context menu', () => {
	it('REQ-WDG-015: edit mode right-click opens popover at cursor position', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const grid = useGridManager({
			canEdit,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
		})
		const event = makeEvent(300, 400)
		const widget = { id: 7 }
		grid.onWidgetRightClick(event, widget)
		expect(event.preventDefault).toHaveBeenCalledOnce()
		expect(grid.state.contextMenuOpen).toBe(true)
		expect(grid.state.contextMenuPosition).toEqual({ x: 300, y: 400 })
		expect(grid.state.selectedWidget).toBe(widget)
	})

	it('REQ-WDG-015: view mode right-click does not open popover and does not preventDefault', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: false }
		const grid = useGridManager({
			canEdit,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
		})
		const event = makeEvent(300, 400)
		grid.onWidgetRightClick(event, { id: 7 })
		expect(event.preventDefault).not.toHaveBeenCalled()
		expect(grid.state.contextMenuOpen).toBe(false)
		expect(grid.state.selectedWidget).toBeNull()
	})

	it('REQ-WDG-017: clamps left when popover would overflow right edge', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const grid = useGridManager({
			canEdit,
			viewport: { innerWidth: 800, innerHeight: 600 },
			menuWidth: 150,
			menuHeight: 100,
		})
		grid.onWidgetRightClick(makeEvent(750, 200), { id: 1 })
		expect(grid.state.contextMenuPosition.x).toBe(650)
		expect(grid.state.contextMenuPosition.x + 150).toBeLessThanOrEqual(800)
	})

	it('REQ-WDG-017: clamps top when popover would overflow bottom edge', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const grid = useGridManager({
			canEdit,
			viewport: { innerWidth: 800, innerHeight: 600 },
			menuWidth: 150,
			menuHeight: 100,
		})
		grid.onWidgetRightClick(makeEvent(400, 580), { id: 1 })
		expect(grid.state.contextMenuPosition.y).toBe(500)
		expect(grid.state.contextMenuPosition.y + 100).toBeLessThanOrEqual(600)
	})

	it('REQ-WDG-017: leaves coordinates untouched when popover fits', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const grid = useGridManager({
			canEdit,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
			menuWidth: 150,
			menuHeight: 132,
		})
		grid.onWidgetRightClick(makeEvent(300, 400), { id: 1 })
		expect(grid.state.contextMenuPosition).toEqual({ x: 300, y: 400 })
	})

	it('REQ-WDG-016: right-clicking a different widget swaps popover position (no stacking)', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const grid = useGridManager({
			canEdit,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
		})
		grid.onWidgetRightClick(makeEvent(100, 100), { id: 'a' })
		expect(grid.state.selectedWidget.id).toBe('a')
		expect(grid.state.contextMenuPosition).toEqual({ x: 100, y: 100 })
		grid.onWidgetRightClick(makeEvent(500, 500), { id: 'b' })
		expect(grid.state.selectedWidget.id).toBe('b')
		expect(grid.state.contextMenuPosition).toEqual({ x: 500, y: 500 })
		expect(grid.state.contextMenuOpen).toBe(true)
	})

	it('REQ-WDG-016: closeContextMenu clears state', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const grid = useGridManager({
			canEdit,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
		})
		grid.onWidgetRightClick(makeEvent(100, 100), { id: 'a' })
		grid.closeContextMenu()
		expect(grid.state.contextMenuOpen).toBe(false)
		expect(grid.state.selectedWidget).toBeNull()
	})

	it('REQ-WDG-016: outside click via document listener closes popover', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const grid = useGridManager({
			canEdit,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
		})
		grid.attach()
		grid.onWidgetRightClick(makeEvent(100, 100), { id: 'a' })
		expect(grid.state.contextMenuOpen).toBe(true)

		const outsideTarget = document.createElement('div')
		document.body.appendChild(outsideTarget)
		const evt = new MouseEvent('click', { bubbles: true })
		Object.defineProperty(evt, 'target', { value: outsideTarget })
		document.dispatchEvent(evt)

		expect(grid.state.contextMenuOpen).toBe(false)
		grid.detach()
		outsideTarget.remove()
	})

	it('REQ-WDG-016: click inside .widget-context-menu does NOT close popover', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const grid = useGridManager({
			canEdit,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
		})
		grid.attach()
		grid.onWidgetRightClick(makeEvent(100, 100), { id: 'a' })

		const wrapper = document.createElement('div')
		wrapper.className = 'widget-context-menu'
		const inner = document.createElement('button')
		wrapper.appendChild(inner)
		document.body.appendChild(wrapper)

		const evt = new MouseEvent('click', { bubbles: true })
		Object.defineProperty(evt, 'target', { value: inner })
		document.dispatchEvent(evt)

		expect(grid.state.contextMenuOpen).toBe(true)
		grid.detach()
		wrapper.remove()
	})

	it('REQ-WDG-016: detach removes the document click listener (no leaks across mounts)', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const grid = useGridManager({
			canEdit,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
		})
		const removeSpy = vi.spyOn(document, 'removeEventListener')
		grid.attach()
		grid.detach()
		const removeCalls = removeSpy.mock.calls.filter((c) => c[0] === 'click')
		expect(removeCalls.length).toBeGreaterThan(0)
		expect(grid.state.contextMenuOpen).toBe(false)
		removeSpy.mockRestore()
	})

	it('REQ-WDG-015 edit: triggerEdit forwards selected widget to onEdit and closes popover', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const onEdit = vi.fn()
		const grid = useGridManager({
			canEdit,
			onEdit,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
		})
		const widget = { id: 'edit-me' }
		grid.onWidgetRightClick(makeEvent(100, 100), widget)
		grid.triggerEdit()
		expect(onEdit).toHaveBeenCalledWith(widget)
		expect(grid.state.contextMenuOpen).toBe(false)
	})

	it('REQ-WDG-015 remove: triggerRemove forwards selected widget to onRemove and closes popover', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const onRemove = vi.fn()
		const grid = useGridManager({
			canEdit,
			onRemove,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
		})
		const widget = { id: 'kill-me' }
		grid.onWidgetRightClick(makeEvent(100, 100), widget)
		grid.triggerRemove()
		expect(onRemove).toHaveBeenCalledWith(widget)
		expect(grid.state.contextMenuOpen).toBe(false)
	})

	it('REQ-WDG-015 cancel: closeContextMenu fires no API callback', async () => {
		const { useGridManager } = await import('../useGridManager.js')
		const canEdit = { value: true }
		const onEdit = vi.fn()
		const onRemove = vi.fn()
		const grid = useGridManager({
			canEdit,
			onEdit,
			onRemove,
			viewport: { innerWidth: 1920, innerHeight: 1080 },
		})
		grid.onWidgetRightClick(makeEvent(100, 100), { id: 'x' })
		grid.closeContextMenu()
		expect(onEdit).not.toHaveBeenCalled()
		expect(onRemove).not.toHaveBeenCalled()
	})
})
