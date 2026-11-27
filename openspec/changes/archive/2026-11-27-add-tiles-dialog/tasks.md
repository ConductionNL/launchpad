# Tasks: add-tiles-dialog

- [x] Add tiles dialog with the four sections and one Add button that counts the chosen tiles
- [x] App catalogue section with search, sign-on kind per row and Request app from IT
- [x] Found on this server: list services an administrator allowed for discovery that have no tile, with port and time found. Decided (no port probing): the administrator lists each service by name and web address (Services to discover, General tab) and LaunchPad asks only those addresses, one GET each, no redirects, cached five minutes; `FoundServicesService`, `GET /api/tiles/found-services`, `GET`/`PUT /api/admin/discoverable-services`, `DiscoverableServicesSettings`; PHPUnit `FoundServicesServiceTest`, `DiscoverableServicesControllerTest`, `TileApiControllerTest`; vitest `addTiles.spec.js`, `AddTilesModal.spec.js`, `DiscoverableServicesSettings.spec.js`; Playwright `tests/e2e/tiles-add-tiles-dialog.spec.ts` grafana-found green on a sandbox Nextcloud 34.0.6 + PostgreSQL + Redis (27 Nov 2026), seen to fail with `found()` answering nothing
- [x] Office section reusing office-tiles, bookmark import reusing BookmarkImportModal
- [x] Translations in every shipped locale
- [x] Unit tests: counting, adding from each section, no duplicate tile
- [x] Playwright: add Word and Excel from the Office section (`tests/e2e/add-tiles-dialog.spec.ts` 1/1 green on a sandbox Nextcloud 34.0.6 + MariaDB, 25 Nov 2026)
