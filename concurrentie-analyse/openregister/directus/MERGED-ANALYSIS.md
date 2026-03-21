# Directus -- Merged Competitive Analysis

**Competitor:** Directus (https://directus.io)
**Repository:** https://github.com/directus/directus (29k+ stars)
**Version Analyzed:** Directus 11.x (latest main branch, monorepo)
**License:** BSL 1.1 (Business Source License) -- converts to GPLv3 after 3 years
**Analysis Date:** 2026-03-14
**Analysis Scope:** 22 spec files, 50 screenshots, 12 documentation files, 1 business-logic flow diagram

---

## 1. Sources Summary

| File | Type | Topic |
|------|------|-------|
| `overview.md` | Overview | Executive summary, tech stack, feature comparison matrix |
| `directus.md` | Overview | High-level competitor card with pricing and feature comparison |
| `business-logic/data-flow.md` | Business Logic | Mermaid flow diagrams for request processing, permissions, Flows, extensions, files, WebSocket, versioning |
| `docs/00-summary.md` | Documentation | Analysis summary with gap assessment and priority ranking |
| `docs/01-overview.md` | Documentation | Platform overview, cloud tiers, pricing details |
| `docs/02-data-model.md` | Documentation | Collections, fields, relationships |
| `docs/03-api-reference.md` | Documentation | REST, GraphQL, filtering, query parameters |
| `docs/04-access-control.md` | Documentation | Users, roles, policies, permissions |
| `docs/05-flows-automation.md` | Documentation | Triggers, operations, data chain |
| `docs/06-extensions-marketplace.md` | Documentation | Extension types, marketplace, SDK |
| `docs/07-realtime.md` | Documentation | WebSockets, subscriptions |
| `docs/08-insights-dashboards.md` | Documentation | Dashboards, panels |
| `docs/09-files-assets.md` | Documentation | File upload, image transforms |
| `docs/10-authentication-sso.md` | Documentation | SSO, OAuth2, LDAP, SAML |
| `docs/11-ai-features.md` | Documentation | AI Assistant, MCP server |
| `specs/architecture/spec.md` | Spec | Monorepo structure, backend/frontend architecture, key dependencies |
| `specs/data-modeling/spec.md` | Spec | Collections, 24 field types, 7 relationship types, JSON support |
| `specs/rest-api/spec.md` | Spec | Endpoint structure, 30+ filter operators, aggregation, deep queries |
| `specs/graphql-api/spec.md` | Spec | Auto-generated GraphQL with subscriptions |
| `specs/access-control/spec.md` | Spec | ABAC system: roles, policies, row/field-level security |
| `specs/flows-automation/spec.md` | Spec | 5 trigger types, 15 built-in operations, visual editor |
| `specs/extensions/spec.md` | Spec | 10 extension types, sandboxed execution, 43 interfaces |
| `specs/content-versioning/spec.md` | Spec | Named versions, delta storage, promote workflow |
| `specs/realtime-websockets/spec.md` | Spec | WebSocket controllers, REST WS, GraphQL WS |
| `specs/insights-dashboards/spec.md` | Spec | Dashboard builder, 12 panel types |
| `specs/file-management/spec.md` | Spec | Multi-driver storage, TUS uploads, metadata extraction |
| `specs/image-transformations/spec.md` | Spec | Sharp-based on-the-fly transforms, caching |
| `specs/row-level-security/spec.md` | Spec | Filter-based permissions, dynamic variables |
| `specs/marketplace-extensions/spec.md` | Spec | npm-backed registry, 274+ extensions |
| `specs/ai-assistant/spec.md` | Spec | Built-in AI chat, configurable providers |
| `specs/mcp-ai-integration/spec.md` | Spec | MCP server, AI-ready data platform |
| `specs/activity-revisions/spec.md` | Spec | Audit trail, revision history, data snapshots |
| `specs/notifications/spec.md` | Spec | In-app notification system |
| `specs/sharing/spec.md` | Spec | Share links for external collaboration |
| `specs/translations-i18n/spec.md` | Spec | System and content translations |
| `specs/import-export/spec.md` | Spec | CSV, JSON, XML, YAML import/export |
| `specs/deployment/spec.md` | Spec | Docker, clustered, multi-tenant deployment |
| `screenshots/` | Screenshots | 50 screenshots covering login, content, settings, data model, flows, dashboards, roles, policies, marketplace |

---

## 2. Product Overview

### What Directus Is

Directus is a mature, full-featured "Data Platform" that wraps any SQL database with auto-generated REST and GraphQL APIs and a Vue 3 admin application ("Data Studio"). It positions itself across four primary use cases:

1. **Backend as a Service (BaaS)** -- auto-generated APIs, authentication, real-time, webhooks
2. **Headless CMS** -- content management decoupled from presentation
3. **Internal Tool Builder** -- no-code admin panels and dashboards
4. **Data Management and Analytics** -- single source of truth with built-in analytics

### Target Market

Developers and agencies building data-driven applications who want to avoid writing boilerplate backend code. Enterprises needing a headless CMS with full API access. Teams that want to wrap existing SQL databases without migration. The product appeals primarily to the JavaScript/TypeScript ecosystem.

### Business Model

Open-core with BSL 1.1 license. Revenue from Directus Cloud (managed hosting) and Enterprise licenses. The BSL restricts production use of newer versions for competing products; each version converts to GPL-compatible after 3 years. This means Directus is **not truly open source** by OSI standards.

### Pricing

| Tier | Price | Key Limits |
|------|-------|-----------|
| **Self-Hosted (Free)** | $0 | Entities under $5M annual finances only |
| **Self-Hosted (Commercial)** | License required | Entities over $5M in production |
| **Cloud Starter** | $15/month | 1 user, 5,000 entries, 50,000 API requests |
| **Cloud Professional** | $99/month | 5 users, 75,000 entries, 250,000 API requests, 150 GB storage |
| **Cloud Business** | $499/month | Extended limits |
| **Cloud Enterprise** | From $15,000/year | Custom everything, dedicated servers, SLA, 20+ regions |
| **Premium Support** | +$300/month | CSM, advisory sessions, 24/7 critical support |

---

## 3. Architecture Summary

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Runtime | Node.js (ESM) |
| API Framework | Express.js |
| Database ORM/Query | Knex.js (multi-DB) |
| Frontend | Vue 3 + Composition API + Pinia |
| Build | pnpm monorepo, Vite (app), Rollup/Rolldown (extensions) |
| Languages | TypeScript throughout |
| Testing | Vitest (unit), Blackbox tests (integration) |
| Real-time | WebSocket (ws library) |
| File processing | Sharp (images) |
| Caching | Keyv (Redis/memory) |
| Validation | Joi, Zod |
| Auth | JWT, argon2, jsonwebtoken |
| Sandboxing | isolated-vm (V8 isolates) |
| GraphQL | graphql-compose |

### System Design

The monorepo (~130k LOC TypeScript) contains:

- **`api/`** (~50k LOC) -- Express.js backend with layered architecture: Controllers (route handlers) -> Services (business logic) -> Database (Knex.js query builder with AST-based compilation)
- **`app/`** (~80k LOC) -- Vue 3 SPA admin UI with 43 interfaces, 20 displays, 6 layouts, 12 panel types
- **`sdk/`** -- TypeScript client SDK for API consumers
- **`packages/`** -- 30+ shared packages (types, constants, schema inspector, storage drivers, extensions SDK, validation, env)

Key architectural patterns:
- **Service-per-collection**: `ItemsService` base class with specialized services for system collections
- **AST query model**: Queries parsed into AST, permissions applied as AST transformations, compiled to Knex SQL
- **Event-driven**: Emitter singleton with `onAction`/`onFilter` hooks for extensions and Flows
- **Transaction-wrapped mutations**: All write operations use database transactions

### Data Model

Directus operates at the **database level**, introspecting existing SQL tables:

- **Collections** map to database tables (real SQL DDL)
- **Fields** map to columns (24 field types including 6 geometry types)
- **Relationships** defined through foreign keys and junction tables (M2O, O2M, M2M, M2A polymorphic, translations, file/files)
- **23 system tables** (`directus_*` prefix) store all metadata: collections, fields, relations, roles, policies, permissions, users, sessions, files, folders, flows, operations, dashboards, panels, activity, revisions, presets, settings, translations, notifications, shares, versions

### Supported Databases

PostgreSQL (+ PostGIS), MySQL 5.7/8, MariaDB, SQLite, Microsoft SQL Server, Oracle DB, CockroachDB.

---

## 4. Feature Inventory

| # | Spec | Description |
|---|------|-------------|
| 1 | Architecture | Node.js/TypeScript monorepo with Express.js API, Vue 3 admin UI, and 30+ shared packages |
| 2 | Data Modeling | Database-level schema with 24 field types, 7 relationship types, and rich field metadata |
| 3 | REST API | Auto-generated RESTful API with 30+ filter operators, aggregation, deep relational queries |
| 4 | GraphQL API | Auto-generated type-safe GraphQL with queries, mutations, and subscriptions |
| 5 | Access Control | ABAC system with hierarchical roles, reusable policies, row-level and field-level security |
| 6 | Row-Level Security | Filter-based item permissions with dynamic variables ($CURRENT_USER, $NOW, etc.) |
| 7 | Flows & Automation | Visual automation engine with 5 trigger types and 15 built-in operations |
| 8 | Extensions | 10 extension types (6 app, 2 API, 1 hybrid, 1 meta) with sandboxed execution |
| 9 | Marketplace | npm-backed extension registry with 274+ extensions, in-app install |
| 10 | Content Versioning | Named content versions with delta storage, promote/compare workflow |
| 11 | Real-time WebSockets | WebSocket subscriptions for live data updates (REST WS + GraphQL WS) |
| 12 | Insights & Dashboards | No-code analytics dashboard builder with 12 panel types |
| 13 | File Management | Multi-driver storage (S3, GCS, Azure, Local, Cloudinary, Supabase) with TUS uploads |
| 14 | Image Transformations | On-the-fly Sharp-based image processing with caching |
| 15 | Activity & Revisions | Complete audit trail with event log and data snapshots |
| 16 | AI Assistant | Built-in conversational AI in the Data Studio with configurable providers |
| 17 | MCP Integration | MCP server for external AI tool connectivity |
| 18 | Notifications | In-app notification system for users, Flows, and system events |
| 19 | Sharing | Share links for external collaboration without user accounts |
| 20 | Translations & i18n | System-level UI translations and content-level translation relationships |
| 21 | Import/Export | CSV, JSON, XML, YAML import/export with background processing |
| 22 | Deployment | Docker-based with clustering support, deployment webhooks, multi-tenant options |

---

## 5. Key Strengths

| # | Strength | Detail |
|---|----------|--------|
| 1 | **GraphQL API** | Auto-generated type-safe GraphQL with subscriptions, dynamically built from the database schema. A major draw for modern frontend frameworks (React, Next.js, Nuxt). |
| 2 | **Flows Automation Engine** | Built-in visual workflow builder with 5 trigger types (event, schedule, webhook, manual, operation) and 15 operations. No external service needed for basic automation. |
| 3 | **Extension Marketplace** | 274+ extensions across 10 granular types (interfaces, displays, layouts, modules, panels, themes, hooks, endpoints, operations, bundles). npm-backed registry with in-app install. |
| 4 | **Content Versioning** | Named draft versions with delta storage, compare, and promote workflow. Enables editorial review cycles and compliance-friendly content management. |
| 5 | **Row-Level Security** | Sophisticated filter-based permissions with dynamic variables ($CURRENT_USER, $CURRENT_ROLE, $NOW). Permissions applied as AST-level query rewrites for zero data leakage. |
| 6 | **WebSocket Subscriptions** | Real-time data subscriptions via both REST-style and GraphQL WebSocket protocols. Permission-filtered so users only see what they are authorized to see. |
| 7 | **Rich Admin UI** | 43 form interfaces, 20 display renderers, 6 collection layouts (table, cards, calendar, kanban, map), 12 dashboard panel types. Highly polished and customizable. |
| 8 | **Multi-Database Support** | Works with 7+ SQL databases including PostgreSQL, MySQL, MariaDB, SQLite, MSSQL, Oracle, CockroachDB. Can wrap existing databases without migration. |
| 9 | **Built-in AI Assistant** | Conversational AI integrated directly into the Data Studio with configurable providers (OpenAI, Anthropic, etc.). Context-aware for current collection/item. |
| 10 | **Mature Query Engine** | AST-based query builder with deep relational joins, 30+ filter operators, aggregation functions (count, sum, avg, min, max), geospatial queries, and regex matching. |

---

## 6. Key Weaknesses

| # | Weakness | Detail |
|---|----------|--------|
| 1 | **BSL License (Not Open Source)** | BSL 1.1 is not OSI-approved. Free self-hosted use is restricted to entities under $5M annual finances. Larger organizations need a commercial license. Each version converts to GPL after 3 years, but current versions remain restricted. |
| 2 | **No Nextcloud Integration** | Cannot leverage Nextcloud's collaboration suite (file sharing, calendar, contacts, talk, office), user management, or app ecosystem. Operates as an isolated standalone system. |
| 3 | **Heavy Runtime** | Full Node.js server with ~130k LOC requires dedicated infrastructure. Heavier resource footprint compared to a PHP app running within existing Nextcloud infrastructure. |
| 4 | **Database-Coupled Schema** | Schema changes require actual DDL migrations (CREATE TABLE, ALTER COLUMN). No runtime dynamic schema changes. Cannot share/version schemas independently of the database structure. |
| 5 | **No Government Ecosystem** | No NL Design System support, no WCAG-focused theming, no Dutch government API compliance (NLGov), no Common Ground integration. Not built for the public sector market. |
| 6 | **No JSON Schema / JSON-LD** | Does not use industry-standard JSON Schema for data modeling. No support for JSON-LD, linked data, or semantic web standards. |
| 7 | **No Document Generation** | No equivalent to DocuDesk for template-based document generation. No PDF/DOCX/ODT output from structured data. |
| 8 | **Limited Automation Integrations** | Flows engine has only 15 built-in operations. While extensible via custom operations, it lacks the breadth of n8n (900+ integrations) or Zapier out of the box. |
| 9 | **Complex Deployment** | Requires separate infrastructure (Node.js server, database, optional Redis, optional storage driver). Cannot be installed as a one-click Nextcloud app. |
| 10 | **No Semantic/Vector Search** | No built-in vector embeddings, semantic search, or AI-powered search capabilities. Full-text search is basic SQL-level only. |

---

## 7. Relevance to OpenRegister

### Direct Competition Level: **HIGH**

Directus is one of OpenRegister's most direct competitors. Both products auto-generate APIs from data schemas and provide admin UIs for data management. They solve the same fundamental problem -- structured data management with API access -- but approach it from opposite directions:

- **Directus** operates at the **database level**: it introspects existing SQL tables, generates real DDL for new collections, and builds APIs from the physical schema.
- **OpenRegister** operates at the **application level**: it stores JSON Schemas as metadata, manages data in a flexible object store, and runs within the Nextcloud ecosystem.

### Patterns to Adopt from Directus

| Pattern | What Directus Does | How OpenRegister Could Adapt |
|---------|-------------------|----------------------------|
| **Filter-based permissions** | Row-level security via filter rules with dynamic variables ($CURRENT_USER, $NOW) | Implement filter-based row-level access control using JSON Schema-compatible filter syntax |
| **Field-level access** | Per-field read/write permissions per role/action | Add field-level visibility and editability to schema permissions |
| **Aggregation queries** | Built-in count, sum, avg, min, max with groupBy | Add aggregation endpoints to the REST API |
| **Deep relational queries** | Nested filter/sort/limit on related collections | Support relational query parameters for object relations |
| **Content versioning** | Named draft versions with delta storage | Implement version branching for objects (beyond audit log revisions) |
| **Presets/defaults in permissions** | Auto-inject field values based on user context | Add context-aware default values in schema/permission configuration |
| **OpenAPI auto-generation** | Permission-aware OAS spec at runtime | Already being implemented; ensure permission-aware filtering |
| **Share links for data** | Scoped read-only access tokens for specific items | Extend Nextcloud share links to cover register objects |

### Clear Differentiators (OpenRegister Advantages)

| Differentiator | Why It Matters |
|---------------|----------------|
| **Truly Open Source (AGPL)** | No revenue restrictions, no commercial license required at any scale. Critical for government procurement and community trust. |
| **Nextcloud-Native** | Integrated with a mature collaboration platform (40M+ users). Leverages existing auth, files, sharing, calendar, contacts, talk, and office suite. One-click install from the App Store. |
| **Runtime Dynamic Schemas** | JSON Schemas stored as data, can be created/modified/versioned at runtime without DDL. Schemas are portable and shareable between registers. |
| **n8n Integration (900+ Integrations)** | External automation platform with vastly more connectors than Directus Flows (15 operations). Mature visual editor with error handling, retry logic, and community-driven nodes. |
| **MCP Standard Protocol** | JSON-RPC 2.0 compliant MCP endpoint, enabling any MCP-compatible AI tool to interact with structured data. Directus has MCP too but OpenRegister's is standards-first. |
| **Dutch Government Ecosystem** | NL Design System theming, NLGov API compliance, Common Ground integration, WCAG AA accessibility. Purpose-built for the public sector. |
| **Semantic/Vector Search** | Built-in vector embeddings and semantic search capabilities, absent in Directus. |
| **JSON-LD / Linked Data** | Standards-based linked data support for interoperability, absent in Directus. |
| **Document Generation** | DocuDesk integration for template-based PDF/DOCX/ODT generation from structured data. |
| **Multi-Register Architecture** | Multiple isolated registers per Nextcloud instance, each with their own schemas, providing true multi-tenancy at the application level. |

---

## 8. Feature Gap Analysis

### Features Directus Has That OpenRegister Should Consider

| Feature | Directus Implementation | Priority | Rationale |
|---------|------------------------|----------|-----------|
| **GraphQL API** | Auto-generated from schema with type-safe queries, mutations, subscriptions | High | Expected by modern frontend frameworks; major developer experience advantage |
| **Row-level security** | Filter-based permission rules with dynamic variables, applied as AST query rewrites | High | Essential for multi-tenant and government use cases; Directus's model is best-in-class |
| **WebSocket subscriptions** | Real-time data updates via REST WS and GraphQL WS protocols | High | Required for collaborative and real-time applications |
| **Content versioning** | Named draft versions with delta storage, compare, promote | Medium | Important for editorial workflows and compliance |
| **Advanced filtering** | 30+ operators including regex, geospatial, nested relational, between, empty checks | Medium | OpenRegister's OData-style filtering is simpler; more operators would improve power |
| **Aggregation queries** | count, sum, avg, min, max with groupBy support in REST and GraphQL | Medium | Needed for analytics and reporting without external tools |
| **Official JavaScript SDK** | TypeScript-first SDK with full API coverage | Medium | Lowers integration barrier for frontend developers |
| **Insights dashboards** | No-code dashboard builder with 12 panel types (charts, metrics, lists) | Medium | Built-in analytics reduces need for external BI tools |
| **Field-level permissions** | Per-field read/write access per role per action | Medium | Fine-grained data protection for sensitive fields |
| **Image transformations** | On-the-fly Sharp-based resize/crop/format with caching | Low | Nextcloud has basic image handling; Sharp-level transforms are nice-to-have |
| **Extension marketplace** | npm-backed in-app extension discovery and install | Low | Nextcloud App Store already serves this need |
| **Collaborative editing indicators** | Live presence indicators showing who is editing | Low | Nice for multi-user environments but not critical |

### Features OpenRegister Has That Directus Lacks

| Feature | OpenRegister Implementation | Competitive Advantage |
|---------|----------------------------|----------------------|
| **Nextcloud integration** | Native app within the Nextcloud ecosystem (auth, files, sharing, calendar, contacts, talk, office) | Strong -- access to 40M+ user base and complete collaboration suite |
| **True open source license** | AGPL with no revenue restrictions or commercial license requirements | Strong -- critical for government procurement and community trust |
| **Runtime dynamic schemas** | JSON Schemas stored as metadata, created/modified at runtime without DDL | Strong -- faster iteration, schema portability, no database migration needed |
| **JSON Schema standard** | Industry-standard schema definition (draft-07+) for validation and interoperability | Strong -- portable, tooling-rich, widely understood |
| **n8n automation (900+ integrations)** | External automation platform with vastly more connectors | Strong -- more integrations than Directus Flows by 60x |
| **NL Design System theming** | Government-compliant theming with CSS variables, WCAG AA | Strong -- essential for Dutch/EU public sector market |
| **Semantic/vector search** | Built-in vector embeddings and semantic search | Moderate -- AI-powered search is a growing differentiator |
| **JSON-LD / Linked Data** | Standards-based linked data support | Moderate -- important for interoperability in government and academic contexts |
| **Document generation (DocuDesk)** | Template-based PDF/DOCX/ODT generation from structured data | Moderate -- common requirement for government and enterprise workflows |
| **Faceted search** | Configurable faceted navigation with aggregation counts | Moderate -- important for catalog and directory use cases |
| **Multi-register isolation** | Multiple isolated registers per instance with separate schemas | Moderate -- true multi-tenancy at the application level |
| **CalDAV integration** | Calendar integration for date-based objects | Low -- niche but useful for scheduling use cases |
| **NLGov API compliance** | Dutch government API standards compliance | Low globally, Strong in NL -- essential for the target market |
| **One-click deployment** | Install as a Nextcloud app from the App Store | Moderate -- dramatically lower barrier to entry vs Docker + Node.js setup |
