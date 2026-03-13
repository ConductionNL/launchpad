# Objects API — Deployment and Configuration

## Prerequisites

- **PostgreSQL 14+** with PostGIS extension (geo-capable)
- **Redis 5/6/7** for cache and task queue
- **Python 3.12+**
- Docker containers available on Docker Hub

## Supported Database Matrix

| | Postgres 14 | Postgres 15 | Postgres 16 | Postgres 17 |
|---|---|---|---|---|
| PostGIS 3.2 | Yes | No | No | No |
| PostGIS 3.5 | Yes | Yes | Yes | Yes |

## Hardware Requirements

| Concurrent Users | Expected RPS | CPUs | RAM (GB) |
|---|---|---|---|
| 100 | 15 | 4 | 8 |
| 250 | 30 | 6 | 12 |
| 500 | 60 | 12 | 24 |
| 1,000 | 120 | 14 | 28 |
| 2,000 | 235 | 16 | 32 |

### Database Performance (pgbench TPS minimums)

| Max objects/day | Required TPS |
|---|---|
| 100 | 200 |
| 1,000 | 500 |
| 10,000 | 1,000 |
| >10,000 | 1,500 |

## Key Environment Variables

### Required
- `SECRET_KEY` — Cryptographic secret
- `ALLOWED_HOSTS` — Comma-separated domains
- `CACHE_DEFAULT` — Redis address (default: localhost:6379/0)
- `CACHE_AXES` — Redis for brute force protection
- `EMAIL_HOST` — Outgoing email server

### Database
- `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`, `DB_PORT`
- `DB_CONN_MAX_AGE` — Connection lifetime (default: 60s)
- `DB_POOL_ENABLED` — Experimental connection pooling

### Celery (Notifications)
- `CELERY_RESULT_BACKEND` — Redis for task queue (default: redis://localhost:6379/1)
- `CELERY_TASK_HARD_TIME_LIMIT` — Task timeout (default: 900s)

### Optional
- `NOTIFICATIONS_DISABLED` — Disable notifications (default: False)
- `OBJECTS_ADMIN_SEARCH_DISABLED` — Disable admin search
- `ENABLE_CLOUD_EVENTS` — Experimental cloud events
- `DISABLE_2FA` — Disable two-factor auth
- `SENTRY_DSN` — Error monitoring

### Observability
- `ELASTIC_APM_SERVER_URL` — Elastic APM endpoint
- `LOG_FORMAT_CONSOLE` — json or plain_console
- `ENABLE_STRUCTLOG_REQUESTS` — Structured logging
- OpenTelemetry SDK enabled by default; set `OTEL_SDK_DISABLED=true` to disable

### CORS
- `CORS_ALLOW_ALL_ORIGINS` — Allow all origins (default: False)
- `CORS_ALLOWED_ORIGINS` — Explicit origin list

### OIDC (Admin SSO)
Supports OpenID Connect for admin interface login via Keycloak or similar providers.

## Deployment Options

1. **Docker Compose** — Quickstart with provided docker-compose.yml
2. **Kubernetes** — Helm charts with recommended 2+ load balancer replicas
3. **VPS** — Manual uwsgi/gunicorn setup with .env file

## Configuration via CLI

Both APIs support `setup_configuration` management command for YAML-based configuration:
- Sites configuration
- OIDC provider/client configuration
- Token auth configuration

## Initial Superuser (Docker)

Set environment variables:
- `OBJECTS_SUPERUSER_USERNAME`
- `OBJECTS_SUPERUSER_EMAIL`
- `OBJECTS_SUPERUSER_PASSWORD`

## Versioning Policy

- New releases every two months
- Major releases every two years
- Each major supported 24 months after next major
- Only 2 most recent minor versions supported per major
- 6-month transition period for API versions
