# Extension Marketplace

## Feature Summary
Directus Marketplace (beta) allows users to discover, install, and manage extensions directly from the Data Studio. It uses an Extensions Registry backed by npm.

## How Directus Implements This

### Extension Types (10 Total)

**App Extensions (7):**
1. **Interfaces** — Custom form inputs for the editor
2. **Displays** — Single-value display components
3. **Layouts** — Collection item listing views
4. **Panels** — Dashboard components for Insights
5. **Modules** — Top-level navigation areas
6. **Themes** — Visual styling of Data Studio
7. **UI Library Components** — Shared component access

**API Extensions (3):**
1. **Hooks** — Event-driven code execution
2. **Endpoints** — Custom API routes
3. **Operations** — Steps in automation Flows

### Marketplace Features
- **Discovery** — Search, filter, sort extensions in project settings
- **Installation** — One-click install from Data Studio
- **Management** — Disable/uninstall from settings
- **Compatibility Warnings** — Version-based compatibility checks
- **Bundles** — Multiple extensions packaged together

### Publishing
- Extensions published to npm automatically appear in registry
- Version compatibility metadata
- Extension detail pages with README, popularity graphs

### Extension SDK
- `npx create-directus-extension` — Scaffold new extensions
- TypeScript support
- Access to internal services (ItemsService, SchemaService, etc.)
- Access to UI components (Data Studio component library)
- Sandbox mode for API extensions

### Extension Bundles
- Package multiple extensions into a single installable unit
- Individual extensions within bundle can be disabled
- Uninstall as a whole unit

## OpenRegister Current State
OpenRegister uses the Nextcloud App Store for distribution. Extensions are full Nextcloud apps (PHP-based). There is no in-app marketplace for smaller extensions or components.

## Gap Analysis

| Capability | Directus | OpenRegister |
|-----------|----------|-------------|
| Marketplace | Built-in (beta) | Nextcloud App Store |
| Extension Types | 10 specialized types | Nextcloud apps |
| In-App Install | Yes | Via App Store UI |
| Custom Interfaces | Dedicated type | Vue components |
| Custom Displays | Dedicated type | Not available |
| Custom Layouts | Dedicated type | Not available |
| Custom Panels | Dedicated type | Not available |
| Themes | Dedicated type | NL Design tokens |
| Custom API Routes | Endpoint extensions | PHP controllers |
| Event Hooks | Dedicated type | PHP event listeners |
| Flow Operations | Dedicated type | n8n nodes |
| Extension SDK | Official CLI + TypeScript | Nextcloud dev tools |
| Bundles | Supported | Not applicable |

## Competitive Impact
**Medium** — The extension ecosystem determines platform flexibility. Directus's granular extension types allow for smaller, focused extensions. However, Nextcloud's App Store is mature and well-established with thousands of apps.

## Recommendation
OpenRegister benefits from Nextcloud's ecosystem but could:
1. Create a registry of OpenRegister-compatible components/schemas
2. Develop a schema marketplace for pre-built data models
3. Build a template library for common use cases
4. Consider a plugin system for custom field renderers within OpenRegister
