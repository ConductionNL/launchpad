---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# Data Modeling

## Overview

NocoDB provides 30+ built-in field types organized into a `UITypes` enum in the SDK. Each field type maps to database-specific column types through SQL UI adapters (MysqlUi, PgUi, SqliteUi, etc.). The meta layer stores field definitions in `nc_columns` and type-specific options in dedicated tables (e.g., `nc_col_formula`, `nc_col_select_options`, `nc_col_lookup`).

## Field Types (UITypes Enum)

### Text Fields
- **SingleLineText** — Basic text input
- **LongText** — Multi-line with optional rich text mode (Markdown/HTML)
- **JSON** — Structured JSON data with syntax highlighting

### Numeric Fields
- **Number** — Integer values
- **Decimal** — Floating-point with precision control
- **Currency** — Number with locale-aware formatting (currency_code, currency_locale)
- **Percent** — Number displayed as percentage
- **Duration** — Time duration (h:mm, h:mm:ss, h:mm:ss.sss)
- **Rating** — 1-5 star/heart/flag/thumb rating with customizable icons
- **AutoNumber** — Auto-incrementing sequence number

### Selection Fields
- **SingleSelect** — Dropdown with colored options
- **MultiSelect** — Multiple colored tags

### Date/Time Fields
- **Date** — Date only
- **DateTime** — Date and time with timezone support
- **Time** — Time only
- **Year** — Year only
- **CreatedTime** — Auto-filled creation timestamp (read-only)
- **LastModifiedTime** — Auto-filled modification timestamp (read-only)

### Identity Fields
- **ID** — Primary key (auto-generated)
- **UUID** — Universally unique identifier
- **CreatedBy** — Auto-filled creator (read-only)
- **LastModifiedBy** — Auto-filled last modifier (read-only)
- **User** — User picker (from workspace members)
- **Collaborator** — Team member assignment

### Media Fields
- **Attachment** — File upload with preview
- **QrCode** — QR code generated from another column's value
- **Barcode** — Barcode generated from another column's value

### Relational Fields
- **Links** (LinkToAnotherRecord) — Relations: HasMany, BelongsTo, ManyToMany, ManyToOne, OneToOne
- **Lookup** — Pull data from linked records (requires relation column + lookup column)
- **Rollup** — Aggregate linked records (COUNT, SUM, AVG, MIN, MAX, etc.)
- **Count** — Count of linked records
- **Formula** — Computed field with 65 formula functions

### Special Fields
- **Checkbox** — Boolean with customizable icons (check, circle, star, heart, moon, thumb, flag)
- **Email** — Validated email address
- **URL** — Validated URL with link rendering
- **PhoneNumber** — Phone number
- **GeoData** — Geographic coordinates (lat/lng)
- **Geometry** — Geographic shapes
- **Colour** — Color picker (hex/rgb)
- **Button** — Action button (webhook, script, AI)
- **SpecificDBType** — Direct database column type

### AI Fields
- **AI Button** — Button that triggers AI actions
- **AI Text** (LongText with AI meta) — AI-generated text content

## Virtual vs Physical Columns

Virtual columns are computed and not stored in the database:
- LinkToAnotherRecord, Formula, QrCode, Barcode, Rollup, Lookup, Links
- CreatedTime, LastModifiedTime, CreatedBy, LastModifiedBy, Button

Physical columns map directly to database columns with type-specific SQL types.

## Relation System

### V1 Relations (Legacy)
- HasMany, BelongsTo, ManyToMany with explicit junction tables
- Foreign key stored in child table

### V2 Relations (Current)
- All relation types use junction tables (MM-like)
- ManyToOne, OneToOne, BelongsTo are junction-based but return single records
- Version field on LinkToAnotherRecordColumn distinguishes V1 vs V2

## Column Metadata

Each column stores:
- `uidt` — UI type enum value
- `dt` — Database type (e.g., "varchar", "integer")
- `np`, `ns` — Numeric precision/scale
- `clen` — Character length
- `pk`, `ai` — Primary key, auto-increment flags
- `system` — Whether it's a system column
- `meta` — JSON metadata (rich mode, AI settings, etc.)

## Relevance to OpenRegister

NocoDB's field type system is more extensive than OpenRegister's JSON Schema approach. Key takeaways:
1. **Pre-built field types** reduce setup time vs defining JSON Schema properties
2. **Rating, Barcode, QrCode** are creative field types worth considering
3. **AI fields** (AI Button, AI Text) are a differentiator
4. **Relation V2** using junction tables for all relation types simplifies the model
5. **Canvas rendering** enables custom cell renderers per field type
