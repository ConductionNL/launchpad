---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Lifecycle Hooks & Middleware

## Overview

Strapi provides two levels of lifecycle hooks: **database-level lifecycles** that fire on entity CRUD operations, and **Document Service middlewares** that wrap higher-level document operations. Additionally, the Koa middleware pipeline handles HTTP request processing. Together, these three layers provide comprehensive extensibility for business logic injection.

## Database Lifecycle Hooks

Located in `@strapi/database`, these fire on every database operation:

### Available Actions
| Action | Fires | Has Result? |
|--------|-------|-------------|
| `beforeCreate` | Before INSERT | No |
| `afterCreate` | After INSERT | Yes |
| `beforeCreateMany` | Before bulk INSERT | No |
| `afterCreateMany` | After bulk INSERT | Yes |
| `beforeUpdate` | Before UPDATE | No |
| `afterUpdate` | After UPDATE | Yes |
| `beforeUpdateMany` | Before bulk UPDATE | No |
| `afterUpdateMany` | After bulk UPDATE | Yes |
| `beforeDelete` | Before DELETE | No |
| `afterDelete` | After DELETE | Yes |
| `beforeDeleteMany` | Before bulk DELETE | No |
| `afterDeleteMany` | After bulk DELETE | Yes |
| `beforeFindOne` | Before SELECT one | No |
| `afterFindOne` | After SELECT one | Yes |
| `beforeFindMany` | Before SELECT many | No |
| `afterFindMany` | After SELECT many | Yes |
| `beforeCount` | Before COUNT | No |
| `afterCount` | After COUNT | Yes |

### Subscriber Interface
```typescript
// Function subscriber - receives all events
strapi.db.lifecycles.subscribe(async (event) => {
  if (event.action === 'afterCreate') {
    console.log('Created:', event.model.uid, event.result);
  }
});

// Object subscriber - selective event handling
strapi.db.lifecycles.subscribe({
  models: ['api::article.article'],  // Optional model filter

  async beforeCreate(event) {
    // event.params contains the query params (data, where, etc.)
    event.params.data.slug = slugify(event.params.data.title);
  },

  async afterCreate(event) {
    // event.result contains the created entity
    await notifySubscribers(event.result);
  },
});
```

### Event Object
```typescript
interface Event {
  action: string;         // The lifecycle action name
  model: Model;           // Database model metadata
  params: Params;         // Query parameters (data, where, populate, etc.)
  result?: unknown;       // Operation result (only in "after" hooks)
  state: Record<string, unknown>;  // Shared state between before/after
}
```

### Built-in Subscribers
- **timestampsLifecyclesSubscriber**: Auto-sets `created_at` and `updated_at`
- **modelsLifecyclesSubscriber**: Runs model-specific lifecycle callbacks

### State Sharing
The `state` object allows data to pass from `before` to `after` hooks:
```typescript
strapi.db.lifecycles.subscribe({
  async beforeUpdate(event) {
    const existingEntry = await strapi.db.query(event.model.uid).findOne({
      where: event.params.where,
    });
    event.state.previousData = existingEntry;
  },
  async afterUpdate(event) {
    const { previousData } = event.state;
    // Compare previous and new data
  },
});
```

### Disable/Enable
Lifecycle hooks can be temporarily disabled:
```typescript
strapi.db.lifecycles.disable();
// Operations here skip lifecycle hooks
strapi.db.lifecycles.enable();
```

## Document Service Middlewares

The Document Service (v5) has its own middleware layer that wraps higher-level operations:

### Middleware Pattern
```typescript
strapi.documents.use(async (ctx, next) => {
  // Before: modify params
  console.log('Action:', ctx.action, 'UID:', ctx.uid);

  // Call next middleware / actual operation
  const result = await next();

  // After: modify result
  return result;
});
```

### Built-in Middlewares
- **databaseErrorsMiddleware**: Catches and wraps database errors
- **Draft and Publish transforms**: Adds status handling
- **i18n transforms**: Adds locale handling
- Various data transformation middlewares

### Middleware Manager
The middleware manager wraps the repository proxy:
- Each content type repository gets wrapped with all registered middlewares
- Middlewares execute in registration order (first registered = outermost)
- Can exclude specific methods from middleware wrapping

## Koa HTTP Middlewares

The HTTP layer uses standard Koa middleware:

### Built-in HTTP Middlewares
| Middleware | Purpose |
|-----------|---------|
| `body` | Request body parsing (koa-body) |
| `cors` | CORS headers |
| `errors` | Error handling and formatting |
| `ip` | IP filtering |
| `logger` | Request logging |
| `powered-by` | X-Powered-By header |
| `public` | Static file serving |
| `query` | Query string parsing (qs) |
| `response-time` | X-Response-Time header |
| `responses` | Response formatting |
| `security` | Security headers (helmet) |
| `session` | Session management |
| `compression` | Response compression |
| `favicon` | Favicon serving |

### Custom Middleware
```typescript
// src/middlewares/custom.ts
export default (config, { strapi }) => {
  return async (ctx, next) => {
    // Before
    await next();
    // After
  };
};
```

### Middleware Configuration
```typescript
// config/middlewares.ts
export default [
  'strapi::logger',
  'strapi::errors',
  'strapi::security',
  'strapi::cors',
  { name: 'strapi::body', config: { jsonLimit: '10mb' } },
  'strapi::query',
  'strapi::session',
  'strapi::favicon',
  'strapi::public',
];
```

## Relevance to OpenRegister

**Key differences:**
- Strapi has three middleware layers; OpenRegister uses Nextcloud's event system + middleware
- Strapi lifecycle hooks are database-level; OpenRegister events are at the service level
- Strapi's state sharing between before/after hooks is elegant

**Features OpenRegister could adopt:**
- State sharing between before/after event handlers (pass data from beforeUpdate to afterUpdate)
- Disable/enable lifecycle hooks for bulk operations or migrations
- Document Service middleware pattern (wrapping repository operations)
- Model-specific subscriber filtering (subscribe to events for specific schemas only)
- The three-tier architecture (HTTP middleware > document middleware > DB lifecycle) provides clean separation of concerns
