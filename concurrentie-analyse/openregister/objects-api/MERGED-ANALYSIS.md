# Objects API + Objecttypes API — Merged Competitive Analysis

**Analyzed**: 2026-03-12
**Sources**: Codebase (every file), Documentation (Read the Docs + VNG), Browser walkthrough (25 screenshots)
**Verdict**: Most direct competitor to OpenRegister — nearly identical concept, different architectural approach

---

## Executive Summary

The Maykin Media Objects API + Objecttypes API is a Python/Django REST API pair that stores generic JSON objects validated against JSON Schema definitions. It maps almost 1:1 to OpenRegister's concept:

| Objects API Concept | OpenRegister Equivalent |
|---|---|
| Objecttype | Schema |
| ObjecttypeVersion | Schema (versioned) |
| Object | Object |
| ObjectRecord | Object (with audit trail) |
| Token + Permission | API token + RBAC |
| — (no concept) | Register |

**Key strategic difference**: Objects API is a headless API-only backend with Django Admin. OpenRegister is a full Nextcloud app with Vue.js frontend, file management, and platform integration.

---

## Feature Inventory — What They Have

### Features Objects API has that OpenRegister ALREADY has

| Feature | Objects API Implementation | OpenRegister Implementation |
|---------|--------------------------|---------------------------|
| JSON Schema validation | jsonschema library, validates data on create/update | JSON Schema validation on object save |
| REST API (CRUD) | DRF ViewSets, full CRUD on /objects and /objecttypes | Nextcloud API routes, full CRUD |
| Token-based auth | Custom TokenAuth model with scopes | Nextcloud auth + API tokens |
| Role-based permissions | Per-objecttype read/read_and_write permissions | Nextcloud RBAC + per-register permissions |
| Search/filtering | Django ORM filters on date, type, registration date | Full-text search + faceted search (Solr/ES) |
| Ordering/sorting | DRF OrderingFilter on record_data JSON fields | Sort on any field |
| Pagination | PageNumberPagination (default 100) | Nextcloud pagination |
| Audit trail | ObjectRecord append-only history | Audit log on all changes |
| Soft delete handling | Records track deletion metadata | Soft deletes with restore |
| Multi-tenancy | Token-scoped objecttype access | Register-based multi-tenancy |
| Admin interface | Django Admin with inline versions | Vue.js admin UI |
| OpenAPI spec | Auto-generated via drf-spectacular | Auto-generated API docs |
| Webhooks/notifications | VNG Notifications API integration | Webhook support |
| Import/export | Objecttype bulk import | CSV/JSON import/export |
| Environment config | 50+ environment variables | Nextcloud IAppConfig |
| Health checks | /api/v2/objects endpoint | Nextcloud app health |

### Features Objects API has that OpenRegister DOES NOT have (gaps)

| Feature | Objects API Implementation | Priority for OpenRegister |
|---------|--------------------------|--------------------------|
| **Bi-temporal history** | Material time (start_at/end_at) + formal time (registration_at). StUF 03.01 standard. Append-only ObjectRecord chain with correction pointers. | HIGH — Dutch government requirement |
| **Schema version lifecycle** | Draft → Published → New Version workflow. Only published versions validate objects. | MEDIUM — we have schemas but no lifecycle |
| **Field-level authorization** | Per-token field restrictions using `glom` library. Unauthorized fields stripped from response, listed in `X-Unauthorized-Fields` header. | MEDIUM — we have object-level, not field-level |
| **PostGIS geometry** | Native GeometryField with `Content-Crs`/`Accept-Crs` headers. Within-polygon spatial queries. | LOW — niche use case, but VNG requires it |
| **GIN-indexed JSON querying** | `data_attrs` filter with 10+ operators (exact, gt, lt, icontains, has_key, jsonpath). PostgreSQL containment `@>`. | MEDIUM — we use search engines instead |
| **CloudEvents for zaak linking** | Async Celery tasks diff record references, emit `objectType~gekoppeld`/`ontkoppeld` events. | LOW — specific to ZGW ecosystem |
| **Merge PATCH (RFC 7396)** | Modified merge patch that preserves `null` values (null = delete requires `__empty__`). | LOW — PATCH works differently in NC |
| **OpenTelemetry metrics** | Built-in Prometheus counters for CRUD operations. | LOW — nice to have for observability |
| **OIDC/SSO admin login** | mozilla-django-oidc integration for admin interface. | LOW — Nextcloud handles auth |
| **VNG API compliance** | Full VNG API Strategy compliance, registered standard. | HIGH — competitive requirement |
| **Sparse field selection** | `fields=` query parameter to return only specific fields. | LOW — optimization |
| **CRS negotiation** | Content-Crs and Accept-Crs headers per VNG spatial standard. | LOW — only for geo features |

### Features OpenRegister has that Objects API LACKS

| Feature | OpenRegister | Objects API |
|---------|-------------|-------------|
| **Custom Vue.js frontend** | Full UI for managing registers, schemas, objects | Only Django Admin (no end-user UI) |
| **Registers** | Group schemas + objects into logical registers | No grouping concept — flat objecttype list |
| **Nextcloud file integration** | Attach files to objects, link to NC storage | No file support at all |
| **Full-text search** | Solr/Elasticsearch integration with faceted search | Basic Django ORM filtering only |
| **Faceted search** | Configurable facets per schema | Not available |
| **AI/Vector embeddings** | Semantic search, vector storage | Not available |
| **MCP protocol** | LLM-friendly API for AI agents | Not available |
| **n8n workflow automation** | Built-in workflow triggers and actions | Only VNG Notifications (no workflow engine) |
| **NL Design theming** | Government design system compliance | Django Admin only |
| **Object cross-references** | Relations between objects across schemas/registers | No relation support |
| **CalDAV integration** | Sync objects to calendar | Not available |
| **JSON-LD / Linked Data** | Semantic web support | Not available |
| **Time-travel queries** | Query object state at any point in time | Has history API but more limited |
| **Zero additional infrastructure** | Runs inside Nextcloud (PHP, same DB) | Requires separate Django + PostgreSQL + Redis |

---

## Architecture Comparison

### Objects API Stack
- Python 3.11 / Django 4.2 / DRF 3.15
- PostgreSQL 14+ with PostGIS
- Redis (caching + Celery broker)
- Celery (async notifications)
- Separate deployment per API (Objecttypes on one port, Objects on another)
- ~15,000 lines of Python code

### OpenRegister Stack
- PHP 8.x / Nextcloud framework
- MySQL/PostgreSQL (Nextcloud DB)
- Optional Solr/Elasticsearch
- Runs as Nextcloud app (no separate deployment)
- Vue.js 2 frontend

---

## Deployment Comparison

| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Install method | Docker Compose (4 containers min) | Nextcloud app store (1 click) |
| Containers needed | 4+ (objecttypes, objects, postgres, redis) | 0 (runs inside Nextcloud) |
| Database | Dedicated PostgreSQL + PostGIS | Shares Nextcloud DB |
| Scaling | Horizontal (multiple API instances) | Nextcloud scaling |
| Updates | Container image updates | Nextcloud app updates |
| Monitoring | OpenTelemetry + Sentry | Nextcloud logging |

---

## VNG Standardization Risk

**This is the primary competitive threat.**

Objects API is on the VNG standardization track. If accepted:
- Dutch municipalities may be _required_ to use VNG-compliant implementations
- OpenRegister would need to implement the VNG Objects API specification to be compliant
- The standard defines specific endpoints, query parameters, and response formats

**Current adoption** (municipalities using Objects API):
- Utrecht, Amsterdam, Delft, Haarlem, Rotterdam, Den Haag

**Mitigation options for OpenRegister**:
1. Implement VNG Objects API compatibility layer (map our API to their spec)
2. Participate in VNG standardization process
3. Position as "Objects API + more" (superset with frontend, files, search, AI)

---

## Detailed Specs Index

### From Codebase Analysis (16 specs)
| Spec | Key Insight |
|------|------------|
| object-type-management | Draft/published/deprecated lifecycle |
| object-crud-lifecycle | Append-only ObjectRecord pattern |
| record-versioning-history | StUF 03.01 dual time tracking |
| json-schema-validation | Two-layer: meta-schema + data validation |
| token-authorization | Custom TokenAuth with per-type permissions |
| field-level-authorization | glom-based field stripping |
| data-attribute-filtering | GIN index + 10 operators + jsonpath |
| geo-spatial-search | PostGIS within-polygon queries |
| notifications-webhooks | VNG notifications-api-common |
| cloud-events-zaak | Async zaak linking events |
| merge-patch-updates | Modified RFC 7396 |
| objecttype-import | Bulk import with conflicts |
| ordering-sorting | JSON field ordering with auth |
| observability-metrics | OpenTelemetry counters |
| api-versioning | Multi-version URL namespace |
| admin-interface | Django admin with search |

### From Documentation Analysis (13 specs)
| Spec | Key Insight |
|------|------------|
| object-crud | Official API contract documentation |
| objecttype-management | Version lifecycle from docs perspective |
| json-schema-validation | Validation rules and error responses |
| versioning | Material/formal time documentation |
| history-tracking | History API query parameters |
| geo-search | GeoJSON and CRS documentation |
| data-filtering | data_attrs query syntax |
| authorization | Token and permission documentation |
| notifications | Notification channel setup |
| observability | Metrics and monitoring docs |
| oidc-sso | OIDC configuration guide |
| admin-interface | Admin usage documentation |
| api-compliancy | VNG compliance checklist |

### From Browser Walkthrough (6 specs)
| Spec | Key Insight |
|------|------------|
| architecture | Observed deployment architecture |
| objecttypes-admin | Admin UI forms and fields |
| objects-admin | Admin UI for object management |
| api-endpoints | Tested API surface (34 endpoints total) |
| permissions | Observed permission behavior |
| versioning | Observed version behavior |
| comparison | UI/UX comparison with OpenRegister |

### Business Logic Diagrams (5)
| Diagram | Flow |
|---------|------|
| object-crud-lifecycle | Create → Update → Version → Delete |
| json-schema-validation | Meta-schema → Data validation → Error |
| authorization-flow | Token → Permission → Field-level → Response |
| search-filter-flow | Query → GIN index → Geo → Ordering |
| notification-flow | CRUD → Notifications → CloudEvents |

### Screenshots (26)
All saved in `screenshots/` — covering login, dashboard, all model list/detail views, forms, API browser.

### Documentation Archive (65+ files)
All in `docs/` — READMEs, RTD pages, OpenAPI specs, RST source docs, VNG compliance, ecosystem analysis.

---

## Recommendations for OpenRegister

### Must-have (competitive parity)
1. **VNG Objects API compatibility** — implement their spec as an alternative API surface
2. **Bi-temporal history** — add material time (start_at/end_at) alongside existing audit trail
3. **Schema versioning** — add draft/published lifecycle to schemas

### Should-have (competitive advantage)
4. **Field-level permissions** — extend RBAC to individual fields
5. **GeoJSON support** — add geometry fields for spatial data
6. **data_attrs-style filtering** — add JSON path querying for structured data

### Already winning on
- Frontend UI, file integration, full-text search, AI/MCP, workflow automation, zero-infra deployment, NL Design theming, object relations, CalDAV
