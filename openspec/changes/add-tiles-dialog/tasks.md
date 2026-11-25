# Tasks: add-tiles-dialog

- [x] Add tiles dialog with the four sections and one Add button that counts the chosen tiles
- [x] App catalogue section with search, sign-on kind per row and Request app from IT
- [ ] Found on this server: list services an administrator allowed for discovery that have no tile, with port and time found (the section lists the app-menu apps without a tile; probing server ports waits for a security decision, see builders-idle.md)
- [x] Office section reusing office-tiles, bookmark import reusing BookmarkImportModal
- [x] Translations in every shipped locale
- [x] Unit tests: counting, adding from each section, no duplicate tile
- [x] Playwright: add Word and Excel from the Office section (`tests/e2e/add-tiles-dialog.spec.ts` 1/1 green on a sandbox Nextcloud 34.0.6 + MariaDB, 25 Nov 2026)
