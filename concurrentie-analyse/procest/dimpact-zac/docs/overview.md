# Dimpact ZAC (Zaakafhandelcomponent) — Overview

## What is ZAC?

ZAC (Zaakafhandelcomponent) is an open-source, generic, workflow-based Common Ground component for managing 'zaken' (cases) in the context of zaakgericht werken, a Dutch approach to case management. It is developed for the [Dimpact cooperative](https://www.dimpact.nl/) and used by Dutch municipalities.

- **Repository**: https://github.com/infonl/dimpact-zaakafhandelcomponent
- **License**: EUPL 1.2
- **Current version**: v4.4.38 (March 2026)
- **Language**: Kotlin (backend, migrating from Java), TypeScript/Angular (frontend)
- **Stars**: 6 | **Forks**: 2 | **Open issues**: 11

## History

- Initially developed by **Atos** (as [NL-AMS-LOCGOV/zaakafhandelcomponent](https://github.com/NL-AMS-LOCGOV/zaakafhandelcomponent))
- July 2023: Development taken over by **Lifely** and **INFO.nl**
- Repository created: June 2023

## Dimpact Cooperative

- **42 municipalities** collaborate through Dimpact
- ZAC is the core component of **PodiumD Zaak**, part of the broader PodiumD suite
- PodiumD suite includes: PodiumD Portaal, PodiumD Contact, PodiumD Formulier, PodiumD Zaak
- First municipality implementations planned for late 2024 / second half 2025

## Technology Stack

| Component | Technology |
|-----------|-----------|
| Backend language | Kotlin (migrating from Java) |
| Frontend framework | Angular (TypeScript) |
| Application server | WildFly |
| Process engine | Flowable (embedded, CMMN + BPMN) |
| Database | PostgreSQL |
| Search engine | Apache Solr |
| Policy engine | Open Policy Agent (OPA) |
| Document conversion | OfficeConverter (LibreOffice-based) |
| Authentication | Keycloak (OIDC) |
| Authorization | PABC (Platform Autorisatie Beheer Component) — new |
| Deployment | Kubernetes (Azure AKS), Helm Chart |
| CI/CD | GitHub Actions |
| Observability | OpenTelemetry, Grafana, Tempo, Prometheus |
| Caching | Caffeine (in-memory) |
| Database migrations | Flyway |
| Forms (BPMN) | Form.io |
| Message broker | RabbitMQ |

## Common Ground Compliance

ZAC follows Common Ground principles:
- Component-based architecture with clear bounded context
- Open source (EUPL 1.2)
- Data at the source (Open Zaak as single source of truth)
- Kubernetes deployment with Helm charts
- NOT compliant with NLX or Haven standards
- Does NOT expose a public API (backend is BFF-only for the Angular frontend)

## Key External Dependencies

### Common Ground Components
- **Open Zaak** — Zaken, Documenten, Catalogi, Besluiten APIs
- **Open Notificaties** — Event notification bus
- **Open Klant** — Customer/citizen data (Klantinteracties API)
- **Objecten API** — Product requests storage
- **Open Formulieren** — Citizen-facing forms (indirect integration)
- **Open Archiefbeheer** — Archiving and record destruction
- **PABC** — Platform authorization management (new)

### External Services
- **Haal Centraal BAG** — Address/location data
- **Haal Centraal BRP** — Personal data (citizens)
- **KVK** — Chamber of Commerce company data
- **SmartDocuments** — Document creation wizard
- **SMTP** — Email sending
- **Microsoft Office Desktop Apps** — WebDAV document editing

## Documentation Structure

- [Solution Architecture](solution-architecture.md) — C4 model architecture docs
- [System Context](system-context.md) — External integrations
- [Data Model](data-model.md) — Database and storage
- [IAM Architecture](iam-architecture.md) — Authentication and authorization
- [Access Control Policies](access-control-policies.md) — OPA role-permission matrix
- [Process Automation](process-automation.md) — CMMN/BPMN engine
- [Product Request Flow](product-request-flow.md) — Productaanvraag support
- [SmartDocuments Integration](smartdocuments-integration.md) — Document generation
- [Solr Architecture](solr-architecture.md) — Search engine
- [Deployment Model](deployment-model.md) — Kubernetes deployment
- [User Manual Features](user-manual-features.md) — Feature summary from gebruikershandleiding
- [Configuration Manual](configuration-manual.md) — Admin/setup features
