# ADR-015: Per-App Prometheus Metrics and Health Checks

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, design, tasks
**Last updated:** 2026-03-20

## Context

Production observability is critical for municipal SaaS serving 342 municipalities. Each app needs standardized metrics and health endpoints. The pattern is consistent across all Conduction apps: MetricsController exposes /api/metrics in Prometheus text format, HealthController exposes /api/health as JSON.

## Decision

- Each Conduction Nextcloud app MUST expose a `/api/metrics` endpoint returning Prometheus text exposition format.
- Each app MUST expose a `/api/health` endpoint returning JSON health status.
- Metrics endpoints MUST require admin authentication.
- Health endpoints SHOULD be accessible without authentication for load balancer checks.
- App-specific metrics MUST use the app name as prefix (e.g., `openregister_objects_total`, `pipelinq_leads_total`).
- Standard metrics every app MUST expose: `{app}_health_status`, `{app}_info` (with version label).
- Standard metrics every app SHOULD expose: request count, error count, response time histogram.
- Each app MUST have a `prometheus-metrics` spec defining its app-specific metrics.
- MetricsController MUST follow the Controller → Service → Mapper pattern (ADR-008).
- Health checks MUST verify OpenRegister connectivity for apps that depend on it.

## Consequences

- Every new app gets a prometheus-metrics spec.
- The production-observability spec in OpenRegister defines the core infrastructure; app specs adopt it.

## Exceptions

- nextcloud-vue — library, not a deployed app. No metrics endpoint needed.
