# xxllnc Zaken — Competitor Analysis

## Overview

- **Website:** https://xxllnc.nl/teams/zaakgericht
- **Open Source:** Partial (some components open source)
- **Self-Hosted:** No (SaaS)
- **Summary:** Modern case management from Dutch municipal practice

## Codebase

- https://gitlab.com/xxllnc/zaakgericht/zaken/start

## Business Model

SaaS subscription model. xxllnc provides their zaaksysteem as a fully managed cloud service. Revenue comes from SaaS subscriptions, implementation services, process configuration, training, and ongoing support. The company has been serving government organizations for over 15 years and has a team of 150+ employees across multiple product lines including zaken, belastingen (taxes), and burgerzaken (civil affairs).

## Target Market

Dutch municipalities and semi-government organizations. Focus on organizations wanting to digitize end-to-end processes with case-oriented working. Used by municipalities of various sizes — the municipality of Epe, for example, runs 200+ case types fully digitally on the platform.

## Pricing

Not publicly disclosed. SaaS subscription pricing depends on the organization's size, number of processes, and required integrations. Implementation timelines range from 3 months to 3 years depending on scope. Contact xxllnc for a custom quote.

## Key Features

- Self-service process builder allowing non-technical users to design, build, and manage processes
- Generic process templates as starting points, customizable per organization
- Intelligent forms with DigiD/eHerkenning authentication for citizen self-service
- Full-fledged archival application (RMA) tested against NEN-ISO 16175-1:2020
- Integrations with BAG, BRP, MijnOverheid, Xential, Office365, DSO
- Case handling from customer contact center, back office, and citizen portal
- Configurable dashboards and management reporting
- Staff scheduling based on competencies and location
- Mobile-friendly interface for field workers
- ZGW API support

## Feature Comparison with Procest

| Feature | xxllnc Zaken | Procest |
|---------|-------|---------|
| Case lifecycle management | Yes | Yes |
| CMMN 1.1 support | No | Yes |
| ZGW API compatible | Yes | Yes |
| Deadline tracking | Yes | Yes |
| Task assignment | Yes | Yes |
| Document checklists | Yes | Yes |
| Decisions (besluiten) | Yes | Yes |
| Sub-cases | Yes | Yes |
| Confidentiality levels | Yes | Yes |
| Audit trail | Yes | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| WCAG AA accessible | Yes | Yes |

## Strengths

- Self-service process configuration empowering non-technical municipal staff to build their own workflows
- Comprehensive archival/RMA functionality certified against Dutch government standards
- 15+ years of municipal domain experience with deep integration into Dutch government systems

## Weaknesses

- SaaS-only deployment — no option for self-hosting or on-premise, creating vendor dependency
- No CMMN or BPMN standards support — process modeling is proprietary
- Closed-source core — despite GitLab presence, the main platform is proprietary SaaS

## Notes

xxllnc is a significant competitor in the Dutch municipal market with a broad product portfolio beyond just case management. Their acquisition strategy (consolidating multiple government software products) gives them cross-selling opportunities. Some components are available on GitLab, making them partially open source. Procest competes on openness, standards compliance, and the ability to self-host.
