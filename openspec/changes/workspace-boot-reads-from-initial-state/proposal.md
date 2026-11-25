# Proposal: workspace-boot-reads-from-initial-state

## Why

Speed budget (hydra ADR-124): every main page usable within 300 ms warm. While the workspace view boots it asks `GET /api/tiles`, `GET /api/acknowledgements/pending` and `GET /api/dashboards/default`. Each launchpad API call costs about 80 ms of Nextcloud bootstrap on the server for a payload of a few bytes, and on a shared box every parallel request takes CPU from the page that is loading. The page already runs in a request that can read all three.

## What changes

- `initial-state-contract`: ADDED REQ-INIT-008. The workspace page carries the optional key `workspaceBootReads`: `{tiles, pendingAcknowledgements, defaultDashboard}`, each part exactly what its endpoint answers for the caller. A part the caller may not read (`tile.index`, `dashboard.get-default-dashboard`), or that fails, is left out. Each reader takes its part once; every later read asks the API.

## Impact

- `lib/Controller/WorkspaceBootReads.php` (new), `PageSources`, `PageController::index`, `InitialStateBuilder::setWorkspaceBootReads`.
- `src/utils/loadInitialState.js` (`takeBootRead`), `src/stores/tiles.js` (`loadTiles`), `src/stores/dashboard.js` (`fetchPendingAcknowledgements`), `src/views/Views.vue` (`loadDefaultDashboardPreference`).
- The key and each part are optional: an older server leaves them out and the readers fetch, so the schema version stays 3.
