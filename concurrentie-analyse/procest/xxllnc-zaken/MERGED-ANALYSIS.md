# xxllnc Zaken -- Merged Competitive Analysis

**Competitor:** xxllnc Zaken (formerly Zaaksysteem.nl)
**Category:** SaaS Zaaksysteem / Case Management Platform
**Analysis date:** 2026-03-14
**Website:** https://xxllnc.nl/applicaties/zaken
**GitLab:** https://gitlab.com/xxllnc/zaakgericht/zaken/start (EUPL-1.2)

---

## 1. Sources Summary

### Codebase Files Analyzed

| File | Description |
|------|-------------|
| `overview.md` | Company background, tech stack, market position, codebase analysis |
| `xxllnc-zaken.md` | Feature comparison table, business model, strengths/weaknesses |
| `business-logic/browser-walkthrough-notes.md` | Browser walkthrough of product pages, GitLab repos, team pages |
| `docs/architecture-and-api.md` | Minty framework, ZGW-API, Koppelen integration platform |
| `docs/business-logic-flows.md` | Mermaid diagrams for 9 core business flows |
| `docs/case-studies.md` | Gemeente Utrecht (8M+ cases) and Gemeente Epe (200 zaaktypen) |
| `docs/product-features.md` | Full feature list from product page, modules, Teams integration |
| `specs/case-management/spec.md` | Core case lifecycle, 80+ field entity, phase system, CQRS |
| `specs/document-management/spec.md` | Document pipeline, virus scan, WOPI, intake workflow |
| `specs/communication/spec.md` | Threaded messaging, contact moments, email import |
| `specs/catalog-admin/spec.md` | Case type versioning, attributes, templates, folder hierarchy |
| `specs/task-management/spec.md` | Phase-bound tasks, assignee notifications, DSO flag |
| `specs/rule-engine/spec.md` | Automatic rule execution via @apply_case_rules decorator |
| `specs/authentication/spec.md` | SAML2, OAuth2/Auth0, case-level authorization |
| `specs/geo-location/spec.md` | GeoJSON features linked to cases, origin tracking |
| `specs/payments/spec.md` | Worldline integration, channel-based pricing |
| `specs/background-jobs/spec.md` | RabbitMQ event system, 10 AMQP consumers, job management |
| `specs/style-configuration/spec.md` | Multi-tenant theming, per-tenant CSS/logos |
| `specs/citizen-portal/spec.md` | PIP (Persoonlijke Internet Pagina), document exchange |
| `specs/archiving/spec.md` | NEN-ISO 16175-1:2020 certified RMA |
| `specs/integration-platform/spec.md` | Koppelen: Connect + Koppel.app + API-Gateway |
| `specs/process-builder/spec.md` | Zero-coding process configuration for administrators |

### Documentation Fetched

- xxllnc.nl product pages (Zaken, Formulieren, Koppelen, team page)
- xxllnc.nl blog posts (ZGW-API, Utrecht case study, Epe success story, 6-step implementation)
- GitLab repository overview, README, subgroup structure
- YouTube search results (no current demos found)
- Maykin Open Formulieren partnership announcement (Feb 2026)

### Screenshots

10 screenshots captured (`screenshots/01-xxllnc-homepage.png` through `screenshots/10-maykin-samenwerking.png`) covering the homepage, product pages, GitLab repos, team stats, and partner announcements.

---

## 2. Product Overview

### What It Is

xxllnc Zaken is a SaaS case management system (zaaksysteem) for Dutch municipalities and semi-governmental organizations. It enables organizations to digitize business processes in a case-oriented way ("zaakgericht werken"). The platform claims to be "Het open source zaaksysteem van Nederland" and offers case lifecycle management, smart forms, citizen self-service, document management, and certified archiving -- all delivered as a managed cloud service.

### Who Makes It

**xxllnc** (headquartered in Hengelo, Netherlands) was formed through a series of acquisitions:
- **Exxellence Groep** -- original company, zaaksysteem and advisory for local government
- **Zaaksysteem.nl** -- acquired June 2020; founded 2009, ~30 employees, ~50 clients
- **Tog Nederland** -- acquired August 2021; combined entity rebranded to xxllnc with ~430 employees
- **Main Capital** -- private equity investor since March 2020

The company now has 100+ employees (core team), 120+ clients, and reports 8.23 million cases processed across 500+ case types.

### Tech Stack

| Component | Technology | Share |
|-----------|-----------|-------|
| Frontend | TypeScript + JavaScript | 27.4% + 13.0% |
| Legacy/Tooling | Perl | 25.5% |
| Backend | Python (custom Minty framework on Pyramid) | 19.8% |
| Database | PostgreSQL (PLpgSQL stored procedures) | 5.2% |
| Message Broker | RabbitMQ (AMQP) | -- |
| Object Storage | MinIO (S3-compatible) | -- |
| Session/Cache | Redis | -- |
| Auth | Keycloak (optional SAML2/OAuth2 IdP) | -- |
| Containerization | Docker Compose | -- |

Repository stats: 54,422 commits, 345 branches, 2,550 tags, created January 2020.

---

## 3. Architecture Summary

xxllnc Zaken uses a microservices architecture with Domain Driven Design, built on a custom Python framework called **Minty**:

- **minty** -- DDD core library (entities, value objects, aggregates, repositories)
- **minty-pyramid** -- REST API server layer on Python Pyramid
- **minty-infra-sqlalchemy** -- SQLAlchemy database infrastructure
- **minty-amqp** -- AMQP consumers for event-driven processing

### Service Topology

```
Nginx Reverse Proxy (HTTPS)
    |
    +-- React Frontend (main, mor, my-pip, vergadering)
    +-- Perl API (v1, legacy endpoints)
    +-- 8 Python HTTP Microservices (v2 APIs):
    |     http-admin (~30 endpoints)
    |     http-auth (1 endpoint)
    |     http-cm (~80 endpoints, case management)
    |     http-communication (~17 endpoints)
    |     http-document (~30 endpoints)
    |     http-geo (2 endpoints)
    |     http-jobs (6 endpoints)
    |     http-style (~3 endpoints)
    +-- Keycloak (auth, SAML)
    +-- Swagger UI
    |
Infrastructure:
    PostgreSQL -- RabbitMQ -- Redis -- MinIO (S3)
    |
10 AMQP Event Consumers:
    Logging (ALL events -> audit table)
    Case Events, Document Processing, Document Events
    Communication, Geo Sync, Notification, Jobs
    Legacy Queue Runner (Perl), Timed Events
```

### Key Patterns

- **CQRS with Event Sourcing** -- all entity mutations emit named AMQP events; a logging consumer writes every event to an audit table
- **Phase-based Rule Engine** -- `@apply_case_rules` decorator fires business rules after every case state change
- **Document Processing Pipeline** -- virus scan, format conversion, preview/thumbnail generation, and search indexing as separate async consumers

---

## 4. Feature Inventory

| # | Spec | Description |
|---|------|-------------|
| 1 | **Case Management** | Core case lifecycle with 80+ field entity, 5 statuses (new/open/stalled/resolved/deleted), phase system, pause/resume with date recalculation, parent/child hierarchy, and 4-level case authorization |
| 2 | **Document Management** | Full document pipeline: upload, virus scan (ClamAV), PDF preview, thumbnail, search indexing (Tika), intake workflow (assign-review-accept/reject), WOPI online editing, 80+ Dutch government categories, 8 confidentiality levels |
| 3 | **Communication** | Threaded messaging linked to cases: email import, internal notes, contact moments, external messages, read/unread tracking, attachment-to-document promotion |
| 4 | **Catalog & Administration** | Case type versioning (draft/active), configurable attributes, document/email templates with variable substitution, folder hierarchy for catalog organization, integration management with transaction tracking |
| 5 | **Task Management** | Phase-bound tasks: system-generated from case type definitions and user-defined; tasks become read-only when case progresses past their phase; email notifications on assignment |
| 6 | **Rule Engine** | Automatic business rule execution via decorator on entity methods; rules fire after every case mutation; synchronous and transactional; cascading rule support |
| 7 | **Authentication** | Multi-method auth (local, SAML2, OAuth2/Auth0), Redis sessions, case-level granular authorization (search/read/write/manage), BSN retrieval logging for privacy compliance |
| 8 | **Geo-Location** | GeoJSON feature storage linked to cases/contacts/custom objects; origin tracking for relationship-based queries; async geo sync via AMQP consumer |
| 9 | **Payments** | Worldline gateway integration; channel-based pricing per case type (web/counter/telephone/email/employee/post); webhook callbacks; test/production modes |
| 10 | **Background Jobs** | RabbitMQ event-driven architecture with 10 consumers; job management API (create/cancel/delete); downloadable results and error reports; timed events for scheduled operations |
| 11 | **Style Configuration** | Multi-tenant theming via named configuration files (CSS, logos, favicon, JSON config); per-tenant citizen portal branding; 10 MB file size limit |
| 12 | **Citizen Portal (PIP)** | Persoonlijke Internet Pagina for citizens: case status tracking, document exchange (bidirectional), messaging, form submission, custom branding per organization |
| 13 | **Archiving (RMA)** | NEN-ISO 16175-1:2020 certified Records Management Application; integrated into case lifecycle; Common Ground archive component; destruction dates and selection lists |
| 14 | **Integration Platform (Koppelen)** | Separate product combining Connect + Koppel.app + API-Gateway; StUF, ZGW-API, HaalCentraal, DSO standards; partner integrations (Xential, ValidSign, Datamask, MijnOverheid, Office365) |
| 15 | **Process Builder** | Zero-coding process configuration for functional administrators; decision trees (vraagbomen); template library; unlimited processes; no developer required |

---

## 5. Key Strengths

1. **Massive proven scale** -- 8.23 million cases, 750 case types at gemeente Utrecht, 2,300+ users in a single deployment, 120+ client organizations
2. **NEN-ISO 16175-1:2020 certified archiving** -- formal compliance with Dutch government archiving standards, integrated into daily workflow rather than a separate system
3. **Zero-coding process builder** -- functional administrators design and modify processes without developer involvement, reducing time-to-deploy for new case types
4. **Deep Dutch government domain knowledge** -- BSN/KvK contact models, StUF/ZGW-API, DigiD/eHerkenning, DSO integration, channel-based leges pricing, contact moments, and 15+ years of municipal experience
5. **Complete event-driven architecture** -- CQRS with automatic audit trail; every entity mutation produces an event that is logged, enabling full traceability
6. **Comprehensive document pipeline** -- virus scanning, format conversion, preview/thumbnail generation, full-text search indexing, intake workflow, and WOPI-based collaborative editing
7. **Case type versioning** -- updating process definitions without affecting running cases, essential for production environments
8. **Phase-based rule engine** -- automatic business rule execution on every case mutation, ensuring process compliance
9. **Broad product ecosystem** -- 25+ integrated applications (belastingen, sociaal domein, omgeving, onderwijs) creating cross-selling opportunities and end-to-end government workflows
10. **ZGW-API compliance** -- developed with Conduction, enabling integration with other Common Ground components

---

## 6. Key Weaknesses

1. **SaaS-only deployment** -- no self-hosted or on-premise option, creating vendor dependency and limiting organizations that require data sovereignty
2. **Significant legacy codebase** -- 25.5% Perl code indicates substantial legacy technical debt; the mono-repo structure (54K+ commits) makes evolution slower
3. **"Open source" is misleading** -- despite the GitLab presence and EUPL license, the actual SaaS delivery model and mono-repo structure limit genuine community contributions; it is effectively proprietary
4. **Integration platform is a separate product** -- Koppelen requires additional licensing, unlike competitors that include integration capabilities in the base offering
5. **No BPMN/CMMN standards compliance** -- process modeling is proprietary, making process definitions non-portable to other systems
6. **Complex infrastructure requirements** -- Docker development environment requires 10GB+ memory, custom DNS, self-signed CA, and GitLab access; not accessible for casual evaluation
7. **Formulieren migration signals weakness** -- replacing their own forms solution with Maykin's Open Formulieren (Feb 2026) suggests their in-house form builder was inadequate
8. **Limited internationalization** -- deeply tied to Dutch government specifics; expanding to other European markets would require significant adaptation
9. **No mobile app** -- citizen portal is web-only; no native or PWA mobile experience documented
10. **Enterprise pricing opacity** -- no public pricing, custom quotes only; makes cost comparison difficult and suggests high price points

---

## 7. Relevance to Procest

xxllnc Zaken is a **direct competitor** to Procest in the Dutch municipal case management market. Both target the same buyer (gemeenten, waterschappen, semi-overheid), solve the same core problem (zaakgericht werken), and claim alignment with Common Ground and ZGW-API standards.

### Where Procest Can Differentiate

| Dimension | xxllnc Zaken | Procest |
|-----------|-------------|---------|
| **Deployment** | SaaS-only | Self-hosted via Nextcloud + cloud option |
| **Open source** | Claimed but effectively proprietary SaaS | Genuinely open source on Nextcloud App Store |
| **Integration** | Separate product (Koppelen) at extra cost | OpenConnector included; n8n for workflows |
| **Standards** | Proprietary process modeling | CMMN 1.1 for portable process definitions |
| **Theming** | Custom file-based per-tenant | NL Design System tokens (standardized) |
| **Auth** | Custom auth stack (Keycloak/SAML2) | Inherits Nextcloud enterprise auth (LDAP, SAML, OAuth2) |
| **Cost model** | Enterprise contracts, opaque pricing | Per-app pricing via Nextcloud App Store |
| **Ecosystem** | Closed 25+ app suite | Open Nextcloud ecosystem with 1,000+ apps |
| **Mobile** | Web-only | Nextcloud mobile apps as foundation |

### What Procest Should Learn From xxllnc

1. **Case type versioning is essential** -- production environments need to update process definitions without breaking running cases
2. **Automatic audit trail via event sourcing** -- every mutation logged without manual instrumentation; implement in OpenRegister
3. **Phase-bound task management** -- tasks tied to case phases that become read-only as the case progresses; more structured than independent tasks
4. **Document processing pipeline** -- leverage Docudesk + n8n to replicate the virus scan, conversion, preview, and search indexing chain
5. **Channel-based pricing** -- Dutch municipalities charge different fees (leges) per intake channel; payment integration will be needed
6. **Contact model depth** -- BSN for persons, KvK for organizations, department/role for employees; essential for Dutch government use

---

## 8. Feature Gap Analysis

Features xxllnc has that Procest must match, build alternatives for, or consciously deprioritize.

### Must Match (critical for market entry)

| Feature | xxllnc | Procest Status | Gap |
|---------|--------|---------------|-----|
| Case lifecycle (new/open/stalled/resolved) | Full state machine with pause/resume | Basic lifecycle | Need pause/resume with date recalculation |
| Case type versioning | Draft/active versions; existing cases keep old version | Not implemented | Critical for production deployments |
| Dutch government contacts (BSN, KvK) | 3 polymorphic contact types | Not implemented | Required for municipal use |
| Document management | Full pipeline (scan, convert, preview, index) | Docudesk (basic) | Integrate Docudesk pipeline with case workflow |
| Archival compliance | NEN-ISO 16175-1:2020 certified | Not implemented | Required for government procurement |
| ZGW-API compliance | Developed with Conduction | Planned | Core requirement for Dutch government |
| Task management | Phase-bound, system-generated + user-defined | Basic tasks | Need phase-binding and case type templates |
| Audit trail | Automatic via event sourcing | Not implemented | Implement event logging in OpenRegister |
| Search and filtering | Advanced search with saved searches, dashboards | Basic | Expand OpenRegister search capabilities |

### Should Build (competitive advantage opportunities)

| Feature | xxllnc | Procest Opportunity |
|---------|--------|-------------------|
| Citizen portal | PIP (proprietary) | Nextcloud-based portal with file sharing; NL Design System theming |
| Process builder | Proprietary zero-code tool | n8n visual workflow builder (more powerful, open) |
| Rule engine | Synchronous decorator-based | n8n webhook triggers (more flexible, async) |
| Communication | Custom threaded messaging | Leverage Nextcloud Talk + n8n email automation |
| Geo-location | GeoJSON features on cases | OpenRegister geo fields + map visualization |
| Multi-tenant theming | File-based per tenant | NL Design System tokens (standardized, richer) |

### Can Deprioritize (xxllnc-specific or low priority)

| Feature | Reason |
|---------|--------|
| Worldline payments | Niche requirement; build on demand |
| Perl legacy compatibility | xxllnc-specific technical debt |
| SAML2 SP implementation | Nextcloud handles this natively |
| Microsoft Teams integration | Nextcloud has its own collaboration tools |
| Documentwatcher (desktop sync) | Nextcloud desktop client covers this |
| Anonymization (Datamask) | Can be added via n8n integration later |
| Digital signing (Zynyo/ValidSign) | Can be added via OpenConnector integration later |
