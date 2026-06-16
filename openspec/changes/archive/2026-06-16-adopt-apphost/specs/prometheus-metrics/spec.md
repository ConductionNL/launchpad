# Prometheus Metrics — AppHost observability delta

## MODIFIED Requirements

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

## REMOVED Requirements

### Requirement: Active Users Metric (REQ-PROM-009)

**Reason**: Never implemented (the prior spec already noted "NOT currently
implemented"); not part of the bespoke output and out of scope for the
parity-preserving engine adoption. May be re-added later as a declarative
`tableCount` descriptor with `groupBy`.

**Migration**: None — the metric was never emitted.
