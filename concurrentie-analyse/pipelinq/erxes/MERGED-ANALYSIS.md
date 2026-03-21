# Erxes Competitive Analysis -- Merged Report

**Date:** 2026-03-14
**Sources:** Codebase analysis (GitHub erxes/erxes), product website (erxes.io), 6 feature specs, source-code browser walkthrough (v2 + erxes-next)

---

## 1. Sources Summary

| Source | Type | Content |
|--------|------|---------|
| `erxes.md` | High-level overview | Business model, pricing, feature comparison table, strengths/weaknesses |
| `overview.md` | Technical deep-dive | Architecture, plugin ecosystem, data model, detailed strengths/weaknesses |
| `specs/sales-pipeline/spec.md` | Feature spec | Board > Pipeline > Stage > Deal hierarchy, GraphQL API, product-aware deals |
| `specs/contacts-crm/spec.md` | Feature spec | Customer/Company model, Conformity relation system, lead management |
| `specs/tickets-support/spec.md` | Feature spec | Ticket system, pipeline-based statuses, client portal integration |
| `specs/project-operations/spec.md` | Feature spec | Linear-style project management, sprints/cycles, milestones, triage |
| `specs/automation-engine/spec.md` | Feature spec | Trigger/action workflow engine, visual editor, execution history |
| `specs/omnichannel-inbox/spec.md` | Feature spec | Unified inbox, Facebook/IMAP/phone/chat integrations, bot handoff |
| `business-logic/browser-walkthrough-notes.md` | Source-code walkthrough | Detailed forms/fields inventory, all frontend components, UI/UX patterns, data models, Docker deployment attempt (failed), enterprise plugin catalog |

**Docker walkthrough limitation:** The erxes v2 Docker images (2.17.36) could not be fully deployed. The Apollo Router binary inside the gateway container crashes repeatedly due to GraphQL Federation schema incompatibilities between plugins (e.g., contacts plugin uses `[String]` where core expects `JSON`; forms plugin missing required arguments). Plugin images are published independently without coordinated compatibility testing -- the `2.17.36` tag for different plugins was built on different dates (core: 2026-03-04, contacts: 2024-09-15), causing schema drift. Only a loading screen screenshot was captured (`01-initial-loading.png`). All detailed analysis is based on comprehensive source code review of both v2 and erxes-next codebases.

---

## 2. Product Overview

**Erxes** (pronounced "erk-sis") is an open-source Experience Operating System (XOS) that unifies marketing, sales, operations, and support into a single platform. It positions itself as a replacement for HubSpot, Zendesk, Linear, and Wix combined.

- **License:** AGPLv3 (core), Enterprise Edition for premium plugins
- **Repository:** https://github.com/erxes/erxes
- **Self-hosted:** Yes, Docker-based deployment (though deployment stability is poor -- see Sources Summary)
- **Business model:** Open-core. Core platform and several plugins are free. Revenue from Enterprise Edition licenses (Content, Accounting, Finance, Team, Property, Tourism, Insurance, Loyalty), paid support/training, and a plugin marketplace. Additional team members cost $5/month per 5 members on hosted plans.
- **Target market:** Mid-market businesses looking for an all-in-one platform to replace multiple SaaS tools. Appeals to organizations wanting full control over their data with self-hosting.
- **Version split:** Two active codebases exist -- the current v2 (production Docker images) and erxes-next (main branch, actively developed with modernized stack including TailwindCSS v4, Radix UI, Jotai, React Hook Form + Zod, and tRPC v11).

---

## 3. Architecture Summary

Erxes is built as an Nx-powered TypeScript monorepo with three layers:

| Layer | Technology | Details |
|-------|-----------|---------|
| **Backend** | Node.js / TypeScript 5.7 | GraphQL Federation gateway (Apollo Router, port 4000) + Core API (port 3300) + plugin microservices (ports 3305+). Express + Apollo Server v4 (Federation). tRPC v11 for inter-service calls (erxes-next). |
| **Frontend** | React 18.3 / Rspack | Module Federation host (port 3001) + plugin micro-frontends (ports 3005+). TailwindCSS v4, Radix UI primitives, Jotai state management, Apollo Client for GraphQL, React Hook Form + Zod validation, BlockNote rich text editor, react-i18next, Recharts. |
| **Apps** | Next.js | Standalone applications: customer portal, POS client, widgets |
| **Data** | MongoDB, Redis, RabbitMQ, Elasticsearch | MongoDB (replica set required) for persistence, Redis for pub/sub + BullMQ job queues + service discovery, RabbitMQ for message brokering (v2; replaced by Redis PubSub in erxes-next), Elasticsearch 7 for search/segments (optional) |
| **API** | GraphQL Federation | Apollo Router federates schemas from core + all plugins. The Router binary runs inside the gateway container and requires all plugin schemas to be compatible -- a frequent failure point. |

Each plugin is a self-contained microservice (backend) + micro-frontend (frontend), registered via Redis-based service discovery. Communication between services uses tRPC (erxes-next) and GraphQL inter-service calls. A typical deployment runs **15+ microservices**, each as a separate Node.js process with its own memory footprint.

**Core modules** (included in every deployment): Contacts, Products, Segments, Automations, Documents.

**Open-source plugins:** Sales (deals, boards, pipelines, POS, e-commerce, orders, covers), Frontline (inbox, tickets, forms, channels, knowledge base, call center, response templates, reports), Operation (tasks, projects, cycles, teams, triage, milestones, templates).

**Enterprise-only plugins:** Content (headless CMS, web builder), Accounting (chart of accounts, transactions, inventory, VAT), Tourism (PMS, TMS), Insurance (contracts, risk types, vendors), Loyalty (scores, vouchers, lotteries, coupons, pricing engine), Payments (invoices, payment gateways), Mongolian (eBarimt, Erkhet sync), Property, Team (HR), Finance (banking).

**UI component library:** Custom design system built on Radix UI primitives (`erxes-ui` shared library + `ui-modules` for cross-plugin widgets). Common patterns include RecordTable (data table with sorting/filtering/column customization), Sheet (slide-out panels), CommandBar (contextual bulk actions), BlockEditor (rich text via BlockNote), and reusable entity selectors (SelectMember, SelectCompany, SelectCustomer).

---

## 4. Feature Inventory

| # | Spec | Description |
|---|------|-------------|
| 1 | **Sales Pipeline** | Four-level hierarchy (Board > Pipeline > Stage > Deal) with Kanban, Gantt, time tracking, product-aware deals with pricing/tax/discount, stage probability (10-90% + Won/Lost), growth hacking scoring (RICE/ICE/PIE), auto-numbering, and stage-level access control |
| 2 | **Contacts CRM** | Two-entity contact model (Customer + Company) with lifecycle states (visitor > lead > customer), generic Conformity relation system for many-to-many linking, contact merging/deduplication, email/phone validation tracking, visitor tracking, segmentation integration, and parent-company hierarchy |
| 3 | **Tickets & Support** | Issue tracking with typed tickets (bug/ticket/feature/question/incident), pipeline-based custom statuses, single-assignee model, client portal access for external users, activity audit trail, knowledge base for self-service, and channel-based filtering |
| 4 | **Project Operations** | Linear-style project management with projects, tasks, cycles (sprints), milestones, teams, triage queue, story points, templates (reusable task defaults), daily background workers for cycle statistics, and configurable estimate point scales per team |
| 5 | **Automation Engine** | Visual workflow editor with trigger/action model, action chaining (nextActionId), execution history tracking, delayed/waiting actions, email templates (HTML editor), plugin-extensible triggers/actions via meta registration, dedicated background execution service, AI agents with training data (file upload + test chat), and Facebook Messenger bots with persistence menu config |
| 6 | **Omnichannel Inbox** | Unified conversations across Facebook Messenger, IMAP email, phone calls (call center with detail pages), and embeddable chat widgets, with bot-to-human handoff, SLA tracking (first response time), response templates (canned responses), conversation query builder, real-time GraphQL subscriptions, and channel-based team assignment |
| 7 | **Forms Builder** | Form creation/edit/preview pages with field configuration, integration linking (connect forms to channels/widgets), and search/filter on form list |
| 8 | **Products & Catalog** | Product management with categories, UOM (unit of measure), barcodes, tax configuration, custom fields support, and product type classification |
| 9 | **Segments** | Dynamic segmentation builder with field-based conditions, operators, and Elasticsearch-backed querying across content types (customers, companies, deals, tasks) |
| 10 | **Organization Structure** | Branches (physical locations), departments (organizational divisions), units (sub-divisions), and positions (job roles/titles) |
| 11 | **Import/Export** | Bulk import from CSV/Excel for customers, companies, deals; export with field selection |
| 12 | **Documents** | Document template management with variable-based document generation |
| 13 | **Reports** | Report index with conversation/ticket analytics |

---

## 5. Key Strengths

1. **Mature sales pipeline** -- The Board > Pipeline > Stage > Deal hierarchy is the most fully-featured open-source sales pipeline available, with product data embedding, stage probability, financial calculations, time tracking, Gantt visualization, and growth hacking scoring. The walkthrough confirmed detailed deal add forms with company/customer/label/tag selectors, and deal detail views with products, checklists, and activity tabs.

2. **Product-aware deals** -- Deals embed line items with productId, quantity, unitPrice, tax, discount, and computed amount. Supports per-line assignment to users/branches/departments and aggregated totals. Products have full catalog management with categories, UOM, barcodes, and custom fields.

3. **Generic relation system (Conformities)** -- A polymorphic many-to-many linking system (mainType + mainTypeId <-> relType + relTypeId) that connects deals to customers, companies to contacts, tickets to customers, etc. without hard-coded foreign keys.

4. **Comprehensive automation engine** -- Visual workflow editor with trigger/action chaining, execution history, delayed actions, and plugin-extensible triggers. Sales-specific triggers include stage probability changes. Additionally includes AI agent configuration (training with file uploads, test chat interface) and Facebook Messenger bot setup.

5. **Plugin architecture** -- Clean microservice boundaries with GraphQL Federation (backend) and Module Federation (frontend). Each plugin is independently deployable with its own port, schema, and UI. The erxes-next frontend uses a modern stack (Radix UI, TailwindCSS v4, Jotai, React Hook Form + Zod) with a polished custom design system.

6. **Omnichannel communication** -- Unified inbox across Facebook, email, phone, and chat with bot support, SLA tracking, and real-time subscriptions. Includes dedicated call center pages, channel-based team assignment, response templates, and reports. Most open-source CRMs lack this depth.

7. **Client portal** -- Next.js customer-facing portal with deal and ticket access, allowing external users to interact with the system.

8. **Contact lifecycle management** -- Visitor tracking (anonymous) > lead (identified) > customer (converted) progression with real-time online status, session counting, and location detection. Company model supports parent-company hierarchy, industry classification, business types, and headquarter countries with flags.

9. **All-in-one scope** -- Covers CRM, marketing, support, project management, e-commerce, POS, forms, knowledge base, and reporting in a single platform, reducing tool sprawl. Enterprise plugins extend into accounting, insurance, loyalty, tourism, and content management.

10. **Real-time updates** -- GraphQL subscriptions backed by Redis for live deal changes, conversations, and agent assignments.

11. **Rich UI component library** -- The `erxes-ui` design system provides consistent patterns: RecordTable with sorting/filtering/column customization, Sheet slide-out panels, CommandBar for contextual bulk actions, BlockNote rich text editor, and reusable entity selectors. Supports light/dark themes, full i18n, and organization switching.

12. **Detailed forms and data capture** -- Customer add forms include avatar upload, email/phone validation status tracking, social media links (LinkedIn, Twitter, Facebook, YouTube, GitHub, Website), and rich text descriptions via BlockNote. Company forms add industry multi-select, business type classification, headquarters country with flags, and parent company selector.

---

## 6. Key Weaknesses

1. **MongoDB-only** -- No SQL database support. Government and enterprise environments often require PostgreSQL or MySQL. This is a fundamental architectural constraint. Additionally requires a MongoDB replica set (not standalone).

2. **Extremely complex deployment** -- Requires MongoDB (replica set), Redis, RabbitMQ (v2) or Redis PubSub (next), Elasticsearch, Apollo Router, and **15+ microservices** (one per plugin), each on its own port as a separate Node.js process. Significant DevOps expertise needed for self-hosting. No working docker-compose file exists in the repository.

3. **Docker deployment instability** -- Plugin Docker images are published independently per-plugin without coordinated compatibility testing. The `2.17.36` tag for different plugins was built on different dates (core: 2026-03-04, contacts: 2024-09-15), causing GraphQL schema drift. Apollo Router crashes when schemas are incompatible (e.g., `[String]` vs `JSON` type mismatches, missing required arguments). This makes self-hosted deployment unreliable out of the box.

4. **Enterprise lock-in** -- Key features (content management, accounting, HR/team, finance, insurance, loyalty, payments, tourism) are Enterprise Edition only, making the "free open-source" claim misleading for full functionality.

5. **Heavy monorepo** -- ~100+ packages in the Nx monorepo with significant build complexity. Contributing and extending is non-trivial. Each plugin being a separate Node.js process creates a heavy memory footprint.

6. **No government compliance** -- No NL Design System support, no WCAG-specific features, no Dutch/EU government API standards (ZGW, GEMMA), no government theming.

7. **No Nextcloud integration** -- No integration with Nextcloud ecosystem (Contacts, Files, Talk, etc.). Completely standalone platform.

8. **Ticket system is immature** -- The frontline ticket module is a simpler, newer design compared to the mature sales pipeline. Lacks the depth of dedicated ticketing tools.

9. **Operation module is basic** -- Linear-style task management without the depth of dedicated PM tools. Limited compared to mature project management solutions, though it does include teams, templates, milestones, and cycles.

10. **Low community engagement** -- Plugin marketplace is still developing. Community contributions are limited relative to the codebase size.

11. **No duplicate detection** -- While contact merging via `mergedIds` exists, there is no built-in automatic duplicate detection. Merging is manual.

12. **Version fragmentation** -- Two active codebases (v2 and erxes-next) create confusion. The erxes-next codebase has modernized dependencies (TailwindCSS v4, tRPC v11, Radix UI) but the Docker images still ship v2, meaning production deployments lag behind the source code significantly.

---

## 7. Relevance to Pipelinq

Erxes is the **most feature-rich open-source competitor** in the pipeline/CRM space for Pipelinq. Its sales pipeline module is the most directly comparable feature set. Key areas of relevance:

### Directly Relevant (high priority to evaluate)

- **Board/Pipeline/Stage hierarchy** -- Erxes groups pipelines under boards, providing an organizational layer above individual pipelines. Pipelinq should evaluate whether pipeline grouping adds value.
- **Stage probability** -- Each stage carries a win probability (10%-90%, Won, Lost), enabling weighted pipeline value calculations and forecasting. This is a standard CRM pattern Pipelinq should consider.
- **Product data on deals** -- Embedding product line items with pricing, tax, and discount calculations directly in deals is powerful for sales-oriented pipelines. Full product catalog with categories, UOM, barcodes, and custom fields.
- **Auto-numbering** -- Configurable per-pipeline auto-generated deal numbers (numberConfig, numberSize, lastNum) for reference tracking.
- **Stage-level access control** -- Restricting who can move or edit cards at specific stages (canMoveMemberIds, canEditMemberIds) is useful for approval workflows.
- **Checklists on deals** -- Embedded sub-task checklists within deal cards are a common CRM pattern.
- **Deal add form patterns** -- The walkthrough revealed detailed form fields: name (required), description (BlockNote rich text), pipeline/stage workflow selector, multi-member assignee, labels, companies, customers, and tags. This is a good reference for Pipelinq's card creation UX.
- **Forms builder** -- Standalone forms module with creation/edit/preview, linkable to channels and integrations. Relevant for data intake workflows in Pipelinq.

### Indirectly Relevant (medium priority)

- **Conformity system** -- The generic relation approach is architecturally interesting but Pipelinq already has OpenRegister's flexible schema system.
- **Contact lifecycle** -- Visitor > lead > customer progression is relevant if Pipelinq tracks lead sources.
- **Automation triggers on stage changes** -- Stage-specific and probability-based automation triggers could enhance Pipelinq's n8n integration.
- **Sprint/cycle management** -- From the operation module; relevant if Pipelinq adds project management capabilities.
- **Triage queue** -- Incoming item classification before pipeline assignment is a useful intake pattern.
- **Organization structure** -- Branches, departments, units, and positions hierarchy could inform multi-team pipeline access patterns.
- **Document templates** -- Variable-based document generation from entity data is useful for contracts and proposals.

### Not Relevant (low priority)

- **Omnichannel inbox** -- Facebook/email/phone integration is outside Pipelinq's scope (Nextcloud Talk handles communication).
- **Growth hacking scoring** (RICE/ICE/PIE) -- Niche feature for experiment prioritization.
- **POS/e-commerce** -- Point-of-sale and e-commerce modules are irrelevant to Pipelinq's government/business focus.
- **Client portal** -- Pipelinq operates within the Nextcloud ecosystem; external portals are handled differently.
- **Enterprise plugins** -- Accounting, insurance, loyalty, tourism, and Mongolian-specific modules are outside Pipelinq's scope.
- **AI agents** -- Erxes's built-in AI agent training is interesting but Pipelinq can leverage Nextcloud Assistant for similar functionality.

---

## 8. Feature Gap Analysis

Features Erxes has that Pipelinq should evaluate for adoption:

| Feature | Erxes Implementation | Pipelinq Status | Priority | Notes |
|---------|---------------------|-----------------|----------|-------|
| Stage probability / win rate | Per-stage probability (10-90%, Won, Lost) | Not implemented | High | Standard CRM feature; enables pipeline forecasting |
| Product line items on deals | Embedded productsData with qty, price, tax, discount | Not implemented | High | Critical for sales-oriented pipelines |
| Pipeline auto-numbering | Configurable number format per pipeline | Not implemented | Medium | Useful for reference tracking (e.g., DEAL-001) |
| Stage access control | canMoveMemberIds, canEditMemberIds per stage | Not implemented | Medium | Enables approval workflows at specific stages |
| Deal checklists | Checklist + ChecklistItem models embedded in deals | Not implemented | Medium | Common CRM sub-task pattern |
| Forms builder | Standalone form creation/edit/preview with integrations | Not implemented | Medium | Data intake for pipelines; erxes links forms to channels |
| Labels with colors | LabelForm with name + color picker, per-pipeline | Not implemented | Medium | Visual categorization of deals within pipelines |
| Board grouping | Boards as containers for related pipelines | Not implemented | Low | Useful for organizations with many pipelines |
| Time tracking on deals | startDate, timeSpent, status per deal | Not implemented | Low | Relevant for service-based sales |
| Gantt/timeline view | Relations + timeline persistence | Not implemented | Low | Visual timeline for deal dependencies |
| Deal hierarchy | parentId for sub-deals | Not implemented | Low | Nested deal structures |
| Stage forms | formId per stage for data collection | Not implemented | Low | Collect specific data when entering a stage |
| Rich text descriptions | BlockNote editor for deal/contact descriptions | Not implemented | Low | Erxes uses BlockNote; Pipelinq could use Nextcloud Text |
| Contact social links | LinkedIn, Twitter, Facebook, YouTube, GitHub, Website | Not implemented | Low | Social media profile linking on contacts |
| Organization structure | Branches, departments, units, positions hierarchy | Not implemented | Low | Multi-level org hierarchy for access control |
| Growth hack scoring | RICE/ICE/PIE frameworks | Not implemented | Very low | Niche experiment prioritization |

Features Pipelinq has that Erxes lacks:

| Feature | Pipelinq Advantage | Notes |
|---------|-------------------|-------|
| Nextcloud integration | Native Nextcloud app with Contacts, Files, Talk | Erxes is completely standalone |
| NL Design System theming | Government-compliant theming via CSS variables | Erxes has no government compliance |
| Dutch government standards | ZGW, GEMMA compatibility path | Erxes is US/global-market only |
| Nextcloud Contacts sync | Bidirectional sync with Nextcloud Contacts | Erxes has no address book sync |
| Procest integration | Case management integration via Procest app | Erxes tickets are basic |
| OpenRegister foundation | Flexible register/schema data model | Erxes is MongoDB-schema-locked |
| Simple deployment | Single Nextcloud app install | Erxes requires 15+ services, Apollo Router, MongoDB replica set, Redis, RabbitMQ/Elasticsearch |
| Duplicate detection | Built-in duplicate detection | Erxes merging is manual only |
| WCAG AA compliance | Government accessibility requirements | Erxes has no WCAG focus |
| Deployment stability | Stable, tested releases | Erxes Docker images suffer from schema drift and Apollo Router crashes due to uncoordinated plugin releases |
