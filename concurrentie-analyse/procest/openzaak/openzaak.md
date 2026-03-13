# OpenZaak — Competitor Analysis (Comprehensive)

## Overview

- **Website:** https://openzaak.org/
- **Open Source:** Yes (EUPL-1.2)
- **Self-Hosted:** Yes (Docker/Kubernetes)
- **Summary:** Open-source ZGW API layer for zaakgericht werken
- **Current Version:** 1.27.0 (released 2026-02-06)
- **Technology:** Python 3.12 / Django / PostgreSQL 14+ / Redis / Celery
- **Primary Maintainer:** Maykin Media B.V.
- **Coordinator:** Dimpact

## Codebase

- https://github.com/open-zaak/open-zaak
- https://github.com/open-zaak/open-notificaties
- https://hub.docker.com/r/openzaak/open-zaak
- Docs: https://open-zaak.readthedocs.io/en/latest/

## Business Model

Community-driven open-source. No license fees. Revenue through ecosystem vendors (Maykin Media, Contezza) providing hosting, implementation, support, and customization. Development co-funded by participating municipalities through Dimpact. SaaS available via opengem.nl (one-day setup), or on-premise with optional service agreements.

## Target Market

Dutch municipalities and government organizations needing a standards-compliant ZGW API backend. Primarily organizations wanting a shared data layer that multiple frontend applications connect to. Founding municipalities: Amsterdam, Rotterdam, Utrecht, Tilburg, Arnhem, Haarlem, 's-Hertogenbosch, Delft, SED coalition (Hoorn, Medemblik, Stede Broec, Drechterland, Enkhuizen).

## Pricing

Free and open source (EUPL-1.2). No license costs. Pay for hosting + support only. SaaS via opengem.nl or self-hosted.

## API Implementation

| API | OpenZaak Version | VNG Standard |
|-----|-----------------|-------------|
| Zaken API | 1.5.1 | 1.5.1 (concept: 1.6.0) |
| Documenten API | 1.4.2 | 1.5.0 (concept: 1.6.0) |
| Catalogi API | 1.3.1 | 1.3.1 (concept: 1.3.2) |
| Besluiten API | 1.1.0 | 1.0.2 (concept: 1.1.0) |
| Autorisaties API | 1.0.0 | 1.0.0 |

Plus numerous experimental extensions (convenience endpoints, cloud events, mandate support, bulk import).

## Key Features

- Full implementation of all 5 ZGW API standards + experimental extensions
- Open Notificaties for event-driven integrations across systems
- Django admin interface for catalog, authorization, and data management
- Authorization module with per-type, per-scope, per-confidentiality-level controls
- 8-level confidentiality system (openbaar through zeer_geheim) with visibility filtering
- Full archiving model with Selectielijst integration and automatic archiefactiedatum calculation
- Zaaktype versioning with concept/publish workflow
- Catalog export/import (.zip archives)
- Document locking, versioning, and multi-backend storage (filesystem, Azure Blob, S3)
- Mandate-based case management (DigiD Machtigen, eHerkenning, ketenmachtiging)
- Bulk document import via CSV
- Cloud Events (experimental) for modern event-driven architecture
- OpenID Connect SSO (ADFS, Azure AD, Keycloak)
- Docker/Kubernetes deployment with performance-tested scaling
- NLX integration for government data exchange
- External ZGW API consumption (federated model)
- Comprehensive audit trail

## Feature Comparison with Procest

| Feature | OpenZaak | Procest |
|---------|-------|---------|
| Case lifecycle management | API only (no UI) | Full UI + API |
| CMMN 1.1 support | No | Yes |
| ZGW API compatible | Reference implementation | Yes (partial) |
| Deadline tracking | Stores dates, no alerts | Yes (active alerts) |
| Task assignment | No | Yes |
| Document checklists | No | Yes |
| Decisions (besluiten) | Yes (API only) | Yes |
| Sub-cases | Yes (API only) | Yes |
| Confidentiality levels | Full 8-level model | Basic |
| Audit trail | Full VNG-compliant | Basic Nextcloud logging |
| Nextcloud integration | No | Native |
| RBAC | API-level (scopes + types + confidentiality) | Nextcloud groups |
| WCAG AA accessible | N/A (no end-user UI) | Yes |
| Archiving/Selectielijst | Full compliance | Basic |
| Document locking | Yes (lockId mechanism) | No |
| Document versioning | Yes (full history) | No |
| Catalog versioning | Yes (concept/publish/version) | No |
| Mandate support | Yes (DigiD/eHerkenning) | No |
| Cloud Events | Experimental | No |
| NLX integration | Yes | No |
| OIDC SSO | Yes (admin) | Nextcloud SSO |
| Bulk document import | Yes | No |
| Dashboard/KPIs | No | Yes |
| My Work view | No | Yes |
| NL Design theming | No (no UI) | Yes |

## Strengths

- **De facto standard** — reference implementation of ZGW APIs with broadest adoption in Dutch government
- **Largest community** — 9+ founding municipalities including Amsterdam, Rotterdam, Utrecht
- **Full VNG compliance** — including complex archiving model, confidentiality, mandate support
- **Active development** — ~12 releases/year, adding experimental features ahead of the standard
- **Production-tested** — performance tested for 2,000 concurrent users
- **Federated model** — cross-vendor interoperability through standard APIs
- **Ecosystem** — integrates with Open Notificaties, Open Formulieren, Open Klant, Open Inwoner, Open Archiefbeheer

## Weaknesses

- **No end-user UI** — API-only layer, requires separate frontend (ZAC, Valtimo, etc.)
- **No workflow engine** — no process modeling, BPMN, CMMN, or task management
- **No deadline alerting** — stores dates but provides no active monitoring
- **Complex deployment** — requires PostgreSQL + PostGIS + Redis + Celery + Open Notificaties
- **Django admin** — functional but not modern; designed for administrators, not end users
- **Single technology** — Python/Django only (vs. Procest's PHP/Nextcloud ecosystem)

## Strategic Position

OpenZaak is **not a direct competitor** to Procest but a **complementary component** and **compliance reference**:

1. **Integration partner** — Procest could consume OpenZaak APIs for maximum interoperability
2. **Implementation reference** — shows exactly what full VNG compliance looks like
3. **Feature roadmap guide** — experimental features indicate where the standard is heading
4. **Market validator** — municipal adoption proves the market need

## Critical Compliance Gaps in Procest (Learned from OpenZaak)

| Gap | Priority | Risk |
|-----|----------|------|
| Archiving/Selectielijst model | CRITICAL | Legal non-compliance (Archiefwet) |
| Full confidentiality enforcement | CRITICAL | Data breach risk |
| Case closure enforcement | HIGH | Data integrity issues |
| Document locking | HIGH | Concurrent editing conflicts |
| Full audit trail | HIGH | Compliance gap |
| Catalog versioning (concept/publish) | HIGH | Configuration management |
| Notification integration | MEDIUM | No event-driven architecture |
| External API interoperability | MEDIUM | No federated participation |
| Mandate authentication | MEDIUM | No citizen portal support |

## Documentation Index

- `docs/` — 15 markdown files covering all ReadTheDocs pages, VNG standards, and GitHub documentation
- `specs/` — 15 feature spec files with "Already in Procest" / "Not yet in Procest" analysis
- `business-logic/` — ZGW data model reference and strategic position analysis
- `docs/pdf-links.md` — all documentation, specification, and resource links
