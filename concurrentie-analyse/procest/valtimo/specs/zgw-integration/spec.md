---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# ZGW Integration -- Valtimo

## Purpose
Deep integration with the Dutch government ZGW (Zaakgericht Werken) API ecosystem. The "GZAC" (Generiek Zaakafhandelcomponent) edition of Valtimo is specifically tailored for Dutch municipalities to manage cases according to Common Ground standards, delegating data storage to external ZGW-compliant registrations while orchestrating processes internally.

## Architecture Overview
- **Backend modules**: `zgw/` directory containing 12+ sub-modules (one per API)
- **Frontend module**: `zgw/` Angular library for ZGW-specific plugin UIs
- **Integration pattern**: Each ZGW API is implemented as a **Valtimo plugin** with configurable actions
- **Authentication**: OpenZaak plugin provides JWT-based auth to ZGW backends
- **Data flow**: BPMN processes trigger ZGW plugin actions via ProcessLinks

## Data Model

### ZaakInstanceLink
Links a Valtimo case (document) to an external ZGW zaak instance.

| Field | Type | Description |
|-------|------|-------------|
| documentId | UUID | Reference to JsonSchemaDocument |
| zaakInstanceUrl | URI | URL of the external zaak in ZGW Zaken API |
| zaakInstanceId | UUID | External zaak UUID |
| zaakTypeUrl | URI | Reference to the zaaktype in Catalogi API |

### Plugin Configurations per ZGW API
Each ZGW API integration is a plugin configuration with properties like:
- Base URL of the API
- Authentication configuration reference (OpenZaak plugin)
- RSIN (government organization identifier)
- Catalogi API reference (for type lookups)

## Business Logic

### ZGW Case Lifecycle (via Plugins)
1. **Case creation**: Valtimo creates `JsonSchemaDocument` internally; BPMN process starts
2. **Zaak creation**: Plugin action creates zaak in external Zaken API, stores `ZaakInstanceLink`
3. **Status updates**: Plugin actions set status via Zaken API (mapped to statustype from Catalogi API)
4. **Document upload**: Plugin uploads to Documenten API, links to zaak via `zaakinformatieobject`
5. **Decisions**: Besluiten API plugin creates/links besluit records
6. **Closure**: Plugin sets resultaat and end-status on the zaak

### Supported ZGW API Actions

#### Zaken API
- Create zaak, set status, set resultaat
- Link documents, create zaakeigenschappen
- Add rollen (roles on the zaak)
- Update zaak properties

#### Documenten API
- Create enkelvoudig informatieobject (upload document)
- Download document content
- Link document to zaak

#### Catalogi API
- List zaaktypen, statustypen, resultaattypen
- Look up informatieobjecttypen
- Resolve roltypen

#### Objecten API + Objecttypen API
- Create/read/update/delete generic objects
- Type-validated against objecttypen definitions
- Used for data beyond standard ZGW entities

#### Notificaties API
- Subscribe to events from other ZGW components
- Publish events for downstream consumers

#### Besluiten API
- Create besluit (decision/ruling)
- Link besluit to zaak

#### Portaaltaak
- Create tasks for citizens via NL Portal
- Bridge between Valtimo user tasks and citizen self-service

### OpenZaak Authentication
- Dedicated plugin that handles JWT authentication to OpenZaak
- Configured with client ID and secret
- Referenced by other ZGW plugins as their auth provider

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- ZGW integration planned via **OpenConnector** (API gateway) and **n8n workflows**
- Not yet as deeply integrated -- ZGW support is a roadmap item
- Would use OpenZaak/OpenKlant ExApp sidecars rather than native plugins
- Focus on being a lightweight alternative that connects to ZGW rather than embedding it

### Valtimo advantages
- 12+ dedicated ZGW API plugins, battle-tested in production municipalities
- Complete zaakgericht werken lifecycle coverage
- Actions directly invokable from BPMN processes (no-code for business users)
- Auto-deployment of ZGW configurations
- NL Portal integration for citizen-facing tasks

### Valtimo disadvantages
- Heavy dependency stack (OpenZaak + Keycloak + Operaton for full ZGW)
- Plugin actions are Valtimo-specific -- not reusable outside the platform
- ZGW API standard is evolving -- plugin maintenance burden is high
- Tightly coupled to BPMN for triggering -- no ad-hoc API calls from UI
