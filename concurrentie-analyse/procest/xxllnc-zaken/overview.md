# xxllnc Zaken - Competitive Analysis Overview

**Competitor:** xxllnc Zaken (formerly Zaaksysteem.nl)
**Category:** SaaS Zaaksysteem / Case Management Platform
**Analyzed date:** 2026-03-14
**Website:** https://xxllnc.nl/applicaties/zaken
**GitLab:** https://gitlab.com/xxllnc/zaakgericht/zaken/start (public)
**Help center:** https://help.zaaksysteem.nl/hc/nl

## Company Background

xxllnc is a Dutch software company headquartered in Hengelo, focused on smart and scalable software for (semi-)government organizations. The company was formed through a series of mergers and acquisitions:

- **Exxellence Groep** — original company, zaaksysteem and advisory for local government
- **Zaaksysteem.nl** — acquired by Exxellence in June 2020; founded 2009 in Amsterdam, ~30 employees, ~50 clients (mostly small/medium municipalities)
- **Tog Nederland** — acquired August 2021; after this acquisition the combined company was rebranded to **xxllnc** with ~430 employees
- **Main Capital** — private equity investor since March 2020

xxllnc now has 100+ employees (core team) and 120+ clients including municipalities, water boards, housing corporations, and healthcare institutions. With the Tog acquisition, total staff reached ~430.

## Product Portfolio

xxllnc operates in several domains with 25+ integrated applications on the **xxllnc Cloud**:

| Domain | Key Applications |
|--------|-----------------|
| Zaakgericht Werken | Zaken, Formulieren, Koppelen, HaalCentraal, Publiceren, Samenwerken |
| Belastingen | Belastingen platform |
| Sociaal Domein | Regiesysteem, Sociale PDC |
| Omgeving | Omgevingsdocumenten, DSO integration |
| Onderwijs | Onderwijsloket |

## Target Market

- Dutch municipalities (gemeenten) — primary target
- Water boards (waterschappen)
- Housing corporations (woningbouwcorporaties)
- Healthcare institutions (zorginstellingen)
- Semi-governmental organizations

## Market Position

xxllnc positions itself as "Het open source zaaksysteem van Nederland" (The open source case management system of the Netherlands). Key differentiators:
- Open architecture based on Common Ground principles
- Self-service process builder (zero-coding)
- Certified archiving (NEN-ISO 16175-1:2020 / formerly NEN-2082)
- SaaS delivery model via xxllnc Cloud
- ZGW-API compliance (developed with Conduction)
- Microsoft Teams integration (available on Microsoft Marketplace)
- 25+ integrated applications in one cloud platform

## Notable Reference Clients

| Municipality | Scale | Key Details |
|-------------|-------|-------------|
| **Utrecht** | ~750 zaaktypen, 8M+ cases, 2,300+ users | Migration from Exxellence Suite; largest known deployment |
| **Epe** | ~200 zaaktypen | Early adopter (2014); replaced multiple back-office systems |

## Technology Stack

Based on the public GitLab repository (54,422 commits, 345 branches, 2,550 tags):

| Component | Technology | Share |
|-----------|-----------|-------|
| Frontend | TypeScript | 27.4% |
| Legacy/Tooling | Perl | 25.5% |
| Backend | Python (Minty framework) | 19.8% |
| Frontend/Scripts | JavaScript | 13.0% |
| Database | PLpgSQL (PostgreSQL) | 5.2% |

### Custom Framework: Minty
- **minty** — Domain Driven Design core library
- **minty-pyramid** — API server layer on top of Python Pyramid framework
- **minty-infra-sqlalchemy** — SQLAlchemy database infrastructure
- Microservices architecture with Docker Compose

## Pricing

No public pricing available. Custom quotes based on:
- Number of processes configured
- Required integrations
- Level of support and implementation assistance
- Implementation timeline (can range from 3 months to 3 years)

## Codebase Analysis (from GitLab source code)

The following analysis is based on the actual source code at `gitlab.com/xxllnc/zaakgericht/zaken/start` (EUPL-1.2 licensed, publicly accessible).

### System Architecture

```
                                    +-------------------+
                                    |   Nginx Reverse   |
                                    |   Proxy (HTTPS)   |
                                    +--------+----------+
                                             |
              +------------------------------+-------------------------------+
              |              |               |              |                |
    +---------+---+  +------+------+  +------+------+ +----+-----+  +-------+------+
    | React FE    |  | Perl API    |  | Python HTTP |  | Swagger  |  | Keycloak     |
    | (main, mor, |  | (v1, legacy)|  | Services    |  | UI       |  | (auth, SAML) |
    | my-pip,     |  |             |  | (v2 APIs)   |  |          |  |              |
    | vergadering)|  +------+------+  +------+------+  +----------+  +--------------+
    +-------------+         |                |
                            |                |
              +-------------+----------------+--------------------+
              |              |               |                    |
    +---------+---+  +------+------+  +------+------+  +---------+--------+
    | PostgreSQL  |  | RabbitMQ    |  | Redis       |  | Minio (S3)       |
    | (data store)|  | (events &   |  | (sessions & |  | (file storage)   |
    |             |  |  bg tasks)  |  |  caching)   |  |                  |
    +-------------+  +------+------+  +-------------+  +------------------+
                            |
              +-------------+-----------------------------+
              |              |               |            |
    +---------+---+  +------+------+  +------+------+ +--+-----------+
    | Case Events |  | Doc Process |  | Communication| | Notification |
    | Consumer    |  | Consumer    |  | Consumer     | | Consumer     |
    +-------------+  +-------------+  +-------------+  +--------------+
```

### Backend Services (8 Python HTTP microservices)

| Service | Path | Endpoints | Domain |
|---------|------|-----------|--------|
| http-admin | /api/v2/admin/ | ~30 | Catalog, case types, templates, integrations, logging |
| http-auth | /api/v2/auth/ | 1 | OAuth2/SAML auth flows |
| http-cm | /api/v2/cm/ | ~80 | Case lifecycle, tasks, contacts, search, dashboard |
| http-communication | /api/v2/communication/ | ~17 | Threads, messages, contact moments |
| http-document | /api/v2/document/ | ~30 | Documents, files, preview, locking, archival |
| http-geo | /api/v2/geo/ | 2 | GeoJSON features |
| http-jobs | /api/v2/jobs/ | 6 | Background job management |
| http-style | /api/v2/style/ | ~3 | Per-tenant theming |

### Event Consumers (10 AMQP consumers)

All entity mutations emit named events via RabbitMQ. A logging consumer subscribes to ALL events for audit trail. Domain-specific consumers handle reactions (document processing, notifications, geo sync, etc.).

### Core Domain: Case Entity

The Case entity has ~80+ fields covering:
- Lifecycle (status: new/open/stalled/resolved/deleted)
- Phase system (numbered milestones with rules)
- Contacts (coordinator, assignee, requestor -- person/employee/organization)
- Dates (registration, target_completion, completion, destruction)
- Archival compliance (type_of_archiving, preservation_period, selection_list)
- Custom fields, payments, hierarchy (parent/child)
- 4-level authorization per case (search/read/write/manage)

### Custom Minty Framework

CQRS/Event Sourcing framework with:
- `@Entity.event("EventName")` decorators on entity methods
- `@apply_case_rules` decorator for automatic phase rule execution
- Pydantic v1 entity validation (migrating to v2)
- SQLAlchemy infrastructure wrappers
- Pyramid HTTP integration

### Test Coverage

310 Python test files + Playwright e2e tests + Perl test suites.

### Feature Spec Index

See `specs/` for detailed analysis of each domain:
- [case-management](specs/case-management/spec.md) -- Core case lifecycle
- [document-management](specs/document-management/spec.md) -- Document pipeline
- [communication](specs/communication/spec.md) -- Messaging and contact moments
- [catalog-admin](specs/catalog-admin/spec.md) -- Case type configuration
- [task-management](specs/task-management/spec.md) -- Phase-bound tasks
- [rule-engine](specs/rule-engine/spec.md) -- Automatic business rules
- [authentication](specs/authentication/spec.md) -- Auth and authorization
- [geo-location](specs/geo-location/spec.md) -- GeoJSON features
- [payments](specs/payments/spec.md) -- Worldline integration
- [background-jobs](specs/background-jobs/spec.md) -- Event system and jobs
- [style-configuration](specs/style-configuration/spec.md) -- Multi-tenant theming
- [citizen-portal](specs/citizen-portal/spec.md) -- Mijn PIP citizen app

## Relevance to Procest

xxllnc Zaken is a direct competitor to Procest in the Dutch municipal case management market. Key areas where Procest should differentiate:

1. **Open source advantage** -- xxllnc claims open source but the actual application code is proprietary SaaS; Procest on Nextcloud is genuinely open
2. **Self-hosted option** -- xxllnc is cloud-only; Procest via Nextcloud offers self-hosted deployment
3. **Modern stack** -- xxllnc has legacy Perl (25.5%); Procest can leverage modern PHP/Vue.js
4. **Integration approach** -- xxllnc requires their "Koppelen" product for integrations; Procest can use OpenConnector natively
5. **Cost model** -- xxllnc requires custom enterprise contracts; Procest can offer simpler per-app pricing through Nextcloud App Store

### Technical Insights from Codebase

6. **Case type versioning** -- xxllnc's version system is essential for production use (updating process definitions without affecting running cases). Procest needs this.
7. **Phase-based rule engine** -- Automatic business rule execution on case mutations is a powerful pattern. Procest can replicate with n8n webhooks.
8. **Document processing pipeline** -- Virus scan + conversion + preview + search indexing as separate microservices. Procest can leverage Docudesk + n8n.
9. **Event sourcing for audit** -- All mutations logged automatically. Procest should implement audit logging in OpenRegister.
10. **Dutch government depth** -- StUF, BRP, KvK, DSO, archival compliance. This domain knowledge takes years to build.
