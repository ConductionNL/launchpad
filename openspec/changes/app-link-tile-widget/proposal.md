---
kind: code
depends_on:
  - add-widget-dialog-with-miniatures
chain:
  - add-widget-dialog-with-miniatures
  - app-link-tile-widget   # this change
  - layout-widgets-replace-multi-tile
---

# Proposal: app-link-tile-widget

## Why

Ruben's review of 27 November 2026:

- Item 12: one "App/Link tile" widget replaces the App tile widget (one app as a tile, `app-tile`) and the link tile
  (`tile`). Its form starts with a choice: an app (pick the app, with its icon and new-items count) or a link (web
  address, title, icon). Colour, pin, appearance and the white-logo default of item 3 apply to both. Existing
  placements of either keep working; nothing on an existing dashboard may be lost.
- Item 14: the form lets the user choose the text colour (title and count) next to the tile colour, from the theme's
  colours plus white and black, white by default. The text colour is stored on the tile and used for its title, count
  and logo tint. The form warns when text and tile colour fall below WCAG AA contrast (4.5:1).
- Item 9 (second half): the steps of the former Add tiles dialog (app catalogue with "Request app from IT", services
  found on the server, ready-made Office tiles, bookmark import) live inside the Add widget dialog, in the App/Link
  tile form (board LpTegelToevoegen).
- Item 3: the white logo on the brand colour, without payoff, is the default the form shows for the Conduction,
  Nextcloud and sendent tiles (already seeded so by REQ-DWB-007).

This is change 2 of 3 in the widget catalogue chain.

## What changes

- `app-tile-widget`: the App/Link tile (REQ-APPTILE-001, -002 modified; REQ-APPTILE-004 to -006 added: the choice, the
  text colour with its contrast check, the migration of existing app tiles and link tiles).
- `tiles`: the tile form (REQ-TILE-040), the icon-only tile and the tile colour (REQ-ICT-001, REQ-ICT-004) take the
  text colour; found services (REQ-TILE-044) are listed in the form's app list; the Add tiles dialog (REQ-TILE-043)
  goes.
- `widgets`: the App tiles section of the picker (REQ-WDG-042) goes.

## Out of scope

- The multi-tile widget (`app-tiles`) and the tabs widget: change 3. The quick links list (Snelkoppelingen,
  `quicklinks`), the links list (`links`) and the link button (`link`, "Create Document") stay their own widgets.

## Boards

LpTegelToevoegen, LpTegelBewerken, LpWidgetKiezen.
