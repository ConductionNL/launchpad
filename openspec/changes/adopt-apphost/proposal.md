---
kind: code
---

# Proposal: LaunchPad Adopts OpenRegister AppHost (Observability + Boilerplate)

## Problem

LaunchPad ships its own drifted copies of the fleet observability/boilerplate skeleton:

- `lib/Controller/HealthController.php` — hand-written database `SELECT 1` check, returns only `{status, checks}` (missing the fleet-standard `app`/`version` fields).
- `lib/Controller/MetricsController.php` — hand-written Prometheus exposition (`launchpad_info`, `launchpad_up`, `launchpad_dashboards_total{type}`, `launchpad_widgets_total`, `launchpad_tiles_total`), all expressible as AppHost descriptors.
- `lib/Service/MetricsCollector.php` + `lib/Service/MetricsQueryService.php` — **dead code**: a parallel metrics implementation referenced by nothing except each other (the controller inlines its own queries). Pure drift debris.
- `lib/Controller/PreferencesController.php` — the fleet-standard per-user preferences copy (get/set + key sanitisation).
- `lib/Repair/InitializeActions.php` + `lib/Service/ActionAuthService.php` — the ADR-023 action-authorization skeleton (seeds `lib/actions.seed.json`).
- `lib/Settings/LaunchPadAdminSection.php` — the pure id/name/icon settings-section skeleton.

Per `apphost-observability-engine` and `apphost-boilerplate-controllers` (openregister), these become manifest descriptors plus service aliases onto `OCA\OpenRegister\AppHost\` generics.

### Identity gotcha: `mydash` store id vs `launchpad` runtime id

LaunchPad's Nextcloud App Store id is **`mydash`** (`appinfo/info.xml` `<id>mydash</id>` — the registered listing and cert; see the launchpad-app-id decision), while the repo, namespace (`OCA\LaunchPad`), and `Application::APP_ID` are **`launchpad`**. The AppHost engine derives the metric prefix from the app id the generic controller is constructed with. Today's hand-written controller hardcodes the **`launchpad_`** prefix (verified in `MetricsController.php`: `launchpad_info`, `launchpad_up`, …).

**This spec pins the prefix**: post-adoption, all metric names MUST keep the `launchpad_` prefix regardless of the store id. `Bootstrap::register($context, Application::APP_ID)` passes `'launchpad'`, which satisfies this — but the baseline-parity diff (tasks 0.x/3.x) is the binding check. If any deployment resolves the runtime app id to `mydash`, the adoption must explicitly override the prefix to `launchpad_` rather than silently renaming every scraped series.

## Proposed Change

Adopt the AppHost for the fleet-skeleton surface only; LaunchPad's large domain surface is untouched.

### Observability descriptors (`src/manifest.json` `observability` block)

| Today (hand-written) | Descriptor |
|---|---|
| `launchpad_info` / `launchpad_up` | implicit — never declared |
| health: database `SELECT 1` | `{"id": "database", "type": "database"}` |
| health: *(none for OR)* — **upgrade**: LaunchPad hard-depends on openregister (`info.xml` `<dependencies><app>openregister</app>`; `ManifestController` degrades gracefully when OR is absent) | `{"id": "openregister", "type": "orAvailable", "severity": "degraded"}` |
| `launchpad_dashboards_total{type}` = `COUNT launchpad_dashboards GROUP BY type`, NULL/`''` → `personal` | `tableCount` on `launchpad_dashboards`, `groupBy: ["type"]`, `labelDefaults: {"type": "personal"}` |
| `launchpad_widgets_total` = `COUNT launchpad_widget_placements` | `tableCount` on `launchpad_widget_placements` |
| `launchpad_tiles_total` = `COUNT launchpad_tiles` | `tableCount` on `launchpad_tiles` |

Known parity deltas to verify/document (task 3.x):

- **NULL→`personal` label default MUST be preserved** (binding requirement, scenario below). Today's controller also maps empty-string `type` to `personal` — verify the engine's `labelDefaults` covers `''` as well as NULL, or record the engine fix needed.
- Today's controller **zero-fills both `personal` and `template` series** even when no rows exist; the engine emits only present groups. Absent-series-as-zero is Prometheus-idiomatic — acceptable as a *documented intentional delta* unless the engine grows zero-fill support.
- Health shape gains the fleet-standard `app`/`version` fields (today LaunchPad omits them) — additive-only, documented intentional delta.
- Metrics endpoint becomes **admin-only** (engine-owned posture per ADR-006); today's controller is `@NoCSRFRequired` without an explicit posture. Scrape configs must authenticate as before for other fleet apps.

### Boilerplate deletions / aliasing

- **Delete** `lib/Controller/HealthController.php`, `lib/Controller/MetricsController.php` — route names `health#index` / `metrics#index` stay; `Bootstrap::register()` aliases `OCA\LaunchPad\Controller\HealthController`/`MetricsController` to the AppHost generics.
- **Delete** `lib/Service/MetricsCollector.php`, `lib/Service/MetricsQueryService.php` — dead code, zero callers.
- **Delete** `lib/Controller/PreferencesController.php` — alias to `GenericPreferencesController`; `preferences#getPreference`/`setPreference` URLs and key sanitisation semantics preserved (boilerplate parity rule 3).
- **Replace** `lib/Service/ActionAuthService.php` with the `GenericActionAuthService` alias; **shrink** `lib/Repair/InitializeActions.php` to a one-line stub `extends GenericInitializeActions` (info.xml `<repair-steps>` lists it twice — install + post-migration — both entries keep resolving; NC requires a concrete class in the app namespace).
- **Shrink** `lib/Settings/LaunchPadAdminSection.php` to a one-line stub `extends GenericSettingsSection`.
- **Modify (not shrink)** `lib/AppInfo/Application.php`: add `Bootstrap::register($context, self::APP_ID)`; the existing domain wiring (Notifier, search provider, 12 cascade listeners, background jobs, XXE boot guard) **stays** — LaunchPad's Application.php is domain code, not the 100-line fleet skeleton.
- **`appinfo/routes.php` stays as-is** (URLs, names, verbs, ordering unchanged). LaunchPad does NOT adopt `Routes::standard()`: its route table has ~150 domain routes with load-bearing ordering constraints (literal-before-wildcard groups, the `page#deepLink` catch-all that MUST be last). Aliasing happens purely in DI, so no route edits are needed for the skeleton endpoints.

### Explicitly OUT of scope (domain code — does not move, does not get aliased)

LaunchPad's admin/settings surface is heavy app-specific functionality, not the fleet skeleton:

- **Admin controllers**: `AdminController`, `AdminSettingsController` (group-priority order — REQ-ASET), `AdminBulkController`, `AdminCleanupController`, `AdminDemoShowcasesController`, `AdminOrgNavigationController`, `AdminWidgetRulesController`, `MetadataAdminController`, `AnalyticsController`, `ActionMatrixController`, `ConfluenceImportController` — all stay.
- **`lib/Settings/LaunchPadAdmin.php`** stays: its `getForm()` builds app-specific initial state (groups, group order, widgets, file-extension allow-list) via `InitialStateBuilder` — domain, not the generic IDelegatedSettings shell. It MAY later extend `GenericAdminSettings` for the #299 plumbing, but that is not required by this change.
- **`PageController`** stays: `deepLink()` performs slug-chain dashboard resolution — domain logic, not the `GenericDashboardController` SPA+catch-all skeleton.
- **`ManifestController`** stays: LaunchPad's `/api/manifest` is a *runtime* v2 manifest assembled from the user's OR dashboard objects (ADR-036 Decision 8) — fundamentally different from the static `src/manifest.json` that carries the `observability` block.
- **All 12 Listeners** stay: they are the DashboardDeletedEvent cascade registry (REQ-CSC-002) plus user/group lifecycle — there is no fleet `DeepLinkRegistrationListener` in LaunchPad.
- **Repair steps** `PurgeOrphanedCascadeData`, `SeedRolePermissions` stay (domain). LaunchPad has no `InitializeSettings` repair step (no register-JSON import step exists today); introducing one is out of scope.
- All Dashboard*/Widget*/Tile*/Feed*/File*/Resource*/Template*/PublicShare*/RoleFeaturePermission*/PeopleWidget* controllers and every domain service.

## Impact

- **Deleted**: 5 PHP files (~700 lines: 2 controllers + PreferencesController + 2 dead metrics services); 3 files shrink to stubs/aliases.
- **Modified**: `src/manifest.json` (observability block), `lib/AppInfo/Application.php` (Bootstrap call), `lib/Repair/InitializeActions.php`, `lib/Settings/LaunchPadAdminSection.php`; `appinfo/routes.php` unchanged.
- **Verification**: baseline-vs-after diff of `/apps/.../api/health` + `/api/metrics` on a seeded dev instance (same metric names incl. `launchpad_` prefix, types, label sets); OR AppHost Newman contract collection; existing LaunchPad e2e + PHPUnit suites.
- **Risk**: prefix flip to `mydash_` if app identity is mis-wired (pinned by requirement + baseline diff); loss of zero-filled `template` series (documented delta); admin-gating of metrics changing scrape behaviour (ADR-006-correct, called out for ops).

## Dependencies

Chained on openregister: `apphost-observability-engine`, `apphost-boilerplate-controllers`.
