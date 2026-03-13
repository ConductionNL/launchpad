# Health Checks

## Feature Summary

Open Beheer provides health check endpoints for monitoring and orchestration
(Kubernetes liveness/readiness probes). It can verify connectivity to its database,
cache, and configured external services.

## How It Works in Open Beheer

### Endpoint

`GET /api/v1/health-checks/`

### What It Checks

Based on the `django-health-check` library:
- Database connectivity (PostgreSQL)
- Cache connectivity (Redis)
- Migrations status (all applied)
- Potentially: external service connectivity

### Configuration

Documented in `docs/developers/health-checks.rst`. Standard Django health check
pattern with registered backends.

## Already in OpenRegister

- **Nextcloud health checks**: Nextcloud has built-in status endpoints
- **Database connectivity**: Verified through Nextcloud's standard checks
- **App status**: Nextcloud reports app enabled/disabled state

## Not Yet in OpenRegister

- **App-level health check endpoint**: OpenRegister does not expose its own `/health` endpoint. It relies on Nextcloud's platform-level health checks.
- **External service connectivity checks**: No endpoint to verify connections to configured external services (e.g., Elasticsearch, n8n)
- **Migration status check**: No dedicated endpoint to verify database migrations are current
