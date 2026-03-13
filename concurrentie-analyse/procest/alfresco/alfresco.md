# Alfresco (Hyland) — Competitor Analysis

## Overview

- **Website:** https://www.alfresco.com/
- **Open Source:** Partially (LGPL)
- **Self-Hosted:** Yes
- **Summary:** Content, process, and governance management with case management

## Codebase

- https://github.com/Alfresco/alfresco-community-repo
- https://github.com/Alfresco/acs-community-packaging

## Business Model

Open-core model. Alfresco Community Edition is free under LGPL. Revenue comes from enterprise subscriptions that add governance services, intelligence features, process automation, cloud deployment, and professional support. Acquired by Hyland in 2020, a large content services company. Revenue streams include enterprise subscriptions, cloud hosting (AWS Marketplace), professional services, and training.

## Target Market

Enterprises and government organizations globally needing content management, records management, and process automation. Strong in regulated industries (financial services, government, healthcare, insurance) where content governance and compliance are critical. Used by large organizations managing millions of documents with complex lifecycle requirements.

## Pricing

- **Community Edition:** Free (LGPL), basic content management and collaboration
- **Enterprise plans:** Starting around $2/user/month for core content services, $5-15/user/month for advanced plans with governance, intelligence, and process automation
- **Cloud deployment:** Available on AWS Marketplace with usage-based pricing
- Enterprise subscriptions with custom pricing — contact Hyland/Alfresco for quotes

## Key Features

- Enterprise content management (ECM) with centralized document repository
- Alfresco Process Services (powered by Activiti) for BPMN process automation
- Alfresco Governance Services for records management and compliance (DoD 5015.02)
- Integration with Microsoft 365 and Google Docs for collaboration
- Content intelligence with AI/ML classification and extraction
- On-premise, cloud, and hybrid deployment options
- REST API framework for integration
- Workflow automation for content-centric business processes
- Version control and document lifecycle management
- Metadata management and full-text search
- Multi-language and multi-tenancy support

## Feature Comparison with Procest

| Feature | Alfresco (Hyland) | Procest |
|---------|-------|---------|
| Case lifecycle management | Partial (content-centric) | Yes |
| CMMN 1.1 support | No | Yes |
| ZGW API compatible | No | Yes |
| Deadline tracking | Partial (workflow timers) | Yes |
| Task assignment | Yes (workflow tasks) | Yes |
| Document checklists | Partial | Yes |
| Decisions (besluiten) | No | Yes |
| Sub-cases | No | Yes |
| Confidentiality levels | Yes (permissions model) | Yes |
| Audit trail | Yes | Yes |
| Nextcloud integration | No (competing DMS) | Native |
| RBAC | Yes | Yes |
| WCAG AA accessible | Partial | Yes |

## Strengths

- Enterprise-grade content management with deep records management and compliance capabilities
- Process automation powered by Activiti engine — proven in high-volume document workflows
- Open-source Community Edition enables evaluation and adoption without upfront cost

## Weaknesses

- Content management platform, not a case management platform — case handling is a secondary capability
- No Dutch government domain knowledge — no ZGW APIs, no zaakgericht werken, no Common Ground
- Acquired by Hyland — product direction may shift toward Hyland's broader portfolio, reducing Alfresco-specific innovation

## Notes

Alfresco competes with Procest primarily in the document management dimension rather than case management. It is a content services platform with some process automation bolted on, not a purpose-built zaakgericht werken application. Procest's native Nextcloud integration actually positions it as an alternative to Alfresco's content management capabilities while adding full case management. The Activiti-powered process services are limited compared to modern BPMN/CMMN engines.
