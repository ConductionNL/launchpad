# SuiteCRM — Competitor Analysis

## Overview

- **Website:** https://suitecrm.com/
- **Open Source:** Yes (AGPL-3.0)
- **Self-Hosted:** Yes
- **Summary:** Enterprise-grade open-source CRM with full sales, marketing, support

## Codebase

- **Repository:** https://github.com/salesagility/SuiteCRM (v7) and https://github.com/salesagility/SuiteCRM-Core (v8)

## Business Model

Open-source with paid cloud hosting and support contracts. The software is completely free to download and self-host (AGPL-3.0). Revenue comes from official cloud hosting plans, enterprise support subscriptions, and professional services. Unlimited users on self-hosted with no per-seat licensing. Maintained by SalesAgility.

## Target Market

Small to large enterprises looking for a full-featured open-source CRM alternative to Salesforce and SugarCRM. Popular in industries with compliance requirements due to data sovereignty (self-hosting). Used by organizations wanting enterprise CRM features without per-user licensing costs.

## Pricing

- **Self-Hosted:** Free (AGPL-3.0, unlimited users)
- **Cloud Starter:** GBP 100/month
- **Cloud Pro Suite:** GBP 400/month
- **Cloud Enterprise:** GBP 600/month
- **Cloud Dedicated:** From GBP 3,200/year
- All cloud plans include same core features; differ in storage, workflow frequency, and hosting resources

## Key Features

- Contact, lead, account, and opportunity management
- Sales pipeline with forecasting
- Marketing automation and email campaign manager
- Case management and customer support portal
- Workflow and process automation (BPM)
- Role-based access control with field-level security
- Detailed reports and dashboards
- Email integration and calendar management
- Product catalog and quote management
- REST/Open API for integrations
- Duplicate detection on import and record creation

## Feature Comparison with Pipelinq

| Feature | SuiteCRM | Pipelinq |
|---------|-------|----------|
| Client management (persons) | Yes | Yes |
| Organization management | Yes | Yes |
| Contact persons (linked) | Yes | Yes |
| Lead pipeline (kanban) | Partial (list-based pipeline) | Yes |
| Request intake | Yes (web-to-lead forms) | Yes |
| Contact moments logging | Yes (calls, meetings, emails, notes) | Yes |
| My Work queue | Partial (activities/tasks) | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | Yes | Yes |
| Import/Export (CSV/vCard) | Yes | Yes |
| Case management integration | Yes (built-in cases module) | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| Audit trail | Yes | Yes |

## Strengths

- Most feature-complete open-source CRM — sales, marketing, support, and BPM in one platform
- Large community and ecosystem with extensive documentation and third-party integrations
- Built-in case management module — no need for external integration

## Weaknesses

- Dated UI compared to modern CRMs — SuiteCRM 8 is improving but still in levelling-up phase
- Complex to configure and maintain — steep learning curve for administrators
- No Nextcloud integration or Dutch government ecosystem support

## Notes

SuiteCRM is the most established open-source CRM and a direct fork of SugarCRM Community Edition. It is the closest feature-for-feature competitor to enterprise CRMs like Salesforce. However, its dated interface and complexity are significant drawbacks. SuiteCRM 8 is being actively developed with a modern Angular frontend but is not yet feature-complete with v7. The lack of Nextcloud integration is its main gap vs Pipelinq.
