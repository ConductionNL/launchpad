# Directus - Competitive Analysis Overview

**Repository:** https://github.com/directus/directus
**Analyzed:** 2026-03-14
**Version:** Latest main branch (monorepo)
**License:** BSL 1.1 (transitions to GPLv3 after 3 years)

## Executive Summary

Directus is a mature, open-source "Data Platform" that wraps any SQL database with a REST + GraphQL API layer and a Vue 3 admin app. It positions itself as a headless CMS / backend-as-a-service, but its schema-driven approach makes it a direct competitor to OpenRegister for data management use cases.

**Key differentiator vs OpenRegister:** Directus operates at the database level (introspects existing SQL tables), while OpenRegister operates at the application level (JSON schemas stored as metadata). Directus requires direct database access; OpenRegister abstracts storage behind Nextcloud.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Runtime | Node.js (ESM) |
| API Framework | Express.js |
| Database ORM/Query | Knex.js (multi-DB) |
| Frontend | Vue 3 + Composition API |
| Build | pnpm monorepo, Vite (app), Rollup/Rolldown (extensions) |
| Languages | TypeScript throughout |
| Testing | Vitest (unit), Blackbox tests (integration) |
| Real-time | WebSocket (ws library) |
| File processing | Sharp (images) |
| Caching | Keyv (Redis/memory) |
| Validation | Joi, Zod |

## Supported Databases

Directus uses Knex.js to support:
- PostgreSQL (+ PostGIS)
- MySQL 5.7 / 8
- MariaDB
- SQLite
- Microsoft SQL Server
- Oracle DB
- CockroachDB

## Architecture Overview

```
directus-monorepo/
  api/              # Express.js backend (core)
    src/
      controllers/  # Route handlers (REST endpoints)
      services/     # Business logic layer
      database/     # Knex migrations, AST query builder
      permissions/  # ABAC permission engine
      extensions/   # Extension manager + sandbox
      operations/   # Built-in Flow operations
      websocket/    # WebSocket controllers
      ai/           # AI/MCP integration
      auth/         # Auth drivers (local, LDAP, OAuth2, OIDC, SAML)
      storage/      # Storage driver abstraction
  app/              # Vue 3 admin UI
    src/
      modules/      # Top-level navigation sections
      interfaces/   # 43 form input components
      displays/     # 20 display renderers
      layouts/      # 6 collection view layouts
      panels/       # 12 dashboard panel types
  sdk/              # TypeScript SDK for API consumers
  packages/         # Shared packages
    types/          # Shared TypeScript types
    constants/      # Shared constants
    schema/         # Database schema inspector
    extensions/     # Extension utilities
    extensions-sdk/ # SDK for extension developers
    storage/        # Storage driver interface
    storage-driver-*/ # S3, GCS, Azure, Local, Cloudinary, Supabase
    validation/     # Validation utilities
    env/            # Environment configuration
    errors/         # Error types
    specs/          # OpenAPI base spec
```

## System Collections (directus_ prefix)

Directus stores all metadata in `directus_*` system tables:

| Table | Purpose |
|-------|---------|
| `directus_collections` | Collection metadata (icon, color, sort, etc.) |
| `directus_fields` | Field metadata (interface, display, validation, etc.) |
| `directus_relations` | Relationship definitions |
| `directus_roles` | User roles (hierarchical) |
| `directus_policies` | Access policies (with IP restrictions) |
| `directus_permissions` | Per-collection, per-action permission rules |
| `directus_users` | User accounts |
| `directus_sessions` | Active sessions |
| `directus_files` | File/asset metadata |
| `directus_folders` | Virtual folder hierarchy |
| `directus_flows` | Automation flow definitions |
| `directus_operations` | Flow operation nodes |
| `directus_dashboards` | Analytics dashboard definitions |
| `directus_panels` | Dashboard panel configurations |
| `directus_activity` | Audit log |
| `directus_revisions` | Data revision history |
| `directus_presets` | Saved collection view presets |
| `directus_settings` | Global settings singleton |
| `directus_translations` | Custom string translations |
| `directus_notifications` | In-app notifications |
| `directus_shares` | Public share links |
| `directus_versions` | Content versioning |

## Feature Comparison Matrix (vs OpenRegister)

| Feature | Directus | OpenRegister |
|---------|----------|-------------|
| Schema definition | Database-level (Knex DDL) | JSON Schema (application-level) |
| Database support | 7+ SQL databases | Nextcloud DB (MySQL/PostgreSQL/SQLite) |
| API | REST + GraphQL + MCP | REST + MCP |
| Auth | JWT + SSO (LDAP/OAuth2/OIDC/SAML) | Nextcloud auth (delegated) |
| Permissions | ABAC (filter-based per action) | Nextcloud groups + row-level |
| Real-time | WebSocket subscriptions | Not yet |
| File management | Multi-driver asset storage | Nextcloud Files integration |
| Automation | Directus Flows (visual) | n8n integration |
| Extensions | 10 extension types | Nextcloud app ecosystem |
| Content versioning | Built-in | Not yet |
| Deployment | Standalone container | Nextcloud app (ExApp) |
| AI/MCP | Built-in MCP server | Built-in MCP server |
| Multi-tenancy | Single DB per instance | Multi-register per Nextcloud |
| Import/Export | CSV, JSON, XML, YAML | CSV, JSON |
| Dashboards | Built-in Insights | Not built-in |
| i18n content | Built-in translations | Not built-in |

## Strengths (vs OpenRegister)

1. **Mature query engine** - AST-based query builder with deep relational joins, aggregation functions, and geospatial support
2. **GraphQL API** - Auto-generated from schema with subscriptions
3. **Rich admin UI** - 43 interfaces, 20 displays, 6 layouts, 12 panel types
4. **Multi-database** - Works with any existing SQL database
5. **Visual automation** - Directus Flows with 15 built-in operations
6. **Content versioning** - Draft/publish workflow built-in
7. **Asset pipeline** - Sharp-based image transformations on-the-fly
8. **Extension ecosystem** - Sandboxed extension runtime with marketplace

## Weaknesses (vs OpenRegister)

1. **No Nextcloud integration** - Cannot leverage Nextcloud's collaboration features, file sharing, or user management
2. **BSL license** - Not truly open source until 3 years after release
3. **Heavy runtime** - Full Node.js server vs lightweight PHP app
4. **Single-purpose** - Standalone system vs integrated platform component
5. **No government theming** - No NL Design System support
6. **Database-coupled** - Schema changes require DDL migrations vs JSON Schema flexibility
7. **No document generation** - No DocuDesk equivalent for template-based output
8. **Complex deployment** - Requires separate infrastructure vs Nextcloud one-click install
