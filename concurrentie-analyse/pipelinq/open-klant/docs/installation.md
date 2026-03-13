# Open Klant -- Installation

**Source**: https://open-klant.readthedocs.io/en/latest/installation/index.html

## Quick Start with Docker

```bash
wget https://raw.githubusercontent.com/maykinmedia/open-klant/master/docker-compose.yml
docker-compose up -d --no-build
docker-compose exec web src/manage.py loaddata klantinteracties contactgegevens
docker-compose exec web src/manage.py createsuperuser
```

Access via `http://localhost:8000/` for admin interface and API.

## Prerequisites

### PostgreSQL
- **Minimum version**: PostgreSQL 14 or higher (since v2.10.0)
- **Supported versions**: 14, 15, 16, 17
- PostgreSQL 13 and lower are incompatible

### Redis
- Used as cache backend and task queue broker
- Supported versions: 5, 6, 7

### RabbitMQ
- Functions as message broker
- Confirmed support for RabbitMQ 4.x

## Configuration

Configuration is done through:

1. **Environment variables** -- Primary configuration method
2. **Admin interface** -- Runtime configuration for OIDC, tokens, etc.
3. **CLI** -- Management commands for setup
4. **Referentielijsten API** -- Integration for reference data (kanaal validation)

### Key Environment Variables

(Note: the full environment configuration reference page was not accessible via ReadTheDocs but is documented in the repository)

Common environment variables include:
- `DJANGO_SETTINGS_MODULE` -- Django settings module
- `SECRET_KEY` -- Django secret key
- `DATABASE_URL` / `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_NAME`, `DB_PASSWORD` -- Database connection
- `CACHE_DEFAULT` / `REDIS_URL` -- Redis connection
- `SITE_DOMAIN` -- Required since v2.7.0, the domain name of the deployment
- `ALLOWED_HOSTS` -- Django allowed hosts
- `IS_HTTPS` -- Whether the deployment uses HTTPS

## Migration (v1 to v2)

The `migrate_to_v2` management command migrates Klant instances from version 1.0.0 to 2.4.0+:

```bash
CLIENT_ID="openklant-v1-client-id" SECRET="openklant-v1-secret" \
  ./src/manage.py migrate_to_v2 \
  https://example.openklant.nl \
  https://example.klantinteracties.nl
```

Authentication via `CLIENT_ID` + `SECRET`, or `ACCESS_TOKEN`. A temporary token is created for the v2 instance and removed after execution.

Additional command `migrate_to_v2_phonenumbers` migrates phone numbers after the main migration.

## Observability

Since v2.14.0, Open Klant supports OpenTelemetry for application metrics:
- Prometheus metrics endpoint
- Grafana dashboards
- Promtail log aggregation
- structlog for structured logging (since v2.10.0)

## Container Health Checks

Docker health checks are available for monitoring container status.

## Scripts

### dump_data.sh

Exports data from Open Klant components to SQL or CSV:

```bash
./dump_data.sh                           # Full dump (2 SQL files)
./dump_data.sh klantinteracties          # Component-specific
./dump_data.sh --csv                     # CSV export
./dump_data.sh --data-only               # Data only (no schema)
./dump_data.sh --schema-only             # Schema only
./dump_data.sh --combined                # Single combined file
```

Environment variables: `DB_HOST` (default: db), `DB_PORT` (5432), `DB_USER` (openklant), `DB_NAME` (openklant), `DB_PASSWORD` (empty).
