# PocketBase Competitive Analysis -- Merged Report

**Date:** 2026-03-14
**Sources:** Codebase analysis (GitHub pocketbase/pocketbase v0.25.9), overview.md, docs/architecture.md, docs/api-and-sdk.md, 12 feature specs

---

## 1. Sources Summary

| Source | Type | Key Content |
|--------|------|-------------|
| `overview.md` | High-level analysis | Product positioning, strengths/weaknesses vs OpenRegister, key takeaways |
| `docs/architecture.md` | Technical deep-dive | Single binary design, package structure, `_collections` schema, hook system, comparison table |
| `docs/api-and-sdk.md` | API documentation | REST endpoint patterns, query parameters, batch API, auth API, realtime API, SDK examples |
| `specs/collections-system/` | Feature spec | Three collection types (Base/Auth/View), 13 field types, JSON field definitions, export/import |
| `specs/auto-generated-api/` | Feature spec | CRUD generation, filter syntax, relation expansion, batch/upsert, API Preview panel |
| `specs/realtime-subscriptions/` | Feature spec | SSE protocol, collection/record subscriptions, auth-aware, 5-min idle timeout |
| `specs/auth-system/` | Feature spec | Email/password, OAuth2 (20+ providers), OTP, MFA, impersonation, JWT tokens |
| `specs/hooks-extensibility/` | Feature spec | Go hooks (compile-time), JS hooks (runtime via goja), priority-based events, cron, custom routes |
| `specs/admin-dashboard/` | Feature spec | Svelte SPA at `/_/`, collection management, record CRUD, API Preview, logs, settings |
| `specs/backup-restore/` | Feature spec | One-click backup, S3 storage, scheduled backups, restore from admin UI |
| `specs/batch-api/` | Feature spec | Transactional multi-operation requests, atomic rollback, PUT upsert |
| `specs/file-storage/` | Feature spec | File field type, thumbnails, S3 support, protected files with tokens |
| `specs/migrations-system/` | Feature spec | Up/down migrations, auto-generation from UI changes, Go and JS formats |
| `specs/search-filtering/` | Feature spec | Custom filter expression language, relation traversal, SQL compilation |
| `specs/view-collections/` | Feature spec | SQL view-backed read-only collections, cross-collection joins |

---

## 2. Product Overview

PocketBase is an open-source backend-as-a-service (BaaS) written in Go that compiles to a **single binary (~30MB)** with **zero external dependencies**. It embeds everything needed for a complete backend: an SQLite database, REST API server, admin dashboard, file storage, realtime engine, auth system, and cron scheduler.

| Property | Value |
|----------|-------|
| **License** | MIT |
| **Language** | Go 1.24+ (backend), Svelte (admin UI) |
| **Database** | SQLite (via modernc.org/sqlite, pure Go) |
| **Version analyzed** | v0.25.9 |
| **Codebase size** | 262 Go files |
| **Repository** | https://github.com/pocketbase/pocketbase |
| **Deployment** | Download binary and run -- no runtime, no container, no database server |
| **Target audience** | Indie developers, prototypers, small-to-medium apps needing a quick backend |

The core philosophy is radical simplicity: create a collection in the admin UI, and a full CRUD REST API with auth, realtime, and file handling is immediately available. The project is a single developer's creation (Gani Georgiev) and has gained significant traction in the developer community as a Firebase/Supabase alternative for self-hosted use.

---

## 3. Architecture Summary

### Single Binary Architecture

PocketBase's defining characteristic is embedding the entire stack into one Go executable:

```
pocketbase binary (~30MB)
  |-- SQLite engine (modernc.org/sqlite, pure Go -- no CGO)
  |-- HTTP router (custom, tools/router/)
  |-- REST API handlers (apis/)
  |-- Svelte admin UI (compiled SPA, embedded via go:embed)
  |-- JavaScript runtime (goja engine for hooks)
  |-- File storage abstraction (local filesystem or S3)
  |-- SSE realtime broker
  |-- Cron scheduler
  |-- SMTP mail client
```

### Package Structure

```
pocketbase/
  core/           # Core app interface, models, DB operations (72 Go files)
  apis/           # REST API handlers (CRUD, auth, realtime, files, batch)
  forms/          # Form validation
  migrations/     # Database migration system
  plugins/
    jsvm/         # JavaScript VM for runtime hooks
    migratecmd/   # Auto-migration generation
    ghupdate/     # GitHub auto-update
  tools/          # Utilities (auth, cron, filesystem, search, router)
  ui/             # Svelte admin dashboard (embedded)
```

### Data Model

PocketBase uses a **meta-driven** approach. Collection definitions are stored in a `_collections` table with field definitions as JSON:

```sql
CREATE TABLE _collections (
  id TEXT PRIMARY KEY, system BOOLEAN, type TEXT,  -- base, auth, view
  name TEXT UNIQUE, fields JSON, indexes JSON,
  listRule TEXT, viewRule TEXT, createRule TEXT,
  updateRule TEXT, deleteRule TEXT, options JSON,
  created TEXT, updated TEXT
);
```

Each collection gets its own SQLite table with columns matching its field definitions. The three collection types are:
- **Base** -- standard data tables with CRUD API
- **Auth** -- user account tables with authentication endpoints
- **View** -- read-only SQL views joining one or more collections

### Hook/Event System

A priority-based event system fires at lifecycle points (Validate, Create, CreateExecute, AfterCreateSuccess, AfterCreateError, and Update/Delete equivalents). Handlers chain via `e.Next()`:

```go
app.OnRecordCreate("products").Bind(&hook.Handler[*RecordEvent]{
    Func: func(e *RecordEvent) error { /* logic */ return e.Next() },
    Priority: 0,
})
```

### Comparison with OpenRegister

| Aspect | PocketBase | OpenRegister |
|--------|-----------|--------------|
| Runtime | Single Go binary | PHP on Nextcloud + Apache |
| Database | Embedded SQLite | MySQL/PostgreSQL via Nextcloud |
| Schema storage | `_collections` JSON fields | Schema entities in DB |
| API generation | Automatic per collection | Automatic per register/schema |
| Admin UI | Embedded Svelte SPA | Nextcloud Vue app |
| Extensions | Go/JS hooks | PHP services + n8n workflows |
| Multi-tenancy | None (single DB) | Via Nextcloud users/groups |
| Deployment | Download and run | Nextcloud app install |
| Search | SQLite filter expressions | Solr/Elasticsearch with faceting |
| File storage | Built-in (local/S3) | Nextcloud file management |
| Standards | None | JSON Schema, OAS, ZGW |

---

## 4. Feature Inventory

| # | Feature | Category | Description | Maturity | Relevance to OpenRegister |
|---|---------|----------|-------------|----------|--------------------------|
| 1 | **Collections System** | Data Model | Three collection types (Base/Auth/View), 13 field types, JSON field definitions, export/import | High | Direct competitor to OpenRegister's schema/register model; simpler but less hierarchical |
| 2 | **Auto-Generated API** | API | Full CRUD REST endpoints per collection with pagination, sorting, filtering, expansion, upsert | High | OpenRegister has similar capability; PocketBase's API Preview with SDK samples is superior |
| 3 | **Auth System** | Security | Email/password, OAuth2 (20+ providers), OTP, MFA, impersonation, JWT tokens, rate limiting | High | OpenRegister delegates to Nextcloud; PocketBase offers more granular per-collection auth |
| 4 | **Realtime Subscriptions** | Data Sync | SSE-based push for collection/record changes, auth-aware, 5-min timeout with auto-reconnect | High | OpenRegister lacks realtime; this is a feature gap worth addressing |
| 5 | **Admin Dashboard** | UI | Svelte SPA with collection management, record CRUD, API Preview, logs, settings | High | OpenRegister uses Nextcloud UI; PocketBase's standalone dashboard is more focused |
| 6 | **Hooks & Extensibility** | Extensibility | Go hooks (compile-time) + JS hooks (runtime via goja), cron, custom routes, hot reload | High | OpenRegister uses PHP services + n8n; PocketBase is lighter but less powerful |
| 7 | **Search & Filtering** | Query | Custom filter expression language with operators, relation traversal, SQL compilation | Medium | OpenRegister's Solr/Elasticsearch is far more capable for faceting and relevance |
| 8 | **File Storage** | Files | File field type, thumbnails, local/S3, protected files with tokens, MIME restrictions | Medium | OpenRegister leverages Nextcloud's mature file system with versioning and sharing |
| 9 | **Batch API** | API | Transactional multi-operation requests, atomic rollback, PUT upsert | Medium | OpenRegister lacks batch API; worth adopting for bulk operations |
| 10 | **View Collections** | Data Model | SQL view-backed read-only collections, cross-collection joins | Medium | Could inspire first-class view support in OpenRegister |
| 11 | **Migrations System** | DevOps | Up/down migrations, auto-generation from UI changes, Go/JS formats | Medium | OpenRegister uses Nextcloud migrations; auto-generation from UI changes is a nice DX feature |
| 12 | **Backup & Restore** | Operations | One-click backup, S3 storage, scheduled backups, restore from admin UI | Medium | OpenRegister relies on Nextcloud/database-level backups; integrated UI is more convenient |

---

## 5. Key Strengths

### 5.1 Radical Simplicity -- Single Binary, Zero Dependencies
PocketBase's single binary deployment is its killer feature. Download one file, run it, and you have a complete backend with database, API, admin UI, auth, and realtime. No Docker, no package managers, no database servers. This makes it trivially easy to deploy on any platform (Linux, macOS, Windows, ARM). The ~30MB binary starts in under a second.

### 5.2 Instant API Generation
Creating a collection in the admin UI immediately generates full CRUD REST endpoints with pagination, sorting, filtering, relation expansion, field selection, and batch operations. The API Preview panel in the admin dashboard is a standout feature: it shows interactive documentation with ready-to-use JavaScript and Dart SDK code samples for every operation, including the current collection's access rules.

### 5.3 Realtime via SSE
PocketBase provides realtime data subscriptions out of the box using Server-Sent Events (SSE) rather than WebSockets. SSE is simpler, works through proxies/CDNs, and supports automatic reconnection. Clients subscribe to entire collections or individual records and receive push notifications for create, update, and delete operations. Subscriptions respect auth rules.

### 5.4 Built-in Auth with 20+ OAuth2 Providers
Any Auth-type collection automatically gets a full set of authentication endpoints: email/password, OAuth2 (Google, GitHub, Apple, Microsoft, Discord, and 15+ more), OTP via email, and MFA. This means multiple user types (customers, staff, admins) can each have their own auth configuration. JWT tokens, rate limiting, impersonation, and auth alerts are all included.

### 5.5 Polished Svelte Admin Dashboard
The embedded admin UI served at `/_/` is surprisingly full-featured for a single-developer project: visual field type picker (13+ types), inline record editing with rich text editor, API rules expression builder, log viewer with charts, backup management, and settings configuration. It communicates purely through the PocketBase API, making it a reference implementation for any client.

### 5.6 Performance
SQLite combined with Go's compilation and concurrency model yields sub-millisecond query times for read operations. The `skipTotal` pagination option avoids expensive COUNT queries. The singleflight pattern prevents duplicate thumbnail generation under concurrent requests.

---

## 6. Key Weaknesses

### 6.1 No Multi-Tenancy
PocketBase runs one database per instance. There is no concept of organizations, workspaces, or tenant isolation. Serving multiple tenants requires running multiple PocketBase instances, each with its own binary and data directory. This is a fundamental limitation for any platform scenario.

### 6.2 SQLite Single-Writer Bottleneck
SQLite allows only one writer at a time. While reads are concurrent and fast, write-heavy workloads hit a serialization bottleneck. PocketBase mitigates this with WAL mode, but it cannot scale writes horizontally. There is no path to PostgreSQL or MySQL -- SQLite is baked into the architecture.

### 6.3 No Workflow Engine or Business Logic Layer
PocketBase has hooks (Go and JS) but no visual workflow builder, no state machines, no approval flows, and no integration engine. Business logic requires writing code. There is nothing comparable to n8n, Activiti, or Camunda. For anything beyond simple CRUD with validation, developers must build from scratch.

### 6.4 No Government or Compliance Standards
PocketBase has no support for:
- JSON Schema validation or OAS export
- NL Design System or WCAG compliance
- Dutch/EU government standards (ZGW, GEMMA, WOO, Archiefwet)
- DPIA, privacy-by-design, or data classification
- Audit trails beyond request logs
- Data sovereignty or hosting requirements

### 6.5 No Federation or Data Harvesting
PocketBase is a standalone data silo. There is no data synchronization, federation protocol, source harvesting, or inter-instance communication. Each instance is isolated.

### 6.6 Limited Search Capabilities
Search is SQLite-based with a custom filter expression language. There is no full-text search engine (Solr/Elasticsearch), no faceted search, no relevance scoring, no stemming/synonyms, and no configurable analyzers. The search works well for structured queries but is inadequate for discovery-oriented use cases.

### 6.7 No Schema Validation Standards
Fields are defined as JSON arrays with custom type identifiers. There is no JSON Schema support, no schema versioning, no schema inheritance, and no cross-collection validation rules. The schema model is pragmatic but non-standard.

### 6.8 Single Developer Risk
PocketBase is primarily maintained by one developer. While the MIT license and Go codebase make it forkable, bus factor of one is a real concern for production deployments.

---

## 7. Relevance to OpenRegister

PocketBase occupies the **"developer simplicity"** end of the backend spectrum, while OpenRegister targets the **"government platform"** end. They share a core concept -- meta-driven schema definitions that auto-generate REST APIs -- but diverge sharply in scope, standards, and deployment model.

### What PocketBase does better than OpenRegister

1. **Developer onboarding** -- zero-to-working-API in under 60 seconds, single binary, no prerequisites
2. **API documentation** -- the API Preview panel with live SDK code samples is significantly better than OpenRegister's current API documentation
3. **Realtime** -- built-in SSE subscriptions with auth awareness; OpenRegister has no realtime capability
4. **Per-collection access rules** -- filter expression-based rules are more flexible than Nextcloud's group-based permissions for API access
5. **Batch operations** -- transactional multi-record operations in a single request; OpenRegister lacks this
6. **Collection export/import** -- JSON-based schema portability between environments; OpenRegister's migration story is more complex

### What OpenRegister does better than PocketBase

1. **Platform integration** -- Nextcloud ecosystem (users, groups, files, sharing, apps, LDAP, SSO)
2. **Multi-tenancy** -- Nextcloud's user/group model provides natural tenant isolation
3. **Search** -- Solr/Elasticsearch integration with faceting, relevance, and full-text capabilities
4. **Standards compliance** -- JSON Schema validation, OAS export, ZGW/GEMMA alignment
5. **Workflow engine** -- n8n integration for visual business logic and integrations
6. **Data hierarchy** -- register/schema model with JSON Schema validation vs. flat collections
7. **Government readiness** -- NL Design System, WCAG, Dutch/EU compliance standards
8. **Federation** -- data source harvesting and cross-instance data sharing
9. **File management** -- Nextcloud's file versioning, sharing, and collaboration features
10. **Enterprise infrastructure** -- MySQL/PostgreSQL horizontal scaling, clustering, backup strategies

### Lessons to adopt

| PocketBase Feature | OpenRegister Opportunity | Priority |
|--------------------|------------------------|----------|
| API Preview with SDK samples | Add interactive API docs to MCP discovery endpoint | High |
| Realtime SSE subscriptions | Add SSE endpoint for register/schema object changes | High |
| Batch API | Add transactional batch endpoint for bulk operations | Medium |
| Per-collection filter rules | Enhance API access rules beyond Nextcloud groups | Medium |
| Collection export/import | Simplify schema/register portability as JSON bundles | Medium |
| View collections | Add first-class computed view support | Low |
| Auto-migration generation | Generate migration code from UI schema changes | Low |

---

## 8. Feature Gap Analysis

This section maps PocketBase's features against OpenRegister's current capabilities to identify gaps and opportunities.

| Feature Area | PocketBase | OpenRegister | Gap? | Action |
|-------------|-----------|-------------|------|--------|
| **Schema definition** | JSON field array, 13 types | JSON Schema, arbitrary properties | No gap | OpenRegister is more standards-compliant |
| **API generation** | Automatic CRUD per collection | Automatic CRUD per register/schema | No gap | Parity; PocketBase has better docs |
| **API documentation** | Interactive API Preview with SDK code | MCP discovery endpoint | **Gap** | Add interactive API docs |
| **Realtime** | SSE subscriptions (collection/record) | None | **Gap** | Implement SSE for object changes |
| **Batch operations** | Transactional batch API | None | **Gap** | Add batch endpoint |
| **Authentication** | Built-in multi-method auth | Delegated to Nextcloud | No gap | Nextcloud auth is more enterprise-ready |
| **Authorization** | Filter expression rules per collection | Nextcloud groups + register permissions | **Partial gap** | Per-schema filter rules could add flexibility |
| **Search** | SQLite filter expressions | Solr/Elasticsearch with faceting | No gap | OpenRegister is superior |
| **File storage** | Built-in (local/S3) with thumbnails | Nextcloud file management | No gap | OpenRegister is superior (versioning, sharing) |
| **Admin UI** | Dedicated Svelte SPA | Nextcloud Vue app | No gap | Different approach, both functional |
| **Realtime admin** | SSE-powered live record updates | Manual refresh | **Gap** | Add live UI updates via SSE |
| **Multi-tenancy** | None | Nextcloud users/groups | No gap | OpenRegister is superior |
| **Workflow/BPM** | None (hooks only) | n8n integration | No gap | OpenRegister is superior |
| **Schema portability** | JSON export/import | Migration-based | **Partial gap** | Add JSON bundle export/import |
| **View/computed data** | View collections (SQL views) | Implicit via queries | **Partial gap** | Consider first-class view support |
| **Backup** | Integrated UI + S3 + scheduling | Database-level + Nextcloud | No gap | Different approach; OpenRegister inherits Nextcloud backup |
| **Standards** | None | JSON Schema, OAS, ZGW | No gap | OpenRegister is superior |
| **Federation** | None | Data source harvesting | No gap | OpenRegister is superior |
| **Migrations** | Auto-generated from UI changes | Nextcloud IRepairStep | **Partial gap** | Auto-migration from schema changes would improve DX |

### Summary of Actionable Gaps

**Must address (high value, proven by PocketBase's adoption):**
1. **Realtime SSE** -- Object change notifications via Server-Sent Events
2. **Interactive API documentation** -- Upgrade MCP discovery to include code samples and live testing

**Should address (medium value):**
3. **Batch API** -- Transactional multi-object operations
4. **Schema portability** -- JSON bundle export/import for registers + schemas + sample data
5. **Granular access rules** -- Filter expression-based API authorization per schema

**Nice to have (low value):**
6. **View support** -- First-class computed/aggregated views across schemas
7. **Auto-migration** -- Generate migration code when schemas change via UI
