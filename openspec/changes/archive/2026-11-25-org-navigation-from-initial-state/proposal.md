# Proposal: org-navigation-from-initial-state

## Why

Speed budget (hydra ADR-124): every main page usable within 300 ms warm. On every workspace load the shell asked `GET /api/admin/org-navigation/position` and `GET /api/admin/org-navigation` before the org rail could decide whether to show. Each launchpad API call costs about 80 ms of Nextcloud bootstrap on the server for a payload of a few bytes, and every parallel call adds CPU contention to the page load. The page already runs in a request that can read both.

## What changes

- `initial-state-contract`: ADDED REQ-INIT-007. The workspace page carries the optional key `orgNavigation`: `{position, tree, language}` with exactly what those two endpoints answer for the caller in the default language. The org navigation store takes it once, on the shell's first load; every later load asks the API.
- The position is read by one method (`OrgNavigationSources::position`) that both the endpoint and the page use.
- A caller the action matrix refuses either action gets no `orgNavigation`, and the shell meets the same refusal from the API as before.

## Impact

- `lib/Controller/OrgNavigationFirstLoad.php` (new), `OrgNavigationSources::position`, `AdminOrgNavigationController::getPosition`, `PageSettingsSources`, `PageController::index`, `InitialStateBuilder::setOrgNavigation`.
- `src/utils/loadInitialState.js` (`takeOrgNavigation`), `src/stores/orgNavigation.js` (`loadFirst`), `src/views/WorkspaceApp.vue`.
- The key is optional: an older server leaves it out and the store fetches, so the schema version stays 3.
