# Design — AppHost adoption (observability)

## Why observability only, not the full boilerplate

The AppHost engine (ADR-040, `OCA\OpenRegister\AppHost\`) has two halves:

1. **Observability** — `GenericHealthController` (public) + `GenericMetricsController`
   (admin) reading a declarative `observability` block. This is purely
   mechanical and identical across the fleet. **LaunchPad adopts this in full.**
2. **Boilerplate** — `Bootstrap::register()` + `Routes::standard()` aliasing
   `DashboardController` / `PreferencesController` / `SettingsController` /
   `AdminSettings` / `SettingsSection` / `InitializeSettings` /
   `InitializeActions` / `DeepLinkRegistrationListener` onto the generics.

The boilerplate half does NOT fit LaunchPad, for concrete reasons verified
against the OpenRegister `development` clone:

| Leaf class | Why it cannot become the generic |
|---|---|
| `Controller\PageController` | Custom SPA host: passes `id-app-content`/`id-app-navigation` overrides, builds typed initial state via `InitialStateBuilder`, serves the workspace-shell template. Not the generic dashboard renderer. |
| `Controller\PreferencesController` | Has a domain DoS guard (`MAX_VALUE_LENGTH = 8192`) + `pref_` key namespacing. **`GenericPreferencesController` does not exist in OR `development`** — `Bootstrap` references it but no file ships, so aliasing it would 500 on dispatch. |
| `Settings\LaunchPadAdmin` | Typed admin initial-state contract (REQ-INIT-002): group list, `group_order`, available-widget descriptors, `allow_user_dashboards`, link-extension allow-list. The generic admin panel renders none of this. |
| `Settings\LaunchPadAdminSection` | Section id `launchpad` already wired; no mechanical win. |
| `Repair\InitializeActions` | Seeds LaunchPad's own ADR-023 action matrix from `lib/actions.seed.json`; not the generic settings-init step. |
| `Application::register()` | Wires the 11-listener `DashboardDeletedEvent` cascade (REQ-CSC-002), the notifier, the search provider, request-scoped `PublicShareContext`, `DebounceHelper`. Replacing it with a bare `Bootstrap::register()` would delete the entire cascade-events registry. |

Forcing `Bootstrap::register()` here would break the app, lose the permission
matrix and cascade registry, and reference a non-existent generic. The honest,
parity-safe scope is observability — which is also the highest-value, defect-
fixing part. This matches the AppHost adoption guidance ("leave entangled
bespoke + note").

## Wiring the observability controllers

`Bootstrap::register()` aliases observability via factory closures that inject
the leaf `appId` as the controller `$appName`. Rather than calling the full
`Bootstrap::register()` (which also wires the boilerplate we are keeping
bespoke), `Application::register()` registers just the two observability
factories directly — the same closure shape `Bootstrap` uses internally, kept
lazy (no top-level `OCA\OpenRegister\…` symbol) so a disabled/absent
OpenRegister never fatals NC bootstrap; the first request to an aliased route
surfaces a 5xx and health reports the OR-unavailable degraded state.

The thin leaf subclasses (`HealthController extends GenericHealthController`,
`MetricsController extends GenericMetricsController`) exist only so the
unchanged route names `health#index` / `metrics#index` resolve to a class in the
`OCA\LaunchPad\Controller` namespace; their bodies are empty.

`$appId` passed to the factories is `Application::APP_ID` = `'launchpad'` — the
runtime NC app id (mount path `custom_apps/launchpad`, all assets served under
`application: 'launchpad'`). The `mydash` value is the App Store publish id only
and is untouched. The Prometheus prefix therefore stays `launchpad_`, and
appConfig/version reads keep using the `launchpad` app id exactly as today.

## Parity contract

Bespoke output (today) vs engine output (after):

| Metric | Today (bespoke) | After (engine) | Parity |
|---|---|---|---|
| `launchpad_info{version,php_version,nextcloud_version} 1` | hand-built | implicit `info` (engine) | exact |
| `launchpad_up 1` | hand-built | implicit `up` (engine) | exact |
| `launchpad_dashboards_total{type=…}` | `GROUP BY type`, fallback `personal`/`template` = 0 | `tableCount` `launchpad_dashboards` `groupBy:[type]`, `labelDefaults:{type:personal}` | exact for populated rows; zero-rows case now emits no synthetic 0-lines (documented improvement — real DB state) |
| `launchpad_widgets_total` | `countTable('launchpad_widget_placements')` | `tableCount` `launchpad_widget_placements` | exact |
| `launchpad_tiles_total` | `countTable('launchpad_tiles')` | `tableCount` `launchpad_tiles` | exact |

Health output:

| Field | Today | After | Parity |
|---|---|---|---|
| `status` | `ok`/`error` | `ok`/`error`/`degraded` (ADR-006) | superset |
| `checks.database` | `ok`/`error` | `ok`/`error` (engine `database` check) | exact |
| `app` | — | `launchpad` | added (ADR-006) |
| `version` | — | installed version | added (ADR-006) |
| auth | login-gated (`@NoCSRFRequired` only) | **public** (`#[PublicPage]`) | improvement (REQ-PROM-007 intent) |

Metrics auth stays admin-only (no `#[NoAdminRequired]` on either side).

Documented intentional improvements (not regressions): public health endpoint,
`app`+`version` health fields, admin-enforced metrics posture owned by the
engine, and zero-row dashboard counts reflecting real DB state instead of
synthetic `personal=0`/`template=0` placeholder lines.

## Security

- Health: `#[PublicPage]` + `#[NoCSRFRequired]` — engine-owned, read-only, no
  per-object data (ADR-005 N/A; no IDOR surface).
- Metrics: admin-required (engine omits `#[NoAdminRequired]`), aggregate COUNTs
  over allowlisted `^[a-z0-9_]+$` `launchpad_*` tables only — no row data, no
  user identifiers, defence-in-depth table-name re-validation in the source.
