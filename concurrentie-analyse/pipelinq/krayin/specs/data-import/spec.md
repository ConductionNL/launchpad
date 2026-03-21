---
competitor: krayin
analyzed_date: 2026-03-14
feature: data-import
priority: medium
---

# Data Import (CSV/Excel)

## Overview

Krayin supports batch importing of leads, persons, and products from CSV and Excel files. The import system includes validation, batched processing via Laravel jobs, error reporting, and sample file downloads.

## Supported Import Types

| Entity | Importer Class |
|--------|---------------|
| Leads | `Importers\Leads\Importer` |
| Persons | `Importers\Persons\Importer` |
| Products | `Importers\Products\Importer` |

## Import Process

1. **Create import** -- Upload file, select entity type
2. **Validate** -- Check file format, field mapping, data validity
3. **Start** -- Process in batches via Laravel queue jobs
4. **Link** -- Post-import linking (relationships between entities)
5. **Index** -- Indexing for search
6. **Monitor** -- Track progress via stats endpoint

## Architecture

- `AbstractImporter` -- Base class with common import logic
- `Import` model -- Tracks import configuration and status
- `ImportBatch` model -- Individual batch processing records
- `ImportBatch` job -- Queue job for async batch processing
- `IndexBatch` / `LinkBatch` jobs -- Post-import processing
- `CSV` / `Excel` source classes -- File parsing
- Storage classes (`Leads\Storage`, `Persons\Storage`) -- Entity-specific storage tracking

## Routes

```
GET    /settings/data-transfer/imports           -- List imports
GET    /settings/data-transfer/imports/create     -- Create form
POST   /settings/data-transfer/imports/create     -- Upload and configure
GET    /settings/data-transfer/imports/edit/{id}  -- Edit
PUT    /settings/data-transfer/imports/update/{id} -- Update
DELETE /settings/data-transfer/imports/destroy/{id} -- Delete
GET    /settings/data-transfer/imports/import/{id} -- Import page
GET    /settings/data-transfer/imports/validate/{id} -- Validate
GET    /settings/data-transfer/imports/start/{id}  -- Start import
GET    /settings/data-transfer/imports/link/{id}   -- Link entities
GET    /settings/data-transfer/imports/index/{id}  -- Index data
GET    /settings/data-transfer/imports/stats/{id}/{state?} -- Progress
GET    /settings/data-transfer/imports/download-sample/{sample?} -- Sample files
GET    /settings/data-transfer/imports/download/{id} -- Download imported file
GET    /settings/data-transfer/imports/download-error-report/{id} -- Error report
```

## Pipelinq Comparison Notes

- Well-structured import pipeline with validation, batching, and error reporting
- Sample file downloads reduce user friction
- Queue-based processing handles large imports
- Only 3 entity types supported (no activities, quotes, organizations)
- No export functionality
- No API-based import (file upload only)
- No field mapping UI -- relies on column name matching
