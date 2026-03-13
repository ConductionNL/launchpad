# Operaton — Competitor Analysis

## Overview

- **Website:** https://operaton.org/
- **Open Source:** Yes (Apache 2.0)
- **Self-Hosted:** Yes
- **Summary:** Community-owned open-source BPM engine, fork of Camunda 7 CE

## Codebase

- https://github.com/operaton/operaton

## Business Model

Community-driven open-source project with no commercial entity behind it. Operaton is community-owned — no private or commercial entity can legally claim ownership or divert community goals. Revenue in the ecosystem comes from third-party service providers offering support, hosting, and implementation services around Operaton. The project itself does not charge for the software.

## Target Market

Organizations currently running Camunda 7 CE that need a migration path after Camunda 7 reached end-of-life in October 2025. Also targets developers and organizations seeking a free, open-source BPMN/DMN engine without vendor lock-in. Relevant for the Dutch government sector where Camunda 7 was used (e.g., as the engine inside Valtimo/GZAC).

## Pricing

Completely free and open source (Apache 2.0). No license costs, no subscriptions, no paid tiers. Support available through community forums and third-party service providers.

## Key Features

- Native BPMN 2.0 process engine running in the JVM
- CMMN 1.1 support for case management
- DMN 1.3 support for decision tables
- Full backward compatibility with Camunda 7 — same database schema, no migration needed
- REST API for remote process access
- Cockpit web application for process operations monitoring
- Admin web application for user/group management
- Tasklist web application for user task management
- Spring Boot and Quarkus integration
- Jakarta EE compatibility
- Plugin mechanism (compatible with Camunda 7 plugins with namespace changes)
- Over 25,000 automated tests ensuring compatibility
- Fundamental code modernization beyond simple renaming

## Feature Comparison with Procest

| Feature | Operaton | Procest |
|---------|-------|---------|
| Case lifecycle management | Partial (CMMN engine) | Yes |
| CMMN 1.1 support | Yes | Yes |
| ZGW API compatible | No | Yes |
| Deadline tracking | Yes (timers) | Yes |
| Task assignment | Yes | Yes |
| Document checklists | No | Yes |
| Decisions (besluiten) | Partial (DMN only) | Yes |
| Sub-cases | Yes (sub-processes/cases) | Yes |
| Confidentiality levels | No | Yes |
| Audit trail | Yes | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| WCAG AA accessible | Partial | Yes |

## Strengths

- Drop-in replacement for Camunda 7 CE with zero database migration — critical for organizations needing a continuation path
- Truly community-owned with no commercial entity — guarantees long-term openness and independence
- Supports BPMN, CMMN, and DMN standards in a single engine (unlike Camunda 8 which dropped CMMN)

## Weaknesses

- Young project (v1.0 released November 2025) — limited production track record and small community
- Generic BPM engine with no domain-specific features for Dutch government case management
- Inherits Camunda 7 architecture limitations — not cloud-native, no horizontal scaling without external tooling

## Notes

Operaton is relevant as the likely migration path for Valtimo/GZAC, which was built on Camunda 7. If Valtimo migrates to Operaton, it gets a continued open-source engine. However, Operaton itself is not a case management application — it is an engine component. Procest competes at a higher level as a complete zaakgericht werken application. Operaton could theoretically be used as a process engine inside Procest or other applications.
