---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# Search and Filtering

## Summary
PocketBase provides a custom filter expression language for searching and filtering records. Filters are compiled to SQL WHERE clauses and support nested field access, relation traversal, and various operators.

## Key Features
- Custom filter syntax: `field operator value` with `&&` (AND) and `||` (OR)
- Operators: `=`, `!=`, `>`, `<`, `>=`, `<=`, `~` (LIKE), `!~`, `?=` (any match), `?!=`, `?~`, `?!~`
- Nested relation field access: `author.name = "John"`
- Special fields: `@request.auth.id`, `@request.auth.collectionId`, `@collection.products.name`
- `@random` sort for random ordering
- `@rowid` for efficient sorting without covering index
- Text search within filter expressions
- Hidden fields searchable only by superusers
- RecordFieldResolver translates field references to SQL column paths

## Architecture
- `tools/search/` - Search provider with pagination, sort, filter
- `tools/search/filter.go` - Filter expression parser (fexpr library)
- `core/record_field_resolver.go` - Resolves field names to SQL expressions
- `core/record_field_resolver_runner.go` (853 lines) - Runs field resolution with join generation

## Relevance to OpenRegister
OpenRegister uses Solr/Elasticsearch for search. PocketBase's SQLite-based search is simpler but lacks faceting, relevance scoring, and full-text search capabilities that OpenRegister provides through external search engines.
