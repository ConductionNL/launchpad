# Dolibarr — Competitor Analysis

## Overview

- **Website:** https://www.dolibarr.org/
- **Open Source:** Yes (GPL-3.0)
- **Self-Hosted:** Yes
- **Summary:** Combined ERP and CRM for SMBs, modular PHP-based

## Codebase

- **Repository:** https://github.com/Dolibarr/dolibarr

## Business Model

Fully open source (GPL-3.0) with revenue from cloud hosting (DoliCloud SaaS), a marketplace of paid modules (DoliStore), and professional services from the partner network. The core software is free with no feature gates. The project is community-driven with thousands of developers, testers, and translators contributing.

## Target Market

Small to medium businesses, freelancers, nonprofits, and foundations. Strong presence in France and Southern Europe. Appeals to organizations that need a combined ERP + CRM without the complexity of Odoo. Modular design lets users activate only the features they need.

## Pricing

- **Self-Hosted:** Free (GPL-3.0, unlimited users)
- **DoliCloud SaaS:** $12-40/month depending on plan and users
- **DoliStore Modules:** Paid add-ons from the marketplace (prices vary)
- No per-user licensing fees for self-hosted

## Key Features

- Customer, prospect (lead), and supplier management with contacts
- Sales pipeline with commercial proposals and orders
- Invoicing, payments, and accounting
- Inventory and stock management
- Project management with task tracking
- HR management (leave, expenses)
- Email integration and mass mailing
- Modular architecture — activate only needed features
- Role-based access control
- REST API and webhook support
- 800+ modules available in DoliStore marketplace
- Multi-language support (50+ languages)

## Feature Comparison with Pipelinq

| Feature | Dolibarr | Pipelinq |
|---------|-------|----------|
| Client management (persons) | Yes | Yes |
| Organization management | Yes (third-parties) | Yes |
| Contact persons (linked) | Yes | Yes |
| Lead pipeline (kanban) | Partial (prospect status, no kanban) | Yes |
| Request intake | Partial (web forms via modules) | Yes |
| Contact moments logging | Partial (agenda events) | Yes |
| My Work queue | Partial (task list) | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | Partial (basic matching) | Yes |
| Import/Export (CSV/vCard) | Yes | Yes |
| Case management integration | No | Yes (Procest) |
| Nextcloud integration | Partial (file storage modules available) | Native |
| RBAC | Yes | Yes |
| Audit trail | Yes (logs) | Yes |

## Strengths

- Combined ERP + CRM in one platform — handles invoicing, accounting, inventory, HR alongside CRM
- Highly modular — users can activate only the features they need, keeping the interface simple
- Large marketplace (800+ modules) and active community, especially strong in European markets

## Weaknesses

- CRM features are less polished than dedicated CRM tools — no kanban pipeline, basic lead management
- UI is functional but dated compared to modern CRMs like Twenty or Attio
- No native Nextcloud integration or Dutch government ecosystem support

## Notes

Dolibarr is best understood as a lightweight ERP that includes CRM features, rather than a CRM-first tool. Its strength is the breadth of business functions (invoicing, accounting, inventory) in one modular package. For organizations that need a simple all-in-one business tool, Dolibarr is compelling. However, its CRM capabilities are basic compared to dedicated CRM solutions. The lack of kanban pipeline views is a notable gap for sales-focused teams.
