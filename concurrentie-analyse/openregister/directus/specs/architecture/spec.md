---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Architecture & Tech Stack

## Overview

Directus is a Node.js/TypeScript monorepo consisting of an Express.js API backend, a Vue 3 admin application, a TypeScript SDK, and ~30 shared packages. It uses Knex.js as its database abstraction layer, supporting 7+ SQL databases.

## Monorepo Structure

The repository uses pnpm workspaces with the following top-level packages:

- **`api/`** - Express.js backend (~50k LOC TypeScript)
- **`app/`** - Vue 3 admin UI (~80k LOC TypeScript/Vue)
- **`sdk/`** - TypeScript client SDK
- **`packages/`** - 30+ shared packages (types, constants, schema, storage drivers, extensions SDK, validation, env)
- **`tests/`** - Blackbox integration tests
- **`directus/`** - CLI entry point / distribution package

## Backend Architecture

The API follows a layered architecture:

1. **Controllers** (route handlers) - Express routers that parse requests, instantiate services, and format responses
2. **Services** (business logic) - Classes that enforce permissions, validate payloads, and orchestrate database operations
3. **Database** (data access) - Knex.js query builder with AST-based query compilation

Key architectural patterns:
- **Service-per-collection**: `ItemsService` is the base class; system collections have specialized services (e.g., `UsersService extends ItemsService`)
- **AST query model**: Queries are parsed into an AST, permissions are applied as AST transformations, then the AST is compiled to Knex SQL
- **Event-driven**: An `Emitter` singleton provides `onAction`/`onFilter` hooks that extensions and Flows subscribe to
- **Transaction-wrapped mutations**: All write operations use database transactions for atomicity

## Frontend Architecture

The admin app is a Vue 3 SPA with:
- Composition API throughout
- Pinia stores for state management
- Custom router with permission-based guards
- Component-based extension system (interfaces, displays, layouts, panels, modules)

## Key Dependencies

| Dependency | Purpose |
|-----------|---------|
| Knex.js | SQL query builder, migrations, multi-DB support |
| Express.js | HTTP server and routing |
| Sharp | Image transformation pipeline |
| ws | WebSocket server |
| jsonwebtoken | JWT authentication |
| argon2 | Password hashing |
| Joi / Zod | Schema validation |
| isolated-vm | Sandboxed extension execution |
| Keyv | Cache abstraction (Redis/memory) |
| Rollup / Rolldown | Extension bundling |
| graphql-compose | GraphQL schema generation |

## Database Design

Directus stores all metadata in `directus_*` prefixed tables (23 system tables). User data lives in custom-named tables that Directus introspects at runtime. This means Directus can be "dropped onto" an existing database and will wrap it with APIs.

The schema is inspected via `@directus/schema` package which provides a cross-database `SchemaInspector` that reads table/column/index/foreign-key metadata from each database's information_schema or equivalent.

## Relevance to OpenRegister

OpenRegister takes a fundamentally different approach:
- **Application-level schema**: JSON Schemas stored as data, not database DDL
- **Nextcloud-native**: Leverages Nextcloud auth, files, sharing, and app ecosystem
- **PHP runtime**: Lighter weight, runs within existing Nextcloud infrastructure
- **Single database**: Uses whatever database Nextcloud is configured with

Directus's architecture is more suitable for greenfield projects or wrapping existing databases. OpenRegister is better suited for extending Nextcloud with structured data capabilities.
