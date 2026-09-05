# Tasks: LaunchPad manifest tier 3

## Decided

- [x] `CnAppRoot` hosts the workspace, with the default `CnAppNav`. The org rail is org-wide API data, not an app menu, so it coexists rather than folding in.
- [x] One skip link: Nextcloud's. `#launchpad-main-content` keeps `tabindex="-1"` for the quick-search Esc contract.
- [x] Dashboard URLs in scope.

## Routing

- [x] `vue-router` (already a dependency, never used) created in `src/main.js` in **path** mode via `createWebHistory(routerBase())`. `routerBase()` reads the location and falls back to `generateUrl`, because Nextcloud serves the app under both `/apps/launchpad/…` and `/index.php/apps/launchpad/…` and a base that assumes one makes every route on the other miss — silently, as an empty content area.
- [x] `routesFromManifest()` derives the table from `manifest.pages`, so a declared page is a routed page by construction.
- [x] The v4 catch-all is `path: '/:pathMatch(.*)*'`. The bare `'*'` was removed in vue-router 4 and does not warn: it simply never matches.
- [x] `/` and `/dashboards/:id` render `WorkspaceApp`; `/store`, `/reports`, `/reports/dashboards`, `/features-roadmap`, `/flows` and `/flows/:id` render their declared page types.

## Manifest

- [x] `Workspace` declared at `/`. The app's home had no page at all.
- [x] `dashboard-detail` changed from `type: dashboard` to `custom` + `WorkspaceApp`. As a declarative `dashboard` page the renderer would have replaced this app's GridStack surface with the generic widget renderer.
- [x] `admin-settings` and `admin-templates-index` resolve to `AdminSettingsRedirect` (see the proposal's "Found while implementing").
- [x] Every `type: custom` page carries a `_note`.

## Shell

- [x] `src/App.vue` roots on `CnAppRoot`, passing the live manifest, the registry and `defaultPageTypes`.
- [x] `src/registry.js` created — the v2 kind-tagged registry `CnPageRenderer` resolves `custom` components against.
- [x] The bespoke skip link and its `focusMainContent` handler are gone.
- [x] `OrgNavigationPanel` stays inside `WorkspaceApp`, unchanged.

## Dashboard URLs

- [x] `WorkspaceApp` watches `$route.params.id` (`immediate`, so a cold deep link selects on arrival) and calls `switchDashboard`. An absent or unknown id is left alone: the store's resolver already picks a sensible active dashboard, and overriding it would make a bad link empty the page instead of falling back.

## Tests

- [x] The four `test.fail()` markers are removed — this is the change that earns it.
- [x] The tripwire test is INVERTED, not deleted: it asserted the absence of `CnAppNav` and now asserts the shared chrome renders *with the workspace still inside it*.
- [x] A new test asserts a dashboard has an address. It asks the app which dashboards the user has rather than seeding an id, because a hardcoded id passes on one instance and nowhere else.

## Verify

- [x] `check:manifest`, `format`, `check:schema-l10n`, `lint` — this repo's CI checks — all pass.
- [x] `npm run build` compiles (webpack exit 0; the three warnings are the pre-existing bundle-size ones).
- [x] `vitest` WorkspaceApp: 14 passed.
- [x] `playwright test --list`: 9 tests collect.
- [ ] The E2E leg on the `development` push run. It runs only there, so this is where routing is actually proven.

## Left for later, deliberately

- [ ] The org rail renders only inside `WorkspaceApp`, so it appears on the dashboard routes and not on `/store`, `/reports` or `/features-roadmap`. That matches today's behaviour exactly (there were no other routes), but it is now a visible inconsistency. Lifting it to the shell is its own change.
- [ ] `DashboardsReport` is `type: dashboard` — a declarative page over LaunchPad's own register. It was unroutable before, so it has never rendered; whether its widgets resolve is unproven until the E2E runs.
