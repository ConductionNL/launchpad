# OneGround ZGW — Competitor Analysis

## Overview

- **Website:** https://github.com/OneGround/ZGW-APIs
- **Open Source:** Yes (Community)
- **Self-Hosted:** Yes
- **Summary:** C# ZGW API implementation with microservices architecture

## Codebase

- https://github.com/OneGround/ZGW-APIs

## Business Model

Open-core model. The Community Edition is free and open source, available on GitHub for governments and software parties to install on their own hosting environment. Revenue comes from commercial editions with additional features, professional support contracts, managed hosting services, and implementation consulting. OneGround positions itself as the .NET alternative to the Python-based OpenZaak.

## Target Market

Dutch municipalities and government organizations that prefer a .NET/C# technology stack over Python (OpenZaak). Targets organizations needing a standards-compliant ZGW API backend with enterprise-grade features like multi-tenancy and Ceph storage. Also appeals to organizations wanting a modern microservices architecture for their ZGW infrastructure.

## Pricing

Community Edition: free and open source. Commercial editions and managed services are available at undisclosed pricing — contact OneGround for quotes. Pricing likely based on number of tenants, storage volume, and support level.

## Key Features

- Full implementation of VNG Realisatie ZGW API standards
- Catalogi API for case type catalogs and case types
- Zaken API for case registration with relationships to documents, decisions, and contacts
- Documenten API for information object registration
- Built with C# and .NET using microservices architecture
- Each API is an independent microservice
- Multi-tenant capability — one installation serves multiple organizations
- Ceph support for document content storage
- Authentication and authorization per ZGW standard
- Audit trail logging of all changes
- Archiving support according to Dutch standards
- Docker Compose and Kubernetes deployment support
- Developer portal with API documentation

## Feature Comparison with Procest

| Feature | OneGround ZGW | Procest |
|---------|-------|---------|
| Case lifecycle management | Partial (API only, no UI) | Yes |
| CMMN 1.1 support | No | Yes |
| ZGW API compatible | Yes | Yes |
| Deadline tracking | Partial (stores dates, no alerts) | Yes |
| Task assignment | No | Yes |
| Document checklists | No | Yes |
| Decisions (besluiten) | Yes (API only) | Yes |
| Sub-cases | Yes (API only) | Yes |
| Confidentiality levels | Yes | Yes |
| Audit trail | Yes | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes (API-level) | Yes |
| WCAG AA accessible | N/A (no end-user UI) | Yes |

## Strengths

- Modern .NET/C# microservices architecture appealing to organizations with Microsoft technology stacks
- Multi-tenant design enabling shared infrastructure across municipalities (cost-efficient)
- Full ZGW API compliance with enterprise features like Ceph storage and Keycloak integration

## Weaknesses

- API-only layer like OpenZaak — no user-facing case handling interface, requires a separate front-end
- Smaller community and adoption compared to OpenZaak
- No workflow engine, process modeling, or task management capabilities

## Notes

OneGround ZGW is similar to OpenZaak in scope — it is a ZGW API backend, not a case handling application. It competes with OpenZaak rather than with Procest directly. Procest could potentially use OneGround as a backend API layer. The .NET stack may be more appealing to municipalities with existing Microsoft infrastructure investments. The multi-tenant architecture is a notable advantage for shared service centers.
