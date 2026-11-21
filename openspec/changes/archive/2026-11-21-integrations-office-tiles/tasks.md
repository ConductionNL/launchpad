# Tasks: integrations-office-tiles

- [x] 1. Implement `OfficeTilesService` (pack per user from `ITemplateManager::listCreators()` and enabled apps; create from template with free-name selection). Verify: PHPUnit.
- [x] 2. Seed `office-tiles.list` and `office-tiles.create`, and add `GET /api/office-tiles` and `POST /api/office-tiles/create`. Verify: PHPUnit and route-reachability.
- [x] 3. Add the `create` link type to `TileUpdater` validation and to `src/components/TileWidget.vue` with the name and folder dialog. Verify: Vitest and PHPUnit.
- [x] 4. Add "Office tiles" to `src/modals/WidgetPickerModal.vue`, placing one container. Verify: Vitest.
- [x] 5. Playwright with Nextcloud Office: place the pack, create a document twice, see two files.
  - Written: `tests/e2e/office-tiles.spec.ts` (the pack placed through "More" → "Add app", whose entry point comes with launchpad#1300; a document created twice gives `Notulen …` and `… (2)` with the first file's etag unchanged; an unknown creator gets 400 and creates nothing). Run 20 Nov against onlyoffice 10.2.1 with a Euro-Office document server 9.3.7 (Nextcloud 34.0.6): 2 of 2 green; the pack is placed from More > Add widget, "New document" twice gives `Notulen ….docx` and `Notulen … (2).docx` with the first file's etag unchanged (#1451).
- [x] 6. Add `nl` and `en` strings, copy the spec to `openspec/specs/office-tiles/spec.md` on archive, and set int-office-tiles to `built` in the parity matrix with evidence lines.
  - 21 Nov: the pack's strings ("Office tiles", the pack description, "File name", "Create", "The file could not be created.") are in `l10n/en.js` and translated in `l10n/nl.js`; int-office-tiles is `built` in `openspec/parity/capabilities.json` with `src/modals/WidgetPickerModal.vue:117` as evidence; the spec moves to `openspec/specs/office-tiles/spec.md` with this archive.
