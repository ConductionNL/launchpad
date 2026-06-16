---
status: proposed
---

# LaunchPad Adoption of OpenRegister AppHost

## Purpose

LaunchPad's `/api/health` and `/api/metrics` run on the OpenRegister AppHost declarative engine, and its fleet-skeleton boilerplate (preferences, action-auth seeding, settings section) runs on the AppHost generics — with output parity to the hand-written copies they replace, the `launchpad_` metric prefix pinned regardless of the `mydash` App Store id, and LaunchPad's domain controllers/listeners untouched.

**Cross-references**: `openregister/openspec/changes/apphost-observability-engine/specs/apphost-observability/spec.md`, `openregister/openspec/changes/apphost-boilerplate-controllers/`

---

## Requirements

### Requirement: Declarative Observability with Pinned launchpad_ Prefix

LaunchPad SHALL serve `/api/health` and `/api/metrics` through the AppHost engine from descriptors in its `src/manifest.json`, with all metric names carrying the `launchpad_` prefix exactly as before adoption, regardless of the Nextcloud App Store id `mydash`.

#### Scenario: Metrics parity after adoption

- **GIVEN** a seeded instance with dashboards, widget placements, and tiles
- **WHEN** `GET /apps/<appid>/api/metrics` is called by an admin
- **THEN** the output MUST contain `launchpad_info{version,php_version,nextcloud_version}`, `launchpad_up`, `launchpad_dashboards_total{type}`, `launchpad_widgets_total`, and `launchpad_tiles_total` with values matching direct table counts
- **AND** no metric name MUST carry a `mydash_` prefix
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: NULL dashboard type maps to the personal label

- **GIVEN** `launchpad_dashboards` rows whose `type` column is NULL (and rows whose `type` is the empty string)
- **WHEN** `GET /apps/<appid>/api/metrics` is called by an admin
- **THEN** those rows MUST be counted under `launchpad_dashboards_total{type="personal"}` via the descriptor's `labelDefaults` — preserving the pre-adoption NULL→`personal` (and `''`→`personal`) mapping
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Health parity after adoption

- **GIVEN** a healthy instance with OpenRegister enabled
- **WHEN** `GET /apps/<appid>/api/health` is called anonymously
- **THEN** the response MUST be HTTP 200 with `checks.database = "ok"` and `checks.openregister = "ok"` in the standard AppHost shape
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: OpenRegister outage degrades but does not fail health

- **GIVEN** an instance where OpenRegister is disabled or unavailable
- **WHEN** `GET /apps/<appid>/api/health` is called anonymously
- **THEN** the response MUST be HTTP 200 with `status = "degraded"` and the `openregister` check reported as failed (the `orAvailable` check carries `severity: degraded`)
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

### Requirement: Boilerplate Replaced by AppHost Generics, Domain Surface Untouched

LaunchPad SHALL delete its fleet-skeleton copies (HealthController, MetricsController, PreferencesController, the dead MetricsCollector/MetricsQueryService pair, ActionAuthService) and serve them via `OCA\OpenRegister\AppHost\` aliases registered by `Bootstrap::register()`, while every domain controller, listener, repair step, and `appinfo/routes.php` (URLs, names, ordering, the trailing `page#deepLink` catch-all) remain unchanged.

#### Scenario: Preferences endpoint parity through the generic controller

- **GIVEN** a user with an existing preference previously written by the deleted `PreferencesController`
- **WHEN** `GET /apps/<appid>/api/preferences/{key}` and `PUT /apps/<appid>/api/preferences/{key}` are called by that user
- **THEN** the same URLs MUST resolve through `GenericPreferencesController`, the previously written value MUST be returned unchanged, and key sanitisation MUST reject the same invalid keys as before
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Action seeding survives the InitializeActions stub swap

- **GIVEN** a fresh install (or upgrade) running the info.xml repair steps
- **WHEN** `OCA\LaunchPad\Repair\InitializeActions` (now a one-line stub extending `GenericInitializeActions`) executes
- **THEN** the actions from `lib/actions.seed.json` MUST be seeded identically to the pre-adoption behaviour, for both `<repair-steps>` registrations
- @e2e exclude install-time repair step — verified by PHPUnit and occ install in CI, no UI surface

#### Scenario: Domain admin surface is not rerouted

- **GIVEN** the adopted app
- **WHEN** an admin opens the LaunchPad admin settings page and exercises group-priority ordering
- **THEN** `LaunchPadAdmin::getForm()` MUST still render the app-specific initial state (groups, group order, widgets, file-extension allow-list) and `AdminSettingsController` endpoints MUST behave exactly as before — none of the domain admin controllers are aliased to AppHost generics
