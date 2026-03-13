# Valtimo (GZAC) — Competitor Analysis

## Overview

- **Website:** https://www.valtimo.nl/
- **Open Source:** Yes (MIT)
- **Self-Hosted:** Yes
- **Summary:** Low-code process automation / Generic Case Management Component

## Codebase

- https://github.com/valtimo-platform/valtimo-backend-libraries
- https://github.com/valtimo-platform/valtimo-frontend-libraries

## Business Model

Open-core model. The core Valtimo platform is open source (MIT license) with no licensing fees. Revenue comes from paid subscriptions that provide security guarantees, liability coverage, professional support, bug fixes, training, and influence over the product roadmap. Additionally, Ritense (the company behind Valtimo) offers a fully managed SaaS option where hosting, updates, and security are handled for the customer. Professional services including implementation consulting and custom development are also offered.

## Target Market

Dutch municipalities and government organizations implementing zaakgericht werken (ZGW). The GZAC edition specifically targets the Common Ground architecture used by Dutch local government. Also used by other public sector organizations and some private sector companies needing process-driven case management.

## Pricing

Free open-source core (MIT). Paid subscriptions for enterprise support, security guarantees, and SaaS hosting. Specific pricing is not publicly disclosed — organizations need to contact Ritense/Taxonic for quotes. Pricing typically scales based on number of users and level of support required.

## Key Features

- Built on top of Camunda 7 as its workflow/process engine
- Full support for BPMN 2.0 process modeling and execution
- CMMN support for dynamic case management
- DMN support for decision tables
- ZGW API integration following Common Ground principles
- Low-code form designer for building user interfaces
- Plugin system for extending functionality with connectors
- Case file management with document storage
- Task management with assignment and delegation
- Configurable dashboards and reporting
- Multi-tenant architecture

## Feature Comparison with Procest

| Feature | Valtimo (GZAC) | Procest |
|---------|-------|---------|
| Case lifecycle management | Yes | Yes |
| CMMN 1.1 support | Partial (via Camunda) | Yes |
| ZGW API compatible | Yes | Yes |
| Deadline tracking | Yes | Yes |
| Task assignment | Yes | Yes |
| Document checklists | Partial | Yes |
| Decisions (besluiten) | Yes | Yes |
| Sub-cases | Yes | Yes |
| Confidentiality levels | Partial | Yes |
| Audit trail | Yes | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| WCAG AA accessible | Partial | Yes |

## Strengths

- Mature process engine (Camunda 7) with extensive BPMN/DMN capabilities and a large Java ecosystem
- Strong adoption in Dutch government with GZAC variant actively used by multiple municipalities
- Comprehensive plugin architecture allowing easy integration with third-party systems

## Weaknesses

- Dependency on Camunda 7 which reached end-of-life in October 2025 — migration path unclear
- Complex Java/Spring Boot stack requiring significant developer expertise to customize
- No native document management — relies on external systems like OpenZaak for document storage

## Notes

Valtimo/GZAC is one of the most direct competitors to Procest in the Dutch government market. It is developed by Ritense (now Taxonic) and has a growing community of municipalities. The dependence on the now end-of-life Camunda 7 is a strategic risk that Procest can capitalize on.
