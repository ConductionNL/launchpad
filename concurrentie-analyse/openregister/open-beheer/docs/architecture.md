# Open Beheer -- Architecture

## High-Level Architecture

Open Beheer is a **Backend-for-Frontend (BFF)** application with a clear two-tier
structure:

```
+-----------------+       +------------------+       +-------------------+
|  React SPA      | <-->  |  Django BFF      | <-->  |  External APIs    |
|  (Vite + TS)    |       |  (Python 3.11+)  |       |  (ZTC, Selectie-  |
|  Port 5173 dev  |       |  Port 8000 dev   |       |   lijst, Object-  |
|                 |       |                  |       |   typen)          |
+-----------------+       +------------------+       +-------------------+
                                  |
                          +-------+-------+
                          |  PostgreSQL   |
                          |  Redis        |
                          +---------------+
```

Open Beheer stores **no domain data** of its own. The PostgreSQL database holds only
user accounts, service configurations, and OIDC settings. All domain data
(zaaktypen, statustypen, etc.) lives in the upstream Catalogi API (Open Zaak).

## Backend (Django BFF)

**Location:** `backend/src/openbeheer/`

The Django backend acts as a proxy layer between the React frontend and one or more
ZGW (Zaakgericht Werken) API services. It:

1. Authenticates users (session + OIDC)
2. Maintains configured connections to external ZGW services
3. Proxies API calls to the Catalogi API (ZTC), Selectielijst API, and Objecttypen API
4. Transforms responses into a frontend-friendly format with field metadata
5. Handles pagination across external APIs

### Key Django Apps

| App | Purpose |
|-----|---------|
| `accounts` | User model and authentication |
| `api` | BFF REST API (DRF + drf-spectacular) |
| `catalogi` | Catalogus listing from ZTC service |
| `zaaktype` | Zaaktype CRUD proxy + templates |
| `statustypen` | Statustype nested CRUD |
| `resultaattypen` | Resultaattype nested CRUD |
| `roltypen` | Roltype nested CRUD |
| `besluittypen` | Besluittype nested CRUD |
| `eigenschappen` | Eigenschap (property) nested CRUD |
| `informatieobjecttypen` | InformatieObjectType CRUD proxy |
| `zaakobjecttypen` | ZaakObjectType nested CRUD |
| `zaaktypeinformatieobjecttypen` | Relation zaaktype <-> informatieobjecttype |
| `services` | ZGW service connection management |
| `config` | App configuration (setup_configuration) |
| `health_checks` | Liveness and readiness probes |

### External API Clients

Defined in `backend/src/openbeheer/clients.py`:

- **ZTC client** -- Catalogi API (Open Zaak). Sets `Accept-Crs: EPSG:4326`.
- **Selectielijst client** -- National selection list for archiving rules.
- **Objecttypen client** -- Object types API for zaakobjecttypen.

All clients are cached and invalidated via Django signals when `Service` or
`APIConfig` models change. A logging wrapper captures request/response details.
Pagination is handled by `iter_pages()` which follows `next` pointers.

### Serialization

Uses **msgspec** (not DRF serializers) for high-performance JSON encoding/decoding
with Python dataclass-like `Struct` types. The API views are generic:
`ListView[P, T, S]` and `DetailView[T]` with type parameters for params, entity,
and schema.

## Frontend (React SPA)

**Location:** `frontend/`

| Technology | Version |
|-----------|---------|
| React | 19.2 |
| React Router | 7.5 |
| TypeScript | 5.7 |
| Vite | 6.3 |
| Storybook | 8.6 |
| Vitest | (testing) |
| Playwright | (E2E) |

### UI Component Library

The frontend uses **@maykin/admin-ui** -- Maykin's own component library that
provides the overall layout (BaseTemplate, navigation, forms, tables).

### Key Frontend Modules

| Directory | Purpose |
|-----------|---------|
| `src/api/` | API client modules: `auth.ts`, `catalogi.ts`, `service.ts`, `zaaktype.ts`, `request.ts` |
| `src/pages/` | Page components: zaaktypen, zaaktype, zaaktypecreate, informatieobjecttypen, informatieobjecttype, informatieobjecttypecreate, login, logout, service |
| `src/views/` | Reusable view patterns: `ListView`, `CreateView` |
| `src/components/` | UI components: `VersionSelector`, `archiveform`, `profile`, `related`, `zaaktypefilter` |
| `src/types/` | Auto-generated TypeScript types from OAS: `api.d.ts` (228KB), `selectielijst.d.ts`, `zaaktype.d.ts`, `user.d.ts` |
| `src/hooks/` | Custom React hooks |
| `src/loaders/` | React Router data loaders |
| `src/lib/` | Shared utilities |
| `src/fixtures/` | Test fixtures / MSW mocks |

### Type Generation

Types are auto-generated from the OpenAPI spec using `openapi-typescript 7.8`.
The command `npm run update-types` regenerates `frontend/src/types/api.d.ts` from
`backend/openbeheer-oas.yaml` (317KB OAS file).

## Deployment

### Docker Compose (Development)

```
services:
  db:        PostgreSQL 17
  redis:     Redis 6
  web:       Django app (DJANGO_SETTINGS_MODULE=openbeheer.conf.docker)
  nginx:     Reverse proxy, port 9000
```

### Environment Variables (Key)

- `DJANGO_SETTINGS_MODULE` -- `openbeheer.conf.docker` for containers
- `DB_NAME`, `DB_USER`, `DB_HOST` -- PostgreSQL connection
- `CACHE_DEFAULT`, `CACHE_AXES` -- Redis connection
- `SECRET_KEY` -- Django secret
- `MYKN_API_URL`, `MYKN_API_PATH` -- BFF API base URL/path
- `TWO_FACTOR_FORCE_OTP_ADMIN` -- 2FA enforcement
- `CSRF_TRUSTED_ORIGINS` -- CORS/CSRF allowlist

### Production

- Docker image: `maykinmedia/open-beheer` on Docker Hub
- Nginx reverse proxy in front of Django
- OIDC authentication recommended
- `django-setup-configuration` for automated setup (OIDC, ZGW services, API config)

## Data Flow

```
User -> React SPA -> BFF API -> ZTC Service (Open Zaak Catalogi API)
                             -> Selectielijst API
                             -> Objecttypen API
```

1. User selects a **Service** (= a configured ZGW backend)
2. User selects a **Catalogus** within that service
3. User browses/creates/edits **Zaaktypen** or **InformatieObjectTypen**
4. For each Zaaktype, user manages nested resources: statustypen, resultaattypen,
   roltypen, besluittypen, eigenschappen, zaakobjecttypen, zaaktypeinformatieobjecttypen
5. All operations are proxied through the BFF to the upstream ZTC API
