---
capability: grid-layout
delta: true
status: draft
---

# Grid Layout — Delta from change `grid-bottom-append-placement`

## MODIFIED Requirements

### Requirement: REQ-GRID-006 Widget Auto-Layout (bottom-append placement)

When a new widget is added to a dashboard, the system MUST place it in a fresh row directly below all existing widgets, without overlapping any existing widget and without moving any existing widget. The algorithm MUST be:

1. **Compute the bottom edge** — for every existing placement compute `gridY + gridHeight`; the new widget's `y` MUST be the maximum of these values (the lowest occupied bottom edge). A placement missing `gridY` contributes `0`; a placement missing `gridHeight` contributes height `1`.
2. **Anchor to the left** — the new widget's `x` MUST be `0`.
3. **Empty dashboard** — when there are no existing placements, the new widget MUST be placed at `(x: 0, y: 0)`.

Existing widgets MUST NOT be moved — there is no push-down — and the helper's `pushed` array MUST always be empty. Default size when the caller omits `w`/`h` MUST be `w=4, h=4`. Position writes MUST trigger the persistence path of REQ-GRID-005.

This requirement deliberately replaces the former "GridStack auto-position + top-left/push-down fallback" rule, which reordered the user's existing layout on every add once the visible rows filled up. The shipped `placeNewWidget(spec, placements, options?)` helper implements bottom-append; this requirement is the authoritative description of that behaviour.

#### Scenario: Append below the only existing widget

- **GIVEN** a dashboard with one widget at `(x:0, y:0, w:6, h:4)`
- **WHEN** a new 4×4 widget is added
- **THEN** it MUST be placed at `(x:0, y:4)` (a fresh row below the existing widget)
- **AND** the existing widget MUST NOT be moved
- **AND** the returned `pushed` array MUST be empty

#### Scenario: Append below the lowest widget regardless of array order

- **GIVEN** a dashboard with widgets at `(x:0, y:5, w:6, h:3)` (bottom edge `8`) and `(x:6, y:0, w:6, h:2)` (bottom edge `2`)
- **WHEN** a new 6×3 widget is added
- **THEN** it MUST be placed at `(x:0, y:8)` (below the lowest bottom edge, not below the last array entry)
- **AND** no existing widget MUST be moved

#### Scenario: First widget on an empty dashboard

- **GIVEN** a dashboard with no widgets
- **WHEN** a new 4×4 widget is added
- **THEN** it MUST be placed at `(x:0, y:0)`

#### Scenario: Existing widgets are never pushed when the top region is full

- **GIVEN** a dashboard whose `[0..12] × [0..4]` region is fully occupied
- **WHEN** a new 4×4 widget is added
- **THEN** it MUST be placed at `(x:0, y:4)`, below the occupied region
- **AND** every existing widget MUST retain its original `gridX`, `gridY`, `gridWidth`, and `gridHeight`
- **AND** the returned `pushed` array MUST be empty

#### Scenario: Default size on omitted dimensions

- **GIVEN** a caller invokes `placeNewWidget({type: 'text'})` with no `w` or `h`
- **WHEN** the placement runs
- **THEN** the placement MUST use `w=4, h=4`
- **AND** the `y` coordinate MUST still be the lowest occupied bottom edge (`0` on an empty dashboard)

#### Scenario: Persistence after placement

- **GIVEN** a new widget has been placed
- **WHEN** the placement completes
- **THEN** the new widget MUST be persisted via the standard placement-update API (REQ-WDG-008 batch update or per-placement PUT)
- **AND** because no existing widget is moved, only the new widget's position needs to be written
