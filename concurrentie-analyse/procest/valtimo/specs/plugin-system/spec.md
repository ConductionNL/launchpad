---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Plugin System -- Valtimo

## Purpose
Provides an annotation-based extensibility framework for connecting Valtimo to external services. Plugins expose configurable actions that can be linked to BPMN activities, enabling no-code integration of external APIs into process workflows.

## Architecture Overview
- **Backend module**: `plugin/` (core framework), `plugin-valtimo/` (Valtimo-specific extensions)
- **Frontend module**: `plugin/` and `plugin-management/` Angular libraries
- **Discovery**: Annotation scanning at startup (`@Plugin`, `@PluginAction`, `@PluginProperty`)
- **Storage**: Plugin definitions and configurations stored in database
- **Security**: Plugin properties support encryption for sensitive values (API keys, passwords)

## Data Model

### PluginDefinition
Discovered at startup from annotated classes. Immutable after registration.

| Field | Type | Description |
|-------|------|-------------|
| key | String | Unique plugin type identifier (from `@Plugin.key`) |
| title | String | Display name |
| description | String | Plugin description |
| pluginClass | String | Fully qualified class name |

### PluginConfiguration
Runtime instance of a plugin definition with configured properties.

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Unique configuration ID |
| definitionKey | String | Reference to PluginDefinition |
| title | String | Instance display name |
| properties | JSON (encrypted) | Configured property values |

### PluginActionDefinition
Actions available per plugin type, discovered from `@PluginAction` annotations.

| Field | Type | Description |
|-------|------|-------------|
| key | String | Action identifier |
| title | String | Display name |
| description | String | Action description |
| pluginDefinitionKey | String | Parent plugin reference |
| activityTypes | Set<Enum> | Compatible BPMN activity types |
| inputProperties | List | Expected input parameters |

## Business Logic

### Plugin Discovery
1. Spring context scans for `@Plugin`-annotated classes at startup
2. Each class registered as a `PluginDefinition` in the database
3. `@PluginAction` methods registered as `PluginActionDefinition`
4. `@PluginProperty` fields define configurable properties with types and constraints

### Plugin Configuration
1. Admin creates a plugin configuration via UI or auto-deployment JSON
2. Properties validated against the definition's property schema
3. Sensitive properties (marked `@PluginProperty(secret=true)`) encrypted at rest
4. Multiple configurations allowed per plugin type (e.g., two Zaken API instances)

### Plugin Action Execution
1. BPMN activity reached during process execution
2. ProcessLink looked up for the activity ID
3. If link type is PLUGIN: resolve the plugin configuration and action
4. Value resolvers evaluate input parameters (`doc:`, `pv:`, fixed values)
5. Action method invoked on the plugin instance with resolved parameters
6. Output stored as process variables or written to the document

### Plugin Events (Lifecycle)
- `@PluginEvent(invokedOn = CREATED)` -- runs when a configuration is created
- `@PluginEvent(invokedOn = UPDATED)` -- runs on configuration update
- `@PluginEvent(invokedOn = DELETED)` -- cleanup on deletion

### Auto-Deployment
Plugin configurations can be deployed from JSON files at startup, enabling infrastructure-as-code.

## Built-in Plugins

### Core
- **Flowmailer** -- transactional email via SaaS
- **SmartDocuments** -- template-based document generation
- **Wordpress Mail** -- email via WordPress

### ZGW Ecosystem (12 plugins)
- Zaken API, Documenten API, Catalogi API, Besluiten API
- Objecten API, Objecttypen API, Notificaties API
- Klanten API, Contactmomenten API
- OpenZaak (authentication), Portaaltaak, Verzoek

### Other
- **Haalcentraal BRP** -- Dutch civilian registry lookups
- **Exact Online** -- accounting integration

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- Uses **n8n nodes** for integrations (1000+ community nodes available)
- No annotation-based plugin framework -- n8n provides the extensibility layer
- ExApp architecture (Python sidecars) for Nextcloud-native extensions
- OpenConnector for API-to-API bridging

### Valtimo advantages
- Type-safe, compile-time plugin contracts (Kotlin/Java annotations)
- Encrypted property storage for secrets
- Tight BPMN integration -- actions directly linked to process activities
- Plugin lifecycle events for setup/teardown
- Auto-deployment from config files

### Valtimo disadvantages
- Requires Java/Kotlin development for new plugins (high skill barrier)
- Plugin changes require redeployment (no runtime installation)
- Smaller plugin ecosystem than n8n's 1000+ community nodes
- No plugin marketplace or hot-reload capability
