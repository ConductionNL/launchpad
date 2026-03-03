# Tasks: pipeline-views-overhaul

## 1. Backend: Pipeline Schema & View Import

- [x] 1.1 Update pipeline schema in pipelinq_register.json
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-001`, `specs/pipeline/spec.md#REQ-PIPE-001`
  - **files**: `pipelinq/lib/Settings/pipelinq_register.json`
  - **acceptance_criteria**:
    - Remove `entityType` property from pipeline schema
    - Add `viewId` (string, format: uuid) to pipeline schema
    - Add `propertyMappings` (array of objects with schemaSlug, columnProperty, totalsProperty) to pipeline schema
    - Add `totalsLabel` (string, nullable) to pipeline schema
    - Add `views` section to components with default-pipeline-view definition
    - View query references pipelinq register and lead + request schemas by slug

- [x] 1.2 Extend OpenRegister ConfigurationService to import views
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-006`
  - **files**: `openregister/lib/Service/Configuration/ConfigurationService.php`
  - **acceptance_criteria**:
    - ConfigurationService::importFromApp() handles `views` key in import data
    - Schema slugs in view queries are resolved to actual UUIDs
    - Import result includes created view IDs
    - Missing schema references log a warning but do not fail the import
    - Existing views with same slug are not duplicated

- [x] 1.3 Update DefaultPipelineService to create default view and reference it
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-005`, `specs/pipeline/spec.md#REQ-PIPE-003`
  - **files**: `pipelinq/lib/Service/DefaultPipelineService.php`, `pipelinq/lib/Service/PipelineStageData.php`
  - **acceptance_criteria**:
    - Default Sales Pipeline has viewId pointing to default view
    - Default Sales Pipeline has propertyMappings: lead→stage/value, request→stage/null
    - Default Sales Pipeline has totalsLabel: "EUR"
    - Default Service Pipeline has viewId pointing to default view
    - Default Service Pipeline has propertyMappings: request→status/null
    - Default Service Pipeline has totalsLabel: null
    - View creation is idempotent (not duplicated on re-run)

- [x] 1.4 Update SettingsLoadService config key mapping
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-005`
  - **files**: `pipelinq/lib/Service/SettingsLoadService.php`
  - **acceptance_criteria**:
    - updateObjectTypeConfiguration stores the default view ID in IAppConfig
    - New config key: `default_view` alongside existing schema/register keys
    - DefaultPipelineService can retrieve the view ID from config

## 2. Frontend: View Service & Data Layer

- [x] 2.1 Create viewService.js for view API access
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-001`, `specs/pipeline-views/spec.md#REQ-PV-007`
  - **files**: `pipelinq/src/services/viewService.js`
  - **acceptance_criteria**:
    - getViews() fetches all available views from /apps/openregister/api/views
    - getView(id) fetches a single view's details including query schemas
    - getSchemaProperties(schemaId) fetches a schema's property definitions
    - All methods use proper Nextcloud auth headers (requesttoken, OCS-APIREQUEST)

- [x] 2.2 Add view-based item fetching to object store
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-001`, `specs/pipeline/spec.md#REQ-PIPE-006`
  - **files**: `pipelinq/src/store/modules/object.js`
  - **acceptance_criteria**:
    - New method: fetchItemsFromView(viewId, pipelineId, limit) queries /api/objects with _view parameter
    - Response items include _schema metadata for schema identification
    - Method returns array of items with schema slug accessible

## 3. Frontend: PipelineBoard Refactor

- [x] 3.1 Refactor PipelineBoard to fetch via View API
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-001`, `specs/pipeline/spec.md#REQ-PIPE-006`
  - **files**: `pipelinq/src/views/pipeline/PipelineBoard.vue`
  - **acceptance_criteria**:
    - fetchPipelineItems uses viewId from selected pipeline (not entityType)
    - Items are placed in columns based on propertyMappings[schemaSlug].columnProperty
    - Items with no matching column appear in first non-closed column
    - Show filter options derived from schemas in the view (not hardcoded lead/request)
    - Legacy fallback: if pipeline has no viewId, fall back to entityType-based fetching

- [x] 3.2 Refactor drag-and-drop to update mapped property
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-003`
  - **files**: `pipelinq/src/views/pipeline/PipelineBoard.vue`, `pipelinq/src/views/pipeline/PipelineCard.vue`
  - **acceptance_criteria**:
    - Drag payload includes item's schema slug
    - onDrop looks up propertyMappings for the item's schema
    - onDrop updates the mapped columnProperty (not hardcoded 'stage')
    - PipelineCard drag data includes schema slug from _schema metadata
    - Items refresh correctly after drop

- [x] 3.3 Make column header totals configurable
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-004`, `specs/pipeline/spec.md#REQ-PIPE-008`
  - **files**: `pipelinq/src/views/pipeline/PipelineBoard.vue`
  - **acceptance_criteria**:
    - getStageTotalValue uses propertyMappings[schemaSlug].totalsProperty
    - Total label uses pipeline.totalsLabel (e.g., "EUR")
    - Schemas with no totalsProperty are excluded from sum
    - Column header shows no total line when no schema has totalsProperty

## 4. Frontend: Styling Overhaul

- [x] 4.1 Restyle PipelineCard to Procest "My Work" pattern
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-008`
  - **files**: `pipelinq/src/views/pipeline/PipelineCard.vue`
  - **acceptance_criteria**:
    - Card uses compact flex-row layout: badge → title (flex:1, truncated) → meta → age/date
    - Padding: 8px, gap: 8-10px between elements
    - Hover: background var(--color-background-hover) with 0.15s transition
    - Overdue: border-left 3px solid var(--color-error)
    - Entity badge uses dynamic schema slug for styling (not hardcoded _entityType)
    - Quick actions (stage select, assign select) remain functional

- [x] 4.2 Restyle kanban columns as dashboard widget containers
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-008`
  - **files**: `pipelinq/src/views/pipeline/PipelineBoard.vue`
  - **acceptance_criteria**:
    - Column has border: 1px solid var(--color-border), border-radius: var(--border-radius-large)
    - Column header has color-coded top border from stage color
    - Column header displays: title (uppercase, 13px, bold), count badge, configurable total
    - Column background: var(--color-main-background) (not dark background)
    - Closed columns remain collapsed at bottom

## 5. Frontend: Pipeline Form & Sidebar Updates

- [x] 5.1 Add "New pipeline"" to PipelineSidebar
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-007`
  - **files**: `pipelinq/src/views/pipeline/PipelineSidebar.vue`
  - **acceptance_criteria**:
    - "New pipeline" button visible in sidebar header or details tab
    - Clicking opens PipelineForm in create mode (empty form)
    - After saving, new pipeline appears in board selector and board switches to it
    - Sidebar emits save event that triggers pipeline list refresh

- [x] 5.2 Replace entityType with view selector in PipelineForm
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-001`, `specs/pipeline-views/spec.md#REQ-PV-007`
  - **files**: `pipelinq/src/views/settings/PipelineForm.vue`
  - **acceptance_criteria**:
    - entityType NcSelect replaced with View selector (fetched from viewService.getViews())
    - Selected view ID stored as viewId on form data
    - Form validates that a view is selected before saving
    - View dropdown shows view name and schema list

- [x] 5.3 Add property mapping editor to PipelineForm
  - **spec_ref**: `specs/pipeline-views/spec.md#REQ-PV-002`, `specs/pipeline-views/spec.md#REQ-PV-004`
  - **files**: `pipelinq/src/views/settings/PipelineForm.vue`, `pipelinq/src/services/viewService.js`
  - **acceptance_criteria**:
    - When a view is selected, form auto-populates property mapping rows (one per schema in view)
    - Each row shows: schema name, columnProperty dropdown, totalsProperty dropdown
    - Property dropdowns populated from schema property definitions (via viewService.getSchemaProperties)
    - totalsLabel text input shown below mappings
    - Mappings saved as propertyMappings array on pipeline object

- [x] 5.4 Update PipelineManager admin settings for new schema
  - **spec_ref**: `specs/pipeline/spec.md#REQ-PIPE-001`
  - **files**: `pipelinq/src/views/settings/PipelineManager.vue`
  - **acceptance_criteria**:
    - Pipeline list shows view name instead of entityType label
    - Pipeline cards display property mappings summary
    - Edit opens PipelineForm with new view/mapping fields populated

## 6. Build & Verify

- [x] 6.1 Build pipelinq frontend and verify
  - **spec_ref**: All specs
  - **files**: `pipelinq/`
  - **acceptance_criteria**:
    - `npm run build` succeeds without errors
    - Pipeline board loads in browser
    - Default pipeline shows items from default view
    - Drag-and-drop updates correct property
    - Column totals reflect configured property
    - New pipeline creation works from sidebar
    - Procest-style card styling applied

## Verification

- [x] All tasks checked off
- [ ] `openspec validate` passes
- [ ] Manual testing against acceptance criteria
- [ ] Code review against spec requirements
