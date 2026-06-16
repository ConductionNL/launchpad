---
status: implemented
---

# Prometheus Metrics Specification

## Purpose

Expose application metrics in Prometheus text exposition format at `GET /api/metrics` for monitoring, alerting, and operational dashboards. Additionally, provide a health check endpoint at `GET /api/health` for container orchestration and load balancer readiness probes.

## Data Model

Metrics are collected at request time from database queries and system information. No persistent metrics storage is used -- all values are computed on-demand.

@e2e exclude pure backend — all scenarios are PHP/service/API/data-layer; no UI surface

### Metrics Architecture
- **MetricsController**: Handles HTTP request, formats output as Prometheus text exposition
- **MetricsCollector**: Orchestrates metric collection, delegates to MetricsQueryService
- **MetricsQueryService**: Executes database queries for entity counts
- **HealthController**: Handles health check requests, performs database connectivity test
## Requirements
### Requirement: Metrics Endpoint (REQ-PROM-001)

The system MUST expose a Prometheus-compatible metrics endpoint accessible to
admin users, rendered by the OpenRegister AppHost observability engine
(`GenericMetricsController`) from the declarative `observability.metrics` block
in `src/manifest.json`. The `/api/metrics` URL is unchanged; the
`metrics#index` route target is aliased to the engine generic via a lazy
factory in `Application::register()`.

#### Scenario: Metrics endpoint returns valid Prometheus format
- GIVEN a Nextcloud admin user
- WHEN they send GET /index.php/apps/launchpad/api/metrics
- THEN the system MUST return HTTP 200
- AND the Content-Type MUST be `text/plain; version=0.0.4; charset=utf-8`
- AND the body MUST contain metrics in Prometheus text exposition format

#### Scenario: Metrics endpoint requires admin authentication
- GIVEN a regular (non-admin) Nextcloud user "alice"
- WHEN she sends GET /api/metrics
- THEN the system MUST deny access (the engine controller declares no `#[NoAdminRequired]`)
- AND no metrics data MUST be exposed

#### Scenario: Metrics endpoint accessible without CSRF token
- GIVEN an admin user or monitoring system
- WHEN GET /api/metrics is sent without a CSRF token
- THEN the system MUST still return metrics (engine controller carries `#[NoCSRFRequired]`)

#### Scenario: Metric names, types and labels are preserved
- GIVEN the engine renders the manifest `observability` block for app id `launchpad`
- WHEN the metrics endpoint is called
- THEN the body MUST contain `launchpad_info`, `launchpad_up`,
  `launchpad_dashboards_total{type=…}`, `launchpad_widgets_total`,
  and `launchpad_tiles_total` with the same HELP/TYPE lines as before adoption
- AND the body MUST end with a newline

### Requirement: Application Info Metric (REQ-PROM-002)

The system MUST expose an info metric with version labels.

#### Scenario: Info metric reports versions
- GIVEN the LaunchPad app version is "1.2.3", PHP version is "8.2.0", and Nextcloud version is "29.0.0"
- WHEN the metrics endpoint is called
- THEN the response MUST include:
  ```
  # HELP launchpad_info Application information
  # TYPE launchpad_info gauge
  launchpad_info{version="1.2.3",php_version="8.2.0",nextcloud_version="29.0.0"} 1
  ```
- AND the value MUST always be 1

#### Scenario: Info metric reads app version from config
- GIVEN the LaunchPad app is installed
- WHEN the info metric is collected
- THEN the app version MUST be read from `IConfig::getAppValue(Application::APP_ID, 'installed_version', '0.0.0')`
- AND PHP version from `PHP_VERSION`
- AND Nextcloud version from `IConfig::getSystemValueString('version', '0.0.0')`

#### Scenario: Info metric with missing version
- GIVEN the app version is not set in config
- WHEN the info metric is collected
- THEN the version MUST default to "0.0.0"

### Requirement: Application Up Metric (REQ-PROM-003)

The system MUST expose an up metric indicating application health.

#### Scenario: Up metric when healthy
- GIVEN the application is running normally
- WHEN the metrics endpoint is called
- THEN the response MUST include:
  ```
  # HELP launchpad_up Whether the application is up
  # TYPE launchpad_up gauge
  launchpad_up 1
  ```

#### Scenario: Up metric always returns 1 if endpoint is reachable
- GIVEN the metrics endpoint is accessible
- WHEN the response is generated
- THEN `launchpad_up` MUST be 1 (if the endpoint can respond, the app is up)
- NOTE: The current implementation always returns 1. A degraded state (0) would only occur if the endpoint itself cannot respond.

### Requirement: Dashboard Count Metrics (REQ-PROM-004)

The system MUST expose dashboard count metrics grouped by type.

#### Scenario: Dashboard counts by type
- GIVEN 50 user dashboards and 5 admin templates exist
- WHEN the metrics endpoint is called
- THEN the response MUST include:
  ```
  # HELP launchpad_dashboards_total Total dashboards by type
  # TYPE launchpad_dashboards_total gauge
  launchpad_dashboards_total{type="user"} 50
  launchpad_dashboards_total{type="admin_template"} 5
  ```

#### Scenario: Dashboard counts with no dashboards
- GIVEN no dashboards exist in the database
- WHEN the metrics endpoint is called
- THEN the response MUST include both types with count 0:
  ```
  launchpad_dashboards_total{type="personal"} 0
  launchpad_dashboards_total{type="template"} 0
  ```
- NOTE: The fallback labels use "personal" and "template" when no data exists, while actual data uses the DB type values ("user", "admin_template").

#### Scenario: Dashboard count query failure
- GIVEN the database query for dashboards fails
- WHEN the metrics endpoint is called
- THEN the system MUST log a warning
- AND the response MUST include fallback values:
  ```
  launchpad_dashboards_total{type="personal"} 0
  launchpad_dashboards_total{type="template"} 0
  ```
- AND the error MUST NOT cause the entire metrics response to fail

### Requirement: Widget Placement Count Metric (REQ-PROM-005)

The system MUST expose the total number of widget placements.

#### Scenario: Widget placement count
- GIVEN 150 widget placements exist across all dashboards
- WHEN the metrics endpoint is called
- THEN the response MUST include:
  ```
  # HELP launchpad_widgets_total Total number of widget placements
  # TYPE launchpad_widgets_total gauge
  launchpad_widgets_total 150
  ```

#### Scenario: Widget count query failure
- GIVEN the database query for widget placements fails
- WHEN the metrics endpoint is called
- THEN the system MUST return 0 for the widget count
- AND log a warning

### Requirement: Tile Count Metric (REQ-PROM-006)

The system MUST expose the total number of tile definitions.

#### Scenario: Tile count
- GIVEN 25 tile definitions exist
- WHEN the metrics endpoint is called
- THEN the response MUST include:
  ```
  # HELP launchpad_tiles_total Total number of tiles
  # TYPE launchpad_tiles_total gauge
  launchpad_tiles_total 25
  ```

#### Scenario: Tile count query failure
- GIVEN the database query for tiles fails
- WHEN the metrics endpoint is called
- THEN the system MUST return 0 for the tile count
- AND log a warning

### Requirement: Health Check Endpoint (REQ-PROM-007)

The system MUST expose a **public** health check endpoint for monitoring and
container orchestration, rendered by the AppHost `GenericHealthController` from
the declarative `observability.health` block. The `/api/health` URL is
unchanged; the `health#index` route target is aliased to the engine generic via
a lazy factory in `Application::register()`.

#### Scenario: Healthy status
- GIVEN the database is accessible
- WHEN GET /index.php/apps/launchpad/api/health is called
- THEN the system MUST return HTTP 200 with JSON containing `status: "ok"`,
  `app: "launchpad"`, a `version` field, and `checks.database: "ok"` (ADR-006 shape)

#### Scenario: Health check is reachable without a login session
- GIVEN an unauthenticated monitoring probe (Kubernetes liveness / load balancer)
- WHEN GET /api/health is sent with no session and no CSRF token
- THEN the system MUST respond (the engine controller carries `#[PublicPage]` + `#[NoCSRFRequired]`)
- AND MUST NOT redirect to the login page

#### Scenario: Database failure
- GIVEN the database is not accessible
- WHEN GET /api/health is called
- THEN the system MUST report `checks.database: "error"` and a non-ok overall status

### Requirement: Metrics Collection Architecture (REQ-PROM-008)

Metrics and health MUST be rendered by the OpenRegister AppHost observability
engine, not by an app-local collector. The `observability` block in
`src/manifest.json` is the single source of truth for which checks and metrics
are exposed; the engine owns the exposition format and the auth posture so the
contract cannot drift per app (ADR-006 / ADR-040).

#### Scenario: Declarative metrics drive the output
- GIVEN the `observability.metrics` block declares `tableCount` descriptors over
  the allowlisted `launchpad_*` own-tables
- WHEN the metrics endpoint is called
- THEN each declared metric MUST be aggregated via COUNT (optionally GROUP BY)
  through the engine, and a single failed source MUST NOT fail the whole response

#### Scenario: Engine degrades safely without OpenRegister
- GIVEN OpenRegister is disabled or absent
- WHEN Nextcloud boots
- THEN LaunchPad MUST still boot (the engine wiring is lazy)
- AND a request to `/api/metrics` or `/api/health` MAY surface the OR-unavailable
  degraded state rather than fataling bootstrap

### Requirement: Metrics Endpoint Performance (REQ-PROM-010)

The metrics endpoint MUST respond quickly to avoid blocking Prometheus scrape intervals.

#### Scenario: Metrics response under load
- GIVEN a large installation with 10,000 dashboards, 50,000 widget placements, and 5,000 tiles
- WHEN the metrics endpoint is called
- THEN the response MUST return within 2 seconds
- AND database queries MUST use COUNT aggregation (not loading full entities)

#### Scenario: Concurrent scrapes
- GIVEN Prometheus scrapes metrics every 15 seconds
- WHEN two scrapes overlap
- THEN both requests MUST complete successfully
- AND no locking or caching issues MUST occur

## Non-Functional Requirements

- **Performance**: GET /api/metrics MUST return within 2 seconds for installations with up to 100,000 rows across all tables. COUNT queries MUST be used rather than loading entities.
- **Security**: The metrics endpoint MUST require admin authentication. No sensitive data (user IDs, passwords, API keys) MUST be exposed in metrics labels.
- **Reliability**: Individual metric collection failures MUST NOT cause the entire endpoint to fail. Fallback values (0) MUST be returned for failed queries.
- **Standards compliance**: Metrics MUST follow the Prometheus text exposition format (version 0.0.4). HELP and TYPE lines MUST be present for every metric.
- **Monitoring integration**: The health check endpoint MUST be usable by Kubernetes liveness/readiness probes and load balancer health checks.

### Current Implementation Status

**Fully implemented:**
- REQ-PROM-001 (Metrics Endpoint): `MetricsController::index()` in `lib/Controller/MetricsController.php` returns Prometheus text format with correct Content-Type header. Admin-only (no `#[NoAdminRequired]`). `@NoCSRFRequired` for external monitoring.
- REQ-PROM-002 (Application Info Metric): Version labels from `IConfig::getAppValue()`, `PHP_VERSION`, and system config.
- REQ-PROM-003 (Application Up Metric): Always returns 1.
- REQ-PROM-004 (Dashboard Count Metrics): SQL query with GROUP BY type. Fallback to 0 on error.
- REQ-PROM-005 (Widget Placement Count): `countTable('launchpad_widget_placements')`.
- REQ-PROM-006 (Tile Count): `countTable('launchpad_tiles')`.
- REQ-PROM-007 (Health Check): `HealthController::index()` in `lib/Controller/HealthController.php` with database connectivity check.
- REQ-PROM-008 (Architecture): `MetricsCollector` and `MetricsQueryService` exist as separate service classes alongside the controller.

**Not yet implemented:**
- REQ-PROM-009 (Active Users): No distinct user count metric.
- Standard metrics from original spec: `launchpad_requests_total` (counter), `launchpad_request_duration_seconds` (histogram), `launchpad_errors_total` (counter) are NOT implemented. These would require middleware/event listeners to track per-request metrics.

### Standards & References
- Prometheus text exposition format: https://prometheus.io/docs/instrumenting/exposition_formats/
- OpenMetrics specification: https://openmetrics.io/
- Nextcloud server monitoring patterns
- OpenRegister MetricsService and HeartbeatController as reference implementation
