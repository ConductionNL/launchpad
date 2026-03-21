# Krayin CRM - Competitive Analysis for Pipelinq

## Executive Summary

Krayin is an open-source CRM built on Laravel 11 (PHP 8.2+), following a modular package architecture with 18 packages under `packages/Webkul/`. It provides a full-featured sales CRM with lead/pipeline management, contact management, quoting, email, activities, products, warehousing, marketing campaigns, and workflow automation. The frontend uses Blade templates with inline Vue.js components (no SPA), styled with Tailwind CSS.

**Repository:** https://github.com/krayin/laravel-crm
**Website:** https://krayincrm.com
**License:** MIT
**Stack:** Laravel 11, PHP 8.2+, MySQL/PostgreSQL, Vue.js (inline), Tailwind CSS, Vite
**GitHub Stars:** 21,700+ (as of 2026-03-14)
**Downloads:** 25,000+
**Trustpilot:** 4.4/5
**Company:** Webkul Software (India/USA), CMMI appraised
**Business Model:** Open-core (free MIT core + paid extensions + cloud hosting + custom development)

## Architecture Overview

### Package Structure

Krayin uses Webkul's Concord modular package system. Each package is self-contained with its own models, repositories, migrations, providers, and contracts. The packages communicate through Laravel's dependency injection and Eloquent relationships.

```
packages/Webkul/
  Admin/        -- Controllers, Routes, Views, DataGrids (the "glue" layer)
  Activity/     -- Activities (calls, meetings, lunches, notes, files)
  Attribute/    -- Dynamic custom attributes (EAV pattern)
  Automation/   -- Workflows + Webhooks
  Contact/      -- Persons + Organizations
  Core/         -- System config, helpers, base repository
  DataGrid/     -- Reusable tabular data grid framework
  DataTransfer/ -- CSV/Excel import for leads, persons, products
  Email/        -- Email client (IMAP inbound, SMTP outbound)
  EmailTemplate/-- Email templates with placeholders
  Installer/    -- Web-based installation wizard
  Lead/         -- Leads, Pipelines, Stages, Sources, Types
  Marketing/    -- Campaigns + Events
  Product/      -- Product catalog with inventory
  Quote/        -- Quotes with line items
  Tag/          -- Tagging system
  User/         -- Users, Roles, Groups (RBAC)
  Warehouse/    -- Warehouse + Location management
  WebForm/      -- Embeddable web-to-lead forms
```

### Key Design Patterns

1. **Proxy Pattern**: Every model has a `*Proxy` class enabling model substitution/extension without modifying core code
2. **Repository Pattern**: All data access goes through dedicated repository classes extending `Webkul\Core\Eloquent\Repository`
3. **Contract/Interface Pattern**: Models implement contracts for loose coupling
4. **EAV (Entity-Attribute-Value)**: Dynamic custom attributes via `CustomAttribute` trait and `attribute_values` table
5. **Event-Driven**: Laravel events dispatched on all CRUD operations (e.g., `lead.create.after`)
6. **Bouncer Authorization**: Custom `bouncer()` helper limits data access by authorized user IDs

### Data Model Summary

```
Organizations (1) --< Persons (1) --< Leads (N)
                                        |-- belongsTo Pipeline
                                        |-- belongsTo Stage
                                        |-- belongsTo Source
                                        |-- belongsTo Type
                                        |-- belongsTo User (sales owner)
                                        |-- hasMany Products (lead_products)
                                        |-- belongsToMany Activities
                                        |-- belongsToMany Tags
                                        |-- belongsToMany Quotes
                                        |-- hasMany Emails

Pipeline (1) --< Stages (ordered by sort_order)
                   |-- code: 'new', 'won', 'lost', etc.
                   |-- probability: 0-100%

Quote (1) --< QuoteItems
             |-- belongs to Person
             |-- belongs to User
             |-- billing/shipping address

Product (catalog) --< ProductInventory --< Warehouse/Location

Activity --< Participants
          |-- Files
          |-- types: call, meeting, lunch, email, note, file

Workflow -- conditions (JSON) + actions (JSON)
         -- triggers on entity events (leads, activities, persons, quotes)

Campaign --> Event --> EmailTemplate
```

## Feature Inventory

| Feature | Maturity | Pipelinq Relevance |
|---------|----------|-------------------|
| Pipeline/Stage Management | High | DIRECT COMPETITOR |
| Lead Management + Kanban | High | DIRECT COMPETITOR |
| Contact Management | High | Core dependency |
| Activity Tracking | Medium | Essential add-on |
| Email Client | Medium | Nice-to-have |
| Product Catalog | Medium | Useful for quoting |
| Quoting | Medium | Revenue tracking |
| Workflow Automation | Medium | Automation layer |
| Webhooks | Medium | Integration point |
| Marketing Campaigns | Low | Out of scope |
| Warehouse/Inventory | Low | Out of scope |
| Data Import (CSV/Excel) | Medium | Onboarding tool |
| Web Forms | Medium | Lead capture |
| Custom Attributes (EAV) | High | Flexibility |
| AI Lead Extraction | Low-Medium | Innovation feature |
| Dashboard Analytics | Medium | Reporting |
| RBAC (Roles/Groups) | High | Multi-user |

## Strengths (vs Pipelinq)

1. **Mature pipeline/Kanban implementation** with drag-and-drop stage changes, rotten lead detection, and probability tracking
2. **Full email client** built-in (IMAP inbound + SMTP outbound, not just notifications)
3. **Comprehensive quoting** with line items, discounts, tax, billing/shipping addresses, and printable output
4. **Workflow automation** with condition-based triggers, email notifications, auto-tagging, webhook firing
5. **EAV custom attributes** allowing user-defined fields on all entities without schema changes
6. **AI-powered lead extraction** from uploaded PDFs/images via OpenRouter API
7. **Data import** supporting CSV/Excel batch imports for leads, contacts, and products
8. **Web-to-lead forms** generating embeddable HTML forms
9. **Marketing campaigns** with event-based email template sending
10. **Granular ACL** with per-entity CRUD permissions and data-level access control (bouncer)

## Weaknesses

1. **No REST/GraphQL API** -- purely web-based controllers, no headless mode
2. **Monolithic frontend** -- Blade templates with inline Vue, not a modern SPA
3. **No multi-tenancy** -- single organization deployment
4. **No calendar integration** -- activities have schedule fields but no CalDAV/Google Calendar sync
5. **Limited reporting** -- dashboard has 8 fixed stat types, no custom report builder
6. **No mobile app** -- responsive web only
7. **Workflow engine is basic** -- condition matching + predefined actions, no visual flow builder
8. **No document generation** -- quotes can be printed, but no document template engine
9. **No project/task management** -- purely CRM focused
10. **Limited integration ecosystem** -- webhooks only, no marketplace or pre-built connectors

## Key Technical Insights for Pipelinq

### Pipeline Model
- Krayin's pipeline has `name`, `rotten_days` (inactivity threshold), and `is_default` flag
- Stages have `code`, `name`, `probability` (0-100%), `sort_order`
- Special stage codes: `won` and `lost` auto-set `closed_at` timestamp
- Leads track `lead_value` (monetary), `expected_close_date`, and `lost_reason`
- **Rotten lead detection**: calculates days since creation vs pipeline's rotten_days threshold

### Lead-Product Relationship
- Leads have a separate `lead_products` table (not the product catalog)
- Stores `quantity`, `price`, `amount` (price x quantity) per product per lead
- This is distinct from the catalog `products` table

### Automation Architecture
- Workflows are config-driven: `workflows.php` defines trigger entities and their events
- Each entity type has an Entity helper class with `getActions()` and `executeActions()`
- Actions include: update lead/person fields, send emails (via templates), add tags, add notes, trigger webhooks
- No visual builder -- configuration via admin forms

### Authorization Pattern
- `bouncer()->getAuthorizedUserIds()` returns array of user IDs the current user can see data for
- Used consistently across all lead/activity queries for data-level access control
- Combined with ACL for feature-level access (CRUD on each entity)

## Commercial Ecosystem

### Paid Extensions (one-time pricing)
| Extension | Price |
|-----------|-------|
| Multi Tenant SaaS | $1,799 |
| WhatsApp Integration | $1,499 |
| VoIP Extension | $4,500 |
| Starter Pack | $499 |
| Purchase Order | $299 |
| Inventory Transfer | $299 |
| Google Integration | Free |

### Services
- Cloud Hosting (managed deployment by Webkul)
- Custom CRM Development
- White Label CRM (rebranding for agencies)
- Support via UVdesk ticketing

### Case Studies (from website)
- CAMRA (UK consumer organization, founded 1971)
- Hult Prize (global, 121,000+ students, 120+ countries)
- Unilavras (Brazilian university, 59+ years)
- Desatnick (US real estate)
- Sparkout (Indian software development)

### Industry Targeting
20+ industry-specific landing pages: B2B, B2C, Automotive, Banking, Call Center, Education, Event Management, Healthcare, Hospitality, Insurance, Logistics, Manufacturing, Marketing, Media, Nonprofit, Real Estate, Recruitment, Retail, Support, Travel.

## Related Webkul Products
- **Bagisto** -- Open source Laravel e-commerce
- **UVdesk** -- Open source helpdesk
- **UnoPIM** -- Open source product information management
- **AureusERP** -- Open source ERP
