# Grid bottom-append placement

`placeNewWidget()` in `src/composables/useGridManager.js` no longer implements the "auto-position + top-left/push-down fallback" algorithm that REQ-GRID-006 mandates. It now **always appends the new widget in a fresh row directly below all existing widgets** (`x = 0`, `y =` the lowest occupied bottom edge) and never moves existing widgets. The composable comment states this "deliberately replaces the former rule," and the rewritten unit tests already assert the new behaviour. The live spec is the only artefact still describing the old algorithm — so spec, comment, and tests currently disagree. This change amends REQ-GRID-006 (and its `@spec`-referenced scenarios) to the bottom-append rule so all three agree.

## Affected code units

- `src/composables/useGridManager.js` — `placeNewWidget(spec, placements, options?)` returns `{x: 0, y: bottomY, w, h, pushed: []}`; behaviour already matches this proposal (no code change required for the algorithm)
- `src/stores/dashboard.js` — `addWidgetToDashboard()` / `addTileToDashboard()` doc comments still describe "try autoPosition, fall back to top-left + push down"; reconciled to the bottom-append wording
- `openspec/specs/grid-layout/spec.md` — REQ-GRID-006 requirement text + scenarios, and the "Current Implementation Status" traceability line, updated at apply time
- Modifies `grid-layout` REQ-GRID-006 (Widget Auto-Layout)

## Why a delta

REQ-GRID-006 currently mandates the autoPosition primary path and a top-left + push-down fallback, including a scenario that asserts a new 4×4 widget lands at `(x:6, y:0)`. The shipped code returns `(x:0, y:4)` for that same input, and the unit test was updated to the new expectation. Because this repo treats openspec as the source of truth (every helper carries an `@spec` tag) the contradiction is a hard inconsistency: the requirement, the inline comment that links it, and the tests must be reconciled. The chosen direction is to bring the spec in line with the shipped code, not to revert the code.

## Approach

- **Bottom-append, no push-down** — the new widget's `y` is the maximum `gridY + gridHeight` across all existing placements; its `x` is always `0`. An empty dashboard places the first widget at `(0, 0)`.
- **Existing widgets are immutable** — the helper never moves an existing widget; the returned `pushed` array is always empty. The defensive `placement.pushed.length > 0` branch in `dashboard.js` therefore never fires but is harmless and stays for caller compatibility.
- **Default size** — `w=4, h=4` when the caller omits `spec.w` / `spec.h` (unchanged from the prior requirement).
- **Persistence** — position writes still flow through the REQ-GRID-005 / REQ-WDG-008 persistence path; because nothing is pushed, only the new widget's position needs to be written.
- **Scenario set** — the "Auto-position into empty space", "Push-down fallback when grid is full at top", and "Pushed widgets remain within their column lane" scenarios are replaced by bottom-append scenarios; "Default size on omitted dimensions" and "Persistence after placement" are retained with updated expectations.

## Capabilities

**Modified Capabilities:**

- `grid-layout` (modifies REQ-GRID-006)

## Notes

- This reverses decision **D1** ("top-left + push-down, not bottom-append") from the archived `widget-collision-placement` change. See `design.md` for the updated decision log and the rationale for choosing bottom-append.
- REQ-GRID-014 (single placement authority) is unaffected — `placeNewWidget` remains the only legal caller of `grid.addWidget(...)` and the grep guard still holds.
