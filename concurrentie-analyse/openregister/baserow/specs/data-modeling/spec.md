---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Data Modeling

## Summary

Baserow's data model follows a hierarchy: Workspace > Database (Application) > Table > Field/Row. Each table is backed by a real PostgreSQL table with dynamically managed columns. This enables native SQL performance for all CRUD operations, filtering, sorting, and aggregations.

## Core Models

### Workspace
- Top-level organizational unit (replaces older "Group" concept)
- Has members with roles (Admin, Member; enterprise adds custom roles)
- Contains applications (databases, builders, automations, dashboards)

### Database (Application)
- Inherits from `Application` base model (polymorphic)
- Contains multiple tables
- Located at: `backend/src/baserow/contrib/database/models.py`

### Table
- Represents a user table; each creates a real PostgreSQL table
- Table name format: `database_table_{id}`
- Has ordering, created/updated timestamps
- Supports full-text search via `tsvector` columns
- Model at: `backend/src/baserow/contrib/database/table/models.py`

### Field
- Polymorphic base model with content type resolution
- Each field = one PostgreSQL column in the dynamic table
- Supports ordering, primary field designation
- Field names stored as `field_{id}` in PostgreSQL, user-facing names stored separately
- Model at: `backend/src/baserow/contrib/database/fields/models.py`

### Row
- Not a Django model in the traditional sense
- Each table generates a dynamic Django model at runtime via `Table.get_model()`
- Rows are actual PostgreSQL rows in the dynamic table
- System columns: `id`, `order`, `created_on`, `updated_on`, `created_by`, `last_modified_by`

## Dynamic Model Generation

The `Table.get_model()` method dynamically creates a Django model class at runtime:

1. Queries all fields for the table
2. Creates Django model fields for each Baserow field
3. Generates a model class with the correct table name
4. Caches model field attributes for performance

This approach means:
- Each user table has a dedicated PostgreSQL table
- Standard SQL indexing, constraints, and query optimization apply
- Field types map to native PostgreSQL column types
- JOINs between linked tables use real foreign keys

## Link Rows (Relations)

- `LinkRowField` creates a many-to-many relationship between tables
- Creates a through table: `database_table_{id}_link_row_{field_id}`
- Supports self-referencing links
- Can create a reverse link field in the target table automatically
- Link rows enable lookups, rollups, and count fields

## Row History

- `RowHistory` model tracks field-level changes
- Records: user, table, row_id, field_names, before/after values, action type
- Supports undo/redo via action UUID tracking

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| Storage | Real PostgreSQL tables per user table | JSON objects in `oc_openregister_objects` |
| Schema enforcement | PostgreSQL DDL + Python validation | JSON Schema validation |
| Relations | M2M through tables with FK constraints | JSON reference fields |
| Row identity | Auto-increment integer PK | UUID primary key |
| History | RowHistory model with field-level diff | Nextcloud activity log |
| Performance | Native SQL queries, indexes | JSON column queries, partial indexing |
| Scale | Millions of rows per table (native SQL) | Limited by JSON query performance |
