# Open Product -- Architecture & Technical Deep Dive

## Overview

Open Product is a centralized Dutch municipal application for managing **product types** (definitions of government services) and **products** (individual instances of those services held by citizens/businesses). Built by Maykin B.V. according to Common Ground principles, it exposes two REST APIs for integration with citizen portals and form platforms.

**Repository:** `maykinmedia/open-product` (note: GitHub repo is `open-product`, PyPI/Docker are `open-product`, RTD is `open-producten`)

## Architecture Principles

### Monolithic Two-API Design

Unlike many Common Ground components that split each resource into its own microservice, Open Product **combines** the Producttype API and Producten API into a single deployment. The rationale (from their architecture docs):

> Open Product combines the Producttype API and Producten API that are essentially tightly coupled, into a single product. This allows for major performance improvements since related objects (like a ProductType for a Product) do not need to fetched over the network but can be directly obtained from the database. This also guarantees data integrity on database level, rather than on service (API) level.

This is a deliberate trade-off: better performance and data integrity, but less microservice purity.

### External References via URN/URL

Open Product does **not** copy data from external systems (Common Ground principle). Instead, it stores references as URN and/or URL pairs. A URN mapping system allows automatic resolution between the two formats:

- **URN format:** `<organisatie>:<systeem>:<component>:<resource>:<identificatie>`
- **Example:** `maykin:zaken-sociaal:zrc:zaak:d42613cd-ee22-4455-808c-c19c7b8442a1`
- **Mapping config:** `REQUIRE_URL_URN_MAPPING` and `REQUIRE_URN_URL_MAPPING` environment variables

This means Open Product has **no authentication credentials** for external systems like Open Zaak -- validation of references is the client's responsibility.

### Caching Strategy

The architecture doc states: "Open Product uses caching wherever possible to prevent needless requests over the network to fetch data from external APIs." Redis is the caching backend.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | Python 3.12+ (94.9% of codebase) |
| Framework | Django 5.2.x |
| API Framework | Django REST Framework (DRF) |
| API Spec Generation | drf-spectacular (OAS 3.0) |
| VNG Tooling | VNG-API-common (commonground-api-common) |
| Database | PostgreSQL 14+ (required since v1.2.0) |
| Cache | Redis |
| Task Queue | Celery (with Redis broker) |
| Notifications | Open Notificaties (VNG Notificaties API) |
| Auth | Token-based + OpenID Connect (mozilla-django-oidc-db) |
| Observability | Elastic APM + OpenTelemetry (since v1.5.0) |
| Logging | structlog (structured JSON logging, since v1.3.0) |
| Code Quality | Ruff linter, pre-commit hooks |
| Frontend Assets | Webpack, SCSS, Node.js 24+ |
| Container | Docker, docker-compose |
| License | EUPL 1.2 |

## Three Django Apps

Open Product consists of three internal Django apps:

1. **producttypen** -- Product type definitions, themes, pricing, content, links, actions, locations, organizations
2. **producten** -- Product instances, owners, documents, cases, tasks
3. **locaties** -- Locations and organizations (shared reference data)

Additional internal modules: `accounts`, `conf`, `logging`, `patches`, `setup_configuration`, `urn`, `utils`.

## Deployment Architecture

```
[Client / Open Inwoner / Open Formulieren]
          |
          v
   [Open Product API]  --- [PostgreSQL 14+]
          |                 [Redis Cache]
          |                 [Celery Workers]
          |
          v
   [Open Notificaties]  (for event publishing)
          |
          v
   [External APIs]
   - Open Zaak (zaken, documenten)
   - Catalogi API (zaaktypen)
   - External DMN engines (pricing rules)
   - Klantinteracties API (klant/partij)
```

## Configuration

### Environment Variables (Key Ones)

- `SECRET_KEY`, `ALLOWED_HOSTS` -- Django basics
- `CACHE_DEFAULT`, `CACHE_AXES` -- Redis addresses (mandatory in Docker)
- `DB_*` -- PostgreSQL connection (with experimental connection pooling)
- `CELERY_RESULT_BACKEND` -- Redis for Celery
- `CORS_ALLOW_ALL_ORIGINS`, `CORS_ALLOWED_ORIGINS` -- CORS config
- `ELASTIC_APM_*` -- Elastic APM integration
- `OTEL_SDK_DISABLED` -- OpenTelemetry toggle (enabled by default since v1.5.0)
- `REQUIRE_URN_URL_MAPPING`, `REQUIRE_URL_URN_MAPPING` -- URN validation strictness

### Setup Configuration

Open Product supports `django-setup-configuration` for automated provisioning of OIDC providers, clients, and other settings via YAML files.

## Comparison with OpenRegister Architecture

| Aspect | Open Product | OpenRegister |
|--------|-------------|--------------|
| Language | Python 3.12 / Django 5.2 | PHP 8.x / Nextcloud |
| Database | PostgreSQL 14+ | PostgreSQL/MySQL/SQLite (via Nextcloud) |
| Deployment | Standalone Docker container | Nextcloud app (inside Nextcloud container) |
| API style | Two fixed REST APIs (OAS 3.0) | Dynamic APIs generated from schemas |
| Data model | Fixed (product types + products) | Flexible (any JSON Schema) |
| Task queue | Celery + Redis | n8n workflows |
| Cache | Redis | Nextcloud APCu + Redis |
| Auth | Token + OIDC | Nextcloud user system + API keys |
| Notifications | VNG Notificaties API | Nextcloud notification system |
| Observability | Elastic APM + OTEL | Nextcloud logging |
| Search | None (basic filtering only) | Full-text + faceted + semantic search |
| Multi-tenancy | None | Native (Nextcloud groups/organizations) |
| Frontend | Django admin only | Full Nextcloud Vue.js UI |
