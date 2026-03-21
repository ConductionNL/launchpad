---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# Collections System

## Summary
PocketBase organizes data into **Collections**, which are analogous to database tables. Each collection has a name, type (Base/Auth/View), field definitions stored as JSON, and per-operation API access rules. Creating a collection automatically creates the corresponding SQLite table and REST API endpoints.

## Key Features
- Three collection types: **Base** (data), **Auth** (user accounts), **View** (SQL views)
- Fields defined as JSON array in `_collections` table
- 13 field types: text, editor, number, bool, email, URL, datetime, autodate, select, file, relation, JSON, password, geo_point
- System collections (prefixed with `_`) are protected from deletion
- Auto-generated `id`, `created`, `updated` system fields
- Collection export/import as JSON for environment portability

## Architecture
- `core/collection_model.go` (1073 lines) - Collection struct with fields, rules, options
- `core/collection_validate.go` (694 lines) - Validation logic
- `core/collection_record_table_sync.go` - DDL sync between collection definition and SQLite table
- `core/collection_query.go` - Query builder for collection operations

## Relevance to OpenRegister
OpenRegister's Schema/Register model is conceptually similar but more hierarchical. PocketBase's flat collection model is simpler but lacks OpenRegister's multi-register organization and JSON Schema validation.
