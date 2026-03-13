# Twenty — Competitor Analysis

## Overview

- **Website:** https://twenty.com/
- **Open Source:** Yes (GPL-3.0)
- **Self-Hosted:** Yes
- **Summary:** Modern open-source CRM with contacts, companies, deals, and pipelines

## Codebase

- **Repository:** https://github.com/twentyhq/twenty

## Business Model

Open-core model backed by Y Combinator. The core CRM is fully open source (GPL-3.0) and free to self-host. Revenue comes from a managed cloud offering with per-user SaaS subscriptions. The team previously sold their startup to Airbnb and has backing from executives at HubSpot, Front, and Pipedrive. The project has earned 28,000+ GitHub stars.

## Target Market

Startups, SMBs, and tech-forward teams looking for a modern Salesforce alternative without vendor lock-in. Appeals to developer-friendly organizations that value open source, data ownership, and a clean Notion-inspired UX.

## Pricing

- **Self-Hosted:** Free (GPL-3.0, unlimited users)
- **Cloud Pro:** $9/user/month
- **Cloud Organization:** $19/user/month
- Third-party hosting (e.g. CloudStation) offers unlimited users from ~$18/month total

## Key Features

- Contact and company management with custom objects
- Visual deal pipeline with kanban and table views
- Rich notes displayed in timeline per record
- Task management directly on records
- Email integration and activity tracking
- Keyboard shortcuts and universal search (cmd+K)
- REST API and webhooks for integrations
- Customizable data model (add custom objects and fields)
- Filter, sort, and group-by views (Notion-inspired UI)
- Built with TypeScript, NestJS, React, PostgreSQL

## Feature Comparison with Pipelinq

| Feature | Twenty | Pipelinq |
|---------|-------|----------|
| Client management (persons) | Yes | Yes |
| Organization management | Yes | Yes |
| Contact persons (linked) | Yes | Yes |
| Lead pipeline (kanban) | Yes | Yes |
| Request intake | No | Yes |
| Contact moments logging | Partial (timeline notes) | Yes |
| My Work queue | No | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | No | Yes |
| Import/Export (CSV/vCard) | Yes (CSV) | Yes |
| Case management integration | No | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | Partial | Yes |
| Audit trail | Partial | Yes |

## Strengths

- Very modern, polished UI inspired by Notion/Linear — appeals to users who find traditional CRMs clunky
- Strong developer community (28k+ GitHub stars) and rapid feature development
- Fully customizable data model with custom objects — not limited to predefined CRM entities

## Weaknesses

- No Nextcloud integration or Dutch government ecosystem support
- No built-in request intake, contact moment logging, or case management workflows
- Relatively young project (started 2023) — less mature than established CRMs

## Notes

Twenty positions itself as the open-source Salesforce alternative. Its modern UI is its biggest differentiator. However, it lacks the government-focused features (Common Ground, NL Design) and Nextcloud-native integration that Pipelinq provides. The project is rapidly growing but still feature-incomplete compared to mature CRMs.
