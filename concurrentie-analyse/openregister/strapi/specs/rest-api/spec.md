---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# REST API Auto-Generation

## Overview

Strapi automatically generates RESTful CRUD API endpoints for every content type defined in the system. The core-api module creates controllers, services, and routes based on the content type's kind (collection type vs single type). All content API routes are prefixed with `/api` by default (configurable via `api.rest.prefix`).

## Auto-Generated Routes

### Collection Types

| Method | Path | Handler | Description |
|--------|------|---------|-------------|
| GET | `/api/{pluralName}` | `find` | List entries with filtering, sorting, pagination |
| GET | `/api/{pluralName}/:id` | `findOne` | Get single entry by document ID |
| POST | `/api/{pluralName}` | `create` | Create new entry |
| PUT | `/api/{pluralName}/:id` | `update` | Update existing entry |
| DELETE | `/api/{pluralName}/:id` | `delete` | Delete entry |

### Single Types

| Method | Path | Handler | Description |
|--------|------|---------|-------------|
| GET | `/api/{singularName}` | `find` | Get the single entry |
| PUT | `/api/{singularName}` | `update` | Update the single entry |
| DELETE | `/api/{singularName}` | `delete` | Delete the single entry |

## Query Parameters

### Fields Selection
```
?fields[0]=title&fields[1]=description
```
Select specific fields to return (sparse fieldsets).

### Filtering
```
?filters[title][$eq]=Hello
?filters[price][$gte]=100
?filters[$or][0][title][$contains]=world
?filters[category][name][$eq]=Tech
```

Supported filter operators:
| Operator | Description |
|----------|-------------|
| `$eq` | Equal |
| `$eqi` | Equal (case-insensitive) |
| `$ne` | Not equal |
| `$nei` | Not equal (case-insensitive) |
| `$lt` | Less than |
| `$lte` | Less than or equal |
| `$gt` | Greater than |
| `$gte` | Greater than or equal |
| `$in` | In array |
| `$notIn` | Not in array |
| `$contains` | Contains substring |
| `$notContains` | Does not contain |
| `$containsi` | Contains (case-insensitive) |
| `$startsWith` | Starts with |
| `$endsWith` | Ends with |
| `$null` | Is null |
| `$notNull` | Is not null |
| `$between` | Between two values |

Logical operators: `$and`, `$or`, `$not`

### Sorting
```
?sort[0]=title:asc&sort[1]=createdAt:desc
```

### Pagination
```
?pagination[page]=1&pagination[pageSize]=25
?pagination[start]=0&pagination[limit]=25
```
Two modes: page-based or offset-based. Default page size: 25, max: 100.

### Population (Relation Loading)
```
?populate=*                    # Populate all first-level relations
?populate[category]=*          # Populate specific relation
?populate[category][fields][0]=name  # Populate with field selection
?populate[category][filters][active][$eq]=true  # Populate with filters
```

Deep population supports nested relations with field selection and filtering at each level.

### Full-Text Search
```
?_q=search+term
```

### Locale (i18n)
```
?locale=en
?locale=fr
```

### Status (Draft/Publish)
```
?status=draft
?status=published
```

## Request/Response Format

### v5 Response Format (Default)
```json
{
  "data": {
    "id": 1,
    "documentId": "abc123",
    "title": "Hello World",
    "createdAt": "2024-01-01T00:00:00.000Z",
    "updatedAt": "2024-01-01T00:00:00.000Z",
    "publishedAt": null,
    "locale": "en",
    "category": {
      "id": 2,
      "documentId": "def456",
      "name": "Tech"
    }
  },
  "meta": {
    "pagination": {
      "page": 1,
      "pageSize": 25,
      "pageCount": 4,
      "total": 100
    }
  }
}
```

### v4 Response Format (via header)
Request with `Strapi-Response-Format: v4` header for backward-compatible nested `attributes` format.

### Create/Update Request Body
```json
{
  "data": {
    "title": "Hello World",
    "content": "...",
    "category": 2
  }
}
```

## Input Validation & Sanitization Pipeline

Every request goes through:
1. **Query validation** - Validates query params against Zod schemas per route
2. **Query sanitization** - Strips unauthorized fields based on user permissions
3. **Input validation** - Validates request body against content type schema
4. **Input sanitization** - Removes fields the user cannot write to
5. **Output sanitization** - Strips fields the user cannot read from the response

The `strictParams` config option (`api.rest.strictParams`) enables strict query parameter enforcement.

## Route Customization

Routes can be customized per API in `src/api/{name}/routes/{name}.ts`:
```typescript
export default {
  routes: [
    {
      method: 'GET',
      path: '/articles/featured',
      handler: 'article.findFeatured',
      config: {
        auth: false,
        policies: [],
        middlewares: [],
      },
    },
  ],
};
```

## Relevance to OpenRegister

**Key differences:**
- Strapi wraps request body in `{ data: {...} }`; OpenRegister uses flat JSON
- Strapi uses deep query parameter nesting for filters; OpenRegister uses `_search`, `_order`, `_limit`, `_offset`
- Strapi has built-in population control; OpenRegister has `_extend` for relation loading
- Strapi generates routes at boot; OpenRegister generates routes dynamically per register/schema

**Features OpenRegister could adopt:**
- Rich filtering operator set (`$contains`, `$between`, `$startsWith`, etc.)
- Sparse fieldsets via `fields` parameter
- Deep population with per-relation field selection and filtering
- Page-based pagination alongside offset-based
- Strict query parameter validation mode
