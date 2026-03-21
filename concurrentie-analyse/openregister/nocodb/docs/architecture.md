# NocoDB Architecture

## Overview

NocoDB is a monorepo built with pnpm workspaces and Lerna, comprising several packages that work together to provide a spreadsheet-like interface on top of relational databases.

## Package Structure

```
packages/
  nocodb/          # NestJS backend (main server)
  nc-gui/          # Nuxt 3 frontend
  nocodb-sdk/      # Shared TypeScript SDK
  nocodb-sdk-v2/   # Next-gen SDK
  nc-secret-mgr/   # Secret management utility
  noco-integrations/ # Integration plugins
  nc-lib-gui/      # GUI library
  nc-mail-assets/  # Email templates
  nc-integration-scaffolder/ # Integration scaffolding tool
```

## Backend (NestJS)

### Directory Layout
```
src/
  controllers/     # 96 REST controllers
  services/        # 115 business logic services
  models/          # 79 data models (meta layer)
  db/              # Database query layer
    BaseModelSqlv2.ts    # Core data access (7,772 lines)
    sql-client/          # Database-specific clients
      lib/mysql/         # MySQL adapter
      lib/pg/            # PostgreSQL adapter
      lib/sqlite/        # SQLite adapter
    formulav2/           # Formula execution engine
    functionMappings/    # SQL function mappings per DB
    links/               # Relation management
    conditionV2.ts       # Filter/where condition builder
    sortV2.ts            # Sort builder
    aggregation.ts       # Aggregation builder
  mcp/              # MCP server implementation
  modules/          # NestJS modules (auth, jobs, oauth)
  integrations/     # Integration store
  plugins/          # Plugin system
  cache/            # Redis/memory cache layer
  schema/           # Schema management
```

### Key Design Patterns
1. **Meta-driven architecture** — All table/column/view definitions stored in meta tables, not code
2. **Database agnostic** — Knex.js + SQL client adapters for each database type
3. **Cache decorators** — `@NcCache` decorator for automatic caching on model methods
4. **Job system** — Async jobs for long-running operations (import, export, webhook delivery)
5. **Hook system** — Event-driven hooks with AppHooksService for cross-cutting concerns

### Data Flow
1. Request hits NestJS controller
2. Controller calls service with context (workspace, base, user)
3. Service uses Model classes (meta layer) to resolve table/column definitions
4. Service calls BaseModelSqlv2 for actual data operations
5. BaseModelSqlv2 builds Knex queries using the resolved meta
6. Result goes through response serialization (virtual columns computed)

## Frontend (Nuxt 3)

### Directory Layout
```
nc-gui/
  pages/           # Route pages (signin, signup, dashboard)
  components/      # Vue components
    smartsheet/    # Grid/form/gallery/kanban components
    cell/          # Cell renderers per field type
    dashboard/     # Dashboard layout
    dlg/           # Dialogs
    shared-view/   # Public view components
  composables/     # Vue composables (state/logic)
  store/           # Pinia stores
  lib/             # Utility libraries
  workers/         # Web workers (for heavy computation)
```

### Key Frontend Architecture
1. **Canvas-based grid** — The spreadsheet grid is rendered on HTML5 Canvas, NOT DOM elements
2. **Pinia stores** — State management for bases, tables, views, data
3. **Composables** — Reusable logic (useData, useCalendarViewStore, useExpandedFormStore)
4. **Windi CSS** — Utility-first CSS framework

## SDK

The `nocodb-sdk` package provides:
- **UITypes enum** — All 30+ field types with metadata
- **Formula engine** — Parser, validator, type inference (65 functions)
- **SQL UI adapters** — Database-specific type mappings (MySQL, PG, SQLite, Oracle, Snowflake, Databricks)
- **Filter/sort utilities** — Client-side filtering and sorting
- **API types** — Auto-generated TypeScript types from OpenAPI spec

## Canvas Grid Architecture

The grid uses HTML5 Canvas for rendering, which provides:
- **Performance** — Can render thousands of rows without DOM overhead
- **Smooth scrolling** — No virtual scroll jank
- **Custom rendering** — Full control over cell rendering (colors, icons, badges)

Trade-off: Canvas elements are not accessible to screen readers or browser DevTools inspection.

## MCP Server

NocoDB includes a built-in MCP (Model Context Protocol) server:
- **Transport:** StreamableHTTP (same as OpenRegister)
- **Per-base tokens** — Each MCP token is scoped to a base
- **Tools:** getBaseInfo, listTables, readRecords, createRecords, updateRecords, deleteRecords
- **Role-based:** Editor+ can write, others can only read
- **Server per request** — New McpServer instance per request (stateless)
