# Directus API Reference

**Source:** https://docs.directus.io/api/, https://docs.directus.io/getting-started/use-the-api.html, https://docs.directus.io/guides/connect/query-parameters.html, https://docs.directus.io/guides/connect/filter-rules.html

## Overview

Each Directus project gets an auto-generated RESTful API and GraphQL API that adapts as you modify your data model. Authentication via access tokens, cookies, or sessions.

The API is generated from an OpenAPI specification maintained at github.com/directus/openapi.

## REST API

### Endpoints Pattern
- `GET /items/{collection}` — List items
- `GET /items/{collection}/{id}` — Get single item
- `POST /items/{collection}` — Create item
- `PATCH /items/{collection}/{id}` — Update item
- `DELETE /items/{collection}/{id}` — Delete item

### Authentication
```bash
curl --header 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
     --url 'https://directus.example.com/items/posts'
```

## GraphQL API

Native GraphQL support with queries, mutations, and subscriptions. Available at `/graphql` endpoint.

## JavaScript SDK

```js
import { createDirectus, rest, readItems } from '@directus/sdk';
const directus = createDirectus('https://directus.example.com').with(rest());
const result = await directus.request(readItems('posts', { filter: { title: { _eq: 'Hello' } } }));
```

## Query Parameters

### Fields
Specify which fields are returned. Supports dot notation for nested relations and wildcards.
```
?fields=title,author.name
?fields=*.*     // all fields + related
```

#### M2A Field Scoping
```
?fields=sections.item:headings.title&fields=sections.item:paragraphs.body
```

### Filter
Filter items based on filter rules:
```
?filter[title][_eq]=Hello
?filter={ "title": { "_eq": "Hello" }}
```

### Search
Full-text search across all string/text fields:
```
?search=Directus
```

### Sort
Sort results (prefix `-` for descending):
```
?sort=sort,-date_created,author.name
```

### Limit / Offset / Page
- `?limit=50` (default: 100, -1 for all)
- `?offset=100`
- `?page=2`

### Aggregate
Functions: `count`, `countDistinct`, `sum`, `sumDistinct`, `avg`, `avgDistinct`, `min`, `max`
```
?aggregate[count]=*
```

### GroupBy
```
?aggregate[count]=views,comments&groupBy[]=author&groupBy[]=year(publish_date)
```

### Deep
Set query parameters on nested relational data:
```
?deep[translations][_filter][languages_code][_eq]=en-US
```

### Alias
Rename fields and fetch same data with different filters:
```
?alias[dutch_translations]=translations&deep[dutch_translations][_filter][code][_eq]=nl-NL
```

### Export
Save response as file: `?export=csv|json|xml|yaml`

### Version
Query versioned content: `?version=v1`

### Backlink
Exclude reverse relations in wildcard expansion: `?backlink=false`

## Filter Rules (Comprehensive)

### Operators

| Operator | Description |
|----------|-------------|
| `_eq` | Equals |
| `_neq` | Not equals |
| `_lt` / `_lte` | Less than / Less than or equal |
| `_gt` / `_gte` | Greater than / Greater than or equal |
| `_in` / `_nin` | In / Not in array |
| `_null` / `_nnull` | Is null / Is not null |
| `_contains` / `_ncontains` | Contains / Doesn't contain |
| `_icontains` / `_nicontains` | Case-insensitive contains |
| `_starts_with` / `_istarts_with` | Starts with (case-sensitive / insensitive) |
| `_ends_with` / `_iends_with` | Ends with (case-sensitive / insensitive) |
| `_between` / `_nbetween` | Between two values |
| `_empty` / `_nempty` | Is empty / Not empty |
| `_intersects` / `_nintersects` | Geospatial intersection |
| `_intersects_bbox` / `_nintersects_bbox` | Bounding box intersection |
| `_regex` | Regular expression (validation only) |
| `_some` / `_none` | Relational: at least one / none match |

### Dynamic Variables
- `$CURRENT_USER` — Current user's primary key
- `$CURRENT_ROLE` — Current role's primary key
- `$NOW` — Current timestamp
- `$NOW(+2 hours)` — Timestamp with offset

### Logical Operators
`_and` and `_or` for grouping multiple rules.

### Functions
Date/time functions: `year()`, `month()`, `day()`, `hour()`, etc.

### $FOLLOW Syntax
Query indirect relations: `$FOLLOW(target-collection, relation-field)`

## Comparison Notes (vs OpenRegister)

| Aspect | Directus | OpenRegister |
|--------|----------|-------------|
| API Generation | Database-mirrored REST + GraphQL | OAS-based REST from schemas |
| GraphQL | Native support | Not available |
| SDK | Official JavaScript SDK | No official SDK |
| Filter Operators | 30+ operators including geospatial | Basic OData-style filters |
| Aggregation | Built-in count, sum, avg, etc. | Limited aggregation |
| Real-time API | WebSockets + GraphQL subscriptions | Not available |
| Export | CSV, JSON, XML, YAML | JSON |
| Content Versioning | Built-in | Not available |
| Nested Filtering | Deep parameter for relational data | Limited nested filtering |
