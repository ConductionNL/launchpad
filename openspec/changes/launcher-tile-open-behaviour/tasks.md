# Tasks: launcher-tile-open-behaviour

## Backend

- [ ] 1. Validate `content.linkTarget` (`same-tab`, `new-tab`, `side-panel`) in `lib/Service/TileUpdater.php` on create and update; unknown values return 400. Verify: PHPUnit cases for each value and for a bad value.
- [ ] 2. Add a nullable `default_link_target` column to `oc_launchpad_dashboards` in a new migration, with getter and setter on `lib/Db/Dashboard.php` and the same three-value check in `DashboardService::updateDashboard()`. Verify: migration test and a service test.
- [ ] 3. Expose `defaultLinkTarget` in the dashboard JSON and the initial state. Verify: controller test asserts the key.

## Frontend

- [ ] 4. Add a "Opens in" choice (same tab, new tab, panel beside the tiles) to `src/modals/TileEditor.vue`, disabled with a reason for outside hosts that are not on the embed allow-list. Verify: Vitest on the editor.
- [ ] 5. Add the dashboard default to `src/modals/DashboardConfigModal.vue`. Verify: Vitest on the modal.
- [ ] 6. Resolve the target in `src/components/TileWidget.vue` (tile, dashboard default, today's rule) and emit `open-in-panel` for `side-panel`. Verify: Vitest per value.
- [ ] 7. Build `TileSidePanel` in `src/views/WorkspaceApp.vue`: one panel, close button, Escape, focus return, refused-framing fallback card with "Open in new tab", overlay below 768px. Verify: Vitest and a Playwright run.
- [ ] 8. Confirm quick search (`src/composables/useTileSearchHost.js`) opens a `side-panel` tile in the panel. Verify: Vitest on `activateResult`.

## Close

- [ ] 9. Add `nl` and `en` strings, update `openspec/specs/tiles/spec.md` on archive, and set d-link-target and d-open-inside to `built` in `openspec/parity/capabilities.json` with evidence lines.
