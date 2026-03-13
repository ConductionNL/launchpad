---
status: draft
source: competitive-analysis
competitor: objects-api
analyzed_date: 2026-03-12
---

# Observability & Metrics — Objects API

## Purpose
Comprehensive observability via OpenTelemetry metrics counters, structured logging (structlog), and integration with Prometheus/Grafana/Promtail stack.

- **Product**: Objects API
- **Category**: Operations / Monitoring
- **Relevance to OpenRegister**: OpenRegister has basic Nextcloud logging; this shows production-grade observability

## Architecture Overview
Uses `opentelemetry` metrics SDK for counters. Every create/update/delete operation on both objects and objecttypes increments counters. Structured logging via `structlog` captures operation details including token identifier and application.

**Counters**:
| Meter | Counter | Description |
|-------|---------|-------------|
| objects.api | objects.object.creates | Objects created |
| objects.api | objects.object.updates | Objects updated |
| objects.api | objects.object.deletes | Objects deleted |
| objecttypes.api.v2 | objecttypes.objecttype.creates | Objecttypes created |
| objecttypes.api.v2 | objecttypes.objecttype.updates | Objecttypes updated |
| objecttypes.api.v2 | objecttypes.objecttype.deletes | Objecttypes deleted |

**Structured log events**: `object_created`, `object_updated`, `objecttype_created`, `objecttype_updated`, `objecttype_deleted`, `object_version_created`, etc. Each includes `token_identifier` and `token_application`.

**Docker observability stack** (docker-compose.observability.yaml):
- Prometheus for metrics scraping
- Grafana for dashboards
- Promtail for log aggregation
- OpenTelemetry Collector for trace/metric routing

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Metrics | OpenTelemetry counters | Nextcloud logging |
| Logging | structlog structured events | Nextcloud logger |
| Dashboards | Grafana integration | None |
| Tracing | OpenTelemetry traces | None |
| Log aggregation | Promtail | None |

**Already in OpenRegister**: Basic Nextcloud logging
**Not yet in OpenRegister**: OpenTelemetry metrics counters, structured logging with operation context, Prometheus/Grafana dashboards, distributed tracing, log aggregation
