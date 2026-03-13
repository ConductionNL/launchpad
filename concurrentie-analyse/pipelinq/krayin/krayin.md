# Krayin — Competitor Analysis

## Overview

- **Website:** https://krayincrm.com/
- **Open Source:** Yes (MIT)
- **Self-Hosted:** Yes
- **Summary:** Laravel-based open-source CRM with lead pipelines and email tracking

## Codebase

- **Repository:** https://github.com/krayin/laravel-crm

## Business Model

Open-core model. The core CRM is free and open source (MIT license). Revenue comes from paid extensions (WhatsApp Extension at $1,499, VoIP Extension at $4,500, Multi-Tenant SaaS Module at $1,799) and white-label licensing. Also offers custom development and support services through the parent company Webkul.

## Target Market

SMEs and startups looking for a customizable, Laravel-based CRM. Appeals to PHP/Laravel developers who want to extend or white-label a CRM solution. Also targets businesses that need WhatsApp and VoIP integration for customer communication.

## Pricing

- **Self-Hosted Core:** Free (MIT license, unlimited users)
- **Multi-Tenant SaaS Module:** $1,799 (one-time)
- **WhatsApp Extension:** $1,499 (one-time)
- **VoIP Extension:** $4,500 (one-time)
- No recurring subscription for the core product

## Key Features

- Lead tracking with visual kanban pipeline
- Deal management and sales forecasting
- Contact and organization management with custom attributes
- Email integration (IMAP) with tracking
- Activity management (calls, meetings, notes)
- Campaign management and email marketing
- Mobile-responsive interface
- Bulk upload for leads, products, and contacts
- Lead creation via PDF or image upload
- REST API for integrations
- White-label ready

## Feature Comparison with Pipelinq

| Feature | Krayin | Pipelinq |
|---------|-------|----------|
| Client management (persons) | Yes | Yes |
| Organization management | Yes | Yes |
| Contact persons (linked) | Yes | Yes |
| Lead pipeline (kanban) | Yes | Yes |
| Request intake | Partial (web-to-lead) | Yes |
| Contact moments logging | Yes (activities) | Yes |
| My Work queue | Partial (dashboard tasks) | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | No | Yes |
| Import/Export (CSV/vCard) | Yes (CSV, bulk upload) | Yes |
| Case management integration | No | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| Audit trail | Partial | Yes |

## Strengths

- Built on Laravel — familiar tech stack for PHP developers with extensive customization options
- One-time pricing for extensions rather than recurring SaaS fees — cost-effective long-term
- White-label ready — can be rebranded and sold as a SaaS product

## Weaknesses

- No Nextcloud integration or Dutch government ecosystem support
- Smaller community compared to SuiteCRM or EspoCRM — fewer third-party integrations
- Paid extensions for essential features like VoIP and WhatsApp add significant cost

## Notes

Krayin is a solid Laravel-based CRM with a focus on the PHP/Laravel ecosystem. Its white-label capability is a differentiator for agencies and SaaS builders. The project is maintained by Webkul, a well-known Laravel ecosystem company. However, it lacks the Nextcloud-native integration and government features that Pipelinq provides. The one-time pricing model for extensions is attractive compared to recurring SaaS fees.
