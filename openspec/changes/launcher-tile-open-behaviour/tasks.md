# Tasks: launcher-tile-open-behaviour

## Backend

- [x] 1. Validate `content.linkTarget` (`same-tab`, `new-tab`, `side-panel`) in `lib/Service/TileUpdater.php` on create and update; unknown values return 400. Verify: PHPUnit cases for each value and for a bad value.
  - `TileLinkTarget` holds the three values and the resolution order; `TileUpdater` refuses anything else on create (`linkTarget`) and update (`content.linkTarget`), which the placement controller answers with 400. `TileUpdaterTest`, `TileLinkTargetTest`.
- [x] 2. Add a nullable `default_link_target` column to `oc_launchpad_dashboards` in a new migration, with getter and setter on `lib/Db/Dashboard.php` and the same three-value check in `DashboardService::updateDashboard()`. Verify: migration test and a service test.
  - Migration `Version002013Date20261114090000` (nullable `default_link_target`, app version bumped), `Dashboard::defaultLinkTarget`, and `DashboardService::updateDashboard()` validates it (empty clears). `DashboardServiceLinkTargetTest`, `Version002013Date20261114090000Test`.
- [x] 3. Expose `defaultLinkTarget` in the dashboard JSON and the initial state. Verify: controller test asserts the key.
  - In `jsonSerialize()` and the viewer view (the initial state carries the serialised dashboard); `PUT /api/dashboard/{id}` takes `defaultLinkTarget` and, like placements, needs full edit rights. Serialise goldens updated.

## Frontend

- [x] 4. Add a "Opens in" choice (same tab, new tab, panel beside the tiles) to `src/modals/TileEditor.vue`, disabled with a reason for outside hosts that are not on the embed allow-list. Verify: Vitest on the editor.
  - Radio group "Opens in" (dashboard / same tab / new tab / panel); the panel is greyed out with a reason when `validateIframeUrl` refuses an outside host. `TileEditor.opensIn.spec.js`.
- [x] 5. Add the dashboard default to `src/modals/DashboardConfigModal.vue`. Verify: Vitest on the modal.
  - "Opens in" select; sent only when changed, so a metadata-only editor's save is unaffected. `DashboardConfigModal.linkTarget.spec.js`.
- [x] 6. Resolve the target in `src/components/TileWidget.vue` (tile, dashboard default, today's rule) and emit `open-in-panel` for `side-panel`. Verify: Vitest per value.
  - `resolveLinkTarget` in `src/utils/tileLinkTarget.js`; a side-panel tile opens the panel through the `tilePanel` store (instead of an `open-in-panel` event chain through `Views`). `TileWidget.linkTarget.spec.js`, `tileLinkTarget.spec.js`.
- [ ] 7. Build `TileSidePanel` in `src/views/WorkspaceApp.vue`: one panel, close button, Escape, focus return, refused-framing fallback card with "Open in new tab", overlay below 768px. Verify: Vitest and a Playwright run.
  - Built: `components/Workspace/TileSidePanel.vue` in `WorkspaceApp` (close button, Escape, focus return, framing check with the "Open in new tab" fallback, overlay below 768px); Vitest `TileSidePanel.spec.js`, `tilePanel.spec.js`. Left open for the Playwright run, which needs a live instance.
- [x] 8. Confirm quick search (`src/composables/useTileSearchHost.js`) opens a `side-panel` tile in the panel. Verify: Vitest on `activateResult`.
  - Quick search clicks the rendered tile link, so it follows the same handler. `useTileSearchHost.sidePanel.spec.js`.

## Close

- [ ] 9. Add `nl` and `en` strings, update `openspec/specs/tiles/spec.md` on archive, and set d-link-target and d-open-inside to `built` in `openspec/parity/capabilities.json` with evidence lines.
  - nl strings added (`l10n/nl.js`, `l10n/nl.json`); English uses the source strings. The spec update and the parity rows are for the archive step.
