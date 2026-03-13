# Erxes — Competitor Analysis

## Overview

- **Website:** https://erxes.io/
- **Open Source:** Yes (AGPL-3.0)
- **Self-Hosted:** Yes
- **Summary:** Open-source XOS combining CRM, marketing automation, and support

## Codebase

- **Repository:** https://github.com/erxes/erxes

## Business Model

Open-core / fair-code model. The core platform and several plugins are free and source-available. Revenue comes from Enterprise Edition licenses for advanced plugins (Content, Accounting, Finance, Team, Property, Tour), paid support and training, and a plugin marketplace where developers can sell extensions. Additional team members cost $5/month on hosted plans.

## Target Market

Mid-market businesses looking for an all-in-one platform to replace multiple SaaS tools (HubSpot, Zendesk, Linear, Wix). Positions itself as an "Experience Operating System" (XOS) rather than just a CRM. Appeals to organizations wanting full control over their data with self-hosting.

## Pricing

- **Self-Hosted Core:** Free (AGPL-3.0)
- **Additional team members:** $5/month per 5 members
- **Enterprise plugins:** Paid license required
- **Support & training:** Additional fee
- Plugin marketplace for third-party extensions

## Key Features

- Omnichannel inbox (email, chat, social media, phone) for customer conversations
- Contact and company management with segmentation
- Sales pipeline with lead capture (landing pages, forms, pop-ups)
- Marketing automation with email campaigns
- Ticket management and customer support
- Task and project management
- Knowledge base and help center
- Plugin-based architecture with marketplace
- Workflow automation engine
- Built with TypeScript, Node.js, GraphQL Federation, React

## Feature Comparison with Pipelinq

| Feature | Erxes | Pipelinq |
|---------|-------|----------|
| Client management (persons) | Yes | Yes |
| Organization management | Yes | Yes |
| Contact persons (linked) | Yes | Yes |
| Lead pipeline (kanban) | Yes | Yes |
| Request intake | Yes (forms, pop-ups, landing pages) | Yes |
| Contact moments logging | Yes (omnichannel inbox) | Yes |
| My Work queue | Partial (tasks module) | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | Partial | Yes |
| Import/Export (CSV/vCard) | Yes (CSV) | Yes |
| Case management integration | Partial (tickets) | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| Audit trail | Partial | Yes |

## Strengths

- All-in-one platform covering CRM, marketing, support, and operations — replaces multiple tools
- Omnichannel communication (email, chat, social, phone) built into the core platform
- Plugin-based architecture allows extensive customization and extension via marketplace

## Weaknesses

- Complex to set up and maintain — requires significant infrastructure (microservices architecture)
- No Nextcloud integration or Dutch government ecosystem support
- Enterprise-critical plugins require paid licenses, making the "free" claim misleading for full functionality

## Notes

Erxes is the most feature-rich open-source competitor in this analysis, but its complexity is a double-edged sword. The microservices architecture requires significant DevOps expertise to deploy and maintain. The plugin marketplace is still developing. It competes more with HubSpot than with focused CRM tools. No relevance to the Dutch government market.
