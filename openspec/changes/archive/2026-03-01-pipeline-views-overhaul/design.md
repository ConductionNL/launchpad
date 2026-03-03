# Design: pipeline-views-overhaul

## Architecture Overview

The pipeline system shifts from a hardcoded entity type model to a view-backed model. The key architectural change is that a pipeline's data source is now defined by an OpenRegister View (a saved search configuration) rather than an enum. This diagram shows the new flow:

```
Pipeline object (OpenRegister)
  ├── viewId ─────────── OpenRegister View
  │                        ├── query.registers: ["pipelinq"]
  │                        └── query.schemas: ["lead", "request", ...]
  ├── propertyMappings ── Per-schema config
  │                        ├── lead: { columnProperty: "stage", totalsProperty: "value" }
  │                        └── request: { columnProperty: "status", totalsProperty: null }
  ├── totalsLabel ─────── "EUR"
  └── stages[] ────────── Column definitions (unchanged)
```

Frontend flow:
```
PipelineBoard
  → fetch pipeline object (has viewId, propertyMappings, stages)
  → fetch items via View API: GET /api/objects?_view={viewId}&pipeline={pipelineId}
  → for each item: look up schema slug → propertyMappings → columnProperty
  → place item in column where item[columnProperty] === stage.name
  → on drag: update item[columnProperty] = targetStage.name
```

## API Design

### Fetching pipeline items (existing OpenRegister API, new query parameter)

`GET /apps/openregister/api/objects?_view={viewId}&pipeline={pipelineId}&_limit=200`

**Response:**
```json
{
  "results": [
    {
      "id": "uuid-1",
      "_schema": { "slug": "lead", "id": "schema-uuid" },
      "title": "TechCorp Deal",
      "stage": "Qualified",
      "value": 20000,
      "assignee": "admin"
    },
    {
      "id": "uuid-2",
      "_schema": { "slug": "request", "id": "schema-uuid-2" },
      "title": "IT Support #42",
      "status": "new",
      "priority": "urgent"
    }
  ],
  "total": 2
}
```

Each result includes `_schema` metadata so the frontend knows which schema the item belongs to and can look up the correct propertyMapping.

### View CRUD (existing OpenRegister API)

`GET /apps/openregister/api/views` — List all views
`POST /apps/openregister/api/views` — Create view
`GET /apps/openregister/api/views/{id}` — Get view details

### Fetching schema properties (for property mapping editor)

`GET /apps/openregister/api/schemas/{id}` — Returns schema with `properties` object

The PipelineForm uses this to populate the columnProperty and totalsProperty dropdowns.

## Database Changes

No new database tables. Changes are to OpenRegister object data only:

### Pipeline schema (pipelinq_register.json)

**Remove:**
- `entityType` (string, enum: "lead", "request", "both")

**Add:**
- `viewId` (string, format: uuid) — Reference to OpenRegister View
- `propertyMappings` (array of objects) — Per-schema mapping config
- `totalsLabel` (string) — Display label for column totals

```json
{
  "viewId": {
    "type": "string",
    "description": "UUID reference to the OpenRegister View defining which schemas this pipeline displays",
    "format": "uuid"
  },
  "propertyMappings": {
    "type": "array",
    "description": "Per-schema configuration for column placement and totals aggregation",
    "items": {
      "type": "object",
      "required": ["schemaSlug", "columnProperty"],
      "properties": {
        "schemaSlug": {
          "type": "string",
          "description": "Slug of the schema this mapping applies to (e.g., 'lead', 'request')"
        },
        "columnProperty": {
          "type": "string",
          "description": "Name of the property that determines which column an item appears in (e.g., 'stage', 'status')"
        },
        "totalsProperty": {
          "type": "string",
          "description": "Name of the numeric property to sum in column headers (e.g., 'value'). Null if no totals for this schema.",
          "nullable": true
        }
      }
    }
  },
  "totalsLabel": {
    "type": "string",
    "description": "Display label for column totals (e.g., 'EUR', 'Hours')",
    "nullable": true
  }
}
```

### View import in pipelinq_register.json

Add a `views` section alongside `registers` and `schemas`:

```json
{
  "components": {
    "registers": { "pipelinq": { ... } },
    "schemas": { "client": { ... }, "lead": { ... }, ... },
    "views": {
      "default-pipeline-view": {
        "slug": "default-pipeline-view",
        "name": "Pipelinq Default View",
        "description": "Default view for pipeline boards - includes leads and requests",
        "query": {
          "registers": ["pipelinq"],
          "schemas": ["lead", "request"]
        },
        "isDefault": true
      }
    }
  }
}
```

## Nextcloud Integration

- Controllers: No changes (Pipelinq uses OpenRegister API directly from frontend)
- Services:
  - `DefaultPipelineService` — Updated to create default view and set viewId/propertyMappings on default pipelines
  - `SettingsLoadService` — Updated to pass views to ConfigurationService
  - `PipelineStageData` — Updated to include viewId and propertyMappings in default pipeline data
- Mappers/Entities: None (all data in OpenRegister)
- Events/Hooks: None

### OpenRegister Changes

- `ConfigurationService::importFromApp()` — Extended to handle `views` key in import data
- Resolves schema slugs in view queries to actual UUIDs from the same import batch

## File Structure

### Backend (PHP)
```
pipelinq/
  lib/
    Settings/
      pipelinq_register.json          # MODIFY: add viewId, propertyMappings, totalsLabel; remove entityType; add views section
    Service/
      DefaultPipelineService.php       # MODIFY: create default view, set viewId on default pipelines
      PipelineStageData.php            # MODIFY: add viewId, propertyMappings, totalsLabel to default data
      SettingsLoadService.php          # MODIFY: update config key mapping for new pipeline properties

openregister/
  lib/
    Service/
      Configuration/
        ConfigurationService.php       # MODIFY: handle 'views' in import data
```

### Frontend (Vue)
```
pipelinq/
  src/
    views/
      pipeline/
        PipelineBoard.vue              # MODIFY: fetch via view, use propertyMappings for columns/drag/totals
        PipelineCard.vue               # MODIFY: restyle to Procest "My Work" pattern, dynamic schema badges
        PipelineSidebar.vue            # MODIFY: add "New pipeline" button, show view info
    views/
      settings/
        PipelineForm.vue               # MODIFY: replace entityType with view selector + property mapping editor
    services/
      viewService.js                   # NEW: fetch views, fetch schema properties
```

## Security Considerations

- View access: OpenRegister Views respect RBAC — only views the user can access are listed
- Property mapping: Frontend reads schema properties but cannot modify schema definitions
- Drag-and-drop: Uses existing OpenRegister object save API with standard CSRF tokens

## NL Design System

- Cards use Nextcloud CSS variables (`--color-border`, `--color-background-hover`, `--color-error`, etc.)
- Entity badges use hardcoded colors for known schemas (lead=blue, request=orange) and CSS variable fallbacks for custom schemas
- Column headers use standard Nextcloud typography variables
- All interactive elements maintain WCAG AA contrast ratios

## Trade-offs

### Decision 1: View per pipeline vs shared views

**Chosen**: Shared views — multiple pipelines can reference the same View.
**Why**: Avoids view proliferation. Users create views once and reuse them across pipelines.
**Alternative**: Auto-create a private view per pipeline. Rejected because it defeats the purpose of OpenRegister's view system.

### Decision 2: Property mapping on pipeline vs on view

**Chosen**: Property mapping on the pipeline object.
**Why**: The same View (e.g., "leads + requests") might be used by two pipelines with different column logic. Keeping mappings on the pipeline preserves this flexibility.
**Alternative**: Put mappings on the View. Rejected because it couples view query configuration with pipeline-specific display logic.

### Decision 3: Schema identification via _schema metadata vs _entityType tag

**Chosen**: Use `_schema.slug` from OpenRegister API response to identify which schema an item belongs to.
**Why**: This is already provided by OpenRegister for all object queries. No custom tagging needed.
**Alternative**: Tag items with `_entityType` manually. Rejected because it adds maintenance burden and doesn't work for custom schemas.

### Decision 4: Column matching — exact name match vs ID-based

**Chosen**: Match item property value to stage name (string comparison).
**Why**: Simple, human-readable. Stage names like "Qualified" match property values like `stage: "Qualified"`.
**Risk**: If a stage is renamed, items with the old name won't match. Mitigation: the rename operation should update all items' property values (future enhancement, not in scope).
