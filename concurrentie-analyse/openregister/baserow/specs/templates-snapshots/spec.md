---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Templates and Snapshots

## Summary

Baserow provides pre-built templates for quick database setup and a snapshot system for point-in-time backups of applications.

## Templates

Located at `backend/src/baserow/core/templates/`

### Template System
- `TemplateCategory` model for organizing templates
- `Template` model with serialized application data
- Templates are complete database/application definitions
- Users can install a template to create a new application
- `InstallTemplateJob` for async template installation

### Template Features
- Pre-configured tables with sample data
- Field types, views, and filters included
- Covers common use cases (CRM, project management, content calendar, etc.)
- Categories for browsing

## Snapshots

Located at `backend/src/baserow/core/snapshots/`

### Snapshot System
- Point-in-time backup of an application
- `Snapshot` model (in core models)
- `CreateSnapshotJob` - async snapshot creation
- `RestoreSnapshotJob` - async snapshot restoration

### Snapshot Features
- Full application state capture (tables, fields, rows, views)
- Restorable to replace current application state
- Multiple snapshots per application
- User-initiated via API or UI

## Import/Export

From `backend/src/baserow/core/models.py`:
- `ExportApplicationsJob` - export applications to file
- `ImportApplicationsJob` - import applications from file
- `ImportExportResource` - stored import/export files
- `ImportExportTrustedSource` - trusted sources for importing

### Airtable Import
Located at `backend/src/baserow/contrib/database/airtable/`:
- Import databases from Airtable
- Maps Airtable field types to Baserow equivalents
- Preserves data and structure

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| Templates | Pre-built database templates | N/A |
| Snapshots | Per-application point-in-time backup | N/A |
| Import | Airtable import, JSON import | Source-based import |
| Export | JSON export, CSV export | OAS export, Archimate export |
| Migration | Airtable migration path | N/A |
