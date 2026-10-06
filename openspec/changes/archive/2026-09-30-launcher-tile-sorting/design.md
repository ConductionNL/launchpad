# Design: launcher-tile-sorting

Read at development `d767c282`.

## Context

- Groups of tiles are container widgets: `src/components/Widgets/Renderers/ContainerWidget.vue` renders `content.placements` (the `children` computed, line 89) in an inner grid, with each child carrying its own inner `gridX`, `gridY`, `gridWidth`, `gridHeight`.
- The container's add and edit form comes from the communal registry in `@conduction/nextcloud-vue`; `openspec/specs/container-widget/spec.md` REQ-CONT-007 fixes it at three fields. LaunchPad can supply its own form through `FORM_OVERRIDES` in `src/constants/widgetRegistry.js:123`, as it already does for calendar, people, spend analytics and Nextcloud widgets.
- `src/composables/useTileClickTracking.js` sends each tile click to `/api/tile-click/{placementId}` for aggregate analytics. `lib/Service/TileAnalyticsService.php` stores only aggregate rows per placement and day and never a user id, by design.
- `src/components/TileWidget.vue:219` calls `recordTileClick()` on activation.

## Decisions

### D1: The sort is a container setting, applied at view time

`content.sortBy` takes `manual` (default), `alphabetical`, `most-used`, `last-used` or `random`. In view mode the container computes an ordered child list and reflows it row by row into the inner grid, keeping each child's width and height. Edit mode always shows the stored positions, so authors edit the real layout. Nothing is written back.

### D2: Usage counts stay in the browser

The composable keeps `{count, lastUsedAt}` per placement id in `localStorage` under `launchpad.tileUse`. Reads and writes are wrapped so a blocked storage falls back to manual order without an error. The server analytics call is unchanged. Because the counts never leave the device, they are not personal data processed by the organisation, and the existing analytics opt-out (`AnalyticsService::isUserOptedOut()`) is not needed for them; the container menu offers "Forget my usage" anyway.

### D3: Ties and new tiles

Alphabetical uses the viewer's locale (`Intl.Collator`) on the tile title. Most used falls back to alphabetical for equal counts, so tiles never used sort after used ones in a stable order. Random reshuffles once per page load, not on every render, so the page does not jump.

### D4: A LaunchPad form, not a change to the communal form

`ContainerWidgetForm.vue` renders the three communal fields plus "Sort tiles". This keeps nextcloud-vue unchanged for the other apps that use the container.

## Declarative-vs-imperative decision

Pure presentation in the renderer; LaunchPad has no schema register for placements.

## Permissions

The sort setting follows the container's edit right (full permission, or admin on a template). Every viewer gets the effect with their own counts.

## Accessibility

A reflow changes the reading order, so the container announces "Sorted by most used" as its accessible description, and keyboard order follows the visual order.

## Test plan

- Vitest: ordering for each mode, tie breaks, the once-per-load shuffle, storage blocked, edit mode shows stored positions.
- Playwright: set "Most used", click one tile three times, reload, it comes first; switch back to "By hand", the original layout returns.

## What the build corrected (2026-09-30)

- Tiles inside a container render through the widget registry, not through `TileWidget.vue`, so a click there never reached `recordTileClick()`. The container counts a click on any of its children itself (`onChildClick`), in the browser only; `recordTileClick()` and its server call are unchanged.
- The widget menu is an editor tool, so "Forget my usage" is a small button under the container title, shown in view mode for most used and last used when there is use to forget.
- A new click does not re-sort until the next page load, so tiles never jump under the pointer. "Forget my usage" re-sorts at once and rebuilds the inner grid.
- When browser storage is blocked, most used and last used show the stored order (the spec's rule), not alphabetical.
- A view-time reflow never reaches the persistence callback: `onGridChange` returns in view mode.
