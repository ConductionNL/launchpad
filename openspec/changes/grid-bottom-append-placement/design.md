# Design — Grid bottom-append placement

## Context

The archived `widget-collision-placement` change formalised REQ-GRID-006 as a two-step algorithm: try GridStack `autoPosition`, and when that returns no slot (or a slot below the viewport) fall back to placing the new widget at `(0, 0)` and pushing every overlapping existing widget down by `newH` rows. That algorithm's decision D1 explicitly preferred "top-left + push-down" over bottom-append.

In practice the push-down behaviour reordered the user's existing layout on every add once the visible rows filled up: adding one widget could shuffle several established widgets to new y-coordinates. `placeNewWidget()` was subsequently rewritten to always append the new widget in a fresh row below everything and never touch existing widgets. The composable comment, the store call sites, and the unit tests were updated — but REQ-GRID-006 was not, leaving the spec contradicting the shipped code (notably the "auto-position into empty space" scenario, which asserts `(x:6, y:0)` where the code now returns `(x:0, y:4)`).

This change reconciles the spec to the shipped behaviour.

## Goals / Non-Goals

**Goals:**

- Make REQ-GRID-006 describe what `placeNewWidget()` actually does: deterministic bottom-append, existing widgets never moved.
- Keep spec, the `@spec`-linked composable comment, and the Vitest expectations in agreement.
- Preserve REQ-GRID-014 (single placement authority) and the persistence path of REQ-GRID-005 / REQ-WDG-008.

**Non-Goals:**

- Re-introducing any auto-position or push-down behaviour. The code direction is settled; the spec follows it.
- Changing the default widget size (`4×4`) or the tile default (`2×2`).
- Touching the grep guard or REQ-GRID-014 scenarios.

## Decisions

### D1 (revised): Bottom-append, not top-left + push-down

**Decision**: A new widget is appended at `x = 0`, `y = max(gridY + gridHeight)` over all existing placements. Existing widgets are never moved; the helper's `pushed` array is always empty. An empty dashboard places the first widget at `(0, 0)`.

**Why this reverses the original D1:**

- Push-down reorders established widgets on every add once the top rows fill — the user's deliberate layout silently changes. Bottom-append is non-destructive: existing widgets keep their exact coordinates.
- "New widget appears at the bottom of what I already arranged" is predictable and matches append-style mental models (adding a row to a list/grid).
- The original D1 rejected bottom-append on the grounds that the new widget could land below the fold on dense dashboards. In the GridStack setup the grid scrolls and the freshly added widget is the one the user just asked for, so scrolling to it is acceptable; non-destructiveness of the existing layout is the higher-value property.

**Bottom-edge computation**: iterate every placement and take `max(gridY + gridHeight)`. A placement missing `gridY` contributes `0`; a placement missing `gridHeight` contributes height `1`. This makes the result independent of array order (the lowest widget wins even if it is not the last entry).

### D2: `pushed` stays in the return shape

**Decision**: `placeNewWidget()` still returns a `pushed` array (always empty) and `dashboard.js` still guards `placement.pushed.length > 0`.

**Rationale**: Keeping the return shape stable avoids churning every caller. The guard is dead under the new algorithm but harmless, and leaving it means a future re-introduction of moves (if ever specced) does not require re-threading the call sites. Documented here so the dead branch is not mistaken for a live code path.

### D3: REQ-GRID-014 untouched

**Decision**: The single-placement-authority requirement and its grep guard are unchanged.

**Rationale**: Centralising placement in one helper is orthogonal to which algorithm that helper runs. Bottom-append lives behind the same single entry point, so the guard and its scenarios remain valid as-is.
