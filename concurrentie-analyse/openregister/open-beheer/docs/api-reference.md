# Open Beheer -- API Reference

## Overview

Open Beheer exposes a BFF (Backend-for-Frontend) REST API at `/api/v1/`. This API is
consumed exclusively by the React frontend. It is **not** a standard ZGW API; it is
a proxy layer that translates between the frontend's needs and the upstream Catalogi
API.

**OpenAPI Spec:** `backend/openbeheer-oas.yaml` (317KB)
**Interactive docs:** `/api/docs/` (ReDoc)
**JSON Schema:** `/api/v1/` endpoint
**OpenAPI schema:** `/api/v1/schema/`

## Authentication Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/auth/login/` | Session-based login |
| POST | `/api/v1/auth/logout/` | Logout |
| GET | `/api/v1/whoami/` | Current user info |
| GET | `/api/v1/oidc-info/` | OIDC configuration for frontend |

Authentication uses Django session cookies. OIDC (OpenID Connect) is supported for
SSO via Mozilla Django OIDC.

## Service Management

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/service/` | List configured ZGW services |

Services represent connections to external ZGW backends (e.g., an Open Zaak instance).
Each service has a slug used as a path parameter in all subsequent endpoints.

## Catalogi

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/service/{slug}/catalogi/` | List catalogi for a service |

## Zaaktypen (Case Types)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/service/{slug}/zaaktypen/` | List zaaktypen |
| POST | `/api/v1/service/{slug}/zaaktypen/` | Create zaaktype |
| GET | `/api/v1/service/{slug}/zaaktypen/{uuid}/` | Get zaaktype detail |
| PATCH | `/api/v1/service/{slug}/zaaktypen/{uuid}/` | Update zaaktype |
| PUT | `/api/v1/service/{slug}/zaaktypen/{uuid}/` | Replace zaaktype |
| DELETE | `/api/v1/service/{slug}/zaaktypen/{uuid}/` | Delete zaaktype |

### Nested Zaaktype Resources

All nested under `/api/v1/service/{slug}/zaaktypen/{uuid}/`:

| Method | Path | Description |
|--------|------|-------------|
| GET/POST | `.../statustypen/` | List/create status types |
| GET/POST | `.../resultaattypen/` | List/create result types |
| GET/POST | `.../roltypen/` | List/create role types |
| GET/POST | `.../besluittypen/` | List/create decision types |
| GET/POST | `.../eigenschappen/` | List/create properties |
| GET/POST | `.../zaakobjecttypen/` | List/create case-object type relations |
| GET/POST | `.../zaaktypeinformatieobjecttypen/` | List/create case-document relations |

## InformatieObjectTypen (Document Types)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/service/{slug}/informatieobjecttypen/` | List document types |
| POST | `/api/v1/service/{slug}/informatieobjecttypen/` | Create document type |
| GET | `/api/v1/service/{slug}/informatieobjecttypen/{uuid}/` | Get document type detail |
| PATCH | `/api/v1/service/{slug}/informatieobjecttypen/{uuid}/` | Update document type |
| PUT | `/api/v1/service/{slug}/informatieobjecttypen/{uuid}/` | Replace document type |
| DELETE | `/api/v1/service/{slug}/informatieobjecttypen/{uuid}/` | Delete document type |

## Zaaktype Templates

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/template/zaaktype/` | List zaaktype templates |
| GET | `/api/v1/template/zaaktype/{uuid}/` | Get template detail |

Templates provide pre-configured zaaktype definitions that can be used as starting
points when creating new zaaktypen.

## Health Checks

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/health-checks/` | Service health status |

## Response Format

Responses use a custom envelope:

**List responses** (`OBList[T]`):
```json
{
  "count": 42,
  "next": "...",
  "previous": "...",
  "results": [...]
}
```

**Detail responses** (`DetailResponse[T]`):
```json
{
  "data": { ... },
  "fields": [ ... ],
  "fieldsets": [ ... ]
}
```

The `fields` array provides metadata about each field (label, type, required,
choices, etc.) enabling the frontend to dynamically render forms without hardcoding
field definitions.

## Error Format

Errors follow the ZGW error format (`ZGWError`):
```json
{
  "type": "...",
  "code": "...",
  "title": "...",
  "status": 400,
  "detail": "...",
  "instance": "..."
}
```

## Technology Notes

- Serialization: **msgspec** structs (not DRF serializers)
- Schema generation: **drf-spectacular**
- Authentication: Django sessions + OIDC
- All domain data operations are proxied to upstream ZGW APIs; the BFF stores nothing
