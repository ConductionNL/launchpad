---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# View Collections

## Summary
PocketBase supports View collections that are backed by SQL views rather than physical tables. This allows creating read-only computed datasets from one or more base collections.

## Key Features
- View collections are SELECT-based SQL views
- Read-only (no create/update/delete API)
- Can join multiple collections
- Inherit field types from the underlying query
- Support all list/search/filter operations
- Views are materialized as SQLite views
- Protection against SQL injection via statement detection

## Architecture
- `core/view.go` (640 lines) - View creation, deletion, and query inference
- `core/collection_model_view_options.go` - View-specific collection options
- Views stored in SQLite as `CREATE VIEW` statements

## Relevance to OpenRegister
OpenRegister's "view" concept is implicit through schema queries. PocketBase's explicit View collections provide a cleaner abstraction for computed/aggregated data. This could inspire OpenRegister to add first-class view support.
