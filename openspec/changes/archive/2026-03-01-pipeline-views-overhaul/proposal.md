# Proposal: pipeline-views-overhaul

## Summary

Replace the hardcoded entity type system on pipelines with OpenRegister Views, add configurable property-to-stage mapping, configurable column totals, and Procest-style card styling. This turns pipelines from a rigid lead/request kanban into a generic, view-backed board that can visualize any combination of entity types with per-schema column and aggregation logic.

## Motivation

The current pipeline system is tightly coupled to "lead" and "request" entity types via a hardcoded `entityType` enum. This limits pipelines to three configurations: lead-only, request-only, or both. Users need the ability to:

1. **Use any combination of entity types** in a pipeline, backed by OpenRegister Views (saved search configurations spanning multiple schemas/registers)
2. **Configure which property determines column placement** per entity type (e.g., leads use `stage`, requests use `status`)
3. **Have drag-and-drop update the actual mapped property** on the object, not just a visual `stage` field
4. **Configure which property to aggregate** in column headers (e.g., `value` for leads, `estimatedHours` for other schemas)
5. **Create new pipelines** directly from the pipeline sidebar
6. **Match Procest dashboard styling** for a consistent look across Conduction apps

## Affected Projects

- [x] Project: `pipelinq` -- Pipeline schema, board, sidebar, form, card, initialization, settings
- [x] Project: `openregister` -- ConfigurationService view import support

## Scope

### In Scope

- Replace `entityType` enum on pipeline schema with `viewId` (OpenRegister View reference)
- Add `propertyMappings` array to pipeline schema (per-schema column property + totals property)
- Add `totalsLabel` string to pipeline schema (display label for totals, e.g., "EUR")
- Create default OpenRegister View during app initialization (spanning lead + request schemas)
- Update DefaultPipelineService to reference the default view and set default property mappings
- Extend OpenRegister ConfigurationService to import views from app settings JSON
- Refactor PipelineBoard to fetch items via view API and use propertyMappings for column placement
- Refactor drag-and-drop to update the mapped column property on the actual object
- Add "Create new pipeline" functionality to PipelineSidebar
- Add view selector and property mapping editor to PipelineForm
- Restyle PipelineCard and kanban columns to match Procest "My Work" list styling
- Make column header totals configurable per pipeline via totalsProperty

### Out of Scope

- Pipeline analytics / conversion rates (REQ-PIPE-012, V1)
- Pipeline funnel visualization (REQ-PIPE-013, V1)
- OpenRegister View CRUD UI (views are selected from existing views or created via the view API)
- Multi-register pipelines (views can technically span registers, but initial implementation targets single-register)

## Approach

1. **Backend first**: Update pipeline schema JSON, extend OpenRegister ConfigurationService for view imports, update initialization repair step to create default view
2. **Frontend data layer**: Add view fetch service, update object store for view-based queries
3. **Frontend board**: Refactor PipelineBoard to use propertyMappings for column/totals logic
4. **Frontend form/sidebar**: Add view selector, property mapping editor, create-new functionality
5. **Styling**: Apply Procest "My Work" card patterns to PipelineCard and column containers

## Capabilities

### New Capabilities

- `pipeline-views` -- OpenRegister View integration: view-backed pipelines, property-to-stage mapping, configurable totals, view creation in initialization

### Modified Capabilities

- `pipeline` -- MODIFIED: Replace entityType with viewId, update data model, update kanban board fetching and drag-and-drop, restyle cards

## Cross-Project Dependencies

- **OpenRegister**: ConfigurationService must be extended to handle `views` in the import JSON (currently only handles `registers` and `schemas`)
- **Procest**: No code changes, but styling patterns from Procest dashboard "My Work" cards are adopted

## Rollback Strategy

Not applicable -- app is not yet in production. Schema changes are non-breaking since no live data exists.

## Open Questions

None -- all requirements were clarified in the previous conversation session.
