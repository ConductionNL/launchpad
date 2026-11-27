# Tasks: app-link-tile-widget

- [x] Registry: `tile` becomes "App or link tile" with `mode`; retire `app-tile` from the catalogue (kept as a render alias) (#2247)
- [x] `TileHost.vue` / `TileWidget.vue`: render both modes; text colour on title, count and icon (mask tint for SVG and monochrome icons) (#2247)
- [x] Form (`TileEditor.vue` reworked) inside the Add widget dialog and as the widget's settings: the choice, "Choose the app" list (Nextcloud apps, catalogue with Request app from IT, found services, Office), Import bookmarks link (folders become Containers of link tiles), link fields, Appearance panel with preview, Tile colour, Text colour, contrast line and warning, what is on the tile, Pin (#2247 the form; #2268 inside the Add widget dialog: the "App or link tile" card opens it embedded in place of the cards, Cancel goes back)
- [x] Contrast: resolve token values from the theme, compute the ratio, warning below 4.5:1 with icon and text (#2247)
- [x] Brand defaults from REQ-DWB-007 when the link or app is Conduction, Nextcloud or sendent (#2247)
- [x] Remove the Add tiles dialog (`AddTilesModal.vue`) and the picker's App tiles section; their sections now live in the form (#2247; the edit bar's Add tiles button now opens the tile form until add-widget-dialog-with-miniatures removes it)
- [x] Repair step: `app-tile` to `tile` mode app (same id and everything else), `tile` without mode to mode link, templates and copies included; import and export convert old types (#2247: `ConvertTilesToAppOrLinkTiles` + `TileModeNode`; the import hydrator converts; export writes what is stored)
- [x] Translations (#2247: en, nl)
- [x] Unit tests: both modes render; text colour default and storage; contrast ratio and warning threshold; repair step (no placement lost, ids kept, idempotent, templates); form choice and list; brand defaults (#2247)
- [ ] Playwright `tests/e2e/app-link-tile.spec.ts`: add an app tile for Files through Add widget with a count; add a link tile with title and web address; set text colour black on a dark tile and see the warning; the tile shows the chosen text colour after Save (#2247: written, not yet run live)
- [ ] Playwright: a dashboard with an old `app-tile` placement still shows the app after the upgrade (fixture seeded through the API before the repair step, or the repair step's live check)
- [ ] Rewrite the e2e specs of the Add tiles dialog, app catalogue, found services, office tiles and bookmark import onto the form (#2247: add-tiles-dialog.spec.ts rewritten onto the form; tiles-add-tiles-dialog.spec.ts still open)
- [ ] Live check on a sandbox against LpTegelToevoegen and LpTegelBewerken; an instance with old app tiles keeps every tile
