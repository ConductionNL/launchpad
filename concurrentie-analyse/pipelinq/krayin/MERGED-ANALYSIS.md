# Krayin CRM Competitive Analysis -- Merged Report

**Date:** 2026-03-14
**Sources:** Source code analysis (GitHub krayin/laravel-crm), website/marketing walkthrough (krayincrm.com), documentation research (krayin-docs repo), 15 feature specs, browser walkthrough (demo offline -- website + user guide captured)

---

## 1. Sources Summary

| Source | Method | What Was Covered |
|--------|--------|-----------------|
| GitHub `krayin/laravel-crm` | Full source code clone and analysis | All 18 packages under `packages/Webkul/`, models, repositories, controllers, migrations |
| GitHub `krayin/krayin-docs` | Documentation repo (v2.1) | Architecture, API reference, extension system, deployment |
| krayincrm.com | Browser walkthrough (Playwright) | Homepage, user guide, extensions marketplace |
| demo.krayincrm.com | Attempted (returned 404, offline) | Could not assess live UI |
| Docker image `webkul/krayin:2.0.1` | Pull attempted (failed) | Could not run locally |

**Screenshots captured:** Homepage (01-homepage.png), user guide with CRM screenshots (02-user-guide.png), extension marketplace (03-extensions.png).

---

## 2. Product Overview

**Krayin** is an open-source CRM built on Laravel 11 (PHP 8.2+) by Webkul Software (India/USA, CMMI appraised). It provides full-featured sales CRM functionality: lead/pipeline management with Kanban boards, contact management, quoting, a built-in email client, activity tracking, products, warehousing, marketing campaigns, and workflow automation.

| Attribute | Value |
|-----------|-------|
| **Repository** | https://github.com/krayin/laravel-crm |
| **Website** | https://krayincrm.com |
| **License** | MIT |
| **Stack** | Laravel 11, PHP 8.2+, MySQL/MariaDB, Vue.js (inline in Blade), Tailwind CSS, Vite |
| **GitHub Stars** | 21,700+ |
| **Downloads** | 25,000+ |
| **Trustpilot** | 4.4/5 |
| **Business Model** | Open-core: free MIT core + paid extensions ($299--$4,500) + cloud hosting + custom development + white label |
| **Target Market** | SMEs and startups seeking a customizable Laravel-based CRM; PHP developers wanting to extend/white-label |
| **Languages** | English, Turkish, Arabic built-in |

**Case studies:** CAMRA (UK consumer org), Hult Prize (global, 121,000+ students), Unilavras (Brazilian university), Desatnick (US real estate), Sparkout (Indian software). Markets to 20+ industries via dedicated landing pages.

**Related Webkul products:** Bagisto (e-commerce), UVdesk (helpdesk), UnoPIM (PIM), AureusERP (ERP).

---

## 3. Architecture Summary

### Tech Stack

| Layer | Technology | Notes |
|-------|-----------|-------|
| **Backend** | Laravel 11 (PHP 8.2+) | Eloquent ORM + Repository Pattern |
| **Frontend** | Vue.js (inline in Blade) + Tailwind CSS | Server-rendered with reactive components, not SPA |
| **Database** | MySQL 5.7.23+ / MariaDB 10.2.7+ | |
| **Build** | Vite | |
| **Package System** | Webkul Concord | Modular packages with own models, migrations, providers |
| **Queue** | Laravel Queue (database/redis) | For imports, email sending |
| **Auth (API)** | Laravel Sanctum | Token-based, separate `krayin/rest-api` package |
| **Calendar** | vue-cal | Frontend calendar component for activities |

### Package Architecture (18 packages under `packages/Webkul/`)

```
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

1. **Proxy Pattern**: Every model has a `*Proxy` class enabling model substitution/extension without modifying core code.
2. **Repository Pattern**: All data access through dedicated repository classes extending `Webkul\Core\Eloquent\Repository`.
3. **Contract/Interface Pattern**: Models implement contracts for loose coupling.
4. **EAV (Entity-Attribute-Value)**: Dynamic custom attributes via `CustomAttribute` trait and `attribute_values` table. Supports 16 field types.
5. **Event-Driven**: Laravel events dispatched on all CRUD operations (e.g., `lead.create.after`). Workflow automation subscribes to these events.
6. **Bouncer Authorization**: Custom `bouncer()` helper limits data access by authorized user IDs (all/group/individual permission levels).

### REST API

The REST API is a **separate package** (`krayin/rest-api`), not included in core. Uses Laravel Sanctum for authentication and L5-Swagger for OpenAPI documentation at `/api/admin/documentation`.

### Data Model (Core Relationships)

```
Organizations (1) --< Persons (1) --< Leads (N)
                                        |-- belongsTo Pipeline
                                        |-- belongsTo Stage
                                        |-- belongsTo Source / Type
                                        |-- belongsTo User (sales owner)
                                        |-- hasMany LeadProducts (quantity, price, amount)
                                        |-- belongsToMany Activities, Tags, Quotes
                                        |-- hasMany Emails

Pipeline (1) --< Stages (ordered by sort_order, probability 0-100%)
Quote (1) --< QuoteItems (per-line discounts, tax, totals)
Product --< ProductInventory --< Warehouse/Location
Activity --< Participants (User or Person) + Files
Workflow -- conditions (JSON, AND/OR) + actions (JSON)
Campaign --> Event --> EmailTemplate
```

---

## 4. Feature Inventory

| # | Spec | Priority | One-Line Description |
|---|------|----------|---------------------|
| 1 | **pipeline-management** | Critical | Multi-pipeline system with ordered stages, probability percentages, rotten lead detection, and Kanban column mapping |
| 2 | **leads** | Critical | Central CRM entity with Kanban + table views, stage transitions, lead-product relationships, filtering, and mass operations |
| 3 | **contacts** | High | Persons (individuals) and Organizations (companies) with JSON multi-value email/phone fields, EAV custom attributes, and activity logging |
| 4 | **custom-attributes** | High | EAV system supporting 16 field types on all entities (leads, persons, organizations, products, quotes, warehouses) without schema changes |
| 5 | **automation-workflows** | High | Event-driven workflows with AND/OR conditions and 7 action types (update fields, send emails, add tags/notes, trigger webhooks) |
| 6 | **activities** | High | Interaction tracking (calls, meetings, lunches, notes, files) with scheduling, participants, calendar view, and polymorphic entity linking |
| 7 | **email** | Medium | Built-in email client with IMAP polling + SendGrid webhook inbound, SMTP outbound, threading, folders, attachments, and lead linking |
| 8 | **quotes** | Medium | Quote generation with line items, per-line discounts/tax, billing/shipping addresses, expiration dates, and printable output |
| 9 | **products** | Medium | Product catalog with SKU, pricing, inventory tracking across warehouses/locations, and per-lead pricing via lead_products |
| 10 | **dashboard-analytics** | Medium | 8 fixed statistical views (revenue, leads over time, top products/persons, pipeline stage distribution) with date-range filtering |
| 11 | **data-import** | Medium | CSV/Excel batch import for leads, persons, and products with validation, queue-based processing, and error reporting |
| 12 | **web-forms** | Low | Embeddable HTML lead capture forms with selectable attributes and customizable styling |
| 13 | **marketing-campaigns** | Low | Event-triggered email campaigns with template sending and batch spooling |
| 14 | **warehouses** | Low | Warehouse and location management with product inventory (in_stock, allocated), reflecting Webkul's e-commerce heritage |
| 15 | **ai-lead-extraction** | Low | AI-powered lead creation from PDF/image uploads via OpenRouter API, extracting title, value, and person data |

---

## 5. Key Strengths

### 5.1 Mature Pipeline and Kanban Implementation
Production-ready Kanban view with drag-and-drop stage transitions, per-column lead value aggregation, pagination (10 per stage with infinite scroll), comprehensive filtering (by person, source, type, tags, value), and rotten lead detection. The pipeline is the primary marketing hook -- it is the first thing visitors see on the website.

### 5.2 Full Built-In Email Client
Unique among CRM tools at this price point. Supports IMAP polling + SendGrid webhook for inbound, SMTP for outbound, email threading, folders (inbox/outbox/sent/draft/trash), attachments, and automatic lead-email linking. Most pipeline tools either lack email integration or require external plugins.

### 5.3 Comprehensive Quoting with Printable Output
Enterprise-grade quoting: line items with per-line discounts (percentage or fixed), per-line tax, coupon codes, billing/shipping addresses, expiration dates, sub-total/grand-total calculations, and a dedicated print endpoint. Quote-to-lead linking via M:N allows one quote across multiple deals.

### 5.4 EAV Custom Attributes on All Entities
Administrators can define unlimited custom fields (16 types: text, select, date, file, address, lookup, etc.) on leads, persons, organizations, products, quotes, and warehouses without database migrations. The `quick_add` flag controls which fields appear in compact creation forms -- good UX.

### 5.5 Workflow Automation with Webhooks
Event-driven workflows trigger on create/update/delete for leads, activities, persons, and quotes. Seven action types (update fields, send email to contact/owner, add tags, add notes, trigger webhooks). Webhooks support all HTTP methods, multiple content types, and placeholder replacement in URL/headers/payload.

### 5.6 Modular Laravel Package Architecture
18 self-contained packages with the Proxy pattern enabling model substitution. Clean separation allows extending or replacing any entity without modifying core code. Well-suited for white-label and custom development.

### 5.7 Commercial Ecosystem and Market Traction
21,700+ GitHub stars, 4.4/5 Trustpilot, case studies spanning UK/US/Brazil/India, 20+ industry-specific landing pages, 7 paid extensions, cloud hosting, white-label licensing, and backing by Webkul (established Laravel ecosystem company). One-time pricing for extensions ($299--$4,500) is attractive vs. recurring SaaS fees.

### 5.8 Data Import Pipeline
Well-structured CSV/Excel import with validation, batched processing via Laravel queue jobs, sample file downloads, and error reporting. Handles large imports asynchronously.

---

## 6. Key Weaknesses

### 6.1 No Built-In REST/GraphQL API
The core CRM is purely web-based controllers (Blade + inline Vue). A REST API exists only as a separate package (`krayin/rest-api`) that must be installed additionally. No headless mode, no GraphQL, and no API-first design.

### 6.2 Monolithic Server-Rendered Frontend
Blade templates with inline Vue.js components -- not a modern SPA. No client-side routing, no state management, no component library. The DataGrid component provides table functionality but everything is server-rendered with page reloads.

### 6.3 No Multi-Tenancy (Core)
Single organization deployment in the free version. Multi-tenancy requires a $1,799 paid extension.

### 6.4 Limited Reporting and Analytics
Dashboard has only 8 fixed statistical views. No custom report builder, no saved reports, no export to PDF/Excel, no forecasting or trend analysis. Date range filtering is the only dimension.

### 6.5 Basic Workflow Engine
Condition matching + predefined actions only. No visual flow builder, no delay/wait actions, no conditional branching within a workflow, no execution history or logging. Everything executes synchronously.

### 6.6 No Calendar/Contact Integration
Activities have scheduling fields but no CalDAV/Google Calendar sync (built-in -- Google Integration is a separate free extension). No contact import from LinkedIn, vCard, or external address books.

### 6.7 No Nextcloud or EU Government Ecosystem
No Nextcloud integration, no Dutch language support, no WOO/ZGW/GEMMA compliance, no NL Design System support, no government theming.

### 6.8 Missing Pipeline Features
No pipeline stage history or audit trail (only current stage tracked). No lead scoring. No weighted pipeline forecasting (stage probabilities exist but are unused in calculations). No pipeline templates or cloning. No pipeline-level permissions.

### 6.9 No Document Generation or E-Signatures
Quotes can be printed but there is no general document template engine. No e-signature integration, no document versioning within the CRM (though files attach to activities).

### 6.10 Limited Integration Ecosystem
7 paid extensions only. No marketplace, no pre-built connectors beyond webhooks, no Zapier/Make integration. The small extension catalog limits adoption in complex environments.

---

## 7. Relevance to Pipelinq

### Direct Competition Areas

| Area | Krayin | Pipelinq | Assessment |
|------|--------|----------|------------|
| **Pipeline/Kanban management** | Mature, production-ready with rotten lead detection | Building | Krayin is ahead -- study their Kanban pagination, filtering, and rotten lead UX |
| **Contact management** | Person + Organization with EAV | Person + Organization via OpenRegister | Similar scope; Pipelinq has Nextcloud Contacts sync advantage |
| **Activity tracking** | 6 activity types with calendar | Contact moments logging | Comparable; Krayin has dedicated calendar view |
| **Lead/deal management** | Comprehensive with products, quotes, AI extraction | Pipeline items | Krayin has more depth (products, quotes, AI) |
| **RBAC** | Bouncer-based data-level ACL + role-based feature ACL | Nextcloud groups/roles | Comparable approaches |

### Pipelinq's Advantages Over Krayin

| Advantage | Why It Matters |
|-----------|---------------|
| **Nextcloud-native** | Contacts sync, Files, Calendar, Mail, Talk, Notifications -- all built-in. Krayin rebuilds these from scratch. |
| **Dutch government compliance** | NL Design System, WOO/ZGW support, Dutch language. Krayin has zero EU government features. |
| **Modern frontend** | Nextcloud Vue SPA vs. Blade + inline Vue. Better UX, state management, client-side routing. |
| **n8n workflow automation** | Visual flow builder with 400+ integrations vs. Krayin's basic condition/action workflows. |
| **Case management integration** | Procest integration for zaakafhandeling. Krayin is purely CRM. |
| **My Work queue** | Unified task queue across pipeline items. Krayin has dashboard tasks but no dedicated work queue. |
| **Duplicate detection** | Built-in. Krayin has no contact deduplication logic. |
| **API-first design** | OpenRegister provides REST API natively. Krayin requires a separate package. |
| **Multi-tenancy** | Nextcloud provides this. Krayin charges $1,799. |

### Features Worth Adopting from Krayin

| Feature | Priority | How to Implement in Pipelinq |
|---------|----------|------------------------------|
| **Rotten lead detection** | High | Track days since creation/last update vs. configurable threshold per pipeline. Highlight stale items in Kanban. |
| **Stage probability percentages** | Medium | Add probability to pipeline stages. Use for weighted pipeline forecasting (value x probability). |
| **Lead-product relationship** | Medium | Allow attaching products/services with quantity and per-deal pricing to pipeline items. Enables deal value breakdown. |
| **Lost reason tracking** | High | Prompt for reason when moving to "lost" stage. Aggregate for analytics. |
| **Per-column value aggregation** | High | Show total deal value sum per Kanban column. Essential pipeline metric. |
| **Quick-add with configurable fields** | Medium | EAV `quick_add` flag pattern -- let admins control which fields appear in compact creation forms. |
| **Kanban pagination** | High | Paginate leads per stage column (10 per page with infinite scroll). Critical for pipelines with many items. |
| **Web-to-lead forms** | Low | Embeddable forms for external lead capture. Could use OpenRegister + public API. |
| **Data import** | Medium | CSV/Excel import with validation and error reporting for bulk onboarding. |
| **Email templates with placeholders** | Medium | Template system with `{%entity.field%}` placeholder replacement for notifications and automation. |

---

## 8. Feature Gap Analysis

### What Krayin Has That Pipelinq Lacks

| Feature | Krayin Implementation | Gap Severity | Recommendation |
|---------|----------------------|--------------|----------------|
| Rotten lead detection | `created_at + rotten_days` threshold per pipeline | **High** | Implement -- simple calculation, high UX value for sales teams |
| Per-column value aggregation | Sum of `lead_value` per Kanban stage | **High** | Implement -- essential pipeline metric, minimal effort |
| Lost reason tracking | Prompt on "lost" stage transition, stored in lead | **High** | Implement -- valuable for analytics and process improvement |
| Kanban pagination | 10 items per stage with infinite scroll | **High** | Implement -- required for scalability with many pipeline items |
| Built-in email client | IMAP + SMTP + threading + lead linking | **Medium** | Skip building -- integrate with Nextcloud Mail instead |
| Quoting with line items | Quotes with per-line discounts, tax, printable output | **Medium** | Consider for v2 -- useful for sales-oriented deployments |
| Lead-product relationship | Products attached per lead with quantity/price/amount | **Medium** | Implement -- enables deal value breakdown and product analytics |
| Stage probabilities | 0-100% per stage (unused in forecasting) | **Medium** | Implement and actually use for weighted forecasting (do what Krayin doesn't) |
| AI lead extraction | PDF/image upload, OpenRouter LLM, extract 5 fields | **Low** | Defer -- Nextcloud AI assistant could provide this later |
| Product catalog | Products with SKU, pricing, inventory | **Low** | Out of scope for pipeline management |
| Marketing campaigns | Event-triggered email templates | **Low** | Out of scope -- n8n handles this better |
| Warehouse management | Inventory across warehouses/locations | **Low** | Not relevant to Pipelinq |

### What Pipelinq Has (or Will Have) That Krayin Lacks

| Feature | Pipelinq Advantage | Why It Matters |
|---------|-------------------|----------------|
| **Nextcloud ecosystem** | Files, Calendar, Contacts, Mail, Talk, Notifications all native | No need to rebuild email client, calendar, file management |
| **Request intake workflow** | Structured intake process | Krayin only has basic web forms |
| **My Work queue** | Unified personal task view | Krayin has no equivalent |
| **Nextcloud Contacts sync** | Native address book integration | Krayin contacts are isolated |
| **Duplicate detection** | Automatic contact deduplication | Krayin has none |
| **Case management (Procest)** | Pipeline items can become zaken | Krayin is purely CRM, no case management |
| **Modern SPA frontend** | Nextcloud Vue with state management | vs. Blade templates with page reloads |
| **n8n automation** | Visual workflow builder, 400+ integrations | vs. basic condition/action rules |
| **NL Design System** | Government-compliant theming | Krayin has no government theming |
| **Dutch language** | Native Dutch UI | Krayin supports English, Turkish, Arabic only |
| **API-first** | OpenRegister REST API built-in | Krayin requires separate `krayin/rest-api` package |
| **Audit trail** | Comprehensive tracking | Krayin has no stage history or audit trail |
| **Multi-tenancy** | Nextcloud native | Krayin charges $1,799 for multi-tenant |

---

## Summary

Krayin is the most directly relevant competitor to Pipelinq in the pipeline/CRM space. It has a mature, production-ready Kanban implementation with features Pipelinq should adopt (rotten lead detection, per-column value aggregation, lost reason tracking, stage pagination). Its built-in email client and quoting system demonstrate what a fully-featured sales CRM looks like.

However, Krayin's architecture is fundamentally different from Pipelinq's approach: it is a standalone Laravel application that rebuilds functionality (email, files, calendar, contacts) that Nextcloud already provides natively. Its frontend is server-rendered Blade with inline Vue (not a modern SPA), it lacks a built-in API, and its workflow automation is basic compared to n8n.

**The strategic takeaway:** Pipelinq should cherry-pick Krayin's best pipeline UX features (rotten leads, value aggregation, stage probabilities, lost reasons) while leveraging the Nextcloud ecosystem for everything else (email, files, contacts, calendar, authentication, multi-tenancy). The result would be a pipeline management tool with comparable or better sales workflow UX, superior integration, and native Dutch government compliance -- none of which Krayin can offer.
