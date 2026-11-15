---
kind: code
---

# Add keyboard-accessible widget move/resize to the dashboard grid

## Why

`src/components/DashboardGrid.vue` (433 lines) renders the drag-and-drop
canvas via GridStack.js. The grid item elements (lines 9-20: the
`v-for="placement in placements"` block with `gs-x`/`gs-y`/`gs-w`/`gs-h`
attributes) carry no `role`, `aria-label`, or `tabindex` — a full-file grep
for `keydown|keyup|@key|tabindex|aria-|role=` returns zero matches in this
file. GridStack's default interaction model is pointer/touch drag only.

The only alternative interaction surface is the right-click context menu,
`src/components/Widgets/WidgetContextMenu.vue`, which offers exactly four
actions: Edit, Visibility rules, Remove, Cancel (lines 13-44) — there is no
Move, resize, or "send to position" action. A keyboard-only user (or a
screen-reader user) can therefore edit a widget's content or delete it, but
cannot change where it sits on the canvas or how large it is at all.

LaunchPad's own `info.xml` markets the app for organizations that "want
consistent, curated dashboards," and the fleet-wide CLAUDE.md requires NL
Design System / WCAG AA support for every Conduction app. Drag-only
repositioning with no keyboard equivalent fails WCAG 2.1 **SC 2.1.1
(Keyboard)** — an entire class of functionality (layout editing) has no
keyboard path — and **SC 4.1.2 (Name, Role, Value)**, since the grid items
expose no accessible role or name at all.

## What Changes

- Add a "Move" action to `WidgetContextMenu.vue`'s existing menu (between
  Edit and Remove), opening a small keyboard-operable move/resize panel (or
  inline arrow-key mode) rather than requiring drag.
- The move panel/mode lets the user, using only the keyboard: nudge the
  widget one grid cell in any direction (arrow keys), grow/shrink width and
  height by one cell (`Shift+Arrow` or explicit +/- buttons), and confirm
  or cancel (`Enter`/`Escape`), writing the same `gridX`/`gridY`/
  `gridWidth`/`gridHeight` fields GridStack already persists via
  `placeNewWidget()` in `src/composables/useGridManager.js`.
- Add `role="group"` and an `aria-label` (e.g. "Widget: {widget title}") to
  each `.grid-stack-item` in `DashboardGrid.vue`, and `tabindex="0"` so the
  items are keyboard-focusable and screen readers announce them as
  navigable units, not just as a rendered content blob.
- Add a visible keyboard-focus outline (`:focus-visible` styling using NC
  CSS variables) to grid items, matching the existing hover/active
  treatment already used elsewhere in the component tree.
- Support opening the same move panel via a keyboard shortcut (e.g.
  `Enter`/`Space` while a grid item has focus, mirroring how the
  right-click menu opens today) so users are not required to right-click
  to discover the new action.
- No change to mouse/touch drag-and-drop behaviour — this is additive.

## Capabilities

### Modified Capabilities

- `grid-layout`: adds a keyboard-operable move/resize path as an
  accessibility-equivalent alternative to pointer-driven GridStack drag,
  and adds accessible roles/names/focus handling to grid items.

## Impact

**Affected code:**

- `src/components/Widgets/WidgetContextMenu.vue` (add Move menu item +
  emit)
- `src/components/DashboardGrid.vue` (grid item roles/tabindex/focus
  styling; wire the new Move action to a panel/mode)
- New component: `src/components/Widgets/WidgetMovePanel.vue` (or
  equivalent) implementing the keyboard move/resize UI
- `src/composables/useGridManager.js` (expose a pure helper for
  computing a new `{gridX, gridY, gridWidth, gridHeight}` from a
  keyboard nudge/resize action, reusing the existing collision logic in
  `placeNewWidget()` rather than duplicating it)

**Affected APIs:** none — the move panel writes through the same
placement-update endpoint the drag-and-drop flow already uses
(`PUT` on the placement, per REQ-WDG-005 conventions referenced in
`WidgetContextMenu.vue`).

**Dependencies:** none new.
