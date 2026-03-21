# Baserow Competitive Analysis -- Merged Report

**Date:** 2026-03-14
**Sources:** Codebase analysis (GitHub baserow/baserow v2.1.6), live instance walkthrough, 16 feature specs, 24 screenshots, 3 documentation files, 1 overview

---

## 1. Sources Summary

This analysis was compiled from the following materials:

| Type | Count | Contents |
|------|-------|----------|
| **Feature specs** | 16 | architecture, data-modeling, field-types, view-types, formula-system, api-rest, application-builder, automations, real-time-collaboration, permissions-rbac, data-sync, webhooks-integrations, ai-features, mcp-support, templates-snapshots, search-export |
| **Screenshots** | 24 | Signup/onboarding flow (4), grid/gallery/form views (6), field types and filters (2), workspace/admin (4), application builder (3), automation builder (3), API docs (1), health checks (1) |
| **Documentation** | 3 | key-takeaways.md (feature adoption priorities), architecture-diagram.md (system components, data flow, plugin registry), methodology.md |
| **Overview** | 1 | overview.md (key findings, architecture comparison, feature matrix, recommendations) |

All specs are located at `concurrentie-analyse/openregister/baserow/specs/*/spec.md`.

---

## 2. Product Overview

Baserow is an open-source no-code database platform and Airtable alternative. It provides a spreadsheet-like interface for creating databases, building applications without code, and automating workflows.

| Attribute | Value |
|-----------|-------|
| **Repository** | https://github.com/baserow/baserow |
| **Version analyzed** | 2.1.6 |
| **License** | MIT (core), Proprietary (premium/enterprise) |
| **Language** | Python (Django) backend, Vue.js/Nuxt 3 frontend |
| **Database** | PostgreSQL (dynamic tables via DDL) |
| **Deployment** | Docker, Kubernetes, Heroku (standalone) |
| **Field types** | 30+ (Text, Number, Date, Link Row, Formula, File, Select, UUID, Rating, Password, AI, etc.) |
| **View types** | 6 (Grid, Gallery, Form -- open source; Kanban, Calendar, Timeline -- premium) |
| **Codebase scale** | ~270k LOC Python backend core, ~47k premium, ~74k enterprise, ~1,600 Vue/JS/TS frontend files |

**Tier model:** The open-source core provides the grid/gallery/form views, 30+ field types, formula engine, webhooks, API tokens, templates, and real-time collaboration. Premium adds Kanban, Calendar, Timeline views, AI fields, and row comments. Enterprise adds RBAC (6 roles), field-level permissions, teams, SSO (SAML/OAuth), audit logging, and data sync connectors.

---

## 3. Architecture Summary

### System Components

```
                        +------------------+
                        |   Caddy Proxy    |
                        |  (reverse proxy) |
                        +--------+---------+
                                 |
                 +---------------+---------------+
                 |                               |
          +------+------+                +-------+-------+
          |  Nuxt/Vue   |                |    Django      |
          |  Frontend   |                |    Backend     |
          |  (SSR + SPA)|                |  (REST + WS)  |
          +------+------+                +---+---+---+---+
                 |                           |   |   |
                 |                    +------+   |   +------+
                 |                    |          |          |
                 |              +-----+----+ +--+---+ +----+-----+
                 |              |  Django   | |Django| |  Celery  |
                 |              |  REST API | |Chan- | |  Workers |
                 |              | (DRF)     | |nels  | | (async)  |
                 |              +-----+----+ +--+---+ +----+-----+
                 |                    |         |          |
                 |                    +----+----+----+-----+
                 |                         |         |
                 |                   +-----+----+ +--+-----+
                 |                   |PostgreSQL | | Redis  |
                 |                   | (data +   | |(cache +|
                 |                   |  dynamic  | | queue) |
                 |                   |  tables)  | +--------+
                 |                   +----------+
                 |
          +------+------+
          | File Storage |
          | (S3 / local) |
          +--------------+
```

### Monorepo Structure

The codebase follows a monorepo pattern with clear separation between tiers:

- **`backend/`** -- Django REST Framework + Channels. Contains `core/` (workspace, users, templates, snapshots, AI, notifications), `contrib/database/` (tables, fields, views, formula, webhooks, search, export, MCP), `contrib/builder/` (application builder), `contrib/automation/` (workflow engine), and `ws/` (WebSocket consumers).
- **`web-frontend/`** -- Nuxt.js 3 / Vue 3 SPA with modules for database UI, builder UI, and automation UI.
- **`premium/`** -- Paid features: Kanban, Calendar, Timeline views; AI field type; row comments; additional exports.
- **`enterprise/`** -- Advanced features: RBAC with 6 roles, field-level permissions, teams, SSO (SAML/OAuth), audit log, data sync connectors (GitHub, GitLab, Jira, HubSpot).
- **`formula/`** -- ANTLR4 grammar (`BaserowFormula.g4`) for the formula parser.

### Key Architectural Patterns

1. **Plugin/Registry system** -- Field types, view types, element types, automation nodes, integration types, export types, and data sync types are all registered via type registries. This makes the system highly extensible.

2. **Dynamic table generation** -- Each user table creates a real PostgreSQL table (`database_table_{id}`). Fields become real columns via `ALTER TABLE`. This enables native SQL performance for queries, filtering, sorting, and aggregations.

3. **Page-based WebSocket subscriptions** -- Clients subscribe to "pages" (e.g., a specific table or view) rather than individual records. Changes broadcast only to users viewing the affected data.

4. **Polymorphic content types** -- Django's ContentType framework provides polymorphism. A `Field` base model has subclasses like `TextField`, `NumberField`, resolved via `content_type` FK.

### Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend framework | Django 4.x + Django REST Framework |
| Real-time | Django Channels (WebSocket via ASGI) |
| Task queue | Celery + Redis (beat scheduler) |
| Database | PostgreSQL (dynamic table creation per user table) |
| Cache | Redis + django-cachalot |
| Frontend framework | Nuxt.js 3 / Vue 3 |
| Formula parsing | ANTLR4 grammar compiled to Python |
| File storage | Local / S3-compatible |
| Reverse proxy | Caddy (built into all-in-one Docker) |
| Telemetry | OpenTelemetry + Sentry |
| AI providers | OpenAI, Anthropic, Mistral, Ollama, OpenRouter |

---

## 4. Feature Inventory

| # | Spec | Key Capabilities | Tier |
|---|------|-----------------|------|
| 1 | **Architecture** | Django monorepo, plugin registry, dynamic table generation, polymorphic content types, hierarchical permissions | Core |
| 2 | **Data Modeling** | Workspace > Database > Table > Field/Row hierarchy, dynamic PostgreSQL tables, M2M link rows, row history with field-level diffs | Core |
| 3 | **Field Types** | 30+ types: Text, Long Text, Number, Rating, Boolean, Date, Duration, URL, Email, Phone, Link Row, File, Single/Multiple Select, Multiple Collaborators, Formula, Count, Rollup, Lookup, UUID, Autonumber, Password, Created On/By, Last Modified On/By. Premium: AI field | Core + Premium |
| 4 | **View Types** | Grid (spreadsheet), Gallery (cards), Form (data entry) -- open source. Kanban (drag-and-drop board), Calendar, Timeline (Gantt-like) -- premium. All views have independent filters, sorts, grouping, field visibility, public sharing | Core + Premium |
| 5 | **Formula System** | ANTLR4-based grammar, AST representation, type inference, SQL expression generation (database-level execution), dependency tracking, circular dependency detection. Types: Formula, Count, Rollup, Lookup | Core |
| 6 | **REST API** | DRF with auto-generated OpenAPI docs, 40+ filter types, multi-field sorting, limit/offset + page pagination, full-text search, field name modes, batch CRUD operations, JWT + database token auth | Core |
| 7 | **Application Builder** | No-code app builder with 20+ elements (headings, text, images, tables, forms, containers, menus), data sources, 9 workflow action types (CRUD, navigation, notification, AI, Slack), page routing with URL params, custom domain publishing, theming | Core |
| 8 | **Automations** | Built-in workflow engine: 5 trigger types (row create/update/delete, HTTP, periodic), 12 action types (CRUD, HTTP request, email, Slack, AI agent, iterator, router). Celery-based execution with history tracking | Core |
| 9 | **Real-Time Collaboration** | Django Channels WebSocket, page-based subscriptions, live row/field/view change events, row history with before/after values, undo/redo via action tracking. Premium: per-row comment threads | Core + Premium |
| 10 | **Permissions & RBAC** | Open source: workspace Admin/Member roles, per-table API token permissions. Enterprise: 6 roles (Admin, Builder, Editor, Commenter, Viewer, No Access), field-level permissions, teams, SSO (SAML/OAuth), audit log | Core + Enterprise |
| 11 | **Data Sync** | Import and sync from external sources. Open source: PostgreSQL, iCal. Enterprise: GitHub Issues, GitLab Issues, Jira Issues, HubSpot Contacts, Local Baserow Table. Periodic sync via Celery beat, synced fields are read-only | Core + Enterprise |
| 12 | **Webhooks & Integrations** | Per-table webhooks (rows.created/updated/deleted), configurable HTTP method/headers, auto-deactivation after failures, call history. Integrations: SMTP, Slack, AI providers, LocalBaserow | Core |
| 13 | **AI Features** | 5 providers (OpenAI, Anthropic, Mistral, Ollama, OpenRouter). AI field type (premium) generates content from row data. AI automation nodes and builder workflow actions | Premium |
| 14 | **MCP Support** | Early implementation (~406 LOC). Basic table listing and row CRUD tools. Not yet a comprehensive MCP server | Core (alpha) |
| 15 | **Templates & Snapshots** | Pre-built database templates (CRM, project management, etc.) with one-click install. Point-in-time application snapshots. Airtable import. Application export/import | Core |
| 16 | **Search & Export** | PostgreSQL full-text search via tsvector columns, background index updates, cross-field search. CSV export (core), JSON/Excel export (premium). View-scoped export with filters/sorts applied | Core + Premium |

---

## 5. Key Strengths

### 5.1 Formula Engine with ANTLR4 Grammar

The formula system is Baserow's strongest technical differentiator. It uses a formal ANTLR4 grammar (`BaserowFormula.g4`) that compiles to an AST, runs type inference, and generates PostgreSQL expressions. Formulas execute at the database level -- no Python evaluation per row. This enables spreadsheet-like computed columns (Formula, Count, Rollup, Lookup) with native SQL performance. Dependency tracking automatically recalculates downstream fields when source data changes.

### 5.2 Six View Types

Baserow offers the richest view system among open-source database tools:
- **Grid** -- Full spreadsheet with column aggregations, grouping, row height, public sharing
- **Gallery** -- Card-based layout with cover images, ideal for catalogs
- **Form** -- Data entry with conditional visibility, validation, public submission
- **Kanban** (premium) -- Drag-and-drop board organized by single select field
- **Calendar** (premium) -- Date-based event layout with month/week/day views
- **Timeline** (premium) -- Gantt-like horizontal timeline with start/end dates

All views share a common infrastructure: independent filters (40+ types with AND/OR groups), multi-field sorting, field visibility, decorators, and public sharing with optional password protection.

### 5.3 Application Builder

A full no-code application builder transforms Baserow from a database tool into an application platform. Users can build multi-page web applications with 20+ UI elements (headings, text, images, tables, forms, containers, menus), connect to Baserow tables as data sources, define workflow actions (CRUD, navigation, notification, AI, Slack), and publish to custom domains. This is unique among open-source database platforms.

### 5.4 Real-Time Collaboration

Django Channels provides true multi-user real-time editing via WebSocket. The page-based subscription model is efficient -- clients only receive events for data they are currently viewing. Row history tracks field-level changes with before/after values and supports undo/redo via action tracking.

### 5.5 Data Sync

Seven external data source connectors (PostgreSQL, iCal, GitHub, GitLab, Jira, HubSpot, local table) allow Baserow to pull in and periodically refresh data from external systems. Synced fields remain read-only (source of truth is external), while users can add additional local fields for annotations.

### 5.6 Templates and Onboarding

Pre-built database templates for common use cases (CRM, project management, content calendar) combined with an excellent onboarding flow (use case selection, field type guidance, welcome tour) significantly lower the barrier to entry.

---

## 6. Key Weaknesses

### 6.1 Heavy Deployment Requirements

Baserow requires PostgreSQL, Redis, Celery workers, and Caddy reverse proxy as minimum infrastructure. The all-in-one Docker image bundles these but is resource-intensive. Kubernetes deployment adds further complexity. This contrasts sharply with a Nextcloud app that installs with one click into an existing platform.

### 6.2 No Government Ecosystem Integration

Baserow has zero integration with the Dutch government ecosystem:
- No NL Design System support (no CSS custom property theming, no government component library)
- No NLGov API compliance
- No WOO, ZGW, GEMMA, or Archiefwet alignment
- No Dutch government procurement or compliance certifications

### 6.3 No Nextcloud Integration

As a standalone application, Baserow has no integration with Nextcloud's file management, sharing model, user/group system, notifications, or app ecosystem. Organizations already running Nextcloud would need to deploy and maintain a separate platform.

### 6.4 Vendor Lock-in Risk

Critical features are gated behind paid licenses:
- **Premium:** Kanban, Calendar, Timeline views; AI field; row comments; JSON/Excel export
- **Enterprise:** RBAC (6 roles), field-level permissions, teams, SSO, audit log, 5 data sync connectors

Organizations starting with the open-source tier may find themselves locked into paid plans as needs grow.

### 6.5 Schema Approach Limitations

The dynamic DDL approach (ALTER TABLE for each field) is powerful for performance but less flexible than JSON Schema for complex validation rules, nested data structures, schema references ($ref), and standards-based interoperability. Schema evolution requires PostgreSQL column migrations.

### 6.6 Early MCP Implementation

At ~406 lines, Baserow's MCP support is minimal -- basic table listing and row CRUD only. This is far behind OpenRegister's production-ready MCP server with full CRUD for registers, schemas, and objects over JSON-RPC 2.0 streamable HTTP transport.

---

## 7. Relevance to OpenRegister

### 7.1 Competitive Positioning

**Baserow is NOT a direct threat to OpenRegister in the Dutch government market** for five reasons:

1. No Nextcloud integration -- the primary deployment platform for Dutch government IT
2. No NL Design System support -- mandatory for government-facing applications
3. No NLGov API compliance or Dutch regulatory alignment
4. Heavier deployment requirements vs. a Nextcloud app install
5. Premium/enterprise feature gating conflicts with government open-source preferences

The target users and use cases differ significantly. Baserow serves business users building internal tools and no-code applications. OpenRegister serves government data management with standards compliance, registry/catalog patterns, and Nextcloud ecosystem integration.

### 7.2 Inspiration Source

Baserow is an excellent source of design patterns and feature ideas:

| Priority | Feature | What to Adopt |
|----------|---------|---------------|
| **High** | Formula / computed fields | Simplified expression system for JSON Schema properties: arithmetic, string ops, date calculations, conditionals, aggregate lookups |
| **Medium** | Additional view types | Gallery view (card-based catalog browsing), Form view (structured data entry from schema), Kanban (status-based workflow boards) |
| **Medium** | Data export | CSV/JSON export with view filter/sort applied |
| **Medium** | Webhooks | Per-register/schema event webhooks (object.created/updated/deleted) to reduce n8n dependency for simple integrations |
| **Low** | Row-level history | Object-level change history with field-level before/after values |
| **Low** | Templates | Pre-built register/schema templates for Dutch government use cases |

### 7.3 Architectural Patterns Worth Studying

- **Registry/plugin pattern** -- Formalized `register()`/`get()` for field types, view types, element types. Similar to OpenRegister's approach but more structured.
- **Dynamic model generation** -- Runtime model construction from schema definitions (useful conceptual parallel to OpenRegister's JSON Schema approach).
- **WebSocket page subscriptions** -- Subscribe to specific resources rather than individual records for efficient real-time updates.

### 7.4 OpenRegister's Differentiators to Preserve

These advantages should be actively maintained:

1. **Nextcloud integration** -- Native embedding in the ecosystem (files, sharing, users, apps)
2. **NL Design System** -- Government theming with CSS custom properties
3. **JSON Schema** -- Standards-based data modeling with complex validation, nested structures, and schema references
4. **Mature MCP** -- Production-ready MCP server (full CRUD, JSON-RPC 2.0, streamable HTTP)
5. **Government focus** -- NLGov API compliance, faceted catalog search, registry management patterns
6. **Lightweight deployment** -- Single Nextcloud app vs. multi-service stack
7. **Faceted search** -- Purpose-built catalog browsing with configurable facets

---

## 8. Feature Gap Analysis

### Table 1: Baserow Features vs. OpenRegister

| Feature | Baserow | OpenRegister | Gap Severity |
|---------|---------|-------------|-------------|
| Grid/table view | Yes | Yes | None |
| Gallery view | Yes (open source) | No | Medium |
| Form view | Yes (open source) | No | Medium |
| Kanban view | Premium | No | Low |
| Calendar view | Premium | No | Low |
| Timeline view | Premium | No | Low |
| 30+ field types | Yes (native PG columns) | ~15 via JSON Schema | Low -- JSON Schema is more flexible |
| Relational links | M2M through tables | JSON $ref references | Low -- different approach, both work |
| Formula fields | ANTLR4 + SQL execution | N/A | High |
| Computed fields (Count/Rollup/Lookup) | Yes | N/A | Medium |
| Application builder | 20+ elements, custom domains | N/A | Low -- different product scope |
| Automations | Built-in (12 action types) | Via n8n ExApp (500+ nodes) | None -- n8n is more capable |
| Webhooks | Per-table, multi-event | N/A | Medium |
| Real-time collaboration | WebSocket, live updates | None (polling) | Medium |
| Row comments | Premium | N/A | Low |
| Row/field history | Field-level before/after | Basic audit log | Medium |
| API tokens | Per-table CRUD granularity | Per-register access | Low |
| Data sync | 7 external sources | Source-based import | Low |
| RBAC | 6 roles (enterprise) | Nextcloud groups | Low -- Nextcloud model is adequate |
| Field permissions | Enterprise | N/A | Low |
| SSO | SAML/OAuth (enterprise) | Nextcloud SSO | None |
| Audit log | Enterprise | Nextcloud activity | Low |
| Snapshots | Yes | N/A | Low |
| Templates | Yes | N/A | Low |
| Airtable import | Yes | N/A | None -- not relevant |
| AI fields | Premium (5 providers) | N/A | Low |
| MCP server | Early (~400 LOC) | Production (full CRUD) | None -- OpenRegister leads |
| Full-text search | PostgreSQL tsvector | JSON search + faceting | Low -- different strengths |
| Data export | CSV/JSON/Excel | N/A | Medium |
| NL Design System | No | Yes | None -- OpenRegister leads |
| Nextcloud integration | No | Native | None -- OpenRegister leads |
| Government compliance | No | NLGov API, WOO-ready | None -- OpenRegister leads |

### Table 2: OpenRegister Features Missing in Baserow

| Feature | OpenRegister | Baserow | Significance |
|---------|-------------|---------|-------------|
| Nextcloud ecosystem | Native app (files, sharing, users, notifications) | Standalone platform | High -- deployment advantage |
| NL Design System | Full CSS custom property theming | No government theming | High -- Dutch market requirement |
| JSON Schema validation | Standards-based with $ref, nested objects, complex rules | Proprietary field system | High -- interoperability |
| Production MCP server | Full CRUD, JSON-RPC 2.0, streamable HTTP | ~400 LOC alpha | High -- AI integration readiness |
| Faceted catalog search | Configurable facets for registry browsing | Basic full-text only | Medium -- catalog use case |
| NLGov API compliance | REST API following NLGov standards | Non-compliant | High -- government procurement |
| Register/catalog patterns | Purpose-built registry management | General-purpose database | Medium -- domain specificity |
| Lightweight deployment | Single Nextcloud app install | 4+ services minimum | Medium -- operational simplicity |
| Source-based data import | Import with provenance tracking | Sync without provenance | Low |
| OAS/Archimate export | Standards-based schema export | N/A | Low -- niche requirement |
