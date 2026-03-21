---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# REST API

## Overview

Directus auto-generates a fully RESTful API for every collection (table) in the database. The API follows consistent patterns for CRUD operations, filtering, sorting, pagination, field selection, and relational data loading.

## Endpoint Structure

### Item endpoints
```
GET    /items/:collection           # List items
POST   /items/:collection           # Create item(s)
GET    /items/:collection/:id       # Read item
PATCH  /items/:collection/:id       # Update item
DELETE /items/:collection/:id       # Delete item
```

### Batch operations
```
SEARCH /items/:collection           # Search with POST body (for complex queries)
PATCH  /items/:collection           # Batch update (with keys in body)
DELETE /items/:collection           # Batch delete (with keys in body)
```

### System endpoints
Each system collection (`directus_*`) has its own dedicated controller:
```
/users, /roles, /policies, /permissions, /files, /folders,
/flows, /operations, /dashboards, /panels, /activity,
/revisions, /collections, /fields, /relations, /settings,
/translations, /notifications, /shares, /versions, /presets,
/comments, /schema
```

### Special endpoints
```
/assets/:id              # File download with optional transformations
/auth/login              # Authentication
/auth/refresh            # Token refresh
/server/info             # Server information
/server/health           # Health check
/schema/snapshot         # Full schema export
/schema/diff             # Schema comparison
/schema/apply            # Apply schema changes
/utils/*                 # Utility endpoints (hash, sort, etc.)
```

## Query Parameters

### Field selection
```
?fields=title,author.name,comments.body
?fields=*                              # All fields
?fields=*.*                            # All fields + one level of relations
?fields=*,translations.*               # Selective deep loading
```

### Filtering
Filter syntax uses nested objects with operators:
```
?filter[status][_eq]=published
?filter[date][_gte]=2024-01-01
?filter[_and][0][status][_eq]=active
?filter[_or][0][title][_contains]=test
?filter[author][name][_eq]=John         # Relational filtering
```

Supported operators:
- `_eq`, `_neq` - Equals / Not equals
- `_lt`, `_lte`, `_gt`, `_gte` - Comparison
- `_in`, `_nin` - In array / Not in array
- `_null`, `_nnull` - Null check
- `_contains`, `_ncontains` - String contains
- `_starts_with`, `_nstarts_with`, `_ends_with`, `_nends_with` - String matching
- `_between`, `_nbetween` - Range
- `_empty`, `_nempty` - Empty check
- `_intersects`, `_nintersects` - Geometry intersection
- `_intersects_bbox`, `_nintersects_bbox` - Bounding box intersection
- `_regex` - Regular expression matching

### Sorting
```
?sort=title                    # Ascending
?sort=-date_created            # Descending
?sort=status,-date_created     # Multiple fields
```

### Pagination
```
?limit=25                      # Items per page
?offset=50                     # Skip items
?page=3                        # Page number (alternative to offset)
```

### Aggregation
```
?aggregate[count]=*
?aggregate[sum]=price
?aggregate[avg]=rating
?aggregate[min]=date
?aggregate[max]=score
?groupBy[]=status
?groupBy[]=category
```

### Deep (relational query parameters)
```
?deep[comments][_limit]=5
?deep[comments][_sort]=-date
?deep[comments][_filter][status][_eq]=approved
```

### Functions
```
?filter[year(date_created)][_eq]=2024
?filter[month(date_created)][_gte]=6
?fields=count(comments)
```

### Search
```
?search=keyword                # Full-text search across searchable fields
```

### Meta
```
?meta=total_count              # Include total count
?meta=filter_count             # Count after filters
?meta=*                        # All meta info
```

## Response Format

```json
{
  "data": [...],
  "meta": {
    "total_count": 150,
    "filter_count": 42
  }
}
```

Single items return `data` as an object. Errors follow a standardized format:
```json
{
  "errors": [
    {
      "message": "You don't have permission to access this.",
      "extensions": {
        "code": "FORBIDDEN"
      }
    }
  ]
}
```

## OpenAPI Specification

Directus auto-generates an OpenAPI 3.0 specification at `/server/specs/oas` that reflects all collections, fields, and relationships. The specification is permission-aware, only showing collections/fields the authenticated user can access.

## Relevance to OpenRegister

OpenRegister's REST API shares many patterns but differs in:
- Uses Nextcloud's routing (`/apps/openregister/api/...`) vs standalone endpoints
- Query parameter syntax is simpler (flat parameters vs nested filter objects)
- No built-in aggregation functions
- No GraphQL endpoint
- OAS generation is a recent addition

Directus's filter syntax is more powerful and expressive, supporting nested logical operators and relational filtering. This is an area where OpenRegister could improve.
