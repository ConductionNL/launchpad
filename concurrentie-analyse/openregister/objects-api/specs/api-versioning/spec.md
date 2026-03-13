---
status: draft
source: competitive-analysis
competitor: objects-api
analyzed_date: 2026-03-12
---

# API Versioning — Objects API

## Purpose
URL-based API versioning with namespace versioning. Currently only v2 is supported. The API version (2.6.0) is separate from the URL version (v2).

- **Product**: Objects API
- **Category**: API Design
- **Relevance to OpenRegister**: OpenRegister API is unversioned

## Implementation
- URL prefix: `/api/v2/`
- DRF versioning: `NamespaceVersioning`
- API version: `2.6.0` (in OpenAPI spec)
- Allowed versions: `("v2",)` — v1 removed
- API version header: `APIVersionHeaderMiddleware`
- OpenAPI schema: auto-generated via drf-spectacular

**CamelCase API fields**: Django models use snake_case but API uses camelCase (e.g., `name_plural` -> `namePlural`, `start_at` -> `startAt`). This is done via `extra_kwargs` in serializers, not an automatic converter.

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Versioning | URL namespace (/api/v2/) | No versioning |
| Schema | Auto-generated OpenAPI 3.x | MCP discovery API |
| Field naming | camelCase in API, snake_case in DB | camelCase in API |
| Documentation | ReDoc at /api/v2/schema/ | N/A |

**Already in OpenRegister**: API with documentation (MCP)
**Not yet in OpenRegister**: URL-based API versioning, auto-generated OpenAPI schema, version headers, ReDoc documentation endpoint
