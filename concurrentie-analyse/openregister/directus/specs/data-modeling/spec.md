---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Data Modeling

## Overview

Directus models data at the database level using real SQL tables and columns. Collections map to database tables, fields map to columns, and relationships are defined through foreign keys and junction tables. Metadata about presentation, validation, and behavior is stored in `directus_collections`, `directus_fields`, and `directus_relations` system tables.

## Collections (Tables)

A collection is a database table with optional metadata:
- **Name**: Maps directly to the SQL table name
- **Icon/Color**: Visual identification in the admin UI
- **Note**: Description text
- **Hidden**: Whether to show in navigation
- **Singleton**: Treat as a single-record settings table
- **Sort field**: Which field to use for manual sorting
- **Archive**: Soft-delete configuration (field, value for archived/unarchived)
- **Accountability**: Whether to track activity (`all`, `activity`, or none)
- **Versioning**: Enable content versioning for this collection
- **Group**: Organize collections in folders

Collections can be "folder" type (no actual table, just organizational) or "alias" type (virtual, like computed/relational fields).

## Field Types

Directus supports 24 field types that map to underlying database column types:

### Scalar types
- `string` - VARCHAR
- `text` - TEXT/LONGTEXT
- `integer` - INT
- `bigInteger` - BIGINT
- `float` - FLOAT
- `decimal` - DECIMAL (with precision/scale)
- `boolean` - BOOLEAN/TINYINT
- `date` - DATE
- `dateTime` - DATETIME
- `time` - TIME
- `timestamp` - TIMESTAMP
- `binary` - BINARY/BLOB
- `uuid` - UUID/CHAR(36)
- `json` - JSON column type
- `hash` - Stored as hashed string (argon2/bcrypt)
- `csv` - Comma-separated values stored as TEXT

### Geometry types
- `geometry.Point`
- `geometry.LineString`
- `geometry.Polygon`
- `geometry.MultiPoint`
- `geometry.MultiLineString`
- `geometry.MultiPolygon`

### Special types
- `alias` - Virtual field (not stored in DB)
- `unknown` - Unrecognized column type

## Relationships

Directus supports the following relationship types:

| Type | Description | Implementation |
|------|------------|---------------|
| `m2o` | Many-to-One | Foreign key column on the "many" side |
| `o2m` | One-to-Many | Inverse of M2O, virtual alias field |
| `m2m` | Many-to-Many | Junction table with two foreign keys |
| `m2a` | Many-to-Any | Junction table with collection name + item ID (polymorphic) |
| `translations` | Translation relation | Special M2M for i18n content |
| `file` | Single file | M2O to `directus_files` |
| `files` | Multiple files | M2M via junction table to `directus_files` |

Relationships are stored in `directus_relations` with both schema-level (foreign key) and meta-level (display, sort, junction fields) configuration.

## Field Metadata

Each field has rich metadata stored in `directus_fields`:

- **Interface**: Which input component to use (43 options)
- **Display**: How to render the value in lists (20 options)
- **Options/Display Options**: Configuration for interface/display
- **Width**: `half`, `half-left`, `half-right`, `full`, `fill`
- **Required/Readonly/Hidden**: Field behavior flags
- **Validation**: Filter-based validation rules
- **Conditions**: Dynamic field behavior based on other field values
- **Sort**: Display order within the form
- **Group**: Field grouping (accordion, detail, raw)
- **Translations**: Localized field labels
- **Special**: Array of special flags (e.g., `cast-json`, `hash`, `uuid`)

## JSON Support

JSON fields are first-class:
- Native JSON column type in the database
- `json()` function for querying nested JSON paths
- `cast-json` special flag for fields stored as JSON strings
- Rich JSON editor interface in the admin UI

## Relevance to OpenRegister

OpenRegister's JSON Schema approach offers more flexibility:
- Schemas can be defined, versioned, and shared independently of database structure
- No DDL operations needed for schema changes
- Cross-register schema reuse
- JSON Schema validation standards (draft-07+)

However, Directus's database-level approach provides:
- Better query performance (indexed columns)
- Support for existing database schemas
- Native SQL operations and aggregations
- Geometry/spatial data support
