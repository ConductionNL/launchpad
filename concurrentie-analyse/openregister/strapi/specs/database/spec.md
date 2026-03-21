---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Database Layer

## Overview

Strapi's database layer (`@strapi/database`) is a custom ORM built on top of Knex.js. It provides entity management, automatic schema migration via diff-based syncing, lifecycle hooks, and multi-dialect support. Unlike ORMs like TypeORM or Prisma, Strapi's database layer is tightly integrated with its content type system and handles schema changes automatically.

## Supported Databases

| Database | Dialect | Notes |
|----------|---------|-------|
| PostgreSQL | `postgresql` | Recommended for production |
| MySQL | `mysql` | Full support |
| MariaDB | `mysql` dialect | Uses MySQL dialect |
| SQLite | `sqlite` | Default for development |

Each dialect has its own implementation in `packages/core/database/src/dialects/`:
- **Schema inspector**: Introspects existing database schema
- **Type mapping**: Maps Strapi types to database column types
- **Initialization**: Per-connection setup (e.g., `PRAGMA` for SQLite, `SET` for PostgreSQL)

## Architecture

```
@strapi/database
  +-- Database (main class)
  |   +-- connection (Knex instance)
  |   +-- dialect (database-specific logic)
  |   +-- metadata (model definitions)
  |   +-- schema (schema provider: sync, diff, build)
  |   +-- migrations (migration runner)
  |   +-- lifecycles (lifecycle hook provider)
  |   +-- entityManager (CRUD operations)
  |   +-- repair (data repair tools)
```

## Entity Manager

The Entity Manager provides the low-level CRUD API:

```typescript
// Query builder pattern
const articles = await strapi.db.query('api::article.article').findMany({
  where: { title: { $contains: 'hello' } },
  orderBy: { createdAt: 'desc' },
  limit: 10,
  offset: 0,
  populate: { category: true },
});

// Single operations
await strapi.db.query('api::article.article').create({ data: {...} });
await strapi.db.query('api::article.article').update({ where: { id: 1 }, data: {...} });
await strapi.db.query('api::article.article').delete({ where: { id: 1 } });
await strapi.db.query('api::article.article').count({ where: {...} });
```

## Query Builder

The query builder (`packages/core/database/src/query/query-builder.ts`) translates Strapi's query format to Knex.js:

### Where Clause Processing
Complex filter tree processing:
- Attribute-level operators (`$eq`, `$contains`, `$gt`, etc.)
- Logical operators (`$and`, `$or`, `$not`)
- Relation filters (joins to related tables)
- Type casting per field type
- Sub-query support via Knex query objects

### Join Handling
- Automatic join generation for relation filters
- Alias management for multiple joins
- Support for deep relation traversal

## Schema Synchronization

Strapi uses a **diff-based auto-migration** system instead of traditional migration files:

### Flow
1. **Metadata to Schema**: Convert content type metadata to database schema definition
2. **Schema Inspection**: Read current database schema via dialect's schema inspector
3. **Schema Diff**: Compare expected schema with actual database schema
4. **Generate Changes**: Create a list of add/remove/modify operations for tables, columns, indexes, foreign keys
5. **Apply Changes**: Execute DDL statements via Knex schema builder

### Diff Algorithm
```typescript
interface SchemaDiff {
  status: 'CHANGED' | 'UNCHANGED';
  diff: {
    tables: {
      added: Table[];
      removed: Table[];
      changed: TableDiff[];   // Columns, indexes, FKs changed
    };
  };
}
```

### Configuration
```typescript
// config/database.ts
export default {
  connection: {
    client: 'postgres',
    connection: {
      host: env('DATABASE_HOST', '127.0.0.1'),
      port: env.int('DATABASE_PORT', 5432),
      database: env('DATABASE_NAME', 'strapi'),
      user: env('DATABASE_USERNAME', 'strapi'),
      password: env('DATABASE_PASSWORD', 'strapi'),
      ssl: env.bool('DATABASE_SSL', false),
    },
  },
  settings: {
    forceMigration: true,    // Apply migrations on boot
    runMigrations: true,     // Run migration files
  },
};
```

## Migration System

Beyond auto-sync, Strapi supports user-written migrations:

### Migration Files
Located in `database/migrations/`:
```typescript
export async function up(knex: Knex): Promise<void> {
  await knex.schema.alterTable('articles', (table) => {
    table.string('slug').unique();
  });
}

export async function down(knex: Knex): Promise<void> {
  await knex.schema.alterTable('articles', (table) => {
    table.dropColumn('slug');
  });
}
```

### Internal Migrations
Strapi also has internal migrations (`packages/core/database/src/migrations/internal-migrations/`) for framework-level schema changes between versions.

### Migration Storage
Migration state is tracked in `strapi_migrations` table.

## Transaction Support

```typescript
await strapi.db.transaction(async ({ trx }) => {
  await strapi.db.query('api::article.article').create({
    data: { title: 'Hello' },
  }, { transaction: trx });
});
```

Uses `AsyncLocalStorage` for transaction context propagation.

## Data Repair

The repair system (`packages/core/database/src/repairs/`) handles data integrity issues:
- Orphaned relation cleanup
- Invalid foreign key resolution
- Schema-data consistency checks

## Relevance to OpenRegister

**Key differences:**
- Strapi uses Knex.js directly; OpenRegister uses Nextcloud's DB abstraction (Doctrine DBAL)
- Strapi auto-syncs schema on boot; OpenRegister manages schema-to-table mapping dynamically
- Strapi's migration system is Knex-native; OpenRegister uses Nextcloud's migration framework

**Features to note:**
- The diff-based auto-migration is elegant but can be dangerous in production
- Transaction context via AsyncLocalStorage is a clean pattern
- Schema inspection per-dialect is well-abstracted
- The query builder's filter operator set is comprehensive
- Data repair tools are a thoughtful addition for production reliability
