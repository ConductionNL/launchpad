# Tasks: launcher-bookmark-import

- [x] 1. Seed `widget.import-bookmarks` as `["admin", "@all"]` in `lib/actions.seed.json` and add the route `POST /api/dashboard/{dashboardId}/tiles/import`. Verify: route-reachability and action-matrix tests.
- [x] 2. Implement `WidgetApiController::importBookmarks()` with the action check, `canAddWidget()` and 409 on quota. Verify: PHPUnit for 401, 403, 409 and 201.
- [x] 3. Add `PlacementService::importBookmarks()`: URL check per bookmark, one quota check, containers for folders, bottom-append, one transaction, a skipped list in the response. Verify: PHPUnit including rollback.
- [x] 4. Write the browser parser for the Netscape bookmark format with size and count limits. Verify: Vitest on Firefox, Chrome and Edge fixtures.
- [x] 5. Build `src/modals/BookmarkImportModal.vue` (file picker, folder and bookmark checkboxes, skipped list) and the "Import bookmarks" entry in the dashboard menu. Verify: Vitest and an axe check.
- [ ] 6. Playwright: import a fixture, pick one folder, see its container at the bottom. Not written: the requirements carry `@e2e exclude` naming the PHPUnit and Vitest files that cover them.
- [x] 7. Add `nl` and `en` strings, fold the delta into `openspec/specs/tiles/spec.md` on archive, and set d-import-bookmarks to `built` in the parity matrix with evidence lines.
