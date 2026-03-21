# Directus Extensions & Marketplace

**Source:** https://docs.directus.io/guides/extensions/overview.html, https://docs.directus.io/guides/extensions/marketplace/

## Extension Architecture

Directus is built to be extensible. Extensions run within the same environment as the main application, with access to underlying services and UI components.

## App Extensions (Data Studio)

### Interfaces
Form inputs used in the content editor. Custom interfaces for unique interaction needs, complex data entry.

### Displays
Small components to display a single value throughout the Data Studio. Receive a value and render it in a user-friendly way.

### Layouts
Listing of items in explore pages. Receive collection, filters, searches. Responsible for fetching and rendering items.

### Panels
Components within Insights dashboards. Building blocks for analytics. Can contain interactive elements for internal applications.

### Modules
Top-level areas of the Data Studio, navigated from the left module bar. Load at specified routes.

### Themes
Style the Data Studio — colors, fonts, visual elements.

## API Extensions

### Hooks
Run code when events occur (schedules, database events, application lifecycle).

### Endpoints
Register new API routes.

### Operations
Single steps in a Flow (no-code automation).

## Extension Bundles
Package multiple extensions together.

## Marketplace (Beta)

### Discovery
- Accessible from project settings
- Search, filter, sort available extensions
- Compatibility warnings based on tested versions

### Management
- Install/uninstall from Data Studio
- Disable individual extensions
- Bundle extensions can be disabled individually but uninstalled as a whole

### Publishing
- Uses Directus Extensions Registry
- During beta, all npm-published extensions are available
- Extensions published to npm automatically appear in registry

## Extension SDK / CLI
- `npx create-directus-extension` to scaffold
- Build with `directus-extension build`
- TypeScript support
- Access to internal services and UI components

## Comparison Notes (vs OpenRegister)

| Aspect | Directus | OpenRegister |
|--------|----------|-------------|
| Extension Types | 7 app + 3 API types | Nextcloud app ecosystem |
| Marketplace | Built-in (beta), npm-based | Nextcloud App Store |
| Custom Interfaces | Dedicated extension type | Vue components via Nextcloud |
| Custom API Routes | Endpoint extensions | PHP controllers in Nextcloud app |
| Themes | Dedicated extension type | NL Design System tokens |
| Extension Bundles | Supported | Not applicable |
| SDK | Official Extension SDK + CLI | Nextcloud development tools |
| Sandboxing | API extension sandbox mode | Nextcloud app isolation |
