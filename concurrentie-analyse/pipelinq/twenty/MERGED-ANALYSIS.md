# Twenty CRM - Merged Competitive Analysis

**Competitor:** Twenty (https://twenty.com)
**Analyzed:** 2026-03-14
**Relevance to:** Pipelinq

---

## 1. Sources Summary

### Codebase Files Analyzed
- **Overview files (2):** `overview.md` (structured analysis), `twenty.md` (initial competitor card)
- **Documentation files (6):** `docs/overview.md`, `docs/data-model.md`, `docs/api-and-integrations.md`, `docs/workflows-and-automation.md`, `docs/views-and-pipelines.md`, `docs/platform-and-extensibility.md`
- **Business logic files (1):** `business-logic/browser-walkthrough-notes.md` (live product walkthrough)
- **Spec files (17):** `specs/activity-tracking/`, `specs/ai-features/`, `specs/api-layer/`, `specs/api-platform/`, `specs/app-marketplace/`, `specs/business-logic/`, `specs/custom-data-model/`, `specs/custom-objects/`, `specs/dashboard-reporting/`, `specs/data-model/`, `specs/email-calendar-integration/`, `specs/email-calendar-sync/`, `specs/permissions-rbac/`, `specs/pipeline-management/`, `specs/pipeline-views/`, `specs/rbac-permissions/`, `specs/workflow-automation/`

**Total: 26 analysis files**

### External Documentation Fetched
- twenty.com (product site)
- docs.twenty.com (official documentation)
- github.com/twentyhq/twenty (source repository)

### Screenshots Captured
**21 screenshots** from a live Docker instance (port 9003), covering: landing page, workspace creation, companies list/detail views, people list, opportunities list and kanban, tasks, notes, workflows list and editor, dashboards list and detail, settings (profile, data model, APIs, general), command palette, and options menu.

---

## 2. Product Overview

### What It Is
Twenty is the leading open-source CRM, positioning itself as a modern alternative to Salesforce. It provides contact and company management, visual deal pipelines, workflow automation, email/calendar sync, and a customizable data model. The product emphasizes developer extensibility, a clean Notion/Linear-inspired UX, and community-driven development.

### Who Makes It
- **Company:** Twenty (YC-backed)
- **Founded:** December 2022
- **Background:** Founders previously sold their startup to Airbnb; backing from executives at HubSpot, Front, and Pipedrive
- **Community:** ~40,400 GitHub stars, ~5,360 forks
- **License:** AGPL-3.0

### Pricing
| Plan | Price | Key Features |
|------|-------|-------------|
| Free (Self-Hosted) | $0 | All core CRM, email/calendar sync, workflows, community support |
| Pro (Cloud) | ~$9/user/month | Hosted infrastructure, standard support |
| Organization (Cloud) | ~$19/user/month | SSO, row-level permissions, enhanced support |
| Organization (Self-Hosted) | Unknown | SSO, row-level permissions on own infrastructure |

### Tech Stack
| Layer | Technology |
|-------|-----------|
| Frontend | React, TypeScript, Recoil state management, Vite, custom component library (twenty-ui) |
| Backend | NestJS (Node.js), TypeORM, custom "Twenty ORM" with workspace-scoped entities |
| API | GraphQL (primary, Yoga + NestJS), REST (auto-generated), MCP (native) |
| Database | PostgreSQL (workspace-per-schema multi-tenancy), ClickHouse (analytics) |
| Search | PostgreSQL tsvector full-text search |
| Caching | Redis |
| Workflow | Custom engine with E2B serverless code execution |
| Email/Calendar | IMAP/SMTP + Google/Microsoft OAuth sync, CalDAV |
| Integrations | Zapier, Pipedream, Nango, webhooks, npm SDK |
| Infra | Docker, Nx monorepo |
| Docs | Mintlify, Figma + Storybook component library |

---

## 3. Architecture Summary

### Multi-Tenancy
PostgreSQL schema-based multi-tenancy: each workspace gets its own database schema. Object metadata is stored in a shared `core` schema, while workspace data lives in workspace-specific schemas. This provides strong data isolation between tenants.

### Data Model
The system separates **standard objects** (Company, Person, Opportunity, Task, Note) from **custom objects** (user-defined). All objects extend `BaseWorkspaceEntity` with common fields (id, createdAt, updatedAt, deletedAt). Twenty supports 27 field metadata types including composite types (Address, FullName, Currency, Emails, Phones) and polymorphic relations via target/join entities.

### API Architecture
Three auto-generated API layers, all reflecting the workspace's data model including custom objects:
1. **GraphQL** (primary) -- Yoga + NestJS, cursor-based pagination, complex filtering, subscriptions
2. **REST** -- Auto-generated OpenAPI endpoints
3. **MCP** -- Native Model Context Protocol for AI agent interaction

Two scopes: Core API (record CRUD) and Metadata API (data model configuration).

### View System
Metadata-driven views (Table, Kanban, Calendar, Widget) with configurable fields, filters, sorts, grouping, and aggregations. Views can be workspace-shared or personal.

### Workflow Engine
Built-in trigger-action engine with 7 trigger types and 12 action types. Supports branching (if/else), iteration, delays, custom JavaScript execution (E2B sandbox), and HTTP requests. Versioned with draft/published states and run monitoring.

### Permission System
Three-level RBAC cascade: All Objects baseline > Object-level overrides > Field-level overrides. Row-level permissions via predicate groups. Roles assignable to users, API keys, and AI agents. SSO (SAML, Google, Microsoft Entra) on Organization plan.

### Email/Calendar Integration
Native sync with Google, Microsoft, and generic IMAP/SMTP/CalDAV providers. Multi-stage sync pipeline with status tracking. Auto-contact creation from email participants and calendar attendees. Visibility controls (metadata only, subject + metadata, full content).

---

## 4. Feature Inventory

| # | Spec | Description |
|---|------|-------------|
| 1 | **pipeline-management** | Kanban pipeline tied to Opportunities with drag-and-drop stage progression, weighted values, and velocity tracking |
| 2 | **pipeline-views** | Metadata-driven view system (Table, Kanban, Calendar) with 10 aggregation operations, nested filters, and grouping |
| 3 | **custom-data-model** | 18 user-facing field types, uniqueness constraints, default values, auto-generated API for custom objects |
| 4 | **custom-objects** | Full custom object lifecycle: creation via GraphQL mutation triggers DB table creation, schema regeneration, and API/view/workflow availability |
| 5 | **data-model** | 27 internal field metadata types including composite types (Address, FullName, Currency), polymorphic targets, and tsvector search |
| 6 | **workflow-automation** | Built-in workflow engine with 7 triggers, 12 actions, branching, iteration, JavaScript execution, and credits system |
| 7 | **business-logic** | Documented flows for deal pipeline, email sync, workflow execution, custom object lifecycle, permission evaluation, and calendar sync |
| 8 | **api-platform** | Dual REST + GraphQL API auto-generated from data model, rate-limited at 100 req/min, with built-in playground |
| 9 | **api-layer** | Three API interfaces (GraphQL, REST, MCP) with auto-generated schema, authentication via API keys/JWT/OAuth |
| 10 | **app-marketplace** | Alpha-stage TypeScript SDK with scaffold tool, npm-based marketplace, React component extensibility, AI skill definitions |
| 11 | **email-calendar-integration** | Deep email/calendar sync architecture: ConnectedAccounts, MessageChannels, CalendarChannels, auto-contact creation, blocklist management |
| 12 | **email-calendar-sync** | Provider support (Google/Microsoft/SMTP/CalDAV), ~400 msg/min sync, visibility levels, folder selection, domain-based company linking |
| 13 | **permissions-rbac** | Three-level cascade RBAC (all > object > field), SSO (SAML/Google/Microsoft Entra), custom roles with API key and AI agent assignment |
| 14 | **rbac-permissions** | Deep-dive into role entities, object/field/row-level permissions, predicate-based filtering, permission flags, and role validation |
| 15 | **dashboard-reporting** | Beta dashboard builder with 6 widget types (bar, pie, line, aggregate, iframe, rich text), tab-based layout |
| 16 | **activity-tracking** | TimelineActivity audit log, polymorphic target linking, notes with rich text (BlockNote), tasks with due dates/status/assignee |
| 17 | **ai-features** | Planned AI chatbot and workflow agents (not yet shipped), AI skills via app SDK, RBAC-scoped AI access |

---

## 5. Key Strengths

1. **Developer experience:** Auto-generated REST + GraphQL + MCP APIs from data model, built-in playground, TypeScript SDK, CLI with schema export. Zero-configuration API for custom objects.

2. **Modern, polished UI:** Clean Notion/Linear-inspired interface with inline table editing, side panel previews, keyboard-first navigation (Ctrl+K command palette), and consistent component patterns across all object types.

3. **Flexible view system:** Table, Kanban, and Calendar views applicable to any object (including custom objects). 10 aggregation operations on kanban columns. Nested filter groups with AND/OR logic. Column footer calculations (count, sum, average, min, max, empty%).

4. **Comprehensive data model:** 27 field types including composite types (Address, FullName, Currency, Emails, Phones). Custom objects are first-class citizens with full API, view, workflow, search, and permission support. Deactivate/reactivate without data loss.

5. **Built-in workflow engine:** 7 trigger types and 12 action types integrated directly into the CRM UI. Visual node-based flow builder. JavaScript code execution in sandboxed environment. Versioning with draft/published states.

6. **Native email/calendar sync:** Deep integration with Gmail, Outlook, IMAP/SMTP, and CalDAV. Auto-contact creation from email participants and calendar attendees. Visibility controls for privacy. ~400 messages/minute sync throughput.

7. **Enterprise RBAC:** Three-level cascade permissions (object > field > row). Predicate-based row-level filtering. AI agents and API keys as first-class role targets. SSO with SAML, Google, and Microsoft Entra.

8. **Self-hosting simplicity:** One-line Docker install, 2GB RAM minimum, PostgreSQL included. Full feature parity with cloud (except SSO on free plan).

9. **Strong community momentum:** ~40,400 GitHub stars, active development cadence, YC backing, enterprise advisors from HubSpot/Front/Pipedrive.

10. **App platform vision:** Alpha SDK for custom objects, React UI components, logic functions, AI skills. npm-based marketplace with scaffold tool and live development sync.

---

## 6. Key Weaknesses

1. **Pipeline is Opportunities-only:** Kanban is tied to a single object's Select field. No generic pipeline engine, no multi-object pipelines, no pipeline templates, no conditional stage transitions, no stage-entry/exit hooks.

2. **Workflow ecosystem is limited:** Only 12 action types compared to n8n's 400+ node types. No conditional branching visible in the visual builder (must use Filter action). Single email recipient. No HTML signatures.

3. **Dashboards are immature (beta):** Only 6 widget types. No table widgets, no gauge charts, no dashboard export, no external sharing. Requires Early Access toggle to activate.

4. **AI features are vaporware:** Chatbot and agents are "planned" and "coming soon" but not shipped. No production AI capability exists today.

5. **No document management:** No file storage, office suite, document collaboration, or content management. CRM exists in isolation from document workflows.

6. **No real-time communication:** No chat, video calling, or messaging. Must rely on external tools for team communication.

7. **SSO requires paid plan:** Organization plan ($19/user/month) needed for SAML/SSO. Self-hosted users on free plan have no SSO option.

8. **No government/compliance features:** No NL Design System support, no Common Ground compliance, no WCAG-focused design, no Dutch language, no government-specific workflows.

9. **Small app ecosystem:** Marketplace is alpha with no review process, no monetization, and npm-based distribution. No quality controls on third-party apps.

10. **Email limitations:** Single recipient per workflow email, no CC option, no HTML signatures, attachments still planned (H1 2026). Calendar sync is read-only (no scheduling from CRM).

11. **No multi-register data isolation:** Workspace-level isolation only. Cannot partition data within a workspace into separate registers with independent access controls.

12. **Rate limits on API:** 100 requests/minute, 60 records per batch. Not suitable for high-throughput integrations without workarounds.

---

## 7. Relevance to Pipelinq

### Direct Competitor Overlap
Twenty and Pipelinq compete directly in the CRM/pipeline management space. Both target organizations wanting self-hosted, open-source alternatives to proprietary CRMs. Twenty's Opportunities + Kanban view is functionally equivalent to Pipelinq's pipeline management, though Twenty's implementation is CRM-specific while Pipelinq aims to be a generic pipeline engine.

### Feature Comparison

| Feature | Twenty | Pipelinq |
|---------|--------|----------|
| Client management (persons) | Yes | Yes |
| Organization management | Yes | Yes |
| Contact persons (linked) | Yes | Yes |
| Lead pipeline (kanban) | Yes (Opportunities-only) | Yes (generic, any object type) |
| Request intake | No | Yes |
| Contact moments logging | Partial (timeline notes) | Yes |
| My Work queue | No | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | Yes (built-in criteria) | Yes |
| Import/Export (CSV/vCard) | Yes (CSV) | Yes |
| Case management integration | No | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | Yes (3-level cascade) | Yes (Nextcloud groups + register-level) |
| Audit trail | Yes (TimelineActivity) | Yes (object versioning) |
| Multi-language | Yes (interface) | Yes (NL Design + i18n) |
| Government compliance | No | Yes (NL Design, Common Ground) |

### Where Twenty Leads
- Auto-generated APIs (REST + GraphQL + MCP) from data model with zero configuration
- Polished kanban view with column aggregations (sum, count, average) and compact mode
- Built-in workflow builder with visual flow editor (no external dependency)
- Command palette (Ctrl+K) for power-user navigation
- Native email/calendar sync with auto-contact creation
- Dashboard builder (beta) with charts and KPI widgets
- Soft-delete with recovery across all objects
- API playground built into settings

### Where Pipelinq Leads
- Generic pipeline engine applicable to any object type (not just Opportunities)
- Conditional stage transitions with validation rules
- Stage-entry/exit hooks with automatic actions
- Pipeline templates for common use cases
- n8n integration with 400+ node types vs 12 workflow actions
- Full Nextcloud ecosystem (files, mail, calendar, chat, video, office suite)
- NL Design System for government theming and WCAG compliance
- OpenRegister's register-level data isolation (multi-tenant partitioning within workspace)
- MCP protocol in production (vs Twenty's planned state for AI)
- SSO/LDAP included free for all self-hosted deployments
- Case management integration via Procest
- Dutch government ecosystem (Common Ground, VNG standards)

---

## 8. Feature Gap Analysis

### Features Pipelinq Should Consider Adopting

| Priority | Feature | Twenty Implementation | Pipelinq Approach |
|----------|---------|----------------------|-------------------|
| High | **Column aggregations on kanban** | Sum/count/avg/min/max per stage column | Add aggregation support to pipeline views via OpenRegister faceting |
| High | **Calculated table footers** | Count, sum, avg, min, max, empty%, not empty% per column | Add calculation row to list views |
| High | **Command palette** | Ctrl+K with context-aware actions, search, navigation shortcuts | Implement Nextcloud-integrated universal search/action palette |
| Medium | **Built-in API playground** | REST + GraphQL interactive builder in settings | Leverage OpenRegister MCP discovery API; add Swagger UI or similar |
| Medium | **Soft-delete with recovery** | "See deleted records" command, recoverable deletion | Add soft-delete to OpenRegister objects with recovery UI |
| Medium | **Side panel preview** | Click to preview in split-view, double-click for full detail | Add side panel component to pipeline/list views |
| Medium | **Inline table editing** | Click any cell to edit directly in the table view | Add inline editing to object list views |
| Medium | **Calendar view** | Date-based record visualization on a calendar layout | Add calendar view type to pipeline views |
| Medium | **Auto-contact creation** | Create Person records from email participants/calendar attendees | Integrate with Nextcloud Contacts to auto-link communications |
| Low | **Custom view sharing** | Workspace-wide vs personal views with access controls | Add view sharing within register permissions |
| Low | **Record navigation arrows** | Prev/next arrows on detail views to browse through records sequentially | Add navigation within object detail views |
| Low | **Favorites system** | Bookmark any record for quick access | Leverage Nextcloud favorites infrastructure |

### Features Where Pipelinq Already Leads

| Feature | Pipelinq Advantage |
|---------|-------------------|
| **Generic pipeline engine** | Not tied to a single object type; any schema can have pipeline stages |
| **n8n automation (400+ nodes)** | Massively larger integration ecosystem than 12 workflow actions |
| **Full collaboration suite** | Files, mail, calendar, chat, video, office suite all in one platform |
| **Government compliance** | NL Design System, Common Ground, WCAG AA, Dutch language |
| **Register-level isolation** | Data partitioning beyond workspace-level multi-tenancy |
| **Production MCP** | AI-native data interaction already working vs planned |
| **Free SSO** | SAML/LDAP included in all self-hosted deployments |
| **Case management** | Procest integration for end-to-end case handling |
| **Mature app ecosystem** | 1000+ Nextcloud apps with review process vs alpha marketplace |

### Strategic Recommendations

1. **Prioritize kanban aggregations:** Twenty's kanban view with stage totals is its most visible differentiator. Adding sum/count/average to Pipelinq's pipeline columns would close the biggest UX gap.

2. **Build a command palette:** Power users expect Ctrl+K navigation. This is a high-impact, relatively low-effort feature that improves perceived polish.

3. **Leverage the platform advantage:** Twenty is a standalone CRM island. Pipelinq should emphasize the integrated Nextcloud experience: a pipeline update triggers a file share, creates a calendar event, and sends a chat notification -- all within one platform.

4. **Don't replicate the workflow builder:** Twenty's built-in workflow editor is polished but limited (12 actions). n8n with 400+ nodes is a stronger proposition. Focus on making the n8n integration seamless rather than building a competing visual editor.

5. **Position against AI vaporware:** Twenty prominently markets AI features that don't exist. Pipelinq's working MCP implementation is a genuine technical advantage -- market it as "AI that works today, not tomorrow."

6. **Target the government gap:** Twenty has zero government/compliance features. Pipelinq's NL Design, Common Ground, and Dutch-language support make it the only viable option for Dutch government CRM needs.
