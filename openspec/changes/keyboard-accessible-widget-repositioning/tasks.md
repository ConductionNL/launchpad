# Tasks — keyboard-accessible-widget-repositioning

## Grid item accessibility baseline

- [ ] Task 1: In `src/components/DashboardGrid.vue`, add `role="group"`,
  `:aria-label="getPlacementAriaLabel(placement)"` (new computed helper
  returning e.g. `Widget: {title}` or `Tile: {title}`), and `tabindex="0"`
  to the `.grid-stack-item` element (currently lines 9-20).
- [ ] Task 2: Add `:focus-visible` CSS to `.grid-stack-item` using NC
  variables (`var(--color-primary-element)` outline), matching the
  existing visual language of the component's other interactive states.
- [ ] Task 3: Wire `keydown.enter` and `keydown.space` on the grid item (in
  edit mode only) to open the same context-menu / move-panel flow that
  right-click already triggers, calling the existing `onItemContextMenu`
  handler with a synthesized position (e.g. centered on the focused
  element's bounding rect) instead of `event.clientX/clientY`.

## Move action

- [ ] Task 4: In `src/components/Widgets/WidgetContextMenu.vue`, add a new
  `role="menuitem"` button "Move" between the existing Edit (line 13) and
  Remove (line 29) buttons, with a new `onMove()` handler emitting a new
  `move` event (add `'move'` to the `emits` array at line 88) then `close`.
- [ ] Task 5: Create `src/components/Widgets/WidgetMovePanel.vue` — a
  focus-trapped panel/modal (reuse `NcModal` or a lightweight popover
  matching `WidgetContextMenu`'s positioning approach) that:
  - Displays the widget's current `gridX/gridY/gridWidth/gridHeight`.
  - Binds `ArrowUp/Down/Left/Right` to nudge `gridX`/`gridY` by 1 cell.
  - Binds `Shift+ArrowRight/Left` and `Shift+ArrowDown/Up` to grow/shrink
    `gridWidth`/`gridHeight` by 1 cell (respecting the existing `gs-min-w`/
    `gs-min-h` = 2 floor already set in `DashboardGrid.vue` line 17-18).
  - Binds `Enter` to confirm (emit `save` with the new placement rect) and
    `Escape` to cancel (emit `close` with no change).
  - Shows a live text readout of the pending position/size for screen
    readers (`aria-live="polite"`).
- [ ] Task 6: In `src/composables/useGridManager.js`, extract a pure
  helper (e.g. `nudgePlacement(placement, direction, allPlacements)`) that
  computes the candidate new rect and reuses the same collision/pushing
  logic `placeNewWidget()` (lines 232+) already implements, so keyboard
  moves get the same collision-avoidance behaviour as drag moves.
- [ ] Task 7: Wire `DashboardGrid.vue`'s `move` event (from the context
  menu) to open `WidgetMovePanel`, and on `save` call the existing
  placement-update path (the same one GridStack's `change` event already
  uses) with the panel's resulting rect.

## Tests

- [ ] Task 8: Add a Vitest test for `WidgetMovePanel.vue` asserting arrow
  keys nudge the displayed position, `Shift+Arrow` resizes, `Enter` emits
  `save` with the expected rect, and `Escape` emits `close` with no `save`.
- [ ] Task 9: Add a Vitest test for `WidgetContextMenu.vue` asserting the
  new Move button emits `move` then `close`.
- [ ] Task 10: Add a Playwright e2e test (per `feedback_playwright-ui-only-newman-api.md`
  — UI-driven, not API-direct) that: focuses a grid item via `Tab`, opens
  the move panel via `Enter`, nudges it right twice via arrow keys, confirms
  via `Enter`, and asserts the widget's rendered grid position changed by
  the expected offset without any pointer/mouse events being dispatched.
- [ ] Task 11: Add an axe-core (or equivalent) accessibility check to the
  existing Playwright/Vitest suite asserting each grid item has a
  discoverable accessible name and that the move panel traps focus
  correctly (no focus leak to the canvas behind it while open).
