# ArkCase — Competitor Analysis

## Overview

- **Website:** https://www.arkcase.com/
- **Open Source:** Yes (LGPL 3.0)
- **Self-Hosted:** Yes
- **Summary:** Open-source case management for government use cases

## Codebase

- https://github.com/ArkCase/ArkCase

## Business Model

Open-core model. ArkCase Community Edition is free and open source under LGPL-3.0. Revenue comes from Enterprise editions with additional features and support, managed hosting, implementation consulting, and training. Two paid tiers: Enterprise Gold with standard support at $60/user/month, and Enterprise Platinum with premium support at custom pricing. Also offers pre-built modules for specific use cases (FOIA, complaints, data privacy) that can be purchased separately.

## Target Market

Government agencies (primarily US federal and state), legal departments, compliance teams, and regulated industries. Pre-built solutions for FOIA (Freedom of Information Act), complaint management, incident management, correspondence management, and legal case management. Targets organizations needing FedRAMP, HIPAA, and HITECH compliance.

## Pricing

- **Community Edition (Open Source):** Free (LGPL 3.0)
- **Enterprise Gold:** $60/user/month — adds professional support, SLA, and enterprise features
- **Enterprise Platinum:** Custom pricing — adds premium support, dedicated account management, and advanced capabilities
- On-premise, hybrid, and cloud deployment options available

## Key Features

- Configurable workflow engine with drag-and-drop builder
- Pre-built automation workflows for FOIA, ROI, data privacy, complaints, and incidents
- Rule-based triggers and automation of repetitive tasks
- RESTful API endpoints for integration with enterprise systems
- Document management with version control
- Correspondence tracking and management
- Full audit trail and compliance reporting
- FedRAMP, HIPAA, and HITECH security compliance
- Role-based access control with granular permissions
- Customizable dashboards and reporting
- Multi-tenant architecture

## Feature Comparison with Procest

| Feature | ArkCase | Procest |
|---------|-------|---------|
| Case lifecycle management | Yes | Yes |
| CMMN 1.1 support | No | Yes |
| ZGW API compatible | No | Yes |
| Deadline tracking | Yes | Yes |
| Task assignment | Yes | Yes |
| Document checklists | Yes | Yes |
| Decisions (besluiten) | Partial (workflow rules) | Yes |
| Sub-cases | Yes | Yes |
| Confidentiality levels | Yes | Yes |
| Audit trail | Yes | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| WCAG AA accessible | Partial (Section 508) | Yes |

## Strengths

- Open-source case management platform with transparent pricing and a clear free tier
- Pre-built solutions for common government use cases (FOIA, complaints) reduce time-to-value
- Strong security compliance (FedRAMP, HIPAA) — proven in regulated US government environments

## Weaknesses

- US-focused with no European/Dutch government domain knowledge — no ZGW APIs, no Common Ground
- Enterprise pricing at $60/user/month is expensive for Dutch municipalities
- Pre-built modules target US-specific regulations (FOIA, HIPAA) — not relevant for Dutch zaakgericht werken

## Notes

ArkCase is the closest international open-source competitor to Procest in terms of positioning — both are open-source case management platforms targeting government. However, ArkCase is completely US-focused with no Dutch government standards support. Procest's ZGW API compliance, CMMN support, and Nextcloud integration make it far more suitable for the Dutch municipal market. ArkCase could become relevant if it expanded into European markets or if Dutch organizations needed FOIA-like transparency tooling.
