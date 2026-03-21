---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# Hooks and Extensibility

## Summary
PocketBase provides two extensibility mechanisms: Go hooks (compile-time) and JavaScript hooks (runtime). Both use a priority-based event system that fires at various lifecycle points.

## Key Features
- **Go hooks**: Compile-time extensions by embedding PocketBase as a Go package
- **JavaScript hooks**: Runtime extensions via `pb_hooks/` directory (`.pb.js` or `.pb.ts` files)
- **JS runtime**: goja (Go-based JavaScript engine with Node.js module support)
- **Hot reload**: JS hooks auto-restart on file changes (HooksWatch mode)
- **Event lifecycle**: Validate, Create, CreateExecute, AfterCreateSuccess, AfterCreateError (and Update/Delete equivalents)
- **Priority-based**: Handlers execute in priority order with `e.Next()` chain
- **Cron jobs**: Built-in cron scheduler accessible from hooks
- **Custom routes**: Extend the API with custom endpoints

## Architecture
- `core/events.go` - Event definitions for all lifecycle hooks
- `tools/hook/` - Hook registration and execution engine
- `plugins/jsvm/jsvm.go` - JavaScript VM configuration and lifecycle
- `plugins/jsvm/binds.go` - JS API bindings (app, collections, records, etc.)
- `plugins/jsvm/pool.go` - VM pool for concurrent JS execution

## JavaScript Hook Example
```javascript
// pb_hooks/products.pb.js
onRecordCreate((e) => {
    e.record.set("slug", e.record.get("name").toLowerCase().replace(/ /g, "-"));
    e.next();
}, "products");
```

## Relevance to OpenRegister
OpenRegister uses PHP services for business logic and n8n for workflows. PocketBase's JS hooks are lighter-weight but less powerful than n8n's visual workflow builder. The hot-reload feature is notable for development experience.
