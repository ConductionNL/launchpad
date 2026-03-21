---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# REST API

## Overview

NocoDB auto-generates a full REST API for every table. The API supports CRUD operations, filtering, sorting, pagination, and nested data. API documentation is available via Swagger/OpenAPI, and code snippets are generated in 9 programming languages.

## API Versions

- **v1** — Legacy API (deprecated)
- **v2** — Current stable API
- **v3** — Next-gen API with improved patterns

## Endpoints

### Data Operations
- `GET /api/v2/db/data/noco/{baseId}/{tableId}` — List records
- `POST /api/v2/db/data/noco/{baseId}/{tableId}` — Create record(s)
- `PATCH /api/v2/db/data/noco/{baseId}/{tableId}/{rowId}` — Update record
- `DELETE /api/v2/db/data/noco/{baseId}/{tableId}` — Delete record(s)

### Bulk Operations
- `POST /api/v2/db/data/bulk/noco/{baseId}/{tableId}` — Bulk insert
- `PATCH /api/v2/db/data/bulk/noco/{baseId}/{tableId}` — Bulk update
- `DELETE /api/v2/db/data/bulk/noco/{baseId}/{tableId}` — Bulk delete

### Meta Operations
- `GET /api/v2/db/meta/projects/` — List bases
- `GET /api/v2/db/meta/projects/{baseId}/tables` — List tables
- `GET /api/v2/db/meta/tables/{tableId}` — Get table with columns
- `POST /api/v2/db/meta/tables/{tableId}/columns` — Create column
- `PATCH /api/v2/db/meta/columns/{columnId}` — Update column

### View Operations
- `GET /api/v2/db/meta/tables/{tableId}/views` — List views
- `POST /api/v2/db/meta/tables/{tableId}/grids` — Create grid view
- `POST /api/v2/db/meta/tables/{tableId}/forms` — Create form view

## Query Parameters

### Filtering
- `where=(Status,eq,Done)` — Single condition
- `where=(Status,eq,Done)~and(Priority,gt,3)` — AND conditions
- `where=(Status,eq,Done)~or(Status,eq,Todo)` — OR conditions
- Operators: eq, neq, gt, gte, lt, lte, like, nlike, is, isnot, in, notin, btw, nbtw

### Sorting
- `sort=-Priority` — Sort descending by Priority
- `sort=Title` — Sort ascending by Title
- `sort=-Priority,Title` — Multi-sort

### Pagination
- `offset=0&limit=25` — Offset-based pagination
- Response includes `pageInfo: { totalRows, page, pageSize, isFirstPage, isLastPage }`

### Field Selection
- `fields=Title,Status,Priority` — Select specific fields

## Authentication

### API Token
- Personal tokens created in Account > API Tokens
- Header: `xc-auth: <token>` or `xc-token: <token>`

### Session Auth
- Cookie-based after signin
- `POST /api/v1/auth/user/signin` returns JWT token

## Code Generation (API Snippets)

NocoDB generates ready-to-use code in 9 languages:
1. **Shell** (curl)
2. **JavaScript** (fetch)
3. **Node** (axios)
4. **NocoDB-SDK** (official SDK)
5. **PHP** (curl)
6. **Python** (requests)
7. **Ruby** (net/http)
8. **Java** (HttpURLConnection)
9. **C** (libcurl)

## Relevance to OpenRegister

1. **Auto-generated API** per table is a huge DX advantage
2. **Code snippet generation** in 9 languages is very developer-friendly
3. **Bulk operations** are essential for data import/export
4. **Filter syntax** is compact but less standard than OData or JSON:API
5. OpenRegister's register/schema/object model is more semantic but less auto-generated
