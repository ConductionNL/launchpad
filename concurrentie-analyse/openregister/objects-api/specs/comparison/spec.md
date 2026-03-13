# OpenRegister vs Maykin Objects API — Feature Comparison

## Executive Summary

The Maykin Objects API is a **standalone Python/Django REST API** focused exclusively on object storage with strong temporal/versioning semantics and ZGW (Zaakgericht Werken) ecosystem integration. OpenRegister is a **Nextcloud app** that provides object storage as part of a broader platform with UI, file management, and workflow capabilities.

**Maykin's strengths:** Temporal model, schema versioning, ZGW integration, field-level permissions
**OpenRegister's strengths:** Integrated UI, Nextcloud ecosystem, file attachments, search/faceting, MCP integration, workflow automation

## Feature-by-Feature Comparison

### Core Data Model

| Feature | Maykin Objects API | OpenRegister | Notes |
|---------|:-:|:-:|-------|
| JSON Schema validation | Yes | Yes | Both validate against JSON Schema |
| Schema versioning (published/draft) | Yes | No | Maykin has explicit version lifecycle |
| Temporal/bi-temporal records | Yes | No | Major differentiator for Maykin |
| Correction tracking | Yes | No | correctionFor/correctedBy |
| GeoJSON geometry support | Yes (PostGIS) | No | Maykin requires PostGIS |
| Labels/tags on types | Yes (JSON field) | No | |
| Data classification | Yes (4 levels) | No | Open, Intern, Confidential, Strictly confidential |
| Update frequency metadata | Yes (7 options) | No | Real-time through Unknown |
| File attachments | No | Yes | OpenRegister supports file uploads |
| Nested object references | No (flat JSON) | Yes | OpenRegister supports object relations |

### API

| Feature | Maykin Objects API | OpenRegister | Notes |
|---------|:-:|:-:|-------|
| REST API | Yes (DRF) | Yes (Nextcloud OCS) | |
| OpenAPI/Swagger docs | Yes (ReDoc) | Yes (via MCP discover) | |
| Pagination | Yes (page/pageSize) | Yes | |
| Field selection | Yes (`fields` param) | No | |
| Data attribute filtering | Yes (`data_attr`) | Yes (via search) | |
| Full-text search in data | Yes (`data_icontains`) | Yes (Solr/Elastic) | OpenRegister has richer search |
| Ordering | Yes (`ordering` param) | Yes | |
| Geo-spatial search | Yes (via PostGIS) | No | |
| Temporal queries | Yes (`date`, `registrationDate`) | No | |
| Search endpoint (POST) | Yes | No (GET-based) | |
| Bulk operations | No | No | |
| MCP integration | No | Yes | OpenRegister has native MCP |
| Webhooks/Notifications | Yes (via Notificaties API) | Yes (via n8n) | Different approaches |

### Authentication & Authorization

| Feature | Maykin Objects API | OpenRegister | Notes |
|---------|:-:|:-:|-------|
| Token-based API auth | Yes | Yes | |
| Session-based (user login) | Admin only | Yes (Nextcloud) | |
| OIDC/SSO | Yes (admin login) | Yes (via Nextcloud) | |
| Per-objecttype permissions | Yes | No (per-register) | Maykin is more granular |
| Read-only / Read-write modes | Yes | No (all-or-nothing) | |
| Field-level authorization | Yes | No | |
| Superuser bypass | Yes (explicit flag) | Yes (Nextcloud admin) | |
| Multi-tenancy | Via organization field | Via Nextcloud groups | |
| 2FA | Yes (TOTP + WebAuthn) | Yes (via Nextcloud) | |

### Administration / UI

| Feature | Maykin Objects API | OpenRegister | Notes |
|---------|:-:|:-:|-------|
| Custom frontend UI | No (Django Admin only) | Yes (Vue.js) | Major differentiator for OpenRegister |
| Object type management | Django Admin form | Nextcloud UI | |
| Object browsing | Django Admin list | Custom list/detail views | |
| Object editing | Django Admin form | Custom forms with validation | |
| Search/faceting UI | No | Yes | OpenRegister has rich search |
| Import from URL | Yes (objecttypes) | No | |
| JSON Schema editor | Basic (raw JSON textarea) | No visual editor | Both need improvement |
| Theme support | Dark/light toggle | NL Design System | |
| Audit log | Django Admin history | Yes | |
| Celery Flower (task monitoring) | Yes | No | |

### Ecosystem Integration

| Feature | Maykin Objects API | OpenRegister | Notes |
|---------|:-:|:-:|-------|
| ZGW ecosystem | Native (NRC, ZRC, etc.) | No | Maykin's core market |
| Nextcloud integration | No | Native | File, user, group, share management |
| NLX gateway support | Yes | No | Dutch government API gateway |
| Notifications API (NRC) | Yes | No (uses n8n) | |
| CommonGround compliance | Yes | Partial | |
| n8n workflow integration | No | Yes (native ExApp) | |
| AI/LLM integration | No | Yes (MCP) | |

### Operations

| Feature | Maykin Objects API | OpenRegister | Notes |
|---------|:-:|:-:|-------|
| Docker deployment | Yes (official images) | Yes (Nextcloud Docker) | |
| YAML-based setup config | Yes | No | |
| OpenTelemetry | Yes | No | |
| Health checks | Yes (built-in) | Via Nextcloud | |
| Database | PostgreSQL + PostGIS | PostgreSQL or MySQL | |
| Rate limiting | Via Django Axes | Via Nextcloud + APCu | |

## Key Observations

### What Maykin Does Better
1. **Temporal/bi-temporal data model** — The record versioning with correction tracking and temporal queries is sophisticated and unique. OpenRegister has no equivalent.
2. **Schema version lifecycle** — Draft/Published workflow ensures schema stability. OpenRegister edits schemas in-place.
3. **Field-level permissions** — Token can have access to only specific fields within an object type.
4. **GeoJSON/PostGIS integration** — Native spatial data support.
5. **ZGW ecosystem** — Built specifically for Dutch government ZGW architecture.
6. **CRS compliance** — Coordinate Reference System handling in headers.

### What OpenRegister Does Better
1. **User-facing UI** — Custom Vue.js frontend vs bare Django Admin. End users can actually use OpenRegister without API knowledge.
2. **Nextcloud integration** — Files, users, groups, shares, apps — entire productivity platform.
3. **Search & faceting** — Solr/Elasticsearch integration with configurable facets.
4. **Workflow automation** — n8n integration for complex business logic.
5. **AI/MCP integration** — LLM-friendly API and MCP standard support.
6. **File attachments** — Objects can have file attachments via Nextcloud file system.
7. **Object relations** — Objects can reference other objects across schemas/registers.

### What Neither Does Well
1. **Visual JSON Schema editor** — Both use raw JSON textareas for schema editing.
2. **Bulk import/export** — Neither has built-in CSV/Excel import.
3. **Schema migration tools** — Neither helps migrate data when schema changes.
4. **API rate limiting per token** — Neither has per-consumer rate limits.

## Strategic Implications for OpenRegister

### Features to Consider Adopting
1. **Schema versioning** — Draft/published workflow would add safety.
2. **Temporal queries** — "Show me the data as it was on date X" is very powerful for government use cases.
3. **Per-objecttype permissions** — More granular than per-register.
4. **Data classification** — Simple metadata field (Open/Intern/Confidential/Strictly confidential).
5. **CRS/geometry support** — If targeting government geo-data use cases.

### Features Where OpenRegister Already Wins
1. Keep investing in the **custom UI** — this is the #1 differentiator.
2. **Search & faceting** — Maykin has basic filtering; OpenRegister's Solr integration is far superior.
3. **n8n/MCP integration** — This is future-proof and unique in the market.
4. **Nextcloud ecosystem** — File management, collaboration, and the app marketplace.
