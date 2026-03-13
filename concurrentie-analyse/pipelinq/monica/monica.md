# Monica — Competitor Analysis

## Overview

- **Website:** https://www.monicahq.com/
- **Open Source:** Yes (AGPL-3.0)
- **Self-Hosted:** Yes
- **Summary:** Personal relationship management for documenting interactions

## Codebase

- **Repository:** https://github.com/monicahq/monica

## Business Model

Open-source with hosted SaaS subscriptions. The software is free to self-host. Revenue comes from paid cloud hosting plans and community support through Patreon. No enterprise licensing or support contracts — the project sustains itself through the traditional model of charging for convenience (hosted service) rather than features.

## Target Market

Individuals and small teams who want to track personal and professional relationships. Not a traditional business CRM — targets people who want to remember details about friends, family, and contacts (birthdays, conversation topics, family members).

## Pricing

- **Self-Hosted:** Free (AGPL-3.0)
- **Cloud Pro:** $8.30/month
- **Cloud Unlimited:** $16.60/month
- Free plan available with limited features on hosted version

## Key Features

- Contact management with detailed personal information (family, pets, birthdays)
- Activity logging — track what you did with whom and when
- Reminders for birthdays, follow-ups, and scheduled calls
- Conversation logging — record what you talked about
- Journal for personal reflections
- Document and photo storage per contact
- Gift tracking and management
- REST API for import/export and automation
- vCard import/export support
- Debt tracking between contacts

## Feature Comparison with Pipelinq

| Feature | Monica | Pipelinq |
|---------|-------|----------|
| Client management (persons) | Yes | Yes |
| Organization management | No | Yes |
| Contact persons (linked) | Partial (family relationships) | Yes |
| Lead pipeline (kanban) | No | Yes |
| Request intake | No | Yes |
| Contact moments logging | Yes (activities) | Yes |
| My Work queue | No | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | No | Yes |
| Import/Export (CSV/vCard) | Yes | Yes |
| Case management integration | No | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | No | Yes |
| Audit trail | No | Yes |

## Strengths

- Excellent personal relationship tracking — best-in-class for logging conversation details, family members, and life events
- Simple, clean interface focused on individual use — very low learning curve
- Strong privacy focus with self-hosting option and no data monetization

## Weaknesses

- Not a business CRM — no pipeline management, no organization entities, no sales workflows
- No multi-user collaboration features or RBAC — designed for single-user use
- No Nextcloud integration or any enterprise/government ecosystem support

## Notes

Monica is a personal relationship management tool, not a business CRM. It excels at helping individuals remember personal details about their network but lacks every business-critical CRM feature (pipelines, organizations, RBAC, integrations). It competes with Pipelinq only in the narrow area of contact management and interaction logging. Very different target audience.
