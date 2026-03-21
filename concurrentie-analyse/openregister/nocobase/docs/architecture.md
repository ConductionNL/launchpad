---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# NocoBase Architecture

## System Architecture

NocoBase is a monorepo containing 26 core packages and 105 plugins. The architecture follows a plugin-everything design where even core features (auth, ACL, file management) are implemented as plugins.

### Server Stack

```
HTTP Request
    |
    v
Koa Middleware Chain
    |
    v
Resourcer (URL -> Resource + Action)
    |
    v
ACL Check (role-based)
    |
    v
Action Handler (list/get/create/update/destroy/custom)
    |
    v
Repository -> Sequelize Model -> Database
```

### Key Server Components

1. **Application** (`@nocobase/server/src/application.ts`)
   - Extends Koa with plugin management, database, ACL, caching, i18n
   - Manages DataSourceManager for multi-database support
   - Integrates AuthManager, CacheManager, AIManager, LockManager
   - PubSub for multi-instance coordination
   - Gateway pattern for HTTP request handling

2. **Database** (`@nocobase/database/src/database.ts`)
   - Wraps Sequelize with Collection/Field abstractions
   - Event-driven: beforeDefineCollection, afterDefineCollection, model CRUD events
   - Migration system via Umzug
   - Dialect support: PostgreSQL, MySQL, MariaDB, SQLite, KingBase

3. **Resourcer** (`@nocobase/resourcer/src/resourcer.ts`)
   - Maps URLs to Resources and Actions
   - Pattern: `/<resource>:<action>` or `/<resource>/<id>/<association>:<action>`
   - Middleware composition via koa-compose
   - Built-in accessors: list, create, get, update, delete

4. **Plugin Manager** (`@nocobase/server/src/plugin-manager/`)
   - Plugin lifecycle: afterAdd -> beforeLoad -> load -> install -> afterEnable
   - Hot-enable/disable without restart
   - Dependency resolution via Toposort
   - Each plugin can register collections, fields, actions, middleware, ACL rules

### Client Architecture

```
React Application
    |
    v
Schema Component Renderer (Formily-based)
    |
    v
Block Provider (data fetching)
    |
    v
Collection Manager (field metadata)
    |
    v
API Client (@nocobase/sdk -> Axios)
```

The client is entirely schema-driven. UI schemas are JSON objects stored in the database that describe component trees. The schema renderer uses Formily to map JSON to React components.

## Data Flow

### Collection Definition
1. User creates collection via UI or plugin defines it in code
2. Collection registered in Database with Model and Repository
3. Sequelize model synced to database table
4. REST endpoints auto-generated via Resourcer
5. ACL rules applied based on role permissions

### UI Schema Storage
1. UI schemas stored in `uiSchemas` collection (database table)
2. Each schema has a tree structure with parent/child relationships
3. Schema nodes reference components, decorators, and props
4. ServerHooks allow server-side logic triggered by schema changes
5. Schema templates enable reusable block patterns

## Multi-tenancy

NocoBase supports multi-app mode via `plugin-multi-app-manager`:
- Each tenant gets a separate application instance
- Shared collection support via `plugin-multi-app-share-collection`
- AppSupervisor manages application lifecycle across tenants
