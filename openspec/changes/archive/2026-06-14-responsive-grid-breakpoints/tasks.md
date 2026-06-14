# Tasks — responsive-grid-breakpoints

## 1. Frontend

- [x] 1.1 Bump `gridstack` to `^12.2.1` in `package.json`; resolve any v10 -> v12 breaking changes
- [x] 1.2 Define constants `CELL_HEIGHT = 60`, `GRID_MARGIN = 8`, `BREAKPOINTS = [{w:1400,c:12},{w:1100,c:8},{w:768,c:4},{w:480,c:1}]` in grid composable (single shared module)
- [x] 1.3 Pass `cellHeight`, `margin`, `columnOpts: {breakpoints, layout: 'moveScale'}` to `GridStack.init` in `useGridManager.js`
- [x] 1.4 Update `WorkspaceApp.vue` (and any other GridStack mount sites) to import + use the shared constants — no inline literals
- [x] 1.5 Update CSS `calc()` usage to read from CSS custom property `--mydash-cell-height` (set from JS at init time from `CELL_HEIGHT`)

## 2. Decision: keep 80 or move to 60?

- [x] 2.1 Resolve cell-height value with stakeholder before applying — going with 60 px per design.md D3 (denser dashboards, modern UX feedback)
- [x] 2.2 If keeping 80, update REQ-GRID-012 height-math scenario accordingly — N/A, 60 px kept; spec scenario `(4 * 60) + (3 * 8) = 264 px` is correct

## 3. Tests

- [x] 3.1 Playwright: assert column count via `grid.opts.column` at 1500 / 1200 / 900 / 640 / 320 px viewport widths
- [x] 3.2 Playwright: visual regression for a 6-widget layout at each of the four breakpoints
- [x] 3.3 Vitest: composable exposes `CELL_HEIGHT`, `GRID_MARGIN`, `BREAKPOINTS` with expected values

## 4. Quality

- [x] 4.1 ESLint + Stylelint clean
- [x] 4.2 Update `openspec/config.yaml` `cellHeight` documentation to match the resolved value (60 or 80)
- [x] 4.3 CHANGELOG: note the GridStack major bump for downstream consumers
