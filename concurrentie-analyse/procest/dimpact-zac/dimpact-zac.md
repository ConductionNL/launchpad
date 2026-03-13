# Dimpact ZAC — Competitor Analysis

## Overview

- **Website:** https://github.com/infonl/dimpact-zaakafhandelcomponent
- **Open Source:** Yes (EUPL-1.2)
- **Self-Hosted:** Yes
- **Summary:** Open-source workflow-based zaakafhandelcomponent

## Codebase

- https://github.com/infonl/dimpact-zaakafhandelcomponent
- Related project: https://gitlab.com/zaaksysteem/zaaksysteem

## Business Model

Cooperative model. ZAC is developed on behalf of Dimpact, a cooperative association (cooperatieve vereniging) of, for, and by Dutch municipalities. Members own the cooperative and direct development priorities. Development costs are shared among member municipalities. Implementation and support services are provided by commercial partners (initially Atos, now Lifely and INFO.nl since July 2023). No license fees — the software is freely available under EUPL-1.2.

## Target Market

Dutch municipalities that are members of the Dimpact cooperative. Currently used by dozens of municipalities implementing zaakgericht werken on Common Ground architecture. Focus on generic case handling that covers 80-90% of all case types with a single configurable work process.

## Pricing

Free and open source (EUPL-1.2). Municipalities pay through their Dimpact cooperative membership, which covers development costs. Implementation and hosting costs are separate and depend on the chosen service provider. Non-Dimpact members can use the software freely but must arrange their own support and hosting.

## Key Features

- Includes Flowable engine for both CMMN and BPMN process execution
- Generic work process covering 80-90% of case types, easily configurable
- Built according to Common Ground principles (data at the source)
- Angular front-end with TypeScript for type-safe development
- Connects to external ZGW APIs (OpenZaak or similar) for data storage
- Task management with case worker assignment
- Document handling with linking to external DMS
- Runs on Azure Kubernetes Services (AKS) with automated CI/CD via GitHub Actions
- Search and filtering capabilities for cases
- Support for case-type-specific and generic process models

## Feature Comparison with Procest

| Feature | Dimpact ZAC | Procest |
|---------|-------|---------|
| Case lifecycle management | Yes | Yes |
| CMMN 1.1 support | Yes (via Flowable) | Yes |
| ZGW API compatible | Yes | Yes |
| Deadline tracking | Yes | Yes |
| Task assignment | Yes | Yes |
| Document checklists | Partial | Yes |
| Decisions (besluiten) | Yes | Yes |
| Sub-cases | Yes | Yes |
| Confidentiality levels | Yes | Yes |
| Audit trail | Yes | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| WCAG AA accessible | Partial | Yes |

## Strengths

- Strong cooperative governance model ensuring development is driven by actual municipal needs
- Uses Flowable engine providing both CMMN and BPMN support in a single platform
- Active development with professional teams (Lifely, INFO.nl) and transparent open-source process

## Weaknesses

- Tied to Azure cloud infrastructure — limits deployment flexibility for organizations preferring on-premise or other clouds
- Requires external ZGW API backend (like OpenZaak) — not a self-contained solution
- Governance through cooperative can slow down decision-making compared to vendor-driven products

## Notes

ZAC is a strong open-source competitor in the Dutch municipal market. The cooperative model provides legitimacy and buy-in from municipalities but can make the product slower to evolve. The Flowable engine gives ZAC a modern process engine advantage. Procest differentiates with native Nextcloud integration and a more self-contained architecture.
