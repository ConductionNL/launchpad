# Proposal: add-tiles-dialog

## Why

Board LpTegelToevoegen draws "Tegels toevoegen": tiles from the app catalogue that IT approved (search, one row per app with how it signs on, Toevoegen, and "Staat uw app er niet bij? App aanvragen bij IT"), services found running on the server without a tile (Grafana, Uptime Kuma, Toevoegen als tegel), ready-made Office tiles to tick (Word, Excel, PowerPoint, Outlook web), and an import of browser bookmarks by drag and drop. It ends with "3 tegels toevoegen".

On development the parts exist apart: the catalogue (`app-catalogue`, app requests), office tiles (`office-tiles`) and a bookmark import (`BookmarkImportModal.vue`). There is no dialog that brings them together, and no requirement for services found on the server.

## What changes

- `tiles`: the Add tiles dialog (REQ-TILE-043) and tiles for services found on the server (REQ-TILE-044).

## Impact

- A new `src/modals/AddTilesModal.vue` reached from the edit bar and the picker's App tiles section, the server service discovery, translations.
