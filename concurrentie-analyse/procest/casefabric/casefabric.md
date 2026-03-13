# CaseFabric (Cafienne) — Competitor Analysis

## Overview

- **Website:** https://casefabric.com/
- **Open Source:** Yes (AGPL-3.0 / Commercial dual license)
- **Self-Hosted:** Yes
- **Summary:** 100% CMMN 1.1 compliant dynamic case management platform

## Codebase

- https://github.com/casefabric/cafienne-engine
- https://github.com/casefabric/getting-started

## Business Model

Dual-license model. Free use is granted under the GNU Affero General Public License version 3 (AGPL-3.0), while commercial use is covered by a separate commercial license (Batav Cafienne SLA). Revenue comes from commercial licenses, professional support, implementation consulting, and partnership fees. CaseFabric also integrates with Mendix for low-code case management solutions.

## Target Market

Organizations needing dynamic case management with strict CMMN compliance. Targets government organizations, knowledge-intensive industries, and enterprises where cases follow unpredictable paths rather than fixed workflows. Also targets Mendix customers wanting to add case management capabilities to their low-code applications.

## Pricing

Open source: free under AGPL-3.0. Commercial license: pricing not publicly disclosed — contact CaseFabric for quotes. The AGPL license requires that any modifications be shared as open source, which effectively pushes commercial users toward the paid license.

## Key Features

- 100% CMMN 1.1 compliant case engine — the only platform claiming full compliance
- Scala/Akka-based engine built for event sourcing and scalability
- Human Tasks, Process Tasks, Case Tasks, and Decision Tasks per CMMN spec
- Dynamic case team management — add case workers from within or outside the organization
- Case file management tracking all information added during case lifecycle
- Docker and Kubernetes deployment support
- REST API for all case operations
- Mendix integration architecture for low-code case management
- Event-sourced architecture for complete case history
- Discretionary items allowing case workers to choose optional tasks

## Feature Comparison with Procest

| Feature | CaseFabric (Cafienne) | Procest |
|---------|-------|---------|
| Case lifecycle management | Yes | Yes |
| CMMN 1.1 support | Yes (100% compliant) | Yes |
| ZGW API compatible | No | Yes |
| Deadline tracking | Yes (CMMN timers) | Yes |
| Task assignment | Yes (case teams) | Yes |
| Document checklists | No | Yes |
| Decisions (besluiten) | Partial (CMMN decisions) | Yes |
| Sub-cases | Yes (CMMN case tasks) | Yes |
| Confidentiality levels | No | Yes |
| Audit trail | Yes (event sourcing) | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes (case team roles) | Yes |
| WCAG AA accessible | No (engine only) | Yes |

## Strengths

- Only platform with claimed 100% CMMN 1.1 compliance — strongest standards adherence for dynamic case management
- Event-sourced architecture provides complete, immutable case history — excellent for audit requirements
- Scala/Akka technology stack designed for high concurrency and scalability

## Weaknesses

- Very small community and limited adoption — niche product with few production deployments
- Engine-only approach — no end-user UI, no forms, no document management out of the box
- No Dutch government domain knowledge — no ZGW APIs, no zaakgericht werken specifics

## Notes

CaseFabric is philosophically closest to Procest in its focus on CMMN for dynamic case management. However, it remains an engine-level product without a complete user-facing application. The AGPL license is strategically interesting — it forces commercial users to either open-source their modifications or buy a commercial license. Procest's advantage is being a complete application with ZGW compliance and Nextcloud integration, while CaseFabric is a lower-level engine component.
