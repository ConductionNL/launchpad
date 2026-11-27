# Design: layout-widgets-replace-multi-tile

## Decisions

### D1. Tabs is a container with tabs

The Tabs widget (`tabs`) reuses the container's inner grid (REQ-CONT-002, -003): `content.tabs` is a list of
`{id, title, placements: []}`, each tab an inner grid with the container's constants. It follows the WAI-ARIA tabs
pattern (tablist, tab, tabpanel; arrow keys move between tabs, Home and End jump). The open tab is remembered per viewer
in the browser for the page load only. Its depth counts with the container's: at most three levels of wells and tabs in
each other (REQ-CONT-006). In edit mode each tab's grid is editable and a tab can be added, renamed, moved and removed
in the widget's form; removing a tab with widgets asks first.

### D2. A well can fill itself with the viewer's apps

Ruben (27 Nov 2026): the multi-tile widget is not frozen into fixed tiles. A well gains a second source of tiles: a
dynamic app list, `content.appList`:

```json
{ "pinned": ["zaaksysteem", "calendar"], "exclude": ["photos"], "mostUsedLimit": 6, "showFilter": true }
```

With `appList` set, the well draws, for each viewer at render time, one App or link tile in app mode per app in that
viewer's own app menu: the pinned apps first in their order (marked pinned), then the others, without the excluded apps
and without launchpad. `mostUsedLimit` (0 is no limit) shows only that many after the pinned ones, the viewer's most
used first (the same browser-side usage as REQ-TSO-002); `showFilter` draws a search field above the tiles that narrows
them by name as the viewer types. Apps the viewer may not open are never in their app menu, so they never show. Tiles
placed by hand in the same well (`content.placements`) follow the dynamic tiles. The well's form gets a section "Apps
from the app menu" with these four settings. No board draws it yet: board follow-up for LpWidgetKiezen or a well's
settings board.

### D2b. Migration: one dynamic well per multi-tile placement

One repair step, idempotent, per dashboard in a transaction. Each `app-tiles` placement becomes a `container` placement
in the same cell, with the same size, sort order, visibility rules, style, title and placement id, whose `appList`
carries its settings: `pinned` and `exclude` as they were, `mostUsedLimit` as it was when `showMostUsed` was on and 0
otherwise, `showFilter` as it was. Templates and their members' copies are converted the same way. Nothing is resolved
at migration time, so each viewer keeps seeing their own apps, as before.

### D3. Seeds

`StartPageWidgetSeeds`, `StarterWidgetSeeds` and `DemoSeedWidgets` stop naming `app-tiles`. The start page's "My apps"
block becomes a well titled "My apps" with the dynamic app list of D2 and the settings the seed used for the multi-tile
widget (`exclude: ['launchpad']`, `mostUsedLimit: 6`, no filter). The repair step of D2b also converts an existing
Vandaag.

### D4. The renderer stays one release

`app-tiles` leaves the catalogue at once. Its renderer stays for one release, so an import file or a template definition
met before conversion still draws, and the import paths convert it. The release after, the renderer goes.

## Risks

- The dynamic list reads the viewer's app menu; it comes with the page's boot data, so a dynamic well adds no request
  (speed rule).
