---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# NocoBase Competitive Analysis

## Purpose

NocoBase is an open-source no-code/low-code development platform built on Node.js, TypeScript, React, and Ant Design. It enables users to build business applications through a visual UI builder with a plugin-based architecture. NocoBase targets organizations that need customizable internal tools, data management systems, and workflow automation without extensive coding.

## Architecture Overview

### Technology Stack
- **Backend:** Node.js (v18+), TypeScript, Koa (HTTP framework), Sequelize (ORM)
- **Frontend:** React 18, Ant Design 5, Formily (form engine), UmiJS (routing)
- **Database:** PostgreSQL (primary), MySQL 8, MariaDB, KingBase (Chinese gov DB)
- **Build:** Lerna monorepo, Yarn workspaces, Vite/Webpack
- **License:** AGPL-3.0 + Commercial dual license

### Core Architecture
NocoBase follows a **plugin-everything** architecture. The core provides:

1. **Application** (`@nocobase/server`) - Koa-based HTTP server with plugin lifecycle management
2. **Database** (`@nocobase/database`) - Sequelize wrapper with Collection/Field abstractions
3. **Resourcer** (`@nocobase/resourcer`) - REST API resource routing (CRUD + custom actions)
4. **ACL** (`@nocobase/acl`) - Role-based access control with resource-level permissions
5. **Client** (`@nocobase/client`) - React application with schema-driven UI rendering
6. **Data Source Manager** (`@nocobase/data-source-manager`) - Multi-database connectivity
7. **Flow Engine** (`@nocobase/flow-engine`) - Client-side action/event orchestration
8. **Cache/Lock/Telemetry** - Infrastructure packages

### Plugin System
NocoBase ships with **105 plugins** organized by category:

| Category | Examples | Count |
|----------|----------|-------|
| Data model | Collections, fields, SQL collections, tree, FDW | ~10 |
| Blocks/UI | Table, form, list, grid card, kanban, gantt, calendar, charts, iframe, markdown | ~15 |
| Actions | Bulk edit, bulk update, duplicate, export, import, print, custom request | ~8 |
| Workflow | Core engine + 20 node types (delay, loop, parallel, JS, SQL, HTTP, manual, etc.) | ~22 |
| Auth/Users | Password auth, SMS auth, API keys, departments, user sync | ~6 |
| Files | File manager (local, S3, Ali OSS, Tencent COS) | ~3 |
| I18n | Localization, locale tester | ~2 |
| System | Backup/restore, logger, error handler, system settings, notifications | ~10 |
| Visualization | Data visualization, ECharts integration | ~2 |
| Other | Theme editor, embed, mobile, public forms, map, comments, AI | ~15+ |

Each plugin follows a standard lifecycle: `afterAdd` -> `beforeLoad` -> `load` -> `install` -> `afterEnable`.

### Monorepo Structure
```
packages/
  core/           # 26 core packages (server, client, database, acl, etc.)
  plugins/
    @nocobase/    # 105 official plugins
  presets/
    nocobase/     # Default plugin bundle
```

## Data Model

### Collections (equivalent to OpenRegister Schemas)
NocoBase uses "Collections" as its data modeling primitive, wrapping Sequelize models:

- **General collections** - Standard database tables
- **Inherited collections** - PostgreSQL table inheritance (parent/child)
- **Tree collections** - Adjacency list pattern for hierarchical data
- **SQL collections** - Virtual collections backed by raw SQL queries
- **Calendar collections** - Collections with date fields for calendar display
- **FDW collections** - Foreign Data Wrapper for cross-database queries

### Field Types (31 built-in)
**Scalar:** string, text, number, boolean, date, datetime, time, password, json, blob, uuid, nanoid, snowflake-id, uid, unix-timestamp

**Relational:** belongs-to, has-one, has-many, belongs-to-many

**Special:** array, set, radio (single-select), virtual, context (auto-populated)

**Plugin fields:** point, polygon, lineString, circle (map), sequence (auto-increment patterns), formula, code, attachment-url, sort, m2m-array, snapshot

### Interfaces (User-facing field presentations)
Each field type maps to one or more "interfaces" that define how the field appears in forms:
input, textarea, number, integer, percent, boolean, select, multiple-select, checkboxes, date, datetime, time, json, many-to-one, one-to-many, many-to-many, one-has-one, one-belongs-to-one

### Collection Options
```typescript
interface CollectionOptions {
  name: string;
  title?: string;
  inherits?: string[];      // PostgreSQL inheritance
  viewName?: string;         // Backed by a DB view
  tree?: string;             // Tree structure type
  template?: string;         // Collection template (file, calendar, etc.)
  sortable?: CollectionSortable;
  autoGenId?: boolean;
  filterTargetKey?: string;
  fields?: FieldOptions[];
  origin?: 'plugin' | 'core' | 'user';
}
```

## Business Logic

### REST API Pattern
NocoBase uses a **resource-action** pattern instead of pure REST:

```
GET    /api/<resource>:list
GET    /api/<resource>:get?filterByTk=<id>
POST   /api/<resource>:create
PUT    /api/<resource>:update?filterByTk=<id>
DELETE /api/<resource>:destroy?filterByTk=<id>
POST   /api/<resource>:<customAction>
```

Built-in actions: `list`, `get`, `create`, `update`, `destroy`, `add`, `remove`, `set`, `toggle`, `move`, `firstOrCreate`, `updateOrCreate`

Nested resources: `/api/<resource>/<resourceId>/<association>:<action>`

### Filtering
```
filter[field.$operator]=value
filter[$and][0][field.$eq]=value
filter[$or][0][field.$gt]=10
```

### Workflow Engine
The workflow engine is NocoBase's most sophisticated subsystem:

**Triggers:**
- Collection trigger (on create/update/destroy)
- Schedule trigger (cron-based)
- Action trigger (before/after API actions)
- Custom action trigger (user-initiated)

**Instructions (Node Types):**
- **Data:** Query, Create, Update, Destroy, Aggregate
- **Logic:** Condition, Multi-conditions, Calculation, Dynamic calculation, Date calculation
- **Flow:** Delay, Loop, Parallel, End, Output
- **Integration:** HTTP request, SQL, JavaScript (sandboxed VM)
- **Human:** Manual (approval flows), CC (carbon copy notifications)
- **Messaging:** Notification, Mailer, Response message

**Execution Model:**
- Processor walks a doubly-linked list of nodes
- Supports pending/resolved/failed/error/aborted/canceled/rejected/retry states
- Uses Snowflake IDs for execution tracking
- LRU cache for workflow logger instances

### Access Control
Role-based with three layers:
1. **System permissions** - Configure UI, manage plugins, clear cache
2. **Plugin permissions** - Per-plugin access (settings pages)
3. **Data source permissions** - Per-collection CRUD with field-level and record-level scope

Roles can be "independent" or part of a union. Default roles: Admin, Member (default for new users), Root.

Data scope filtering: restrict records visible to a role using filter conditions (e.g., "only records created by current user").

### Notification System
Multi-channel notification manager:
- Email (SMTP)
- In-app messages
- Extensible channel types

### Authentication
Pluggable authenticator system:
- Password (username/email + password) - built-in
- SMS verification
- Custom auth providers via plugin API
- Token blacklist for session management
- Multi-factor via verification plugin

## UI Reference

NocoBase's UI is entirely schema-driven using a JSON-based UI schema stored in the database.

### Page Types
- **Classic page (v1)** - Block-based page layout
- **Modern page (v2)** - Enhanced page with new features
- **Group** - Menu grouping
- **Link** - External URL menu item

### Block Types (Data Blocks)
- **Table** - Spreadsheet-like data grid with sorting, filtering, pagination
- **Form** - Create/edit forms with field validation
- **Details** - Read-only record view
- **List** - Vertical record list
- **Grid Card** - Card-based grid layout
- **Calendar** - Calendar view for date-based records
- **Charts** - Data visualization (bar, line, pie, etc.)
- **Gantt** - Project timeline view
- **Kanban** - Drag-and-drop board view

### Filter Blocks
- **Filter Form** - Search/filter form connected to data blocks
- **Collapse** - Collapsible filter panels

### Other Blocks
- **Markdown** - Rich text content
- **Iframe** - Embedded external content
- **Action panel** - Custom action buttons
- **Workflow todos** - Pending workflow tasks

### Theme System
Four built-in themes: Default, Dark, Compact, Compact Dark. Customizable via Ant Design token system (color, spacing, typography, border radius, etc.).

## Screenshots

| # | Screenshot | Description |
|---|-----------|-------------|
| 01 | `01-login.png` | Login page with username/email + password |
| 02 | `02-dashboard-empty.png` | Empty dashboard after first login |
| 03 | `03-settings-menu.png` | Settings dropdown showing all admin sections |
| 04 | `04-plugin-manager.png` | Plugin manager with 94 plugins, categorized |
| 05 | `05-data-sources.png` | Data sources page showing main PostgreSQL source |
| 06 | `06-collections.png` | Collection manager with Roles and Users tables |
| 07 | `07-users-permissions.png` | Users management with nickname, roles, email |
| 08 | `08-roles-permissions.png` | Role permission editor with system/plugin/data tabs |
| 09 | `09-workflow.png` | Workflow management (empty, ready for new workflows) |
| 10 | `10-ui-editor-menu.png` | UI editor mode showing page type options |
| 11 | `11-ui-editor-page.png` | Empty page in UI editor with "Add block" button |
| 12 | `12-block-types.png` | Block type picker showing all available blocks |
| 13 | `13-theme-editor.png` | Theme editor with 4 built-in themes |
| 14 | `14-authentication.png` | Authentication manager with password authenticator |

## Comparison Notes

### NocoBase vs OpenRegister

| Aspect | NocoBase | OpenRegister |
|--------|----------|--------------|
| **Architecture** | Standalone Node.js app | Nextcloud app (PHP) |
| **Data modeling** | Collections with DB-backed schema | Schema/Register abstraction with JSON Schema |
| **UI** | Built-in visual UI builder (React) | Nextcloud Vue frontend |
| **API** | Resource-action pattern | REST + OAS export |
| **Workflow** | Built-in engine with 20+ node types | Delegated to n8n ExApp |
| **Auth** | Own auth system | Nextcloud auth (SSO, LDAP, etc.) |
| **Plugins** | 105 built-in plugins | Nextcloud app ecosystem |
| **Database** | PostgreSQL/MySQL direct | Nextcloud DB layer |
| **Hosting** | Self-hosted or cloud | Self-hosted Nextcloud |
| **License** | AGPL-3.0 + Commercial | EUPL (OpenRegister) |
| **Theming** | Ant Design tokens (4 themes) | NL Design System tokens |
| **File storage** | Local, S3, Ali OSS, Tencent COS | Nextcloud file system |
| **i18n** | Built-in localization plugin | Nextcloud l10n |
| **Inheritance** | PostgreSQL table inheritance | JSON Schema `allOf` composition |
| **Search** | Basic filtering per collection | Full-text search with faceting |

### Key NocoBase Strengths
1. **Visual UI builder** - Drag-and-drop page/block construction without code
2. **Massive plugin ecosystem** - 105 plugins covering most business needs
3. **Built-in workflow engine** - Sophisticated with branching, loops, approvals
4. **Schema-driven UI** - Entire UI stored as JSON, enabling runtime customization
5. **Multiple visualization blocks** - Kanban, Gantt, Calendar, Charts out of the box
6. **Theme system** - 4 built-in themes with full Ant Design token customization

### Key NocoBase Weaknesses (vs OpenRegister)
1. **No Nextcloud integration** - Standalone app, no file/calendar/contact/mail ecosystem
2. **No government standards** - No NL Design System, no WCAG focus, no Dutch gov compliance
3. **No semantic data model** - Collections are database tables, not linked data
4. **Heavy resource usage** - Full Node.js stack vs lightweight PHP app
5. **Vendor lock-in risk** - Commercial features behind paid license
6. **No OAS/OpenAPI export** - Resource-action pattern, not standard REST
7. **Limited search** - No full-text search, no faceting, no Elasticsearch/Solr integration

### Features to Consider Adopting
1. **Block-based page builder** - Could inspire OpenRegister dashboard/view system
2. **Collection inheritance** - PostgreSQL-style parent/child relationships
3. **Built-in chart blocks** - Data visualization directly on collection data
4. **Kanban/Gantt views** - Project management perspectives on register data
5. **Public forms** - External form submission to collections
6. **Backup/restore** - Full application state backup and restore
