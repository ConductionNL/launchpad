---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Plugin System

## Overview

Strapi's plugin architecture is one of its core strengths. Plugins are modular packages that can add content types, services, controllers, routes, middlewares, and admin panel UI extensions. The system supports both official Strapi plugins and community/custom plugins, with a marketplace for discovery.

## Plugin Structure

A Strapi plugin follows this structure:
```
my-plugin/
  admin/
    src/
      index.ts          # Admin panel registration
      pages/            # Admin pages
      components/       # Admin components
  server/
    src/
      index.ts          # Server-side entry point
      register.ts       # Runs at Strapi register phase
      bootstrap.ts      # Runs at Strapi bootstrap phase
      destroy.ts        # Cleanup on shutdown
      content-types/    # Plugin content types
      controllers/      # Route handlers
      services/         # Business logic
      routes/           # Route definitions
      middlewares/      # Koa middlewares
      config.ts         # Default configuration
  strapi-admin.js       # Admin entry point
  strapi-server.js      # Server entry point
  package.json
```

## Plugin Lifecycle

### Registration Phase (`register`)
- Define content types, custom fields, middlewares
- Extend other plugins
- Register event listeners
- Runs before database is connected

### Bootstrap Phase (`bootstrap`)
- Seed data, run migrations
- Initialize services
- Subscribe to events
- Runs after database is ready

### Destroy Phase (`destroy`)
- Cleanup connections
- Unsubscribe from events
- Runs on server shutdown

## Plugin Registry

The plugin registry (`packages/core/core/src/registries/plugins.ts`) manages all loaded plugins:
- Plugins are registered as modules with `plugin::` namespace prefix
- Each plugin exposes: content types, controllers, services, routes, middlewares, policies
- Plugins can depend on other plugins (access via `strapi.plugin('name')`)

## Plugin Loading

The loader (`packages/core/core/src/loaders/plugins/`) handles:
1. **Scan node_modules** for packages with `strapi.kind: "plugin"` in package.json
2. **Scan local plugins** in `src/plugins/` directory
3. **Apply user configuration** from `config/plugins.ts`
4. **Validate plugin structure**
5. **Register all plugin modules**

### Plugin Configuration
```typescript
// config/plugins.ts
export default {
  'my-plugin': {
    enabled: true,
    resolve: './src/plugins/my-plugin', // Local plugin path
    config: {
      // Plugin-specific config
      apiKey: env('MY_PLUGIN_API_KEY'),
    },
  },
};
```

## Extension Points

Plugins can extend Strapi through:

### Content Types
```typescript
export default {
  contentTypes: [
    {
      schema: {
        kind: 'collectionType',
        info: { singularName: 'my-entity', pluralName: 'my-entities', displayName: 'My Entity' },
        attributes: {
          name: { type: 'string', required: true },
        },
      },
    },
  ],
};
```

### Custom Fields
Plugins can register custom field types:
```typescript
strapi.customFields.register({
  name: 'color',
  plugin: 'color-picker',
  type: 'string', // Underlying database type
  inputSize: { default: 4, isResizable: true },
});
```

### Admin Panel Extensions
- Custom pages and sections
- Content-Type Builder extensions (via `pluginOptions`)
- Custom field editors
- Injection zones (predefined UI slots)

### Event Subscribers
```typescript
strapi.db.lifecycles.subscribe({
  models: ['api::article.article'],
  async afterCreate(event) {
    // React to content creation
  },
});
```

## Official Plugins

| Plugin | Package | Description |
|--------|---------|-------------|
| Users & Permissions | `@strapi/plugin-users-permissions` | Public user auth |
| i18n | `@strapi/plugin-i18n` | Content localization |
| GraphQL | `@strapi/plugin-graphql` | GraphQL API |
| Documentation | `@strapi/plugin-documentation` | Swagger docs |
| Sentry | `@strapi/plugin-sentry` | Error tracking |
| Color Picker | `@strapi/plugin-color-picker` | Custom color field |
| Cloud | `@strapi/plugin-cloud` | Strapi Cloud integration |

## Marketplace

Strapi has a marketplace at market.strapi.io with:
- Community plugins
- Verified plugins
- Plugin ratings and reviews
- Install via npm/yarn

## Relevance to OpenRegister

**Key differences:**
- Strapi plugins are NPM packages; OpenRegister extensions are Nextcloud apps
- Strapi plugins can add content types; OpenRegister apps create schemas via the API
- Strapi plugins have admin panel integration; Nextcloud apps have their own UI

**Patterns to consider:**
- The register/bootstrap/destroy lifecycle is clean and well-structured
- Custom field registration pattern (extending available field types)
- Plugin configuration via a central config file
- Namespace isolation (`plugin::name`) prevents conflicts
- The concept of injection zones for UI extensibility
