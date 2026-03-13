# Objects API vs OpenRegister — Comprehensive Comparison

## Executive Summary

The Maykin Media Objects API is a Python/Django-based REST API for storing generic objects validated against JSON schemas. It is being positioned as a VNG (Dutch municipal) standard. OpenRegister is a Nextcloud app providing similar functionality but with a fundamentally different architecture (embedded in Nextcloud, PHP-based, with a GUI frontend).

## Architecture Comparison

| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| **Language** | Python 3.12+ / Django | PHP / Nextcloud |
| **Database** | PostgreSQL + PostGIS | PostgreSQL / MySQL |
| **Platform** | Standalone Docker service | Nextcloud app (embedded) |
| **Frontend** | Django admin only (no end-user UI) | Full Vue.js frontend |
| **API** | REST (OAS 3.0) | REST + MCP |
| **Authentication** | API tokens (40-char bearer) | Nextcloud user/app auth |
| **Deployment** | Docker / Kubernetes | Nextcloud app store / Docker |
| **Cache** | Redis | APCu (Nextcloud) |
| **Task Queue** | Celery + Redis | n8n workflows |
| **Monitoring** | OpenTelemetry, Sentry, Elastic APM | Nextcloud logging |

## Feature-by-Feature Comparison

### Objects API Has, OpenRegister Also Has
- CRUD operations on objects
- JSON Schema validation
- UUID identification
- REST API
- Pagination
- Ordering/sorting
- Search/filtering
- Admin interface
- Docker deployment
- Open source (EUPL vs AGPL)

### Objects API Has, OpenRegister Does NOT Have
1. **Immutable record history** — Objects API never mutates records; updates create new ones
2. **Material/formal history** — Two-axis time tracking (StUF standard)
3. **Time-travel queries** — Query objects as they were on any date
4. **Correction records** — Explicit correction chain
5. **Schema versioning** — Draft/published/deprecated workflow
6. **GeoJSON geometry** — Native spatial data with PostGIS
7. **Geo search** — Point-in-polygon spatial queries
8. **Per-objecttype token authorization** — Fine-grained API token permissions
9. **Field-level authorization** — Restrict access to specific fields
10. **Notifications API integration** — VNG-standard event notifications
11. **Data classification** — open/intern/confidential/strictly_confidential
12. **Rich objecttype metadata** — Contact info, update frequency, provider org, etc.
13. **Sparse field selection** — `fields` parameter for partial responses
14. **Recursive PATCH merge** — Deep merge of nested JSON data
15. **Published OpenAPI spec** — Lint-checked, SDK-generated
16. **Postman collections** — Ready-to-use API test collections
17. **OpenTelemetry metrics** — CRUD counters, auth metrics, HTTP duration
18. **Distributed tracing** — Elastic APM, OTel tracing
19. **CSV data export** — Dump to SQL or CSV
20. **VNG compliancy documentation** — Formal standards compliance
21. **API Strategy compliance** — Nederlandse API Strategie adherence
22. **MIM information model** — Enterprise Architect models

### OpenRegister Has, Objects API Does NOT Have
1. **End-user frontend** — Full Vue.js UI for non-technical users
2. **Nextcloud integration** — Files, sharing, collaboration, calendar, contacts
3. **MCP protocol** — Machine-readable API for LLM/AI integration
4. **n8n workflow automation** — Visual workflow builder for business logic
5. **Multi-register architecture** — Objects organized in registers
6. **NL Design theming** — Government-standard UI theming
7. **Search engine integration** — Elasticsearch/Solr support
8. **Faceted search** — Configurable facets for filtering
9. **File attachments** — Direct file upload to objects via Nextcloud
10. **Multi-database** — PostgreSQL and MySQL support
11. **App ecosystem** — Part of larger Nextcloud/Conduction app ecosystem
12. **ExApp sidecar** — Python sidecar for extended capabilities
13. **No separate infrastructure** — Runs inside existing Nextcloud

## Competitive Threat Assessment

### HIGH THREAT: VNG Standardization
- Objects API is being submitted as VNG standard
- If accepted, Dutch municipalities may be required to use it
- OpenRegister needs to ensure API compatibility or risk being excluded

### MEDIUM THREAT: Ecosystem Lock-in
- Objects API integrates with OpenZaak, Open Notificaties, Open Formulieren
- These are also becoming VNG standards
- Deep integration creates switching costs

### LOW THREAT: Technical Superiority
- Objects API has more API features but NO frontend
- OpenRegister's Nextcloud integration is a major differentiator
- Objects API requires separate deployment/infrastructure

## Strategic Recommendations for OpenRegister

### Must-Have (to compete in VNG ecosystem)
1. Implement VNG Objects API compatible endpoints (can coexist with existing API)
2. Add JSON schema versioning with draft/published workflow
3. Implement material/formal history tracking
4. Add per-register/schema authorization
5. Publish OpenAPI spec

### Should-Have (strong competitive advantages)
6. Add GeoJSON geometry support
7. Implement Notificaties API integration
8. Add sparse field selection
9. Data classification metadata
10. Postman collection generation

### Nice-to-Have (polish)
11. OpenTelemetry metrics
12. Correction records
13. Recursive PATCH merge
14. CSV export
15. Admin search with operator syntax

### Keep as Differentiators
- End-user friendly Vue.js frontend (Objects API has NONE)
- Nextcloud integration (files, collaboration, sharing)
- n8n workflow automation
- MCP protocol for AI
- NL Design theming
- No additional infrastructure required

## Municipal Adoption

### Objects API Known Users
- Municipality of Utrecht (project lead)
- Municipality of Amsterdam
- Municipality of Delft
- Municipality of Haarlem
- Municipality of Rotterdam
- GBI
- Dimpact (consortium)
- Den Haag (feature requests)

### Community
- Common Ground community group active
- 7 GitHub stars, 14 forks, 59 open issues (active development)
- Regular 2-month release cycle
- Professional support by Maykin Media
