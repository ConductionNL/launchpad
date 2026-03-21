---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# REST API

## Summary

Baserow provides a comprehensive REST API built on Django REST Framework (DRF) with auto-generated OpenAPI documentation. The API supports CRUD for all entities, with advanced filtering, sorting, pagination, field name modes, and webhook event control.

## API Structure

Base URL: `/api/`

### Core Endpoints
- `POST /api/user/` - User registration
- `POST /api/user/token-auth/` - JWT authentication
- `POST /api/user/token-refresh/` - Token refresh
- `GET /api/settings/` - Instance settings
- `GET /api/workspaces/` - List workspaces

### Database Endpoints
Located in `backend/src/baserow/contrib/database/api/urls.py`:

- `/api/database/tables/` - Table CRUD
- `/api/database/fields/` - Field CRUD (per table)
- `/api/database/rows/` - Row CRUD (per table)
- `/api/database/views/` - View CRUD (per table)
- `/api/database/tokens/` - API token management
- `/api/database/webhooks/` - Webhook configuration
- `/api/database/export/` - Data export
- `/api/database/formula/` - Formula operations
- `/api/database/data-sync/` - Data sync configuration
- `/api/database/field-rules/` - Conditional field rules

### Row API Features

**Filtering** (via query parameters):
- `filter__field_{id}__{type}=value`
- Filter types: `equal`, `not_equal`, `contains`, `contains_not`, `higher_than`, `lower_than`, `date_equal`, `date_before`, `date_after`, `single_select_equal`, `boolean`, `empty`, `not_empty`, `link_row_has`, `multiple_select_has`, and 30+ more
- `filter_type=AND|OR` for combining filters

**Sorting**:
- `order_by=field_{id}` or `order_by=-field_{id}` (descending)
- Multiple sort fields: `order_by=field_1,-field_2`

**Pagination**:
- `LimitOffsetPagination`: `?limit=20&offset=0`
- `PageNumberPagination`: `?page=1&size=20`

**Search**:
- `search=query` parameter with configurable search modes
- Full-text search via PostgreSQL tsvector

**Field Names**:
- `user_field_names=true` - use human-readable field names instead of `field_{id}`

**Include/Exclude Fields**:
- `include=field_1,field_2` - only return specific fields
- `exclude=field_3` - exclude specific fields

**Link Row Joins**:
- `join=field_{id}` - include linked row data inline (like SQL JOIN)

**Row Metadata**:
- `include=row_metadata` - include metadata like comments count

## Authentication Methods

1. **JWT Token**: `Authorization: JWT <token>` - for user sessions
2. **Database Token**: `Authorization: Token <key>` - for API access with per-table permissions (CRUD granularity)

## API Tokens

- Scoped to workspace
- Per-table permission control: create, read, update, delete
- Can be scoped to all tables, specific database, or specific table
- Track usage: `handled_calls` and `last_call`
- Model: `backend/src/baserow/contrib/database/tokens/models.py`

## OpenAPI Documentation

- Auto-generated via `drf-spectacular`
- Available at `/api/redoc/` and `/api/schema/`
- Dynamic schema based on table/field configuration

## Batch Operations

- `POST /api/database/rows/table/{id}/batch/` - Create multiple rows
- `PATCH /api/database/rows/table/{id}/batch/` - Update multiple rows
- `POST /api/database/rows/table/{id}/batch-delete/` - Delete multiple rows

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| API framework | DRF with OpenAPI auto-generation | Nextcloud OCS + custom REST |
| Authentication | JWT + Database Tokens | Nextcloud auth + Basic Auth |
| Filtering | 40+ filter types via query params | Basic property filtering |
| Pagination | Limit/offset + page number | Limit/offset |
| Search | PostgreSQL full-text search | JSON property search |
| Batch ops | Create/update/delete batches | Single operations |
| Field name modes | ID-based or human-readable | Property-based |
| Token permissions | Per-table CRUD granularity | Per-register access |
