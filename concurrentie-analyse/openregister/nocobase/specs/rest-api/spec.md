---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# REST API

## Purpose

NocoBase exposes a resource-action API pattern that automatically generates CRUD endpoints for every collection. The API supports filtering, pagination, sorting, field selection, and nested resource traversal.

## Architecture Overview

The API is handled by three core packages:
- `@nocobase/resourcer` - URL routing and action dispatch
- `@nocobase/actions` - Built-in action implementations
- `@nocobase/server` - Middleware pipeline (auth, ACL, parsing)

### URL Pattern
```
<method> /api/<resource>:<action>?<params>
<method> /api/<resource>/<resourceId>/<association>:<action>?<params>
```

## Data Model

### Resources
Each collection automatically becomes a resource. Custom resources can also be defined:
```typescript
app.resourceManager.define({
  name: 'charts',
  actions: { query: queryHandler },
});
```

### Actions
Built-in actions registered via `registerActions()`:
- `list` - Paginated record listing
- `get` - Single record retrieval
- `create` - Record creation
- `update` - Record update
- `destroy` - Record deletion
- `add` / `remove` / `set` / `toggle` - Association management
- `move` - Reorder (sortable collections)
- `firstOrCreate` / `updateOrCreate` - Upsert operations

## Business Logic

### Filtering Operators
```
$eq, $ne, $gt, $gte, $lt, $lte
$in, $notIn, $between
$includes, $notIncludes
$like, $notLike, $iLike
$is, $isNot (for null checks)
$and, $or (logical operators)
```

### Middleware Pipeline
```
cors -> bodyParser -> i18n -> dataWrapping -> auth -> parseVariables
    -> dataTemplate -> validateFilterParams -> ACL -> action handler
```

### Relation Traversal
```
GET /api/users/1/roles:list          # User's roles
GET /api/posts/5/comments:list       # Post's comments
POST /api/users/1/roles:add          # Add role to user
POST /api/users/1/roles:remove       # Remove role from user
```

### Custom Actions
Plugins register custom actions:
```typescript
app.resourceManager.registerActionHandler('myResource:customAction', async (ctx) => {
  ctx.body = { result: 'custom' };
});
```

## Requirements

### Functional
- Auto-generated CRUD for all collections
- Filtering with 15+ operators
- Pagination, sorting, field selection
- Nested resource/association endpoints
- Custom action registration
- Batch operations (bulk update, bulk delete)

### Non-functional
- Request logging with timing
- Error handling with structured responses
- Rate limiting (via middleware)
- Request size limits

## Comparison Notes

### vs OpenRegister API
- NocoBase uses resource:action pattern; OpenRegister uses standard REST verbs
- NocoBase has richer filtering operators; OpenRegister has basic query params
- OpenRegister exports OpenAPI/OAS specs; NocoBase has plugin-api-doc for Swagger
- Both support pagination and sorting
- OpenRegister supports search with faceting; NocoBase has basic filtering only
- NocoBase has built-in association endpoints; OpenRegister uses object references
