---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Search and Export

## Summary

Baserow provides PostgreSQL full-text search across table rows and view-based data export. Search uses tsvector columns for performance. Export supports CSV with view filter/sort applied.

## Search

Located at `backend/src/baserow/contrib/database/search/`

### Architecture
```
search/
  handler.py       # SearchHandler with multiple search modes
  models.py        # Search-related models
  expressions.py   # PostgreSQL search expressions
  regexes.py       # Search query parsing
  receivers.py     # Signal handlers for index updates
  tasks.py         # Background index update tasks
```

### Search Modes
- Multiple search modes defined in `SearchHandler`
- Full-text search via PostgreSQL `tsvector` columns
- Each table has a `tsv` column for pre-computed search vectors
- Background tasks update search indexes on row changes

### Search Features
- Cross-field search (searches all text-compatible fields)
- Search query parsing with special characters
- Per-field search contribution
- API parameter: `?search=query`
- Search mode selection: `?search_mode=full-text-with-count`

### Index Management
- tsvector columns added per table
- Indexes updated on row create/update/delete
- Background task processing for bulk operations
- Signal-based reactive index updates

## Export

Located at `backend/src/baserow/contrib/database/export/`

### Architecture
```
export/
  handler.py          # Export orchestration
  registries.py       # Exporter type registry
  file_writer.py      # File writing abstraction
  table_exporters/
    csv_table_exporter.py  # CSV export
  models.py           # Export job models
  tasks.py            # Async export tasks
```

### Export Features
- View-based export (applies view filters/sorts)
- CSV format (core)
- JSON and Excel export (premium/enterprise)
- Async export via Celery jobs
- Download link generation
- Export includes field names and formatted values

### Export Process
1. User requests export for a view
2. Export job created (async)
3. Celery worker generates file
4. File stored in configured storage backend
5. Download URL returned to user

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| Search | PostgreSQL full-text search with tsvector | JSON property search, faceting |
| Search index | Pre-computed tsvector per table | No dedicated search index |
| Export formats | CSV (core), JSON/Excel (premium) | N/A |
| Export scope | Per-view with filters | N/A |
| Search API | `?search=` query parameter | Various search endpoints |
| Async export | Celery job-based | N/A |

Baserow's PostgreSQL-native full-text search is performant for large datasets. OpenRegister's faceted search approach is more flexible for catalog use cases but less performant for free-text search.
