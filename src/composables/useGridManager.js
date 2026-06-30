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
 * 2. Add-widget placement helper (REQ-GRID-014 single placement authority):
 *    - `placeNewWidget(spec, placements, options?)` — always appends the new
 *      widget in a fresh row below all existing widgets; never moves existing
 *      widgets. Single legal caller of `grid.addWidget(...)` in the codebase
 *      (enforced by grep test).
 *    - `DEFAULT_W`, `DEFAULT_H` constants.
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
 * Compute the placement coordinates for a new widget being added to the
 * dashboard.
 *
 * Behaviour: always append the new widget in a fresh row directly below all
 * existing widgets — `x = 0`, `y =` the lowest occupied bottom edge. Existing
 * widgets are never moved (there is no push-down), and an empty dashboard
 * places the first widget at `(0, 0)`.
 *
 * This deliberately replaces the former "keep the new widget on-screen by
 * inserting it at top-left and pushing every existing widget down" rule, which
 * reordered the user's layout on every add once the visible rows filled up.
 *
 * @param {object} spec target widget spec — `w`/`h` default to {@link DEFAULT_W}/{@link DEFAULT_H}
 * @param {Array<object>} placements current placements in LaunchPad field-name form
 *   (`gridX`, `gridY`, `gridWidth`, `gridHeight`, `id`)
 * @param {object} [options] optional knobs (accepted for caller compatibility;
 *   none affect bottom placement)
 * @return {{ x: number, y: number, w: number, h: number, pushed: Array<{id: any, gridY: number}> }}
 *   `pushed` is always empty — existing widgets are never moved.
 */
/** @spec openspec/specs/grid-layout/spec.md */
export function placeNewWidget(spec, placements, options = {}) {
	const w = (spec && Number.isFinite(spec.w) && spec.w > 0) ? spec.w : DEFAULT_W
	const h = (spec && Number.isFinite(spec.h) && spec.h > 0) ? spec.h : DEFAULT_H

	const safePlacements = Array.isArray(placements) ? placements : []
	let bottomY = 0
	for (const p of safePlacements) {
		const y = Number.isFinite(p.gridY) ? p.gridY : 0
		const ph = Number.isFinite(p.gridHeight) ? p.gridHeight : 1
		bottomY = Math.max(bottomY, y + ph)
	}

	return { x: 0, y: bottomY, w, h, pushed: [] }
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
