# Flowable — Competitor Analysis

## Overview

- **Website:** https://www.flowable.com/
- **Open Source:** Yes (Apache 2.0)
- **Self-Hosted:** Yes
- **Summary:** Workflow engine supporting BPMN, CMMN, and DMN

## Codebase

- https://github.com/flowable/flowable-engine

## Business Model

Open-core model. The core Flowable engine is open source under Apache 2.0. Revenue comes from enterprise editions with additional features (clustering, advanced monitoring, connectors), professional support subscriptions, training, and implementation consulting. Also offers Flowable Cloud Design as a free-to-use modeling tool to drive adoption. Enterprise customers include banks (e.g., ZKB Zurich), insurers, and government organizations.

## Target Market

Enterprise organizations across industries needing process automation and case management. Strong presence in financial services (banks, insurers), government, and large enterprises. Used by Dimpact ZAC as its embedded process engine. Targets both developers (open-source engine) and business users (enterprise low-code platform).

## Pricing

Open-source engine: free (Apache 2.0). Enterprise editions: not publicly disclosed — contact Flowable for pricing. Flowable Cloud Design (modeling tool): free. Enterprise pricing is typically based on number of cores/instances and support level.

## Key Features

- BPMN 2.0 engine for business process modeling and execution
- CMMN 1.1 engine for case management modeling
- DMN 1.1 engine for decision tables
- Low-code platform for rapid application development (enterprise)
- AI chat integration for model editing using prompts (2025 release)
- Form designer for building user interfaces
- Event-driven architecture with event registry
- REST API for all engine operations
- Clustering and horizontal scalability (enterprise)
- Rich connector framework for integration
- Spring Boot integration for Java applications
- Embeddable engine — can run inside any Java application

## Feature Comparison with Procest

| Feature | Flowable | Procest |
|---------|-------|---------|
| Case lifecycle management | Yes (via CMMN) | Yes |
| CMMN 1.1 support | Yes (native) | Yes |
| ZGW API compatible | No | Yes |
| Deadline tracking | Yes (timers) | Yes |
| Task assignment | Yes | Yes |
| Document checklists | No | Yes |
| Decisions (besluiten) | Partial (DMN only) | Yes |
| Sub-cases | Yes | Yes |
| Confidentiality levels | No | Yes |
| Audit trail | Yes | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| WCAG AA accessible | Partial (enterprise UI) | Yes |

## Strengths

- Native support for all three standards (BPMN, CMMN, DMN) in a single engine — most comprehensive standards coverage
- Battle-tested in high-throughput enterprise environments (banks, insurers) with proven scalability
- Embedded as the engine inside Dimpact ZAC — proven in Dutch government context

## Weaknesses

- Generic process engine with no domain-specific features for Dutch government (no ZGW, no zaakgericht werken)
- Enterprise features (clustering, advanced UI, connectors) require paid license — open-source version is limited
- Requires significant development effort to build a complete case management application on top of the engine

## Notes

Flowable is not a direct competitor to Procest — it is a component that Procest or other case management systems could embed. Dimpact ZAC already uses Flowable as its process engine. Flowable's strength is in pure standards compliance (BPMN+CMMN+DMN). Procest's advantage is being a complete, domain-specific application for Dutch zaakgericht werken rather than a generic engine that requires assembly.
