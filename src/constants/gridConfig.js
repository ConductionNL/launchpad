/**
 * SPDX-FileCopyrightText: 2024 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Shared grid configuration constants — thin re-export from
 * `src/composables/useGridManager.js`, which is the single source of truth
 * per REQ-GRID-012 / design.md D5.
 *
 * Kept as a separate module for backward-compat while test files that
 * pre-date the composable migration are updated; new code MUST import
 * directly from the composable.
 */

export {
	CELL_HEIGHT,
	GRID_MARGIN,
	DEFAULT_COLUMNS as GRID_COLUMNS,
	BREAKPOINTS,
	COLUMN_LAYOUT,
} from '../composables/useGridManager.js'
