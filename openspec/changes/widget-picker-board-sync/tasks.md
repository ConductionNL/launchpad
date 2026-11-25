# Tasks: widget-picker-board-sync

- [x] Picker body: search, kind column with counts, card grid in two columns, sections Widgets from Launchpad, App tiles, From other Nextcloud apps
- [x] App tiles section: one tile per catalogue app in its tile colour, plus Own tile; one click places it as a one-cell tile (a click chooses, Add widget or a double click places; the discovered apps follow the catalogue)
- [x] Disabled cards for kinds the role may not add, with the reason; the label Older widget type for legacy widgets
- [ ] Assistant line at the top when the assistant is available (open: no "describe what you need" proposal flow exists to reuse; launchpad-ai-dashboard-assistant built summary widgets, not a layout proposal)
- [x] Translations in every shipped locale
- [x] Unit tests: counts per kind, filtering by kind and search, disabled card is not clickable, app tile placement
- [x] Playwright: open Add widget on My workspace, pick Data and charts, add Pie chart (`tests/e2e/widgets-picker-board-sync.spec.ts` 4/4 green on a sandbox Nextcloud 34.0.6 + MariaDB, 25 Nov 2026. The run found one app bug: `POST /api/dashboard/{id}/tile` dropped gridWidth/gridHeight, so a picker app tile landed 2x2 instead of one cell; `RequestDataExtractor::extractTileData()` now keeps a given size. Spec fixes: dialog width read from `.modal-container`, the footer checked against the scrolling body, and the role rule given a real allow-list and a place in group_order)
