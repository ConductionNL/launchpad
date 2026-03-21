---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# DataStore Extension

## What It Does

The DataStore extension adds structured data storage to CKAN. While core CKAN treats resources as file references (URLs to CSVs, PDFs, etc.), DataStore creates actual PostgreSQL tables for tabular data, enabling SQL-like queries via the API.

## How It Works

DataStore provides its own action API (883 lines in `ckanext/datastore/logic/action.py`):

- `datastore_create` - Creates a PostgreSQL table linked to a CKAN resource. Supports field definitions (name, type, info), primary keys, indexes, aliases, and bulk record insertion.
- `datastore_upsert` - Insert or update records using primary key matching. Supports methods: `insert`, `upsert`, `update`.
- `datastore_delete` - Delete specific records via filters or drop the entire table.
- `datastore_search` - Query with filters, full-text search, sorting, field selection, pagination.
- `datastore_search_sql` - Execute read-only SQL queries directly against DataStore tables.
- `datastore_info` - Return table metadata (fields, types, record count).

The backend uses PostgreSQL directly (not via SQLAlchemy ORM) with parameterized queries. The `DatastoreBackend` class provides an abstraction layer, with `ckanext/datastore/backend/postgres.py` as the default implementation.

DataStore integrates with DataPusher/xloader to automatically parse uploaded CSV/Excel files into DataStore tables.

## Key Source Files
- `ckanext/datastore/logic/action.py` (883 lines) - DataStore CRUD actions
- `ckanext/datastore/backend/postgres.py` - PostgreSQL backend implementation
- `ckanext/datastore/logic/schema.py` - Input validation schemas
- `ckanext/datastore/interfaces.py` - Plugin hooks for DataStore

## Relevance to OpenRegister

DataStore is the closest CKAN feature to OpenRegister's schema-driven object storage. Both store structured records queryable via API. Key differences: OpenRegister validates records against JSON Schema definitions; DataStore has no schema validation beyond PostgreSQL column types. OpenRegister's approach provides stronger data quality guarantees.
