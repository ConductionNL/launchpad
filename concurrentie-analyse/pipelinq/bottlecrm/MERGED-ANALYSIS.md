# BottleCRM (Django-CRM) Competitive Analysis -- Merged Report

**Date:** 2026-03-14
**Sources:** Codebase analysis (GitHub MicroPyramid/Django-CRM), website review (bottlecrm.io), 9 feature specs (accounts, cases, contacts, invoices, leads, multi-tenancy, opportunities, pipeline-kanban, tasks), Docker walkthrough with 47 screenshots (`business-logic/browser-walkthrough-notes.md`)

---

## 1. Sources Summary

| Source | File | Content |
|--------|------|---------|
| Product overview | `overview.md` | Full architecture, data model (Mermaid ER), sales pipeline flow, tech stack, differentiators vs Pipelinq |
| Initial analysis | `bottlecrm.md` | Business model, pricing, target market, high-level feature comparison |
| Accounts spec | `specs/accounts/spec.md` | Account entity, email campaigns, duplicate detection, invoice protection |
| Cases spec | `specs/cases/spec.md` | Support tickets, SLA tracking, knowledge base (Solutions), case pipelines |
| Contacts spec | `specs/contacts/spec.md` | Contact entity, duplicate detection, account linking, communication preferences |
| Invoices spec | `specs/invoices/spec.md` | Products, invoices, estimates, recurring invoices, payments, templates, client portal |
| Leads spec | `specs/leads/spec.md` | Lead pipeline, conversion workflow, stale detection, follow-up tracking |
| Multi-tenancy spec | `specs/multi-tenancy/spec.md` | PostgreSQL RLS, org model, JWT auth, magic links, security audit logging |
| Opportunities spec | `specs/opportunities/spec.md` | Deal stages, line items, deal aging, sales goals with pace tracking |
| Pipeline/Kanban spec | `specs/pipeline-kanban/spec.md` | Reusable Pipeline->Stage->Entity pattern, decimal ordering, WIP limits, view toggle |
| Tasks spec | `specs/tasks/spec.md` | CRM tasks (entity-linked) + standalone Board/Kanban system |
| Browser walkthrough | `business-logic/browser-walkthrough-notes.md` | Hands-on Docker walkthrough of all modules with 47 screenshots documenting login flow, dashboard, all CRUD forms, navigation, settings, invoicing sub-pages, portal, and technical issues encountered |

---

## 2. Product Overview

BottleCRM is a modern, fully open-source CRM (MIT license) built by MicroPyramid. It targets startups and small businesses with a complete sales-to-invoice lifecycle in a single platform. The project has no paid tiers or feature gates -- MicroPyramid monetizes through consulting and custom development.

**Tech Stack:**

| Layer | Technology |
|-------|-----------|
| Backend | Django 5.x + Django REST Framework |
| Frontend | SvelteKit 2.x + Svelte 5 (runes) + TailwindCSS 4 + shadcn-svelte (bits-ui) |
| Mobile | Flutter (Dart) -- Android + iOS; also a SvelteKit-based mobile app directory |
| Database | PostgreSQL 16 with Row-Level Security (RLS) |
| Cache/Broker | Redis 7 |
| Background Jobs | Celery |
| Auth | JWT (access + refresh tokens, magic links, Google/Microsoft OAuth) -- no password login |
| PDF Generation | WeasyPrint (server-side invoice/estimate PDF rendering) |
| File Storage | AWS S3 |
| Email | AWS SES |
| API Docs | drf-spectacular (Swagger / Redoc) |
| Container | Docker Compose (backend :9010, frontend :9011, postgres, redis, celery) |
| Drag-and-drop | svelte-dnd-action (kanban card reordering) |

**Architecture:**
```
SvelteKit Frontend (9011) --> Django REST API (9010) --> PostgreSQL 16 (RLS)
Flutter / SvelteKit Mobile --/                            |
                                                          Redis 7 (cache/broker)
                                                          Celery (async tasks)
                                                          WeasyPrint (PDF)
```

**Target Market:** Startups and small businesses looking for a free, modern CRM. The multi-tenant architecture also appeals to SaaS builders who want to offer CRM as a service.

**Pricing:** Completely free. MIT license, unlimited users, no subscription fees. Costs are limited to hosting infrastructure.

---

## 3. Architecture Summary

### Data Model

BottleCRM uses an **account-centric hub model** where the Account (organization) entity is the central node linking all CRM entities:

- **Org** (tenant) scopes all data via PostgreSQL RLS
- **Account** links to Contacts, Opportunities, Cases, Invoices, Orders, Tasks
- **Lead** is the entry point -- converts into Account + Contact + Opportunity
- **Opportunity** carries line items from a Product catalog and flows into Invoices/Orders
- **Case** provides customer support with SLA tracking and a knowledge base
- **Task** links to any single CRM entity (account, opportunity, case, or lead)
- **Invoice** links to Account, Contact, and optionally Opportunity; carries line items, payments, and a template reference
- **Estimate** mirrors the invoice structure but with estimate-specific statuses (Accepted/Declined/Expired); can convert to Invoice
- **Product** is a standalone catalog entity referenced by invoice/opportunity line items
- **SalesGoal** tracks period-based targets (revenue, deals won, new accounts) per assigned user

### Multi-Tenancy

Enterprise-grade isolation via PostgreSQL Row-Level Security:
- Every table has an `org_id` column
- Django middleware sets `app.current_org` PostgreSQL session variable per request
- RLS policies filter all queries automatically at the database level
- Application-level `BaseOrgModel` provides additional scoping via `OrgScopedQuerySet`
- Cross-org validation on comments/attachments prevents subtle data leaks
- Walkthrough confirmed: after login, user selects an org from a list (with role badge: admin/member), or creates a new one; org switching available from the sidebar footer

### Authentication

- JWT with session tracking (access + refresh tokens, revocation support)
- Magic link passwordless login -- the only email-based auth method; **no traditional password login exists**
- Google OAuth (PKCE flow) and Microsoft OAuth
- Walkthrough confirmed: login page shows only "Continue with Google" and email magic link; each browser session requires a new magic link cycle
- Dedicated `SecurityAuditLog` for login attempts, org switches, permission denials, cross-org attempts

### Frontend Architecture (from Docker walkthrough)

- **Slide-out sheet pattern:** All create/edit forms use a right-side slide-out sheet (~480px) rather than modals or page navigation. Sheet has: entity type badge header, form fields with "Show X more fields" expandable section, footer with Cancel + Create button
- **Configurable table columns:** Column selector with "X/Y" visible count indicator; bulk select via checkboxes; inline editing for status dropdowns
- **View toggle:** List (table), Kanban, and Calendar views available on Leads, Cases, and Tasks
- **Dashboard widgets:** Today's Focus bar, sales pipeline visualization (horizontal stages with count + dollar value), 2x2 key metrics cards, pipeline-by-stage bar chart, Hot Leads, My Tasks (with All/Overdue/Today/Week tabs), My Opportunities, Goal Progress, and Recent Activity feed
- **Toast notifications:** Bottom-right auto-dismiss green toasts for all CRUD operations
- **Navigation sidebar:** CRM section (Dashboard, Leads, Contacts, Accounts, Deals, Goals, Tickets, Tasks), Sales section (Invoices with expandable sub-items), Support section (Help Desk), and a collapsible footer with user profile card and org switcher

### Pipeline/Kanban Pattern

Reusable three-model pattern applied across Leads, Cases, and Tasks:
- **Pipeline** -- named pipeline per org (e.g., "Inbound", "Support", "Development")
- **Stage** -- kanban column with ordering, color, stage_type, WIP limit, and status mapping
- **Entity** -- has `stage` FK + `kanban_order` (DecimalField 15,6 for insert-between positioning)
- Walkthrough confirmed drag-and-drop via svelte-dnd-action; kanban cards show title + company with column footer counts

Opportunities use a fixed 6-stage pipeline (Prospecting, Qualification, Proposal, Negotiation, Closed Won, Closed Lost -- not customizable).

### API Structure (from Docker walkthrough)

RESTful API at `/api/` with dedicated endpoints for each module:
- `auth/` -- magic link, Google OAuth, token refresh, profile
- `contacts/`, `accounts/`, `leads/`, `opportunities/`, `cases/`, `tasks/` -- standard CRUD
- `invoices/` -- invoices, estimates, products, templates, payments, recurring
- `users/` -- user management, teams, roles
- `settings/` -- API settings, org settings
- `tags/` -- cross-entity tag management
- API key management per organization (title, description, auto-generated key, is_active)

---

## 4. Feature Inventory

| # | Spec | Description |
|---|------|-------------|
| 1 | **Accounts** | Company/organization records with email campaigns, revenue tracking, industry classification, and duplicate detection |
| 2 | **Cases** | Customer support tickets with priority-based SLA tracking (first response + resolution), custom pipelines, knowledge base (Solutions), and list/kanban view toggle |
| 3 | **Contacts** | Individual person records with duplicate detection (email/phone/name), account linking (primary FK + M2M), Do Not Call flag, and communication preference fields |
| 4 | **Invoices** | Full invoicing suite: product catalog (name, SKU, price, tax rate), invoices (8 statuses from Draft to Cancelled), estimates (with Accept/Decline/Expire flow and conversion to invoice), recurring invoices (7 frequencies including custom), payments (7 methods), PDF templates (logo, colors, custom HTML via WeasyPrint), client portal with token-based public access, and invoice reports sub-page |
| 5 | **Leads** | Lead capture with 28 fields (10 primary, 18 extended in collapsible section), custom kanban pipelines, automated conversion to Account+Contact+Opportunity, stale detection, follow-up date tracking, and Hot/Warm/Cold rating |
| 6 | **Multi-Tenancy** | PostgreSQL RLS data isolation, per-org user profiles with role-based permissions (admin/user), JWT/magic link/OAuth auth, org creation and switching, and security audit logging |
| 7 | **Opportunities** | Sales deals with fixed 6-stage pipeline, line items from product catalog (with discount type and tax rate), deal aging (green/yellow/red), sales goals with pace tracking (weekly/monthly/quarterly/yearly targets), auto-probability mapping, and Amount Source toggle (Manual / Calculated from Products) |
| 8 | **Pipeline/Kanban** | Reusable Pipeline->Stage->Entity pattern across modules, decimal ordering for drag-drop via svelte-dnd-action, WIP limits, stage-to-status mapping, and list/kanban/calendar view toggle |
| 9 | **Tasks** | Dual task system: CRM tasks linked to business entities (mutually exclusive parent) + standalone Board/Kanban with membership roles (owner/admin/member), board columns with WIP limits, and list/board/calendar view modes |
| 10 | **Dashboard** | Comprehensive sales overview: Today's Focus bar, horizontal pipeline visualization with stage values, 4 key metrics (Pipeline Value, Weighted Pipeline, Won This Month, Conversion Rate), Hot Leads widget, My Tasks with tabs, My Opportunities, Goal Progress, and Recent Activity feed |
| 11 | **Settings** | Organization settings, tag management (active/archived with color and description), Salesforce data import, and user/team management with role assignment |
| 12 | **Portal** | Public client-facing pages for invoice viewing/payment and estimate viewing/acceptance via token-based URLs (no login required) |

---

## 5. Key Strengths

1. **Complete sales lifecycle** -- Lead capture through invoicing and payment tracking in a single platform, with no feature gaps in the core sales flow
2. **Enterprise-grade multi-tenancy** -- PostgreSQL RLS provides database-level data isolation that cannot be bypassed by application bugs, complemented by security audit logging
3. **Modern tech stack** -- Svelte 5 + TailwindCSS 4 + Django 5 with excellent developer experience; Flutter mobile app for Android/iOS
4. **Rich invoicing suite** -- Products, invoices, estimates, recurring invoices (7 frequencies), payments (7 methods), WeasyPrint PDF templates with logo/color customization, and client portal with token-based public access
5. **Deal intelligence** -- Stage aging with configurable thresholds (green/yellow/red), sales goals with pace tracking, and auto-probability mapping per stage
6. **Lead conversion workflow** -- Automated creation of Account + Contact + Opportunity from a lead, including migration of comments, attachments, tags, and team assignments
7. **Reusable pipeline/kanban pattern** -- Clean three-model architecture (Pipeline->Stage->Entity) applied consistently across Leads, Cases, and Tasks with WIP limits and stage-to-status mapping
8. **Duplicate detection** -- Built-in service matching on email, phone, name, company, and website (domain-normalized) across contacts, leads, and accounts
9. **Fully open source** -- MIT license with no premium tier, no feature gates, and no subscription fees
10. **SLA tracking on cases** -- Priority-based default SLA hours (first response + resolution) with breach detection
11. **Polished UX patterns** -- Consistent slide-out sheet forms with collapsible "show more fields" sections, configurable table columns, inline status editing, and a comprehensive dashboard with actionable widgets (confirmed via walkthrough)
12. **Salesforce import** -- Built-in data import from Salesforce eases migration for teams switching CRMs

---

## 6. Key Weaknesses

1. **No workflow automation** -- No visual workflow builder; Celery is used only for background email tasks, not user-configurable business logic
2. **No custom fields** -- Schema is entirely fixed; users cannot add custom fields to any entity
3. **No email sync or inbox integration** -- Only outbound email via AWS SES; no inbound email parsing or email tracking
4. **No calendar integration** -- No Google/Outlook calendar sync despite being mentioned on the marketing site; calendar view returned 500 "fetch failed" in walkthrough
5. **No document generation beyond invoices** -- No contract templates, proposal generation, or general document automation
6. **No configurable reporting/analytics** -- Dashboard exists but no user-defined reports or data export beyond basic CSV
7. **Fixed opportunity stages** -- The 6 opportunity stages are hardcoded; only Leads, Cases, and Tasks have custom pipelines
8. **Limited integrations** -- AWS-only (SES, S3); no webhook system, no Zapier, no third-party CRM integrations (though Salesforce import exists for one-time migration)
9. **Single-maintainer risk** -- Primarily developed by MicroPyramid; small community and limited documentation
10. **No Nextcloud or government ecosystem support** -- No integration with Nextcloud, no Dutch government compliance (WOO, ZGW, GEMMA), no Common Ground alignment
11. **Dev environment instability** -- Docker walkthrough revealed Tailwind CSS 4.2.1 + bits-ui incompatibility, PUBLIC_DJANGO_API_URL split-brain (SSR uses Docker network hostname which fails in browser), Vite dev server crashes, and multiple pages returning 404/500 errors (board view, calendar view, settings index, profile page)
12. **No password-based login** -- Only magic link and Google OAuth; each browser session requires a new magic link cycle, creating friction for local/self-hosted deployments without SMTP configured
13. **Task board instability** -- Board view returned 404 (requires pre-created board), calendar view returned 500; the dual task system (CRM tasks + standalone boards) adds conceptual complexity

---

## 7. Relevance to Pipelinq

BottleCRM is the most feature-comparable open-source competitor to Pipelinq in the CRM space. While it operates in a completely different ecosystem (standalone Django app vs. Nextcloud app), its feature set reveals several areas where Pipelinq could learn or differentiate.

**Direct competitive overlap:**
- Lead/contact/account management
- Pipeline/kanban views for deals
- Task management linked to CRM entities
- Team assignment and tagging

**Where BottleCRM is ahead:**
- Complete sales-to-invoice lifecycle (Pipelinq has no invoicing)
- Automated lead conversion (lead -> account + contact + opportunity in one action)
- Deal aging indicators with per-stage configurable thresholds
- Sales goals/quotas with pace tracking (weekly/monthly/quarterly/yearly targets with on_track/at_risk/behind status)
- Duplicate detection across entities
- SLA tracking on support cases
- Mobile app (Flutter)
- Comprehensive dashboard with pipeline visualization, key metrics, and actionable widgets
- Slide-out sheet UX for fast entity creation without losing context
- Configurable table columns with inline status editing
- Salesforce import for CRM migration
- Product catalog with line items on both opportunities and invoices
- Client portal for invoice/estimate viewing (token-based, no login)

**Where Pipelinq is ahead:**
- Native Nextcloud integration (files, contacts, calendar, n8n workflows)
- Schema-driven dynamic pipelines (more flexible than BottleCRM's fixed opportunity stages)
- Dutch government ecosystem support (WOO, ZGW, GEMMA, Common Ground)
- Workflow automation via n8n (vs. BottleCRM's code-only Celery tasks)
- Request intake functionality
- Contact moments logging
- Case management integration via Procest
- Nextcloud Contacts sync
- RBAC and audit trail (via Nextcloud)
- Stable authentication (Nextcloud login vs. magic-link-only friction)
- Production-ready deployment (Nextcloud app store vs. manual Docker setup with known instability)

**Ecosystem difference:** BottleCRM is a standalone application requiring its own infrastructure (PostgreSQL, Redis, Celery, S3, SES). Pipelinq benefits from the entire Nextcloud ecosystem (authentication, file storage, contacts, calendar, n8n, docudesk) without needing to build these capabilities.

**UX lessons from walkthrough:** BottleCRM's slide-out sheet pattern for entity creation is notably efficient -- users can create a lead or contact without navigating away from the list view. The collapsible "show X more fields" pattern keeps forms approachable while exposing power-user fields on demand. The dashboard's "Today's Focus" bar (due tasks + follow-ups) is a practical feature that drives daily user engagement. These UX patterns are worth studying for Pipelinq's frontend.

---

## 8. Feature Gap Analysis

Features from BottleCRM that Pipelinq should consider adopting, ordered by relevance:

### High Priority (core CRM value)

| Feature | BottleCRM Implementation | Pipelinq Opportunity |
|---------|-------------------------|---------------------|
| **Lead conversion workflow** | Automated creation of Account + Contact + Opportunity with data migration (comments, attachments, tags, teams) | Build a conversion action that creates linked objects in OpenRegister with data carry-over |
| **Deal aging indicators** | Per-stage configurable thresholds with green/yellow/red status based on days in stage | Add `stage_changed_at` tracking and configurable aging thresholds per pipeline stage |
| **Duplicate detection** | Email, phone, name, company, website matching across contacts/leads/accounts | Implement matching service using OpenRegister object queries; critical for data quality |
| **Decimal kanban ordering** | DecimalField(15,6) for insert-between positioning without full reorder | Adopt decimal ordering pattern for kanban drag-drop performance |
| **Dashboard with actionable widgets** | Today's Focus bar (due tasks + follow-ups), pipeline visualization with stage values, 4 key metrics cards, Hot Leads, My Tasks with tab filters, Goal Progress | Build a CRM dashboard landing page with configurable widgets; "Today's Focus" concept is high-impact for daily user engagement |

### Medium Priority (competitive differentiation)

| Feature | BottleCRM Implementation | Pipelinq Opportunity |
|---------|-------------------------|---------------------|
| **Sales goals/quotas** | Period-based revenue/deals-closed targets with pace tracking (on_track/at_risk/behind); dedicated /goals page with Active/Completed/Needs Attention tabs | Implement as dashboard widgets using OpenRegister object aggregation |
| **Stage-to-status mapping** | Auto-updates entity status when entering a kanban stage | Ensures consistency between list and kanban views; reduces user confusion |
| **WIP limits on stages** | Advisory limits on how many items can be in a kanban column | Add to pipeline stage configuration; enforce visually in frontend |
| **SLA tracking** | Priority-based default hours for first response and resolution with breach detection | Relevant if Pipelinq adds service/support features; could delegate to Procest |
| **Stale lead detection** | Computed `is_stale` property (>30 days without contact, not converted/closed) | Add computed indicators for leads/deals without recent activity |
| **Slide-out sheet forms** | Right-side slide-out sheet (~480px) with collapsible "Show X more fields" section; user stays on list view while creating entities | Adopt this pattern for quick entity creation in Pipelinq; better than full page navigation for frequently-repeated actions |
| **Configurable table columns** | Column selector showing "X/Y" visible count; users toggle which columns appear; inline dropdown editing for status fields | Add column visibility preferences per user per entity type in Pipelinq list views |
| **Product catalog** | Standalone products (name, SKU, price, currency, tax rate) referenced by invoice and opportunity line items | Could integrate with docudesk or implement as an OpenRegister schema for quoting/proposal features |

### Lower Priority (nice-to-have)

| Feature | BottleCRM Implementation | Pipelinq Opportunity |
|---------|-------------------------|---------------------|
| **Invoicing/estimates** | Full invoice suite with products, payments (7 methods), recurring (7 frequencies), WeasyPrint PDF templates (logo, colors, custom HTML), estimate-to-invoice conversion | Likely better served by integration with existing tools (docudesk, n8n) rather than building in-app |
| **Knowledge base (Solutions)** | Draft->reviewed->approved->published articles linked to cases | Could be built on OpenRegister schemas; lower priority for CRM-focused tool |
| **Client portal** | Token-based public URLs for invoice/estimate viewing with PDF download | Nextcloud's share links provide similar capability |
| **Line items on opportunities** | Product catalog with per-item pricing, discount (percentage/fixed), tax rate, and Amount Source toggle (Manual / Calculated) | Adds quoting capability but increases complexity significantly |
| **Email campaign tracking** | AccountEmail model with per-recipient delivery tracking | n8n workflows + Nextcloud mail provide better automation potential |
| **Salesforce import** | Built-in import flow with progress tracking | Relevant only if targeting Salesforce migration users; lower priority for government market |
| **Activity feed** | Chronological audit log with action type, description, user, timestamp, linked entity; grouped by date on dashboard | Nextcloud has activity logging, but a CRM-specific activity feed on entities could add value |

### Not Relevant for Pipelinq

| Feature | Reason |
|---------|--------|
| PostgreSQL RLS multi-tenancy | Nextcloud handles multi-tenancy at the application level |
| Flutter mobile app | Nextcloud has its own mobile apps |
| AWS SES/S3 integration | Nextcloud provides file storage and (optionally) mail |
| JWT/magic link auth | Nextcloud handles authentication; magic-link-only is actually a weakness for self-hosted |
| Security audit logging | Nextcloud provides audit logging |
| API key management | Nextcloud handles API auth via app passwords and OAuth |
