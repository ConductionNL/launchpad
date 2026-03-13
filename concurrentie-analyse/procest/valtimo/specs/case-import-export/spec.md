---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Case Import/Export -- Valtimo

## Purpose
Enables packaging complete case definitions (schema, process definitions, forms, plugins, permissions, dashboards) into portable ZIP archives for deployment across environments. This supports the development-to-production pipeline and sharing of case type configurations between Valtimo installations.

## Architecture Overview
- **Backend modules**: `exporter/` (ZIP creation) and `importer/` (ZIP ingestion)
- **Format**: ZIP archive containing JSON/BPMN/XML files organized by type
- **Scope**: Exports the full case definition, not individual case instances
- **API**: Management API endpoints for export/import operations

## Data Model

### Export Archive Structure
```
case-export.zip/
  case-definition.json        -- Case definition metadata + JSON Schema
  *.bpmn                      -- BPMN process definitions
  *.form.json                 -- Form.io form definitions
  *.formflow.json             -- FormFlow definitions
  *.process-link.json         -- Process link configurations
  *.dashboard.json            -- Dashboard configurations
  *.search-field.json         -- Search field configurations
  *.permission.json           -- PBAC permission configurations
  *.role.json                 -- Role definitions
```

### ExportRequest
| Field | Type | Description |
|-------|------|-------------|
| caseDefinitionKey | String | Case type to export |
| versionTag | String | Specific version to export |

### ImportResult
| Field | Type | Description |
|-------|------|-------------|
| success | Boolean | Whether import succeeded |
| errors | List<String> | Validation/import errors |
| warnings | List<String> | Non-fatal issues |
| importedResources | Map | Count of imported resources by type |

## Business Logic

### Export Flow
1. Admin requests export via management API or UI
2. Exporter collects all resources associated with the case definition:
   - Case definition with JSON Schema
   - Linked BPMN process definitions
   - Form definitions referenced by process links
   - FormFlow definitions
   - Process link configurations
   - Dashboard configurations
   - Search field configurations
   - Permission and role configurations
3. Resources serialized to JSON/BPMN format
4. Packaged into a ZIP archive
5. ZIP returned as download

### Import Flow
1. Admin uploads ZIP archive via management API or UI
2. Importer validates archive structure
3. Resources extracted and validated individually
4. Dependencies resolved (e.g., forms referenced by process links must exist)
5. Resources deployed in dependency order:
   - Roles and permissions first
   - Case definition and schema
   - Process definitions
   - Forms and form flows
   - Process links
   - Dashboards and search fields
6. Result returned with success/error/warning details

### Case Migration (related)
- Separate from import/export -- migrates case instances between definition versions
- Admin selects source and target version
- Case content re-validated against new schema
- Process instances migrated if process definition changed

### CSV Export
- Separate feature for exporting case instance data (not definitions)
- Exports case list to CSV file based on configured list columns
- Filtered by search criteria

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- OpenRegister supports register/schema export via API
- n8n workflows can be exported/imported as JSON
- No unified "case type package" export
- Nextcloud file system provides natural file-based sharing

### Valtimo advantages
- Complete case type packaging (schema + process + forms + permissions in one ZIP)
- Dependency resolution during import
- Version-aware export (specific version tags)
- Case migration between definition versions
- CSV export for case instance data

### Valtimo disadvantages
- Export format is Valtimo-proprietary (not interoperable)
- No selective export (all-or-nothing per case definition)
- No diff/merge tooling for comparing exports
- No environment-specific overrides in import (same config for dev/staging/prod)
- Import can fail silently on partial dependency issues
