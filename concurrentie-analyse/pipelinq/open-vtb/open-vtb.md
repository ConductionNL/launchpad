# Open VTB — Competitor Analysis

## Overview

- **Website:** https://github.com/maykinmedia/open-vtb
- **Documentation:** https://open-vtb.readthedocs.io/ (referenced but may not be live)
- **Docker Hub:** https://hub.docker.com/r/maykinmedia/open-vtb (referenced but may be 404)
- **Open Source:** Yes (EUPL-1.2)
- **Self-Hosted:** Yes (Docker/Kubernetes)
- **Version:** 0.1.0 (pre-release)
- **Tech Stack:** Python 3.12+ / Django 5.2 / DRF / PostGIS
- **Summary:** Open Verzoeken, Taken en Berichten (Requests, Tasks, and Messages) -- a registration component providing three separate APIs for managing citizen requests, government-assigned external tasks, and one-way messages in the Dutch government Common Ground ecosystem.

## Maturity Assessment

| Indicator | Value | Assessment |
|-----------|-------|------------|
| Version | 0.1.0 | Pre-release, not production-ready |
| GitHub Stars | 2 | Minimal community awareness |
| GitHub Forks | 0 | No external contributors |
| Commits | 169 | Moderate development activity |
| Open Issues | 22 | Backlog of work |
| Last Activity | January 2025 | Development may have stalled |
| Docker Image | 404 on Docker Hub | May not be published |
| ReadTheDocs | Referenced but unverified | Documentation may be incomplete |
| Production Users | None known | No known deployments |
| VNG Standards Compliance | Partial | Inspired by archived Verzoeken API; Taken and Berichten APIs are self-defined, no VNG standard |

**Verdict:** Open VTB is an early-stage, pre-release product with no known production deployments. The underlying VNG Verzoeken API standard was archived in 2023, and its replacement (Klantinteracties) has stalled. The Taken and Berichten APIs are entirely self-defined with no formal standard backing them.

## Codebase

- **GitHub:** https://github.com/maykinmedia/open-vtb
- **Language:** Python 89.6%, SCSS 4.2%, HTML 3.6%, Shell 1.1%
- **Framework:** Django 5.2 + Django REST Framework + vng-api-common
- **Database:** PostGIS (PostgreSQL with spatial extensions)
- **API Docs:** drf-spectacular (OpenAPI 3.x) with ReDoc/Swagger
- **Auth:** mozilla-django-oidc-db (OIDC) + Token auth
- **Quality:** Ruff linter, GitHub Actions CI/CD

### Three Separate Django Apps

1. **verzoeken/** -- 7 models (VerzoekType, VerzoekTypeVersion, Verzoek, VerzoekBron, VerzoekBetaling, Bijlage, BijlageType)
2. **taken/** -- 1 model (ExterneTaak with polymorphic task types)
3. **berichten/** -- 2 models (Bericht, Bijlage)

## Business Model

Open VTB is developed by Maykin Media B.V. for the "Platform Dienstverlening werkgroep." The software is free under the EUPL license. Like other Maykin products, revenue is generated through the OpenGem initiative providing SaaS hosting, implementation, and support services. Development is co-funded by participating municipalities. No license fees apply.

## Target Market

Dutch municipalities and government organizations that need a standards-compliant registry for citizen requests, government-to-citizen tasks, and official messages. Part of the broader Common Ground ecosystem alongside Open Zaak, Open Klant, Open Formulieren, and Open Inwoner. Targets the specific gap between "citizen submits a request" and "case is created and managed."

## Pricing

- **Software:** Free (EUPL-1.2 license, no license costs)
- **SaaS (OpenGem):** Pay only for support and infrastructure, monthly cancellable
- **Self-hosted:** Free, with optional paid support from Maykin or partners

## Key Features

### Verzoeken (Requests)
- Request type registry with versioned JSON Schemas for validation
- Citizen request intake with structured data (aanvraagGegevens)
- Payment tracking (VerzoekBetaling: amount, currency, provider, transaction reference)
- GeoJSON geometry support (PostGIS: Point, Line, Polygon)
- Source application tracking (VerzoekBron: app name + submission ID)
- Attachment management via URN references to document store
- Channel tracking (which intake channel was used)
- Language field per request
- Version lifecycle: draft -> published -> deprecated
- Validity periods (begin/einde geldigheid) on schema versions

### Taken (External Tasks)
- Three task types: betaaltaak (payment), formuliertaak (embedded form), gegevensuitvraagtaak (external form link)
- Status lifecycle: open -> uitgevoerd/niet_uitgevoerd/afgebroken -> verwerkt
- Automatic reminder date calculation (configurable days before deadline)
- FormIO-compatible embedded form definitions
- Pre-fill and received data capture
- URN-based assignment to citizens/businesses
- Case and product linking via URN
- Polymorphic API with type-specific endpoints

### Berichten (Messages)
- Government-to-citizen one-way messaging
- Create + Read only (no update/delete -- immutable audit trail)
- Dual-channel: local portal + Mijn Overheid berichtenbox routing
- Scheduled publication (publicatiedatum)
- Read tracking (geopend_op, portal only)
- Markdown support for local portals, plain text for Mijn Overheid
- Attachment management with template-aware forwarding

### Cross-Cutting
- RFC 8141 URN-based cross-system referencing (BSN, KvK, cases, documents, products)
- OIDC + Token authentication
- Django admin interface
- Docker deployment support
- Pagination (max 500 per page)

## Feature Comparison with Pipelinq

| Feature | Open VTB | Pipelinq |
|---------|---------|----------|
| Client management (persons) | No (URN references to Open Klant) | Yes (built-in) |
| Organization management | No (URN references to KvK/HR) | Yes (built-in) |
| Contact persons (linked) | No | Yes |
| Lead pipeline (kanban board) | No | Yes |
| Request intake | Yes (Verzoeken -- core feature with JSON Schema validation) | Yes |
| Payment tracking | Yes (VerzoekBetaling + BetaalTaak) | Not yet |
| Form definitions | Yes (FormIO-compatible in FormulierTaak) | Not yet |
| Geo-location on requests | Yes (PostGIS geometry) | Not yet |
| Task management | Yes (ExterneTaak with 3 types) | Yes (pipeline stages) |
| Deadline + reminders | Yes (auto-calculated reminders) | Not yet |
| Citizen messaging | Yes (Berichten -- core feature) | Not yet |
| Mijn Overheid integration | Yes (berichtenbox forwarding) | Not applicable |
| Read tracking | Yes (geopend_op) | Not yet |
| Contact moments logging | Partial (Berichten only, one-way) | Yes |
| My Work queue | Partial (Taken API only, no UI) | Yes (with UI) |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | No | Yes |
| Import/Export (CSV/vCard) | No | Yes |
| Case management integration | Yes (URN references to ZAAK) | Yes (Procest) |
| Nextcloud integration | No | Native |
| User interface | No (API-only, needs Open Inwoner) | Yes (full Nextcloud UI) |
| RBAC | Yes (OIDC + token-based) | Yes |
| Audit trail | Partial (Berichten immutability) | Yes |
| Schema versioning | Yes (draft/published/deprecated lifecycle) | Via OpenRegister |
| URN cross-referencing | Yes (RFC 8141) | Not yet |
| Multi-tenancy | No | Via Nextcloud groups |

## Strengths

- **Focused scope**: Covers the specific request-task-message lifecycle gap in the Common Ground ecosystem
- **Rich Verzoeken model**: Versioned JSON Schema validation, payment tracking, geo-location, source tracking are well-designed
- **Typed task system**: Three distinct task types (payment, form, data-request) with polymorphic API
- **Berichten audit trail**: Immutable messages with dual-channel (portal + Mijn Overheid) routing
- **URN-based decoupling**: Clean cross-system references without tight integration dependencies
- **Django ecosystem**: Leverages mature Python/Django stack with vng-api-common patterns

## Weaknesses

- **API-only, no UI**: Requires Open Inwoner or another front-end to be useful to end users
- **Pre-release**: v0.1.0 with no known production deployments and potentially stalled development (last activity Jan 2025)
- **Deprecated standards**: The VNG Verzoeken API it draws from was archived in 2023; Taken and Berichten APIs have no VNG standard backing
- **Narrow scope**: Only handles requests, tasks, and messages -- no CRM, pipeline, or relationship management
- **No validation of URN targets**: Stores URN strings but does not verify referenced entities exist
- **Limited adoption**: 2 GitHub stars, 0 forks, no known contributors outside Maykin
- **EUR-only**: Payment tasks limited to Euro currency
- **No search/filter**: Basic list endpoints only, no advanced querying

## Ecosystem Position

### Integration with Other Maykin Products

| Product | Relationship |
|---------|-------------|
| **Open Zaak** | VTB references cases via URN (hoort_bij, is_gerelateerd_aan) |
| **Open Klant** | VTB references persons/orgs via URN (initiator, ontvanger) |
| **Open Formulieren** | Submits verzoeken to VTB; formuliertaak is FormIO-compatible |
| **Open Inwoner** | Front-end that displays taken + berichten from VTB |
| **Open Notificaties** | Can notify on VTB state changes |
| **Open Product** | VTB references products via URN (heeft_betrekking_op) |

### Compared to Competitors

| Product | Type | Maturity | Scope |
|---------|------|----------|-------|
| **Open VTB** | Open source, API-only | Pre-release (v0.1.0) | Requests + Tasks + Messages only |
| **Pipelinq** (Conduction) | Open source, full UI | Active development | CRM + Pipeline + Tasks + more |
| **ZAC** (Dimpact/Info.nl) | Open source | Production | Case handling + tasks (broader) |
| **e-Suite** (Atos/Centric) | Proprietary | Production, legacy | Full stack |
| **RX.Mission** (Roxit) | Proprietary | Production | Full service delivery |

## Notes

Open VTB fills a specific gap in the Common Ground ecosystem: the space between a citizen submitting a request and a case being created. However, it is a headless API registry with no user interface, requiring Open Inwoner or a similar portal to be useful.

The Verzoeken concept maps loosely to Pipelinq's lead/request intake, and the Taken concept maps to Pipelinq's My Work queue, but Pipelinq offers these features with a visual kanban interface and CRM context rather than as bare API endpoints.

**Key strategic insight**: Open VTB's VNG standards compliance claim is weaker than it appears. The Verzoeken API standard was archived in 2023, the replacement (Klantinteracties) has stalled since July 2024, and the Taken and Berichten APIs have no standard at all. Pipelinq could implement these same API patterns without being constrained by unstable/deprecated standards.

**What Pipelinq should consider adopting from Open VTB**:
1. Payment tracking models (VerzoekBetaling, BetaalTaak)
2. FormIO-compatible form definitions for tasks
3. Automatic reminder/deadline calculation
4. Schema version lifecycle (draft/published/deprecated)
5. URN-based cross-system referencing (if interoperability with Common Ground is desired)
6. Scheduled message publication and read tracking
