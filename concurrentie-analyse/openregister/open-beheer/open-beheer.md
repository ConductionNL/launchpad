# Open Beheer -- Competitor Analysis

## Overview

- **Website:** https://github.com/maykinmedia/open-beheer
- **Docs:** https://open-beheer.readthedocs.io/ (Sphinx, from `docs/` directory)
- **Open Source:** Yes (EUPL-1.2)
- **Self-Hosted:** Yes (Docker image `maykinmedia/open-beheer`)
- **Version:** 0.9.0 (pre-1.0, active development)
- **Summary:** Unified functional management interface across all technical registrations -- provides a user-friendly admin UI on top of systems like Open Zaak's Zaaktypencatalogus, allowing functional managers to configure case types, statuses, roles, and archiving without dealing with raw APIs
- **Tagline:** "Functioneel beheer voor/over alle registraties heen -- like a silo, but not a silo"

## Codebase

- **GitHub:** https://github.com/maykinmedia/open-beheer
- **Docker Hub:** https://hub.docker.com/r/maykinmedia/open-beheer
- **Backend:** Python 3.11+ / Django / DRF / msgspec / PostgreSQL / Redis
- **Frontend:** React 19 / TypeScript 5.7 / Vite 6.3 / React Router 7.5
- **UI Library:** @maykin/admin-ui (Maykin's component library)
- **Testing:** Vitest + Playwright (E2E) + Storybook 8.6
- **API types:** Auto-generated from OAS via openapi-typescript 7.8

## Business Model

Developed by Maykin Media, originally commissioned by Gemeente Rotterdam. The software is free under the EUPL license. Revenue is generated through implementation, customization, hosting, and support services. Like other Maykin products, it is offered through the OpenGem initiative with no license fees -- municipalities only pay for support and infrastructure.

### Stakeholders (Copyright Holders)

1. Dimpact (cooperative of 30+ municipalities)
2. Gemeente Den Haag
3. Gemeente Rotterdam (original commissioning party)
4. Gemeente Utrecht
5. Maykin (developer)

## Target Market

Functional managers (functioneel beheerders) in Dutch municipalities who need to configure and manage case types, document types, statuses, and other settings across multiple Common Ground registrations. Targets non-technical users who currently struggle with the fragmented, developer-oriented admin interfaces of individual registration components like Open Zaak.

## Pricing

- **Software:** Free (EUPL-1.2 license, no license costs)
- **SaaS (OpenGem):** Pay only for support and infrastructure, monthly cancellable
- **Self-hosted:** Free, with optional paid support from Maykin or partners

## Architecture

Open Beheer is a **Backend-for-Frontend (BFF)** -- it stores NO domain data. The Django backend proxies all requests to external ZGW APIs (Open Zaak Catalogi API, Selectielijst API, Objecttypen API). PostgreSQL only holds user accounts and service configuration. See `docs/architecture.md` for details.

```
React SPA (Vite) <-> Django BFF (msgspec) <-> Open Zaak Catalogi API
                                           <-> Selectielijst API (VNG)
                                           <-> Objecttypen API
```

## Key Features

### Domain Features
- **Zaaktype management**: Full CRUD for case types with nested resources (statustypen, resultaattypen, roltypen, besluittypen, eigenschappen, zaakobjecttypen, zaaktypeinformatieobjecttypen)
- **InformatieObjectType management**: CRUD for document type definitions
- **Version management**: Geldigheid-based version timeline with concept/published states
- **Template-based creation**: Pre-configured templates for common zaaktype patterns
- **Publishing workflow**: Draft -> edit -> publish lifecycle with automatic date management
- **Selectielijst integration**: National archival reference data for resultaattypen
- **Multi-service support**: Connect to multiple Open Zaak instances
- **Multi-catalogus support**: Select and switch between catalogi within a service

### Technical Features
- BFF proxy architecture -- works with any ZGW-compliant backend
- Dynamic field metadata (OBField) -- backend drives form rendering
- OIDC authentication (SSO)
- Session-based auth with CSRF protection
- 2FA enforcement for admin
- Brute-force protection (django-axes)
- Health check endpoints for Kubernetes
- OpenAPI spec (317KB OAS file)
- Auto-generated TypeScript types from OAS
- Storybook component documentation

## Ecosystem Integrations

| System | Integration Level | Notes |
|--------|------------------|-------|
| Open Zaak (Catalogi API) | **Primary** | Full CRUD proxy for all Catalogi resources |
| Selectielijst API (VNG) | **Direct** | National archival reference data |
| Objecttypen API | **Direct** | Object type definitions for zaakobjecttypen |
| Open Zaak (Zaken/Documenten API) | None | Only Catalogi, not case/document instances |
| Open Klant | Indirect | Configured zaaktypen consumed by Open Klant |
| Open Formulieren | Indirect | Forms use zaaktypen/informatieobjecttypen |
| Open Archiefbeheer | Indirect | Uses archival config from resultaattypen |
| Open Producten | None | No current integration |

### Predecessor: Open Zaaktypebeheer

Open Zaaktypebeheer (`maykinmedia/open-zaaktypebeheer`, v0.1.3) is a narrower tool
that only manages zaaktype-informatieobjecttype relations. Open Beheer is its broader
successor covering the full Catalogi API surface.

## Feature Comparison with OpenRegister

| Feature | Open Beheer | OpenRegister |
|---------|------------|--------------|
| JSON Schema data modeling | No (consumes existing schemas) | Yes |
| Auto-generated REST APIs | No (UI layer only) | Yes |
| Full-text search | No | Yes |
| Faceted search | No | Yes |
| RBAC | Partial (admin-level OIDC) | Yes |
| Audit trails | No (delegates to underlying APIs) | Yes |
| Multi-tenancy | No | Yes |
| Webhooks / Events | No (delegates to underlying APIs) | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Partial (displays existing relations) | Yes |
| Soft deletes | No | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | Yes (consumes NLGov APIs) | Yes |
| Draft/publish workflow | Yes | No |
| Version management (geldigheid) | Yes | No (has change-based versions) |
| Template-based creation | Yes | No |
| Inline related object editing | Yes | No |
| Dynamic field metadata API | Yes | Partial (JSON Schema) |
| Multi-backend proxy | Yes | No (is the backend) |
| Selectielijst integration | Yes | No |
| Storybook docs | Yes | No |

## Strengths

- Solves a real pain point -- functional managers currently need to navigate multiple technical admin interfaces to configure case types, and Open Beheer provides a unified, business-oriented view
- API-first approach means it works with any compliant backend, not tied to a single registration system
- Purpose-built for the Dutch government functional management workflow with process-oriented navigation
- Strong stakeholder backing (Dimpact = 30+ municipalities, Den Haag, Rotterdam, Utrecht)
- Modern tech stack (React 19, Vite, msgspec for high-performance serialization)
- Draft/publish workflow that OpenRegister lacks
- Version management with validity date ranges

## Weaknesses

- UI-only layer with no data storage or API capabilities of its own -- entirely dependent on underlying registration components like Open Zaak
- Very narrow scope -- only covers configuration/management of existing Catalogi API resources, not data modeling, storage, or querying
- Early-stage project (v0.9.0) with documentation still being developed (manual section is placeholder)
- Template system is hardcoded in Python (no database-backed templates)
- No search, no reporting, no analytics
- Only manages type definitions, not instance data (cases, documents)

## Competitive Assessment

Open Beheer competes with OpenRegister specifically on the admin UI experience for managing type definitions. The competitive dynamics are:

**Where Open Beheer wins:**
1. Works with existing ZGW ecosystem without requiring data migration
2. Draft/publish workflow for governance
3. Inline related object editing in data grids
4. Version timeline with geldigheid dates
5. Selectielijst integration for archival compliance
6. Growing stakeholder base (Dimpact could roll it out to 30+ municipalities)

**Where OpenRegister wins:**
1. Self-contained platform -- data storage, APIs, search, admin UI in one app
2. Flexible schema system (any data structure, not just ZGW types)
3. Full-text and faceted search
4. Audit trails and time-travel
5. RBAC with granular permissions
6. Nextcloud ecosystem (files, users, shares, collaboration)
7. AI/vector embeddings for semantic search
8. No external API dependency -- no proxy overhead

**Key strategic risk:**
Open Beheer could become the de facto standard management interface in the Common Ground ecosystem, backed by major municipalities and Dimpact. If municipalities standardize on Open Beheer + Open Zaak for type management, OpenRegister's admin UI needs to offer a compelling alternative or risk being perceived as "not the standard tool." However, OpenRegister's advantage as a full platform (not just a UI layer) means it serves a fundamentally different use case.

## Documentation

See `docs/` directory for detailed documentation:
- `docs/architecture.md` -- Technical architecture deep dive
- `docs/ecosystem-integrations.md` -- How it integrates with Common Ground components
- `docs/api-reference.md` -- BFF API endpoint reference
- `docs/gemeente-rotterdam-context.md` -- Rotterdam origin story and governance
- `docs/pdf-links.md` -- All documentation links and references
