/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * useGridManager — combined GridStack configuration + Vue 2 composable.
 *
 * Three responsibilities live in this single module:
 *
 * 1. Grid configuration constants and helpers (REQ-GRID-007 responsive
 *    breakpoints, REQ-GRID-012 cell geometry, REQ-GRID-013 GridStack v12 pin):
 *    - `CELL_HEIGHT`, `GRID_MARGIN`, `DEFAULT_COLUMNS`, `BREAKPOINTS`,
 *      `COLUMN_LAYOUT`, `CELL_HEIGHT_CSS_VAR`
 *    - `getColumnOpts()` — returns the `columnOpts` bag for `GridStack.init`
 *    - `syncCellHeightCssVar()` — mirrors `CELL_HEIGHT` into a CSS variable
 *
 * 2. Add-widget placement helper (REQ-GRID-006 widget auto-layout +
 *    REQ-GRID-014 single placement authority):
 *    - `placeNewWidget(spec, placements, options?)` — primary auto-position
 *      with top-left + push-down fallback. Single legal caller of
 *      `grid.addWidget(...)` in the codebase (enforced by grep test).
 *    - `DEFAULT_W`, `DEFAULT_H`, `DEFAULT_VIEWPORT_ROWS` constants.
 *
 * 3. Right-click context-menu state for widget placements
 *    (REQ-WDG-015..017, widget-context-menu):
 *    - `useGridManager({canEdit, onEdit, onRemove, ...})` factory
 *    - Tracks `contextMenuOpen`, `contextMenuPosition`, `selectedWidget`
 *    - `onWidgetRightClick`, `closeContextMenu`, `triggerEdit`,
 *      `triggerRemove`, `attach`, `detach`
 *
 * Why combined: all three halves were authored independently (responsive
 * grid breakpoints + widget collision placement + widget context menu) but
 * converged on a single composable file as the natural home for grid-coupled
 * logic.
 *
 * Naming note
 * -----------
 * GridStack v12 calls the responsive options bag `columnOpts` (not
 * `columnOptions` or `responsive`). The exported `getColumnOpts()` factory
 * returns an object that can be spread directly into `GridStack.init`.
 *
 * Placement-helper rule (REQ-GRID-014)
 * ------------------------------------
 * `placeNewWidget(spec, placements, options?)` is the SINGLE authority for
 * "where does the next widget go?". All add-widget code paths (toolbar
 * dropdown, keyboard shortcut, drag-from-picker, AddWidgetModal submit)
 * MUST funnel through it. Inline calls to `grid.addWidget(...)` outside
 * this file are forbidden and enforced by a grep test.
 */

import Vue from 'vue'

// ---------------------------------------------------------------------------
// Grid configuration constants (REQ-GRID-007/012/013)
// ---------------------------------------------------------------------------

/**
 * Cell height in pixels. Single source of truth for both the JS init call
 * and the `--launchpad-cell-height` CSS custom property.
 *
 * @type {number}
 */
export const CELL_HEIGHT = 60

/**
 * Inter-cell margin in pixels. Applied uniformly to all four sides by
 * GridStack when passed as a number.
 *
 * @type {number}
 */
export const GRID_MARGIN = 8

/**
 * Default column count for a dashboard that does not override `gridColumns`.
 *
 * @type {number}
 */
export const DEFAULT_COLUMNS = 12

/**
 * Responsive breakpoint table — four entries, monotonically descending.
 *   1400 px : full HD desktops (12 columns)
 *   1100 px : standard laptops with the Nextcloud sidebar visible (8)
 *    768 px : iPad portrait threshold (4)
 *    480 px : standard mobile breakpoint (1, single-column stack)
 *
 * @type {Array<{ w: number, c: number }>}
 */
export const BREAKPOINTS = Object.freeze([
	{ w: 1400, c: 12 },
	{ w: 1100, c: 8 },
	{ w: 768, c: 4 },
	{ w: 480, c: 1 },
])

/**
 * GridStack reflow algorithm name.
 *
 * @type {'moveScale'}
 */
export const COLUMN_LAYOUT = 'moveScale'

/**
 * CSS custom-property name used to mirror `CELL_HEIGHT` into the cascade.
 *
 * @type {string}
 */
export const CELL_HEIGHT_CSS_VAR = '--launchpad-cell-height'

/**
 * Build the `columnOpts` object passed to `GridStack.init`. Returned as a
 * fresh shallow copy so callers can mutate without affecting the frozen
 * `BREAKPOINTS` constant.
 *
 * @return {{ breakpoints: Array<{ w: number, c: number }>, layout: string, breakpointForWindow: boolean }}
 */
/** @spec openspec/specs/grid-layout/spec.md */
export function getColumnOpts() {
	return {
		breakpoints: BREAKPOINTS.map(b => ({ ...b })),
		layout: COLUMN_LAYOUT,
		breakpointForWindow: true,
	}
}

/**
 * Mirror the JS `CELL_HEIGHT` value into the CSS `--launchpad-cell-height`
 * custom property on the document root. No-op when `document` is unavailable.
 *
 * @return {void}
 */
/** @spec openspec/specs/grid-layout/spec.md */
export function syncCellHeightCssVar() {
	if (typeof document === 'undefined' || !document.documentElement) {
		return
	}
	document.documentElement.style.setProperty(
		CELL_HEIGHT_CSS_VAR,
		`${CELL_HEIGHT}px`,
	)
}

// ---------------------------------------------------------------------------
// Widget placement helper (REQ-GRID-006 + REQ-GRID-014)
// ---------------------------------------------------------------------------

/**
 * Default widget width in grid columns when the caller omits `spec.w`.
 *
 * @type {number}
 */
export const DEFAULT_W = 4

/**
 * Default widget height in grid rows when the caller omits `spec.h`.
 *
 * @type {number}
 */
export const DEFAULT_H = 4

/**
 * Fallback "viewport rows" used when the caller does not pass a measured
 * value. 8 rows × 60 px ≈ 480 px which is the smallest first-paint surface.
 *
 * @type {number}
 */
export const DEFAULT_VIEWPORT_ROWS = 8

/**
 * Pure rectangle-overlap test on integer grid coordinates.
 *
 * @param {{x: number, y: number, w: number, h: number}} a
 * @param {{x: number, y: number, w: number, h: number}} b
 * @return {boolean}
 */
function rectsOverlap(a, b) {
	return (
		a.x < b.x + b.w
		&& b.x < a.x + a.w
		&& a.y < b.y + b.h
		&& b.y < a.y + a.h
	)
}

/**
 * Engine-free emulation of GridStack's `findEmptyPosition` scan.
 *
 * @param {{w: number, h: number}} sz target widget size in cells
 * @param {Array<{x: number, y: number, w: number, h: number}>} nodes existing widgets
 * @param {number} columns total grid columns
 * @param {number} maxScanRows scan ceiling
 * @return {{x: number, y: number} | null}
 */
function scanForEmptySlot(sz, nodes, columns, maxScanRows) {
	if (sz.w > columns) {
		return null
	}
	for (let y = 0; y < maxScanRows; y++) {
		for (let x = 0; x <= columns - sz.w; x++) {
			const candidate = { x, y, w: sz.w, h: sz.h }
			const collides = nodes.some(n => rectsOverlap(candidate, n))
			if (!collides) {
				return { x, y }
			}
		}
	}
	return null
}

/**
 * Compute the placement coordinates and any required push-down side effects
 * for a new widget being added to the dashboard.
 *
 * Algorithm (REQ-GRID-006):
 *   1. **Primary** — try `grid.addWidget({...spec, autoPosition: true})`
 *      via the supplied live GridStack instance, OR emulate the same scan
 *      with `scanForEmptySlot`.
 *   2. **Fallback** — when step 1 returns no slot OR the picked slot is
 *      below `viewportRows` (off-screen on first paint), place the new
 *      widget at `(0, 0)` and shift every overlapping existing widget to
 *      `gridY = h`.
 *
 * @param {object} spec target widget spec — `w`/`h` default to {@link DEFAULT_W}/{@link DEFAULT_H}
 * @param {Array<object>} placements current placements in LaunchPad field-name form
 *   (`gridX`, `gridY`, `gridWidth`, `gridHeight`, `id`)
 * @param {object} [options] optional knobs
 * @param {number} [options.gridColumns] column count, defaults to {@link DEFAULT_COLUMNS}
 * @param {number} [options.viewportRows] visible rows on first paint, defaults to {@link DEFAULT_VIEWPORT_ROWS}
 * @param {object} [options.grid] live GridStack instance — when supplied the engine is used directly
 * @return {{ x: number, y: number, w: number, h: number, pushed: Array<{id: any, gridY: number}> }}
 */
/** @spec openspec/specs/grid-layout/spec.md */
export function placeNewWidget(spec, placements, options = {}) {
	const w = (spec && Number.isFinite(spec.w) && spec.w > 0) ? spec.w : DEFAULT_W
	const h = (spec && Number.isFinite(spec.h) && spec.h > 0) ? spec.h : DEFAULT_H
	const columns = options.gridColumns || DEFAULT_COLUMNS
	const viewportRows = options.viewportRows || DEFAULT_VIEWPORT_ROWS

	const safePlacements = Array.isArray(placements) ? placements : []
	const nodes = safePlacements.map(p => ({
		id: p.id,
		x: Number.isFinite(p.gridX) ? p.gridX : 0,
		y: Number.isFinite(p.gridY) ? p.gridY : 0,
		w: Number.isFinite(p.gridWidth) ? p.gridWidth : 1,
		h: Number.isFinite(p.gridHeight) ? p.gridHeight : 1,
	}))

	let primaryHit = null
	if (options.grid && options.grid.engine) {
		const probe = { w, h, _id: '__launchpad_probe__' }
		const liveNodes = options.grid.engine.nodes.filter(n => n._id !== probe._id)
		const found = options.grid.engine.findEmptyPosition(probe, liveNodes, columns)
		if (found) {
			primaryHit = { x: probe.x, y: probe.y }
		}
	} else {
		primaryHit = scanForEmptySlot({ w, h }, nodes, columns, viewportRows * 4)
	}

	const primaryAcceptable = primaryHit !== null && primaryHit.y < viewportRows

	if (primaryAcceptable) {
		return { x: primaryHit.x, y: primaryHit.y, w, h, pushed: [] }
	}

	const newRect = { x: 0, y: 0, w, h }
	const pushed = []
	for (const node of nodes) {
		if (rectsOverlap(newRect, node)) {
			pushed.push({ id: node.id, gridY: h })
		}
	}

	return { x: 0, y: 0, w, h, pushed }
}

/**
 * Minimum widget width/height in grid cells. Mirrors the `gs-min-w` /
 * `gs-min-h` floor set on every grid item in `DashboardGrid.vue`.
 *
 * @type {number}
 */
export const MIN_CELLS = 2

/**
 * Compute the candidate rectangle for a keyboard-driven move or resize of
 * an existing placement, reusing the same collision model as
 * {@link placeNewWidget} so keyboard moves get the same push-down
 * behaviour as pointer drags (WCAG 2.1 SC 2.1.1 keyboard-equivalent path).
 *
 * Pure function — no DOM, no GridStack instance — so it is unit-testable
 * and shared between the keyboard move panel and any future caller.
 *
 * Supported actions:
 *   - `move-up` / `move-down` / `move-left` / `move-right` — shift by one
 *     cell, clamped to the grid bounds (x ∈ [0, columns − w], y ≥ 0).
 *   - `grow-width` / `shrink-width` / `grow-height` / `shrink-height` —
 *     resize by one cell, clamped to {@link MIN_CELLS} and the column
 *     count.
 *
 * @param {{gridX: number, gridY: number, gridWidth: number, gridHeight: number, id?: any}} placement
 *   the placement being moved/resized (LaunchPad field-name form)
 * @param {string} action one of the supported action strings above
 * @param {Array<object>} allPlacements every placement on the dashboard
 *   (used to compute push-down side effects), in LaunchPad field-name form
 * @param {object} [options] optional knobs
 * @param {number} [options.gridColumns] column count, defaults to {@link DEFAULT_COLUMNS}
 * @return {{ gridX: number, gridY: number, gridWidth: number, gridHeight: number, pushed: Array<{id: any, gridY: number}> }}
 *   the clamped candidate rect plus any existing placements that must be
 *   pushed down to `gridY = newRect.gridY + newRect.gridHeight` to avoid
 *   overlap. `pushed` is empty when the move/resize is a no-op or clear.
 */
/** @spec openspec/specs/grid-layout/spec.md */
export function nudgePlacement(placement, action, allPlacements, options = {}) {
	const columns = options.gridColumns || DEFAULT_COLUMNS

	const x = Number.isFinite(placement?.gridX) ? placement.gridX : 0
	const y = Number.isFinite(placement?.gridY) ? placement.gridY : 0
	const w = Number.isFinite(placement?.gridWidth) ? placement.gridWidth : MIN_CELLS
	const h = Number.isFinite(placement?.gridHeight) ? placement.gridHeight : MIN_CELLS

	let nx = x
	let ny = y
	let nw = w
	let nh = h

	switch (action) {
	case 'move-up':
		ny = Math.max(0, y - 1)
		break
	case 'move-down':
		ny = y + 1
		break
	case 'move-left':
		nx = Math.max(0, x - 1)
		break
	case 'move-right':
		nx = Math.min(Math.max(0, columns - w), x + 1)
		break
	case 'grow-width':
		nw = Math.min(columns - x, w + 1)
		break
	case 'shrink-width':
		nw = Math.max(MIN_CELLS, w - 1)
		break
	case 'grow-height':
		nh = h + 1
		break
	case 'shrink-height':
		nh = Math.max(MIN_CELLS, h - 1)
		break
	default:
		// Unknown action → no change.
		break
	}

	// Keep the widget inside the horizontal bounds after any width change.
	if (nx + nw > columns) {
		nx = Math.max(0, columns - nw)
	}

	const newRect = { x: nx, y: ny, w: nw, h: nh }
	const pushed = []
	const others = Array.isArray(allPlacements) ? allPlacements : []
	for (const other of others) {
		if (other == null || other.id === placement?.id) {
			continue
		}
		const otherRect = {
			x: Number.isFinite(other.gridX) ? other.gridX : 0,
			y: Number.isFinite(other.gridY) ? other.gridY : 0,
			w: Number.isFinite(other.gridWidth) ? other.gridWidth : 1,
			h: Number.isFinite(other.gridHeight) ? other.gridHeight : 1,
		}
		if (rectsOverlap(newRect, otherRect)) {
			pushed.push({ id: other.id, gridY: (ny + nh) })
		}
	}

	return {
		gridX: nx,
		gridY: ny,
		gridWidth: nw,
		gridHeight: nh,
		pushed,
	}
}

// ---------------------------------------------------------------------------
// Right-click context-menu state (REQ-WDG-015..017)
// ---------------------------------------------------------------------------

/**
 * Default popover dimensions used when clamping. A real popover is
 * `min-width: 150px` (REQ-WDG-017) and approximately three buttons tall.
 */
const DEFAULT_MENU_WIDTH = 150
const DEFAULT_MENU_HEIGHT = 132

/**
 * Create a grid-manager state container for the right-click context menu.
 *
 * @param {object} options factory options
 * @param {{value: boolean}} options.canEdit reactive boolean controlling
 *   whether right-click opens the popover.
 * @param {Function} [options.onEdit] called with `(widget)` on Edit click.
 * @param {Function} [options.onRemove] called with `(widget)` on Remove click.
 * @param {Function} [options.onVisibilityRules] called with `(widget)` on
 *   "Visibility rules…" click.
 * @param {number} [options.menuWidth] override for clamp width (px)
 * @param {number} [options.menuHeight] override for clamp height (px)
 * @param {{innerWidth: number, innerHeight: number}} [options.viewport] override
 *   for the viewport (defaults to `window`); injectable for tests.
 * @return {{
 *   state: {contextMenuOpen: boolean, contextMenuPosition: {x: number, y: number}, selectedWidget: (object|null)},
 *   onWidgetRightClick: (event: MouseEvent, widget: object) => void,
 *   closeContextMenu: () => void,
 *   triggerEdit: () => void,
 *   triggerRemove: () => void,
 *   triggerVisibilityRules: () => void,
 *   attach: () => void,
 *   detach: () => void,
 * }}
 */
/** @spec openspec/specs/grid-layout/spec.md */
export function useGridManager(options = {}) {
	const {
		canEdit,
		onEdit,
		onRemove,
		onVisibilityRules,
		menuWidth = DEFAULT_MENU_WIDTH,
		menuHeight = DEFAULT_MENU_HEIGHT,
		viewport,
	} = options

	const state = Vue.observable({
		contextMenuOpen: false,
		contextMenuPosition: { x: 0, y: 0 },
		selectedWidget: null,
	})

	function getViewport() {
		const v = viewport || (typeof window !== 'undefined' ? window : null)
		if (!v) {
			return { width: Infinity, height: Infinity }
		}
		return { width: v.innerWidth, height: v.innerHeight }
	}

	function clampToViewport(x, y) {
		const { width, height } = getViewport()
		let clampedX = x
		let clampedY = y
		if (clampedX + menuWidth > width) {
			clampedX = Math.max(0, width - menuWidth)
		}
		if (clampedY + menuHeight > height) {
			clampedY = Math.max(0, height - menuHeight)
		}
		return { x: clampedX, y: clampedY }
	}

	function onWidgetRightClick(event, widget) {
		if (!canEdit || !canEdit.value) {
			return
		}
		event.preventDefault()
		const { x, y } = clampToViewport(event.clientX, event.clientY)
		state.contextMenuPosition = { x, y }
		state.selectedWidget = widget
		state.contextMenuOpen = true
	}

	function closeContextMenu() {
		state.contextMenuOpen = false
		state.selectedWidget = null
	}

	function triggerEdit() {
		const widget = state.selectedWidget
		closeContextMenu()
		if (typeof onEdit === 'function' && widget) {
			onEdit(widget)
		}
	}

	function triggerRemove() {
		const widget = state.selectedWidget
		closeContextMenu()
		if (typeof onRemove === 'function' && widget) {
			onRemove(widget)
		}
	}

	function triggerVisibilityRules() {
		const widget = state.selectedWidget
		closeContextMenu()
		if (typeof onVisibilityRules === 'function' && widget) {
			onVisibilityRules(widget)
		}
	}

	function handleDocumentClick(event) {
		if (!state.contextMenuOpen) {
			return
		}
		const target = event.target
		if (target && typeof target.closest === 'function' && target.closest('.widget-context-menu')) {
			return
		}
		closeContextMenu()
	}

	let attached = false

	function attach() {
		if (attached || typeof document === 'undefined') {
			return
		}
		document.addEventListener('click', handleDocumentClick)
		attached = true
	}

	function detach() {
		if (!attached || typeof document === 'undefined') {
			return
		}
		document.removeEventListener('click', handleDocumentClick)
		attached = false
		closeContextMenu()
	}

	return {
		state,
		onWidgetRightClick,
		closeContextMenu,
		triggerEdit,
		triggerRemove,
		triggerVisibilityRules,
		attach,
		detach,
	}
}
