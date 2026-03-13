# Bonitasoft — Competitor Analysis

## Overview

- **Website:** https://www.bonitasoft.com/
- **Open Source:** Yes (GPL)
- **Self-Hosted:** Yes
- **Summary:** Open-source BPM and low-code development platform

## Codebase

- https://github.com/bonitasoft/bonita-engine
- https://github.com/bonitasoft/bonita-web

## Business Model

Open-core model. The Community Edition is free under GPLv2. Revenue comes from paid Enterprise editions (Access and Scale) that add security, scalability, monitoring, and continuous improvement capabilities. Pricing is subscription-based, structured by number of users, complexity of automation needs, and additional modules or services required. Also offers professional services including implementation consulting, training, and custom connector development.

## Target Market

Enterprises across industries needing business process automation and digital operations modernization. Strong in manufacturing, financial services, government, and utilities. French-headquartered company with a global customer base. Targets both IT teams (developers using the open-source engine) and business teams (low-code visual programming).

## Pricing

- **Community Edition:** Free (GPLv2), limited features, community support only
- **Enterprise Access:** Paid subscription — adds production support, professional connectors, actor filters, and monitoring
- **Enterprise Scale:** Paid subscription — adds horizontal clustering, high availability, load balancing, and advanced analytics
- Specific pricing not publicly disclosed — contact Bonitasoft for quotes

## Key Features

- BPMN 2.0 graphical process modeling and execution
- Low-code/visual programming with clear separation from coding
- Bonita Studio for process design with drag-and-drop interface
- Form designer for building user-facing applications
- REST API connector framework for integration with existing systems
- Actor filters for dynamic task assignment
- Horizontal scalability through clustering (enterprise)
- Application deployment and lifecycle management
- Process analytics and monitoring dashboards
- Reusable components and connector marketplace
- Integration with enterprise systems (SAP, Salesforce, LDAP, etc.)

## Feature Comparison with Procest

| Feature | Bonitasoft | Procest |
|---------|-------|---------|
| Case lifecycle management | Partial (process-centric) | Yes |
| CMMN 1.1 support | No | Yes |
| ZGW API compatible | No | Yes |
| Deadline tracking | Yes (timers, SLAs) | Yes |
| Task assignment | Yes (actor filters) | Yes |
| Document checklists | No | Yes |
| Decisions (besluiten) | Partial (business rules) | Yes |
| Sub-cases | Partial (sub-processes) | Yes |
| Confidentiality levels | No | Yes |
| Audit trail | Yes | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| WCAG AA accessible | Partial | Yes |

## Strengths

- Mature open-source BPM platform with a large community and extensive documentation
- Strong separation between visual/low-code development and coding — accessible to business users
- Proven horizontal scalability through clustering for enterprise-grade workloads

## Weaknesses

- No CMMN support — only BPMN, limiting dynamic case management capabilities
- No Dutch government domain knowledge — no ZGW APIs, no zaakgericht werken awareness
- GPL license of Community Edition creates adoption friction for organizations wanting to embed it in proprietary solutions

## Notes

Bonitasoft is a well-established open-source BPM platform but lacks case management-specific features. It competes in the broader process automation space rather than the zaakgericht werken market. Procest's advantage is a purpose-built case management application with Dutch government domain specifics, while Bonitasoft is a generic process automation toolkit that would require significant customization for zaakgericht werken use cases.
