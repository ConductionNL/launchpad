---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Data Import & Export

## Overview

Directus provides built-in import and export capabilities for collections, supporting CSV, JSON, XML, and YAML formats. Import/export operations run as background jobs with error tracking and user notifications.

## Export

### Supported Formats
- **JSON** - Native JavaScript objects, preserves types and nested structures
- **CSV** - Flat tabular format using `json2csv` library, with field flattening for nested data
- **XML** - XML output via `js2xmlparser`
- **YAML** - YAML output via `js-yaml`

### Export Process
1. User selects a collection, fields, filters, and sort order
2. The export service creates a temporary file
3. Items are read in batches (configurable `EXPORT_BATCH_SIZE`, default 5000)
4. Each batch is streamed to the temporary file in the selected format
5. The completed file is uploaded to the configured storage location
6. The user receives a notification with a download link

### Export Configuration
- **Fields**: Select which fields to include (supports nested relational fields)
- **Filter**: Apply any filter to limit exported rows
- **Sort**: Order the exported data
- **Limit**: Maximum number of items to export
- **Format**: Output format (json, csv, xml, yaml)

### Streaming Architecture
Exports use Node.js streams to handle large datasets without loading everything into memory:
- Readable stream from database (batched queries)
- Transform stream for format conversion
- Writable stream to temporary file
- Then upload to storage

## Import

### Supported Formats
- **JSON** - Array of objects or stream of objects
- **CSV** - Parsed via `papaparse` with auto-detection of delimiters

### Import Process
1. User uploads a file or provides a URL
2. The import service validates the file format
3. Items are parsed and processed in a queue (`async.queue`)
4. Each item is created or updated (upsert) via the standard `ItemsService`
5. Errors are tracked per row with detailed error messages
6. The user receives a summary notification

### Error Handling
- Errors are tracked per field and per row number
- Row numbers are grouped into ranges for compact error reporting
- A configurable `MAX_IMPORT_ERRORS` limit prevents runaway imports
- When the limit is reached, remaining rows are skipped
- Error types include validation errors, foreign key violations, and type mismatches

### Import Options
- **Collection**: Target collection
- **Format**: Input format (auto-detected from file extension)
- **Primary key handling**: If an item with the same primary key exists, it will be updated (upsert)

## Schema Export/Import

Separate from data import/export, Directus provides schema migration tools:

### Schema Snapshot
`GET /schema/snapshot` returns a complete schema definition including:
- All collections and their metadata
- All fields with types, defaults, and metadata
- All relations
- Versioned with a hash for change detection

### Schema Diff
`POST /schema/diff` compares a snapshot against the current database schema and returns the differences.

### Schema Apply
`POST /schema/apply` applies a diff to the current database, executing DDL operations (create/alter/drop tables/columns).

This enables schema migration between environments (dev -> staging -> production).

## Relevance to OpenRegister

OpenRegister currently supports:
- JSON import/export
- CSV import/export
- No XML or YAML support
- No background processing for large imports
- No schema migration tools

Areas for improvement inspired by Directus:
- Streaming import/export for large datasets
- Error tracking with row-level detail
- Schema snapshot/diff/apply for environment migration
- YAML export (useful for configuration files)
- Background job processing with user notifications
