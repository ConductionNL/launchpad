# Tasks — grid-bottom-append-placement

## Tasks

- [x] Task 1: Amend REQ-GRID-006 requirement text in `openspec/specs/grid-layout/spec.md` from "auto-position + top-left/push-down fallback" to the bottom-append rule (`x = 0`, `y = max(gridY + gridHeight)`, existing widgets never moved, empty dashboard → `(0,0)`) — captured in this change's `specs/grid-layout/spec.md` delta
- [x] Task 2: Rewrite the REQ-GRID-006 scenarios — remove "Push-down fallback when grid is full at top" and "Pushed widgets remain within their column lane"; replace "Auto-position into empty space" with bottom-append scenarios; update "Default size on omitted dimensions" and "Persistence after placement" to the no-push expectations (delta restates the full scenario set)
- [x] Task 3: Reconcile the stale doc comments in `src/stores/dashboard.js` (`addWidgetToDashboard` and `addTileToDashboard`) that still describe "try autoPosition, fall back to top-left + push down" so they describe bottom-append; logic untouched
- [ ] Task 4 (apply time): Update the "Current Implementation Status" traceability line for REQ-GRID-006 in `openspec/specs/grid-layout/spec.md` (line ~496) from "autoPosition primary path and the top-left + push-down fallback … Push-down side effects flow through `updatePlacements`" to the bottom-append description
- [x] Task 5: Confirm the existing Vitest suite (`src/composables/__tests__/useGridManager.spec.js`, `describe('REQ-GRID-014 placeNewWidget() — always appends at the bottom')`) already encodes the new expectations and passes — no test change required
- [x] Task 6: Confirm REQ-GRID-014 (single placement authority) and its grep guard are unaffected — `grid.addWidget` still appears only in `useGridManager.js` and its test file

## Verification

`openspec validate` exits clean. REQ-GRID-006, the `@spec`-linked comment in `useGridManager.js`, the `dashboard.js` call-site comments, and the Vitest expectations all describe bottom-append. No new push-down behaviour is reintroduced.

## Tests (company-wide ADR-009)

No new tests required — the bottom-append Vitest cases already exist and pass. This change reconciles documentation/spec to shipped code.

## Documentation (company-wide ADR-010)

Composable header comment already describes bottom-append; `dashboard.js` call-site comments reconciled per Task 3.

## i18n (company-wide ADR-005)

No user-facing strings introduced.
