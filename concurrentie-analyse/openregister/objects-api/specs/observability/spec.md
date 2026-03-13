---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# Observability — Objects API (Documentation View)

## Purpose
Full observability stack with OpenTelemetry metrics, structured logging, distributed tracing, and error monitoring. Enables monitoring of API performance and usage in production.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/installation/observability/

## OpenTelemetry Metrics

### Generic Metrics
- `http.server.duration` — HTTP request duration histogram (ms)
- `http.server.request.duration` — Future replacement (seconds, not yet active)

### Application Metrics

#### Accounts
| Metric | Type | Description |
|--------|------|-------------|
| `objects.auth.user_count` | Gauge (global) | Number of users by type (all/staff/superuser) |
| `objects.auth.login_failures` | Counter | Failed login attempts |
| `objects.auth.user_lockouts` | Counter | Account lockout events |
| `objects.auth.logins` | Counter | Successful logins |
| `objects.auth.logouts` | Counter | Logout events |

#### Object Operations
| Metric | Type | Description |
|--------|------|-------------|
| `objects.object.creates` | Counter | Objects created via API |
| `objects.object.updates` | Counter | Objects updated via API |
| `objects.object.deletes` | Counter | Objects deleted via API |

### OTel Configuration
- SDK enabled by default
- Set `OTEL_SDK_DISABLED=true` to disable
- Uses standard OpenTelemetry export protocols

## Logging
- Structured JSON logging by default (`LOG_FORMAT_CONSOLE=json`)
- Request logging (`ENABLE_STRUCTLOG_REQUESTS=true`)
- Outgoing request logging with optional DB storage
- Log levels: CRITICAL, ERROR, WARNING, INFO, DEBUG

## Tracing
- Elastic APM integration
- Distributed tracing across microservices
- Vendor-agnostic OTel tracing under development

## Error Monitoring
- Sentry integration via `SENTRY_DSN`

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Metrics | OpenTelemetry (CRUD counters, auth metrics) | Nextcloud logging |
| Logging | Structured JSON, request logging | Nextcloud logger |
| Tracing | Elastic APM, OTel tracing | Not available |
| Error monitoring | Sentry | Nextcloud error handling |
| CRUD counters | Yes (creates/updates/deletes) | Not available |
| Auth monitoring | Login/logout/lockout metrics | Nextcloud auth logs |

**Already in OpenRegister**: Basic logging via Nextcloud
**Not yet in OpenRegister**: OpenTelemetry metrics, CRUD operation counters, distributed tracing, Sentry integration, structured JSON logging, auth metrics
