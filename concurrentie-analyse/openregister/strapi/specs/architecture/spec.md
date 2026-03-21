---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Architecture

## Overview

Strapi v5 is a Node.js/TypeScript monorepo application built on Koa.js as the HTTP framework. The core is structured around a Container pattern where the main `Strapi` class acts as a dependency injection container, registering and resolving services, plugins, middlewares, and providers.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Runtime | Node.js >= 20 |
| Language | TypeScript 5.4 |
| HTTP Framework | Koa.js |
| Database ORM | Knex.js (custom wrapper in `@strapi/database`) |
| Admin Frontend | React 18 |
| Build System | Nx 20 + Rollup + SWC |
| Package Manager | Yarn 4 (Berry) with workspaces |
| Testing | Jest + Vitest + Playwright (E2E) |
| Validation | Zod v4 |

## Monorepo Structure

```
strapi/
  packages/
    core/           # Core framework packages (15 packages)
      strapi/       # CLI + bootstrapper
      core/         # Main Strapi class, server, services
      database/     # Knex.js ORM layer
      admin/        # React admin panel
      content-type-builder/  # Schema editor
      content-manager/       # Content CRUD + history
      content-releases/      # Scheduled publishing (EE)
      review-workflows/      # Review stages (EE)
      upload/       # Media library
      permissions/  # ABAC permission engine
      openapi/      # OpenAPI spec generation
      types/        # Shared TypeScript types
      utils/        # Shared utilities
    plugins/        # Official plugins (7 packages)
    providers/      # Storage + email providers (8 packages)
    utils/          # Dev utilities (eslint, tsconfig, etc.)
    cli/            # CLI tooling
    generators/     # Code generators (plop)
    admin-test-utils/  # Test helpers
  templates/        # Starter project templates
  examples/         # Example applications
  tests/            # Integration + E2E tests
```

## Core Container Architecture

The `Strapi` class extends `Container` and serves as the central registry:

```
Strapi (Container)
  +-- db (Database)          # Knex.js database instance
  +-- server (Server)        # Koa HTTP server
  +-- documents (Service)    # Document Service (v5 primary data access)
  +-- entityService          # Legacy entity service (deprecated in v5)
  +-- eventHub               # Event pub/sub system
  +-- webhookRunner          # Webhook dispatch
  +-- requestContext          # AsyncLocalStorage for request context
  +-- customFields           # Custom field registry
  +-- features               # Feature flag service
  +-- contentAPI             # Content API sanitization/validation
  +-- plugins                # Plugin registry
  +-- admin                  # Admin module
  +-- cron                   # Cron job service
```

## Service Registries

Strapi uses dedicated registries for each extension point:

- **plugins** - Plugin modules (`plugin::name`)
- **apis** - User-defined APIs (`api::name`)
- **content-types** - Schema definitions
- **components** - Reusable component schemas
- **controllers** - Request handlers
- **services** - Business logic
- **middlewares** - Koa middleware stack
- **policies** - Authorization policies
- **hooks** - Lifecycle hooks
- **models** - Database models
- **sanitizers** - Data sanitization
- **validators** - Data validation

## Boot Sequence

1. Load configuration (env, database, server, admin, API settings)
2. Register internal services (db, server, event hub, etc.)
3. Initialize providers (config, content API, cron, telemetry, webhooks)
4. Load plugins (scan `node_modules` + local plugins directory)
5. Load APIs (scan `src/api/` directory)
6. Load components (scan `src/components/` directory)
7. Load middlewares and policies
8. Sync database schema (auto-migration via diff algorithm)
9. Run plugin bootstrap hooks
10. Start Koa HTTP server

## Enterprise Edition (EE)

Strapi has a dual-license model. The `strapi.EE` flag gates enterprise features:
- Content Releases (scheduled publishing)
- Review Workflows (multi-stage approval)
- Audit Logs
- SSO (SAML, OAuth)
- AI features (content translation)

EE features check `strapi.ee.features.isEnabled('feature-name')` at module load time.

## Relevance to OpenRegister

**Key architectural differences:**
- Strapi is standalone; OpenRegister embeds in Nextcloud
- Strapi uses file-based schema definitions; OpenRegister stores schemas in the database
- Strapi requires restart for schema changes; OpenRegister applies them at runtime
- Strapi has its own user management; OpenRegister leverages Nextcloud's

**Patterns to consider adopting:**
- Container/registry pattern for modular service composition
- Auto-generated API routes from schema definitions
- Middleware pipeline for request processing
- Event hub pattern for decoupled feature integration
