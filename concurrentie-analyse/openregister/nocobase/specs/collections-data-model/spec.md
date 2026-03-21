---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Collections & Data Model

## Purpose

NocoBase's data model is built around "Collections" - an abstraction layer over database tables powered by Sequelize ORM. Collections define the structure of data including fields, relationships, inheritance, and tree structures.

## Architecture Overview

The Collection system lives in `@nocobase/database` and wraps Sequelize models with additional metadata, event hooks, and field management.

```
Collection
  ├── Fields (Map<string, Field>)
  ├── Model (Sequelize Model)
  ├── Repository (CRUD operations)
  └── Options (metadata, sortable, template, origin)
```

## Data Model

### Collection Types

1. **General Collection** - Standard database table with auto-generated ID, timestamps
2. **Inherited Collection** - Uses PostgreSQL table inheritance (`inherits` option). Child collections share parent columns and can add their own. Supports multi-level inheritance.
3. **Tree Collection** - Adjacency list pattern with `parentId` self-reference. Provides `TreeRepository` with `findTrees()`, `findRoots()`, `findChildren()`.
4. **SQL Collection** - Virtual collection backed by a raw SQL query. Read-only, no migration.
5. **View Collection** - Backed by a database view (`viewName` option).
6. **FDW Collection** - Foreign Data Wrapper for cross-database queries (PostgreSQL).

### Field Types (31 built-in)

| Category | Types |
|----------|-------|
| String | `string`, `text`, `password`, `uid`, `uuid`, `nanoid`, `snowflake-id` |
| Numeric | `number`, `unix-timestamp` |
| Boolean | `boolean`, `radio` (single-select) |
| Date/Time | `date`, `date-only`, `datetime`, `datetime-tz`, `datetime-no-tz`, `time` |
| Complex | `json`, `array`, `set`, `blob`, `virtual` |
| Relational | `belongs-to`, `has-one`, `has-many`, `belongs-to-many` |
| Special | `context` (auto-populated from request context), `sort` (ordering) |

### Relationship Definitions

```typescript
// Has Many
{ type: 'hasMany', name: 'posts', target: 'posts', foreignKey: 'userId' }

// Belongs To
{ type: 'belongsTo', name: 'user', target: 'users', foreignKey: 'userId' }

// Many to Many
{ type: 'belongsToMany', name: 'tags', target: 'tags', through: 'post_tags' }

// Has One
{ type: 'hasOne', name: 'profile', target: 'profiles', foreignKey: 'userId' }
```

### Interface System

Interfaces define how fields appear in the UI. Each database field type can have multiple interface presentations:

- `input` -> renders as text input
- `textarea` -> renders as multi-line text
- `select` / `multiple-select` -> renders as dropdown
- `checkboxes` -> renders as checkbox group
- `datetime` -> renders as date picker
- `many-to-one` -> renders as record picker
- `percent` -> renders as percentage input

## Business Logic

### Collection Lifecycle

1. `new Collection(options, context)` - Creates model, sets up fields
2. `collection.sync()` - Syncs to database (CREATE TABLE / ALTER TABLE)
3. `collection.setField(name, options)` - Adds or updates a field
4. `collection.removeField(name)` - Removes a field (with atomicity guarantee)
5. `collection.repository` - Provides CRUD operations

### MagicAttributeModel

NocoBase uses a `MagicAttributeModel` pattern where an `options` JSON column stores arbitrary extra attributes. This enables schema-less extension of any collection without database migrations.

### Field Validation

Fields support Joi-based validation rules that are automatically applied on create/update:
- Required, min/max length, pattern, enum values
- Custom validation functions

## Requirements

### Functional
- Create/edit/delete collections through UI or API
- Define field types with validation rules
- Configure relationships between collections
- Support PostgreSQL table inheritance
- Tree structure support (adjacency list)
- Virtual collections from SQL/views
- Sync collections from existing database tables

### Non-functional
- Schema changes applied without downtime
- Migration system for versioned schema evolution
- Atomic field operations (rollback on error)

## Comparison Notes

### vs OpenRegister Schemas
- NocoBase collections are tightly coupled to database tables; OpenRegister schemas are JSON Schema-based abstractions
- NocoBase inheritance uses PostgreSQL-specific features; OpenRegister uses JSON Schema `allOf` composition
- NocoBase has 31 field types; OpenRegister relies on JSON Schema types with format hints
- NocoBase has no concept of Registers (grouping schemas); collections are flat with categories
- OpenRegister supports object-level versioning; NocoBase does not track object history natively
