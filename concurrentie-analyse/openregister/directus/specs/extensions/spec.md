---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Extension System

## Overview

Directus has a comprehensive extension system with 10 extension types covering both frontend (app) and backend (API) customization. Extensions can be loaded from the filesystem, installed from npm, or bundled together. A sandboxed execution environment (isolated-vm) provides security for third-party extensions.

## Extension Types

### App Extensions (Frontend)

| Type | Purpose | Example |
|------|---------|---------|
| **Interface** | Custom form input components | Rich text editor, color picker, map selector |
| **Display** | Custom value renderers for list/detail views | Progress bar, QR code, formatted date |
| **Layout** | Custom collection view layouts | Calendar, Kanban board, map view |
| **Module** | Custom top-level navigation sections | Custom dashboard, external tool embed |
| **Panel** | Custom dashboard panel widgets | Chart, metric counter, list |
| **Theme** | Custom color themes | Dark mode, brand-specific colors |

### API Extensions (Backend)

| Type | Purpose | Example |
|------|---------|---------|
| **Hook** | Event listeners (action/filter/init/schedule/embed) | Custom validation, external sync, cache warming |
| **Endpoint** | Custom REST endpoints | Custom search, proxy, aggregation |

### Hybrid Extensions

| Type | Purpose | Example |
|------|---------|---------|
| **Operation** | Custom Flow operation (app UI + API handler) | Custom API call, data transformation |

### Meta Extensions

| Type | Purpose |
|------|---------|
| **Bundle** | Package multiple extensions together |

## Extension SDK

The `@directus/extensions-sdk` provides:
- CLI for scaffolding new extensions (`create-directus-extension`)
- Build tools (Rollup/Rolldown configuration)
- TypeScript types for all extension APIs
- Vue component utilities

## Extension API (Backend)

### Hooks

```typescript
export default defineHook(({ filter, action, init, schedule, embed }) => {
  // Filter: modify data before operation
  filter('items.create', async (payload, meta, context) => {
    payload.slug = slugify(payload.title);
    return payload;
  });

  // Action: run after operation
  action('items.create', async (meta, context) => {
    await notifyExternalService(meta.key);
  });

  // Init: run on server start
  init('app.before', async () => {
    await warmCache();
  });

  // Schedule: cron-based
  schedule('0 * * * *', async () => {
    await cleanupExpiredItems();
  });

  // Embed: inject HTML into the app
  embed('head', '<link rel="stylesheet" href="..." />');
});
```

### Endpoints

```typescript
export default defineEndpoint((router, context) => {
  const { services, getSchema } = context;

  router.get('/custom-search', async (req, res) => {
    const schema = await getSchema();
    const itemsService = new services.ItemsService('articles', {
      schema,
      accountability: req.accountability,
    });
    const results = await itemsService.readByQuery({ search: req.query.q });
    res.json({ data: results });
  });
});
```

### Operations

```typescript
// API side
export default defineOperationApi({
  id: 'custom-transform',
  handler: async (options, context) => {
    const { data, accountability, database, getSchema } = context;
    // Perform custom logic
    return { transformed: true };
  },
});

// App side (UI for configuring the operation)
export default defineOperationApp({
  id: 'custom-transform',
  name: 'Custom Transform',
  icon: 'transform',
  options: [
    { field: 'template', name: 'Template', type: 'string', meta: { interface: 'input' } },
  ],
  overview: ({ options }) => [{ label: 'Template', text: options.template }],
});
```

## Sandboxed Execution

Directus uses `isolated-vm` to run extensions in a sandboxed V8 isolate:
- Limited memory and CPU
- No direct filesystem or network access
- Interacts with Directus via a proxied SDK
- Configurable via `EXTENSIONS_SANDBOX_*` environment variables

## Extension Management

- Extensions are loaded from `EXTENSIONS_PATH` (default: `./extensions`)
- Can be installed from npm via `EXTENSIONS_MARKETPLACE_TRUST`
- Settings per extension stored in `directus_extensions` table
- Extensions can be enabled/disabled at runtime
- `EXTENSIONS_AUTO_RELOAD` enables hot-reloading during development (chokidar file watching)

## Built-in Extensions

The Directus app ships with a large number of built-in extensions:
- **43 interfaces**: text input, rich text (HTML/Markdown), code editor, file upload, relational dropdowns, map, tags, sliders, color pickers, boolean toggles, date/time pickers, group containers, presentation elements
- **20 displays**: formatted values, labels, ratings, colors, icons, images, related values, JSON viewer
- **6 layouts**: tabular (table), cards (grid), calendar, kanban, map
- **12 panels**: bar chart, line chart, pie chart, time series, metric, meter, label, list, relational variable

## Relevance to OpenRegister

OpenRegister relies on the Nextcloud app ecosystem for extensions, which provides:
- Wider ecosystem (thousands of Nextcloud apps)
- Integrated marketplace (Nextcloud App Store)
- Consistent authentication and UI framework
- PHP-based, familiar to the target developer community

Directus's extension model is more granular (field-level components vs full apps) and provides better sandboxing, but requires JavaScript expertise and is limited to Directus-specific functionality.
