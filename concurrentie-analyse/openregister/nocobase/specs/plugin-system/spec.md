---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Plugin System

## Purpose

NocoBase's plugin system is the foundation of its architecture. Everything beyond the core framework is a plugin, including authentication, ACL, file management, and even the UI schema storage. The system supports 105 built-in plugins with hot-enable/disable capability.

## Architecture Overview

Plugins extend the `Plugin` abstract class from `@nocobase/server`. Each plugin has access to the full application context including database, ACL, resourcer, and other plugins.

```typescript
abstract class Plugin {
  app: Application;        // Full app context
  db: Database;           // Via this.app.db

  async afterAdd() {}     // After plugin registered
  async beforeLoad() {}   // Before plugin loads (register fields, models)
  async load() {}         // Main load (register resources, actions, middleware)
  async install() {}      // First-time installation
  async afterEnable() {}  // After plugin enabled
  async afterDisable() {} // After plugin disabled
  async remove() {}       // Plugin removal cleanup
}
```

## Data Model

Plugins are tracked in the `applicationPlugins` collection:
- `name` - Plugin identifier
- `packageName` - NPM package name
- `version` - Semantic version
- `enabled` - Active status
- `installed` - Installation status
- `builtIn` - Whether part of default preset

## Business Logic

### Plugin Discovery
- Built-in plugins discovered via `findBuiltInPlugins()` scanning `@nocobase/plugin-*`
- Local plugins discovered via `findLocalPlugins()` scanning file system
- Plugins resolved via npm package resolution

### Plugin Lifecycle
1. **Registration** - Plugin class registered with PluginManager
2. **Loading** - `beforeLoad` -> `load` called in dependency order (Toposort)
3. **Installation** - `install()` called on first enable, runs migrations
4. **Enable/Disable** - Hot toggle without restart, triggers `afterEnable`/`afterDisable`

### What Plugins Can Do
- Register database collections and fields
- Define API resources and actions
- Add middleware to the request pipeline
- Register ACL rules and snippets
- Add UI components (client-side)
- Register workflow triggers and instructions
- Define custom field types and interfaces

## Requirements

### Functional
- Enable/disable plugins at runtime without restart
- Plugin dependency resolution
- Plugin versioning and compatibility checking
- Per-plugin migration system
- Plugin marketplace/registry support

### Non-functional
- Plugins isolated from each other (no direct coupling)
- Plugin load order determined by dependency graph
- Failed plugin load does not crash the application

## Comparison Notes

### vs Nextcloud App System
- NocoBase plugins are NPM packages; Nextcloud apps are PHP packages with `appinfo/info.xml`
- NocoBase has hot-enable/disable; Nextcloud requires page reload
- NocoBase has 105 built-in; Nextcloud has thousands in the app store
- NocoBase plugins are mostly first-party; Nextcloud has a large third-party ecosystem
- Both support dependency declaration and version constraints
