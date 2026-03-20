# ADR-013: Loadable Register Templates

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, design, tasks
**Last updated:** 2026-03-20

## Context

Multiple Conduction apps need pre-configured data models and seed data to function out of the box. Pipelinq needs CRM schemas (clients, contacts, leads, pipelines), Procest needs case management schemas, OpenCatalogi needs publication schemas, and OpenRegister itself ships mock registers for the five Dutch base registries (BRP, KVK, BAG, DSO, ORI). Without a standardized pattern, each app would invent its own way to define and load register configurations, leading to inconsistent formats, fragile import logic, and no guarantee of idempotency.

The "Loadable Register Template" pattern solves this by establishing a single JSON file format (OpenAPI 3.0.0 + `x-openregister` extensions), a single import pipeline (`ConfigurationService::importFromApp()` delegating to `ImportHandler`), and a single convention for file placement (`lib/Settings/{name}_register.json`). This makes the entire app suite self-contained from `docker compose up` -- a key competitive differentiator over competitors like KISS, Dimpact ZAC, and Open Formulieren that all require extensive external infrastructure.

Three categories of register templates exist today:

1. **App configuration templates** -- define the data model an app needs to operate (e.g., `pipelinq_register.json`, `procest_register.json`, `docudesk_register.json`, `softwarecatalogus_register.json`, `larpingapp_register.json`, `publication_register.json`). These contain schemas, registers, views, and mappings but typically no seed objects.
2. **Mock data templates** -- provide fictional but realistic seed data for Dutch government base registries (`brp_register.json`, `kvk_register.json`, `bag_register.json`, `dso_register.json`, `ori_register.json`). These contain schemas plus `components.objects[]` arrays with the `@self` envelope pattern. They are marked with `x-openregister.type: "mock"`.
3. **Government standard templates** -- define schema structures aligned to Dutch government APIs (Haal Centraal BRP, KVK Handelsregister, BAG/PDOK, DSO/IMOW, VNG ORI) that can serve as starting points for production registers.

## Decision

### File Format

Register templates MUST be stored as JSON files following the OpenAPI 3.0.0 structure with `x-openregister` extensions. The top-level structure MUST contain:

```json
{
  "openapi": "3.0.0",
  "info": { "title": "...", "description": "...", "version": "1.0.0" },
  "x-openregister": { "type": "application|mock", "app": "appid", ... },
  "components": {
    "registers": { ... },
    "schemas": { ... },
    "objects": [ ... ]
  }
}
```

### File Location

- Register template files MUST be stored at `lib/Settings/{name}_register.json` within the owning app's directory.
- The filename MUST use snake_case and end with `_register.json`.
- Mock data register files MUST reside in `openregister/lib/Settings/` since OpenRegister is the foundation repository that owns shared data models.
- App configuration register files MUST reside in the consuming app's own `lib/Settings/` directory (e.g., `pipelinq/lib/Settings/pipelinq_register.json`).

### Import Mechanism

- Templates MUST be loaded via `ConfigurationService::importFromApp(appId, data, version, force)`, which delegates to `ImportHandler::importFromApp()`.
- Apps MUST call `importFromApp()` from a repair step or a dedicated `SettingsLoadService` so that the register is automatically provisioned on app install and upgrade.
- The `appId` parameter MUST match the Nextcloud app ID. The `version` parameter MUST be the current app version (from `IAppManager::getAppVersion()`), enabling version-based skip logic to avoid re-importing unchanged configurations.
- The `force` parameter SHOULD default to `false`. When `false`, the ImportHandler MUST skip creation of registers, schemas, and objects that already exist (matched by slug). When `true`, existing records MUST be updated to match the template contents.

### Idempotency

- Import MUST be idempotent: re-importing the same file with `force: false` MUST NOT create duplicate registers, schemas, or objects.
- Existing entities MUST be matched by slug, not by database ID.
- The ImportHandler MUST use `ObjectService::searchObjects` with `_rbac: false` and `_multitenancy: false` to find existing objects regardless of organisation context, preventing cross-tenant duplicates.
- Version comparison (`version_compare`) MUST be used to skip re-import when the stored configuration version matches or exceeds the incoming version, unless `force: true`.

### The @self Envelope Pattern

- Seed data objects in `components.objects[]` MUST use the `@self` envelope to declare which register and schema they belong to:
  ```json
  {
    "@self": {
      "register": "brp",
      "schema": "ingeschreven-persoon",
      "slug": "suzanne-moulin"
    },
    "burgerservicenummer": "999993653",
    "naam": { ... }
  }
  ```
- The `register` value MUST match a key in `components.registers`.
- The `schema` value MUST match a key in `components.schemas`.
- The `slug` MUST be a unique, human-readable identifier used for idempotent matching.

### Type Identification

- The `x-openregister.type` field MUST be set on every register template:
  - `"application"` for app configuration templates that define the data model an app requires.
  - `"mock"` for templates containing fictional seed data intended for demo/development environments.
- Consuming apps MUST be able to filter registers by type via the API (`GET /api/registers?type=mock`).

### Mock Data Quality

- Mock data MUST be realistic enough for meaningful demonstrations (real Dutch street names, valid postcodes in `[1-9][0-9]{3}[A-Z]{2}` format, correct municipality codes).
- Mock data MUST be distinguishable from real data: the `x-openregister.type: "mock"` marker MUST be persisted as register metadata, and where applicable fictional municipality names (e.g., "Voorbeeldstad" in ORI) SHOULD be used.
- Cross-register references MUST be consistent: BRP addresses MUST link to BAG records, KVK vestiging addresses MUST match BAG nummeraanduiding records, DSO locations MUST reference valid BAG municipality codes.
- All BSNs in BRP mock data MUST pass the Dutch 11-proef validation.

### Schema Compliance

- All schemas in register templates MUST comply with ADR-006 (OpenRegister Schema Standards): explicit property types, required property markings, and descriptive names.
- Schema slugs and register slugs defined in template files MUST be stable across versions and SHALL NOT change without a major version bump.

### Documentation

- Each app MUST document in its own `README.md` or `openspec/` directory which register templates it provides and which it consumes.
- Mock register templates MUST be accompanied by a spec in `openspec/specs/` describing the data model, seed data coverage, and cross-register linking (see `openspec/specs/mock-registers/spec.md`).

## Consequences

- **For spec authors:** When a spec requires new data models, the spec MUST define the register template file contents (schemas, properties, seed objects) as part of the deliverable. The spec MUST reference this ADR and specify whether the template is type `application` or `mock`.
- **For implementers:** New apps that store data in OpenRegister MUST ship a `{appname}_register.json` template and load it via `ConfigurationService::importFromApp()` in a repair step. They MUST NOT create registers/schemas programmatically outside this pipeline.
- **For QA:** Idempotency MUST be tested by running the import twice and asserting no duplicate records. Cross-register referential integrity MUST be validated for mock data sets.
- **For operations:** Mock registers can be disabled in production by setting `mock_registers_enabled` to `false` in `IAppConfig`, which prevents auto-import during repair steps. Previously imported mock data is NOT automatically deleted -- explicit reset via `occ openregister:load-register --force` or the API is required.
- **For the suite as a whole:** All five mock registers (BRP, KVK, BAG, DSO, ORI) plus all app configuration registers can coexist, making the full development environment available from a single `docker compose up` with no external dependencies.

## Exceptions

- **External client repos** (e.g., `Softwarecatalogus/`) that ship their own register JSON files in non-standard locations (`website/static/api/`) are NOT required to follow the `lib/Settings/` convention, as they are not Nextcloud apps and do not use the `ConfigurationService` pipeline.
- **Standalone ExApp sidecar wrappers** (openklant, opentalk, openzaak, valtimo, n8n-nextcloud) that proxy external services rather than storing data in OpenRegister are exempt from shipping register templates.
- **One-off data imports** via CSV/Excel upload or the data-sync-harvesting pipeline (see `data-sync-harvesting` spec) do not need to follow the register template format, as they are runtime operations rather than app-bundled configurations.
