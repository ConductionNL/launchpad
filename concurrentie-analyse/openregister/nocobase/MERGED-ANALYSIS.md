# NocoBase Competitive Analysis -- Merged Report

**Date:** 2026-03-14
**Sources:** Codebase analysis (GitHub nocobase/nocobase), live instance exploration (14 screenshots), documentation research, 16 feature specs

---

## 1. Sources Summary

| Source | File | What It Covers |
|--------|------|----------------|
| Product overview | `overview.md` | Full product summary, tech stack, data model, business logic, UI reference, comparison tables, screenshots |
| Architecture deep-dive | `docs/architecture.md` | Server stack (Koa middleware chain), client architecture (Formily schema renderer), data flow, multi-tenancy |
| Business logic patterns | `business-logic/patterns.md` | 6 core patterns (plugin-everything, schema-driven UI, collection-first, resource-action API, registry, event hooks), architecture decisions, differentiators |
| API reference | `docs/api-reference.md` | URL patterns, filtering operators, middleware pipeline |
| Feature specs (16) | `specs/*/spec.md` | Collections & Data Model, Plugin System, Workflow Engine, Access Control, UI Builder, REST API, Authentication, Audit Logs, Calendar/Gantt/Kanban, Data Visualization, File Management, I18n & Localization, Map Fields, Notification System, Public Forms, Theme System |

---

## 2. Product Overview

NocoBase is an open-source **no-code/low-code development platform** built on Node.js, TypeScript, React, and Ant Design. It enables users to build business applications through a visual UI builder with a plugin-based architecture, targeting organizations that need customizable internal tools, data management systems, and workflow automation without extensive coding.

**License:** AGPL-3.0 + Commercial dual license. The AGPL core is free to use and modify, but advanced features (enterprise plugins, commercial support, white-label options) require a paid license.

**Market focus:** Enterprise internal tools, CRM, ERP, project management, and data management. Strong presence in the Chinese market with KingBase (Chinese government database) support. No specific government compliance focus (no NL Design, no WCAG, no ZGW/GEMMA).

**Scale:** 105 built-in plugins, 26 core packages, monorepo architecture with Lerna/Yarn workspaces. The platform ships as a standalone Node.js application (not embedded in another platform).

**Key numbers:**
- 105 official plugins across 10 categories
- 31 built-in field types + plugin-provided field types
- 9 data block types (Table, Form, Details, List, Grid Card, Calendar, Charts, Gantt, Kanban)
- 4 trigger types and 20+ workflow instruction types
- 4 built-in themes (Default, Dark, Compact, Compact Dark)
- 5 database dialects supported (PostgreSQL, MySQL, MariaDB, SQLite, KingBase)

---

## 3. Architecture Summary

### Technology Stack

| Layer | Technology | Notes |
|-------|-----------|-------|
| **Backend** | Node.js (v18+), TypeScript, Koa | HTTP framework with middleware chain |
| **ORM** | Sequelize | Collection/Field abstractions over raw models |
| **Frontend** | React 18, Ant Design 5, Formily | Schema-driven UI rendering |
| **Routing** | UmiJS | Client-side routing |
| **Database** | PostgreSQL (primary), MySQL 8, MariaDB, SQLite, KingBase | Multi-dialect via Sequelize |
| **Build** | Lerna monorepo, Yarn workspaces, Vite/Webpack | 26 core packages + 105 plugins |
| **Process** | PM2 | Process management for production |
| **Reverse proxy** | Nginx | Static file serving |

### Server Request Flow

```
HTTP Request -> Koa Middleware Chain -> Resourcer (URL -> Resource + Action)
    -> ACL Check (role-based) -> Action Handler -> Repository -> Sequelize Model -> Database
```

### Client Rendering Flow

```
UI Schema (JSON in DB) -> SchemaComponent Renderer (Formily) -> React Components (Ant Design)
    -> Block Providers (data fetching) -> Collection Manager (field metadata) -> API Client (Axios)
```

### Core Packages (26)

1. **Application** (`@nocobase/server`) -- Koa-based HTTP server with plugin lifecycle management, DataSourceManager, AuthManager, CacheManager, AIManager, LockManager, PubSub
2. **Database** (`@nocobase/database`) -- Sequelize wrapper with Collection/Field abstractions, event-driven model hooks, migration system via Umzug
3. **Resourcer** (`@nocobase/resourcer`) -- REST API resource routing (CRUD + custom actions), middleware composition via koa-compose
4. **ACL** (`@nocobase/acl`) -- Role-based access control with resource-level permissions, field filtering, data scoping
5. **Client** (`@nocobase/client`) -- React application with schema-driven UI rendering via Formily
6. **Data Source Manager** (`@nocobase/data-source-manager`) -- Multi-database connectivity
7. **Flow Engine** (`@nocobase/flow-engine`) -- Client-side action/event orchestration
8. **SDK** (`@nocobase/sdk`) -- Axios-based API client
9. **Cache/Lock/Telemetry** -- Infrastructure packages

### Key Design Decisions

- **Plugin-everything:** Even core features (auth, ACL, file management, UI schema storage) are implemented as plugins
- **Database as source of truth:** Collections, UI schemas, plugin state all stored in database tables -- no external config files
- **Monolithic server:** Single Node.js process serves API + static files; no microservices
- **Schema-driven UI:** Entire interface stored as JSON in database, rendered at runtime by Formily

---

## 4. Feature Inventory

| # | Spec | Category | Description |
|---|------|----------|-------------|
| 1 | Collections & Data Model | Data | 6 collection types (General, Inherited, Tree, SQL, View, FDW), 31 field types, 4 relationship types, MagicAttributeModel for schema-less extension |
| 2 | Plugin System | Architecture | 105 built-in plugins, hot-enable/disable, dependency resolution via Toposort, NPM-based packaging, standard lifecycle hooks |
| 3 | Workflow Engine | Automation | 4 trigger types (Collection, Schedule, Action, Custom), 20+ instruction types, linked-list processor, approval flows, JavaScript/SQL execution |
| 4 | Access Control | Security | Three-layer RBAC (system, plugin, data source), field-level permissions, record-level data scoping, union roles, route-level menu visibility |
| 5 | UI Builder | Frontend | Visual drag-and-drop page builder, JSON schema storage, 9 data block types, schema templates, schema initializers, WYSIWYG editor mode |
| 6 | REST API | Integration | Resource-action pattern (`/api/resource:action`), 12 built-in actions, 15+ filter operators, nested resource traversal, custom action registration |
| 7 | Authentication | Security | Pluggable authenticator system, password + SMS providers, token blacklist, multi-factor via verification plugin |
| 8 | Audit Logs | Compliance | Automatic change tracking via Sequelize model hooks, records create/update/destroy with before/after values |
| 9 | Calendar, Gantt & Kanban | Views | Three project management block types: Calendar (date-based), Gantt (timeline), Kanban (drag-and-drop board) |
| 10 | Data Visualization | Analytics | Chart blocks with ECharts integration, query actions on collection data, bar/line/pie/scatter/area chart types |
| 11 | File Management | Storage | Multi-backend file storage (local, S3, Ali OSS, Tencent COS), multer-based upload, attachment fields |
| 12 | I18n & Localization | Platform | i18next on server and client, translation management for system strings and user content, locale tester plugin |
| 13 | Map Fields | Geospatial | Point, polygon, line, circle field types with GeoJSON storage, map block visualization (Leaflet/AMap/Google Maps) |
| 14 | Notification System | Communication | Multi-channel notifications (email SMTP, in-app messages), workflow-triggered, extensible channel types |
| 15 | Public Forms | External | Unauthenticated form submission to collections via public URL, configurable fields, CAPTCHA support |
| 16 | Theme System | Appearance | Ant Design token-based theming, 4 built-in themes, custom theme creation, color/spacing/typography/border customization |

---

## 5. Key Strengths (Top 10)

### 5.1 Massive Plugin Ecosystem (105 Plugins)
NocoBase ships with 105 first-party plugins covering data modeling, UI blocks, workflow automation, authentication, file management, visualization, and more. This provides out-of-the-box coverage for most business application needs without third-party dependencies. Plugins are hot-toggleable -- enable/disable without restart.

### 5.2 Visual UI Builder
The drag-and-drop UI builder allows non-developers to construct complete application interfaces. Pages are composed of blocks (Table, Form, Calendar, Kanban, etc.) that bind to collections. The WYSIWYG editor mode with orange dashed borders, "Add block" buttons, and settings gear icons provides an intuitive building experience.

### 5.3 Schema-Driven UI Rendering
The entire interface is stored as JSON schemas in the database and rendered at runtime by a Formily-based renderer. This decouples the UI from code, enabling runtime customization without deployment, UI versioning and rollback, and non-developer interface building.

### 5.4 Built-In Workflow Engine
The workflow engine with 4 trigger types and 20+ instruction types covers most automation needs: data triggers, scheduled execution, conditional branching, loops, parallel execution, HTTP requests, JavaScript/SQL execution, and human approval flows. Tightly integrated with the data model (collection triggers fire automatically).

### 5.5 Rich Field Type System (31 Types)
31 built-in field types spanning scalar (string, number, boolean, date), relational (belongs-to, has-many, many-to-many), and special types (context, sort, virtual). Plugin fields add geospatial (point, polygon), formula, sequence, and attachment-url. The interface system maps each field to appropriate UI presentations.

### 5.6 Collection Type Variety
Six collection types beyond standard tables: Inherited (PostgreSQL table inheritance), Tree (adjacency list hierarchies), SQL (virtual collections from raw queries), View (database views), and FDW (foreign data wrappers for cross-database queries). This provides flexible data modeling options.

### 5.7 Three-Layer Access Control
The RBAC system operates at three levels: system permissions (configure UI, manage plugins), plugin permissions (per-plugin settings access), and data source permissions (per-collection CRUD with field-level whitelisting and record-level data scoping). Union roles allow combining multiple roles with most-permissive evaluation.

### 5.8 Multiple Visualization Modes
Nine data block types (Table, Form, Details, List, Grid Card, Calendar, Charts, Gantt, Kanban) provide different perspectives on the same collection data. Users can place multiple block types on a single page, creating rich dashboards without code.

### 5.9 Public Forms
The public forms plugin enables unauthenticated data collection via shareable URLs. This is valuable for surveys, contact forms, and citizen submissions -- a feature that many no-code platforms lack.

### 5.10 Multi-Database Support
Five database dialects supported (PostgreSQL, MySQL, MariaDB, SQLite, KingBase) plus the Data Source Manager for connecting to external databases. FDW collections enable cross-database queries within PostgreSQL.

---

## 6. Key Weaknesses (Top 10)

### 6.1 No Nextcloud Integration
NocoBase is a standalone application with its own auth, file storage, notifications, and calendar. It cannot leverage Nextcloud's ecosystem (Files, Talk, Calendar, Mail, Contacts, 100+ apps). Organizations using Nextcloud would need to run two separate platforms with no integration between them.

### 6.2 No Government Standards Compliance
No NL Design System support, no WCAG accessibility focus, no ZGW/GEMMA compliance, no WOO support, no Archiefwet compliance. The platform has zero European government relevance. Its only government-adjacent feature is KingBase support for the Chinese market.

### 6.3 Commercial Lock-In for Advanced Features
The AGPL + Commercial dual license means advanced features (enterprise plugins, white-label, commercial support) require a paid license. This creates vendor lock-in risk for organizations that grow to need enterprise capabilities. The commercial boundary is not always clear.

### 6.4 Standalone Infrastructure Requirements
NocoBase requires its own Node.js server, PostgreSQL/MySQL instance, and process management (PM2, Nginx). It cannot share infrastructure with an existing Nextcloud deployment. This doubles the operational burden for organizations already running Nextcloud.

### 6.5 No Semantic Data Model
Collections are database tables, not semantic abstractions. There is no JSON Schema, no linked data, no OpenAPI/OAS export (only a Swagger plugin), and no object-level versioning. The data model is relational-first with no support for document-oriented or graph patterns.

### 6.6 Limited Search Capabilities
NocoBase provides basic per-collection filtering with 15+ operators but lacks full-text search, faceting, search result aggregation, or integration with dedicated search engines (Elasticsearch, Solr). For applications with large datasets or complex search requirements, this is a significant gap.

### 6.7 No Object Versioning or History
Unlike OpenRegister which tracks object-level versioning, NocoBase does not natively track record history. The audit log plugin records changes but does not provide point-in-time reconstruction or version comparison. Record state at a previous time cannot be retrieved.

### 6.8 Tight UI Coupling
The schema-driven UI is powerful but tightly coupled to Ant Design and Formily. Custom UI components require React/Formily expertise. There is no way to use alternative frontend frameworks (Vue, Svelte) or design systems (NL Design, Material UI). The UI layer is not interchangeable.

### 6.9 Chinese Market Focus
Much of the documentation, community discussion, and feature development targets the Chinese market (KingBase database, AMap for maps, Alibaba OSS, Tencent COS, SMS auth via Chinese providers). This creates a cultural and linguistic barrier for European adoption.

### 6.10 Monolithic Architecture
Single Node.js process handles everything. No microservices, no ability to scale individual components (e.g., workflow processing separately from API serving). PM2 provides basic process management but no true horizontal scaling of subsystems.

---

## 7. Relevance to OpenRegister

### 7.1 Competition Level: **Low-Medium**

NocoBase and OpenRegister operate in adjacent but distinct market segments. NocoBase is a standalone no-code platform for building internal business tools. OpenRegister is a Nextcloud app providing register/schema-based data management within the Nextcloud ecosystem. The overlap occurs when organizations need structured data management with UI customization -- but the delivery model is fundamentally different.

**Where they compete:**
- Data modeling and CRUD operations on structured data
- Custom views and visualization of record data
- Workflow automation triggered by data changes
- Role-based access control on data

**Where they do not compete:**
- NocoBase targets standalone deployment; OpenRegister targets Nextcloud users
- NocoBase targets no-code/business users; OpenRegister targets developer-oriented government teams
- NocoBase has no Dutch/EU government compliance; OpenRegister is built for it
- NocoBase has no file/collaboration ecosystem; OpenRegister inherits Nextcloud's

### 7.2 Patterns Worth Adopting

| Pattern | NocoBase Implementation | Adoption Recommendation for OpenRegister |
|---------|------------------------|------------------------------------------|
| **Visual UI builder** | Drag-and-drop page builder with 9 block types, stored as JSON schemas | Consider a simplified block-based view builder for OpenRegister dashboards. Not full no-code, but allowing admins to compose views (Table, Form, Detail, Chart) on register data without writing Vue code. |
| **Plugin architecture with registry** | Registry pattern for triggers, instructions, storage types, ACL strategies | Apply registry pattern to OpenRegister extension points: custom field types, validation rules, export formats. Allow apps to register new capabilities without modifying core code. |
| **Schema-driven rendering** | JSON UI schemas in database, rendered by Formily at runtime | OpenRegister already has JSON Schema for data; extend this concept to define default display configurations (column order, visible fields, default sort) per schema. |
| **Collection-level auto-API** | Every collection automatically gets CRUD endpoints | OpenRegister already does this well with register/schema-based API. Validate that the filtering operators match NocoBase's richness (15+ operators). |
| **Block types for different views** | Table, Calendar, Kanban, Gantt, Chart blocks on same data | Implement additional view modes on register data beyond list/detail. Priority: Chart/visualization blocks and Calendar views. Kanban and Gantt are lower priority. |
| **Public forms** | Shareable URLs for unauthenticated data submission | Valuable for government use cases (citizen submissions, survey responses). Implement as public API endpoints with configurable field exposure and CAPTCHA. |
| **Data scoping per role** | Filter conditions on roles restrict visible records | Implement record-level ACL scoping: "Member role sees only records where department = user.department." Currently OpenRegister lacks this granularity. |
| **Field-level permissions** | Whitelist which fields each role can read/write | Useful for sensitive data (BSN, personal information). Allow schemas to define per-field visibility rules tied to Nextcloud groups/roles. |

### 7.3 Key Differentiators (OpenRegister Advantages)

| Differentiator | OpenRegister | NocoBase |
|---------------|-------------|----------|
| **Nextcloud ecosystem** | Full access to Files, Talk, Calendar, Mail, 100+ apps | Standalone, no ecosystem |
| **Government compliance** | NL Design System, WCAG AA, ZGW/GEMMA ready | No government standards |
| **Semantic data model** | JSON Schema with `allOf` composition, object versioning | Database tables with no schema standard |
| **OpenAPI/OAS export** | Automatic OAS spec generation per register | Resource-action pattern, no standard API docs |
| **Full-text search** | Faceted search with Elasticsearch/Solr integration | Basic per-collection filtering only |
| **Deployment model** | Nextcloud app (install from app store) | Standalone Node.js deployment |
| **License** | EUPL (fully open) | AGPL + commercial dual license |
| **Workflow integration** | n8n with 400+ integration nodes | Built-in engine with 20+ nodes (fewer integrations) |
| **Authentication** | Nextcloud auth (LDAP, SAML, OIDC, 2FA) | Basic auth + SMS only |
| **File management** | Nextcloud Files (versioning, sharing, WebDAV) | Local/S3/OSS (basic storage) |

---

## 8. Feature Gap Analysis

### What NocoBase Has That OpenRegister Lacks

| Feature | NocoBase Implementation | Gap Severity | Recommendation |
|---------|------------------------|--------------|----------------|
| Visual UI builder | Drag-and-drop page/block construction with 9 block types | **Medium** | Build a simplified view composer for admin users. Allow composing Table, Form, Detail blocks on register data without Vue code. |
| Kanban/Gantt/Calendar views | Specialized block types for project management | **Medium** | Calendar view is most valuable for government (deadlines, appointments). Kanban useful for status-based workflows. Gantt is low priority. |
| Chart/visualization blocks | ECharts integration with query actions on collection data | **Medium** | MyDash partially covers this. Consider embedding lightweight chart components in register views. |
| Built-in workflow engine | Tightly integrated with data model, collection triggers | **Low** | n8n via ExApp already provides this with more integration nodes. NocoBase's tight coupling is actually a disadvantage for flexibility. |
| Public forms | Unauthenticated data submission via shareable URLs | **Medium** | Implement public submission endpoints per schema with configurable field exposure, validation, and CAPTCHA. |
| Field-level permissions | Per-role whitelist of readable/writable fields | **Medium** | Add field visibility rules to schema definitions. Useful for BSN, personal data restriction per Nextcloud group. |
| Record-level data scoping | Filter conditions on roles restrict visible records | **High** | Implement data scoping rules per Nextcloud group: "Group X sees only records where department = Y." Critical for multi-department government use. |
| Theme editor with live preview | Ant Design token customization with 4 built-in themes | **Low** | NL Design System tokens already provide this capability. No gap for government use cases. |
| Geospatial fields and map blocks | Point, polygon, line, circle fields with Leaflet visualization | **Low** | Niche feature. Implement if specific government use cases require it (e.g., spatial planning registers). |
| Multi-database connectivity | Data Source Manager + FDW for cross-database queries | **Low** | OpenRegister operates within Nextcloud's database. External data sources are handled via OpenConnector. Different approach, not a gap. |
| Collection inheritance | PostgreSQL table inheritance for parent/child collections | **Low** | JSON Schema `allOf` composition provides equivalent capability at the schema level. |
| In-app notifications | Multi-channel notification manager with workflow integration | **Low** | Nextcloud Notifications already provides this. n8n can trigger notifications. |

### What OpenRegister Has That NocoBase Lacks

| Feature | OpenRegister Advantage | Why It Matters |
|---------|----------------------|----------------|
| **Nextcloud ecosystem integration** | Access to Files, Talk, Calendar, Mail, Contacts, 100+ apps without additional infrastructure | Organizations get collaboration, communication, and file management alongside data management -- no need for separate tools |
| **NL Design System theming** | Government-compliant design tokens, WCAG AA accessibility, professional Dutch government appearance | Mandatory for Dutch government adoption; NocoBase has no equivalent |
| **JSON Schema data model** | Standards-based schema definitions with `allOf` composition, `$ref` references, format validation | Interoperable, portable, and semantically meaningful data models vs. proprietary collection definitions |
| **Object versioning** | Point-in-time record state, version comparison, audit reconstruction | Government compliance (Archiefwet) requires proving historical state of records |
| **OpenAPI/OAS export** | Automatic specification generation per register/schema | Standard API documentation, client generation, integration testing -- vs. proprietary resource-action URLs |
| **Full-text search with faceting** | Elasticsearch/Solr integration with faceted search, aggregations, highlighting | Large-register search with complex filtering; NocoBase only has basic per-collection filters |
| **Register grouping** | Schemas organized into Registers with shared configuration and access control | Logical data organization; NocoBase collections are flat with only categories |
| **ZGW/GEMMA compliance** | Dutch government interoperability standards built into the architecture | Required for Dutch government procurement; NocoBase has zero EU compliance |
| **EUPL license** | Fully open license with no commercial feature restrictions | No vendor lock-in risk; all features available to all users |
| **Nextcloud authentication** | LDAP, SAML, OIDC, 2FA, Nextcloud user management | Enterprise-grade auth without additional infrastructure; NocoBase has basic password + SMS only |
| **n8n workflow integration** | 400+ integration nodes, visual editor, standalone scalability | More integration options and independent scaling vs. NocoBase's 20+ tightly-coupled nodes |
| **Nextcloud Files integration** | Versioning, sharing, WebDAV, Collabora/OnlyOffice, folder structures | Full document management ecosystem vs. basic file storage backends |
| **App store distribution** | One-click install from Nextcloud app store | vs. manual Node.js deployment with PM2/Nginx configuration |

---

## Summary

NocoBase is a feature-rich no-code platform with an impressive breadth of capabilities: 105 plugins, a visual UI builder, a built-in workflow engine, and multiple visualization blocks. Its plugin-everything architecture and schema-driven UI rendering are well-engineered patterns worth studying.

However, NocoBase operates in a fundamentally different market segment from OpenRegister. It is a standalone application requiring its own infrastructure, with no Nextcloud integration, no European government compliance, and a commercial dual license that restricts advanced features. Its strengths (no-code UI building, Chinese market support, standalone deployment) are irrelevant or even disadvantageous in OpenRegister's target market (Dutch government organizations already using Nextcloud).

The primary value of studying NocoBase for OpenRegister is in **pattern adoption**: the visual view builder concept, the registry pattern for extensibility, field-level and record-level access control, and public forms for citizen data submission. These patterns should be implemented using Nextcloud-native tools and Dutch government standards rather than replicating NocoBase's standalone approach.

**Strategic position:** NocoBase proves that schema-driven, no-code data platforms have strong market demand. OpenRegister's competitive advantage is delivering similar data management capabilities *within* the Nextcloud ecosystem *with* European government compliance -- a combination NocoBase cannot offer.
