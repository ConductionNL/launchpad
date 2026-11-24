# Proposal: dashboard-first-load-from-initial-state

## Why

Speed budget (hydra ADR-124): every main page usable within 300 ms warm. The workspace page already reads the visible dashboards and the active dashboard on the server to fill its initial state, and then the dashboard store asks `GET /api/dashboards/visible` and `GET /api/dashboard` for the same data again before it draws anything: about 250 ms of round trip on every page load (PERF-HANDOFF, launchpad).

## What changes

- `initial-state-contract`: ADDED REQ-INIT-006. The workspace page carries the store's first load as the optional key `dashboardFirstLoad`: `{visible, active}` with exactly the payloads of those two endpoints. The store takes it once, on its first load; every later load asks the API.
- The two payloads are built by one class (`DashboardPayloads`) that both the API controller and the page use, so they cannot drift apart.
- A caller the action matrix refuses either action gets no first load, and the store meets the same refusal from the API as before.

## Impact

- `lib/Controller/DashboardPayloads.php` (new), `DashboardApiController` (`visible`, `getActive` delegate to it), `PageSources`, `PageController::index`, `InitialStateBuilder::setDashboardFirstLoad`.
- `src/utils/loadInitialState.js` (`takeDashboardFirstLoad`), `src/stores/dashboard.js` (`loadDashboards`).
- The key is optional, like `deepLinkPath`: an older server leaves it out and the store fetches, so the schema version stays 3.
