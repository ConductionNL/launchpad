# Prometheus Metrics - Design Document

## Architecture

### Backend
- **Controller**: `Controller\MetricsController` - Metrics endpoint (admin-only, no CSRF)
- **Controller**: `Controller\HealthController` - Health check endpoint
- **Service**: `Service\MetricsCollector` - Orchestrates metric collection
- **Service**: `Service\MetricsQueryService` - Database queries for counts

### Endpoints
- `GET /api/metrics` - Prometheus text exposition format (admin-only)
- `GET /api/health` - Health check (database connectivity)

### Metrics Exposed
- `launchpad_info{version, php_version, nextcloud_version}` - App info gauge
- `launchpad_up` - Application up gauge
- `launchpad_dashboards_total{type}` - Dashboard count by type (personal/template)
- `launchpad_widgets_total` - Total widget placements
- `launchpad_tiles_total` - Total tiles

### Key Design Decisions
- Metrics computed on-demand (no persistent metrics storage)
- Prometheus text exposition format 0.0.4
- Content-Type: text/plain; version=0.0.4; charset=utf-8
- @NoCSRFRequired for external monitoring tool access
- @AdminRequired for security (metrics not exposed to regular users)
- Health endpoint checks database connectivity
