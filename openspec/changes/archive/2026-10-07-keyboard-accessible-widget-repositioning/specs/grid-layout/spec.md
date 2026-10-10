---
capability: grid-layout
delta: true
status: draft
---

# Grid Layout — Keyboard-Accessible Move and Resize

## Context

`DashboardGrid.vue` renders widget placements on a GridStack.js canvas.
Today the only way to reposition or resize a widget is pointer/touch drag.
This delta adds an accessible-name/role baseline to grid items and a
keyboard-operable move/resize path so the layout-editing feature meets
WCAG 2.1 SC 2.1.1 (Keyboard) and SC 4.1.2 (Name, Role, Value).

## ADDED Requirements

### Requirement: REQ-GRID-KBD-001 Grid items expose an accessible role and name

Every rendered grid item MUST carry `role="group"`, a computed
`aria-label` describing the widget (e.g. "Widget: {title}"), and
`tabindex="0"` so it is reachable via sequential keyboard navigation
(`Tab`/`Shift+Tab`) and announced by assistive technology as a distinct,
named unit.

#### Scenario: Grid item is keyboard-focusable

- **GIVEN** a dashboard with 3 widget placements in edit mode
- **WHEN** a keyboard user presses `Tab` repeatedly from the canvas
- **THEN** focus MUST land on each grid item in turn
- **AND** each focused item MUST have a visible focus indicator

#### Scenario: Screen reader announces the widget

- **GIVEN** a grid item wrapping a widget titled "Team Announcements"
- **WHEN** a screen reader user focuses the item
- **THEN** it MUST announce a group with the accessible name "Widget:
  Team Announcements" (or the tile-equivalent label for tile placements)

### Requirement: REQ-GRID-KBD-002 Keyboard-operable move and resize

Any user MUST be able to reposition or resize a focused widget placement
using only the keyboard, producing the same `gridX`/`gridY`/`gridWidth`/
`gridHeight` outcome a mouse drag would produce, including respecting the
existing minimum size (`gs-min-w`/`gs-min-h` = 2) and existing collision/
push behaviour.

#### Scenario: Opening the move panel via keyboard

- **GIVEN** a grid item has keyboard focus
- **WHEN** the user presses `Enter` or `Space`
- **THEN** the same panel/menu reachable via right-click MUST open
- **AND** a "Move" action MUST be present in that panel/menu

#### Scenario: Nudging position with arrow keys

- **GIVEN** the move panel is open for a widget at `gridX=2, gridY=3`
- **WHEN** the user presses `ArrowRight`
- **THEN** the pending position MUST become `gridX=3, gridY=3`
- **AND** the change MUST NOT be persisted until the user confirms

#### Scenario: Resizing with Shift+arrow keys

- **GIVEN** the move panel is open for a widget at `gridWidth=3,
  gridHeight=2`
- **WHEN** the user presses `Shift+ArrowRight`
- **THEN** the pending size MUST become `gridWidth=4, gridHeight=2`
- **AND** the size MUST NOT shrink below the existing 2-cell minimum in
  either dimension

#### Scenario: Confirming and cancelling

- **GIVEN** the move panel is open with a pending position/size change
- **WHEN** the user presses `Enter`
- **THEN** the placement MUST be updated via the same persistence path
  drag-and-drop already uses
- **WHEN** instead the user presses `Escape`
- **THEN** no change MUST be persisted and the panel MUST close

#### Scenario: Collisions are resolved the same way as drag

- **GIVEN** a keyboard-driven move would overlap an existing placement
- **WHEN** the user confirms the move
- **THEN** the system MUST apply the same collision/push resolution
  `placeNewWidget()` already applies for drag-and-drop moves — no widget
  MUST silently overlap another after confirmation
