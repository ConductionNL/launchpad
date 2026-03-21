---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# Auto-Generated REST API

## Summary
PocketBase automatically generates full CRUD REST endpoints for every collection. The API includes pagination, sorting, filtering, field selection, relation expansion, and batch operations -- all without writing any code.

## Key Features
- Automatic CRUD endpoints: `GET` (list/view), `POST` (create), `PATCH` (update), `DELETE`
- Filter syntax with operators: `=`, `!=`, `>`, `<`, `>=`, `<=`, `~` (contains), `?=` (any), `?~` (any contains)
- Sorting with `+`/`-` prefix for ASC/DESC
- Relation expansion up to 6 levels (`?expand=author,author.company`)
- Field selection with `:excerpt()` modifier
- `skipTotal` for cursor-based pagination performance
- Batch API for transactional multi-operation requests
- PUT upsert (create or update based on ID)

## Architecture
- `apis/record_crud.go` - List, view, create, update, delete handlers
- `apis/batch.go` - Batch transaction handler with regex-matched routes
- `tools/search/` - Search provider with filter parsing and SQL generation
- `core/record_field_resolver.go` - Field reference resolution for queries

## API Preview
The admin dashboard includes an interactive API Preview panel showing:
- SDK code samples (JavaScript and Dart)
- Full parameter documentation
- Example response JSON
- Per-operation access rules

## Relevance to OpenRegister
OpenRegister generates similar CRUD APIs via its register/schema system. PocketBase's API Preview with inline SDK samples is a feature OpenRegister could adopt for its MCP discovery endpoint.
