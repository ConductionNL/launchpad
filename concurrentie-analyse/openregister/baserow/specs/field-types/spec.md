---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Field Types

## Summary

Baserow supports 30+ field types, implemented via a registry pattern. Each field type defines its PostgreSQL column type, serialization, validation, filtering, sorting, and import/export behavior. The field type system is the most complex part of the codebase at ~7,500 lines in `field_types.py` alone.

## Core Field Types (Open Source)

| Field Type | Class | PostgreSQL Type | Description |
|-----------|-------|----------------|-------------|
| Text | `TextFieldType` | `text` | Single-line text |
| Long Text | `LongTextFieldType` | `text` | Multi-line rich text |
| Number | `NumberFieldType` | `numeric(65, N)` | Configurable decimal places (0-10), thousand/decimal separators |
| Rating | `RatingFieldType` | `integer` | 1-5 star rating, configurable style (star/heart/thumbs/flag/smile) |
| Boolean | `BooleanFieldType` | `boolean` | True/false checkbox |
| Date | `DateFieldType` | `timestamptz` | Date with optional time, configurable format, timezone support |
| Duration | `DurationFieldType` | `interval` | Time duration with multiple display formats |
| Created On | `CreatedOnFieldType` | `timestamptz` | Auto-set on row creation (read-only) |
| Last Modified | `LastModifiedFieldType` | `timestamptz` | Auto-updated on row change (read-only) |
| Created By | `CreatedByFieldType` | `integer FK` | Auto-set to creating user (read-only) |
| Last Modified By | `LastModifiedByFieldType` | `integer FK` | Auto-set to modifying user (read-only) |
| URL | `URLFieldType` | `varchar(10000)` | URL with regex validation |
| Email | `EmailFieldType` | `varchar(254)` | Email with regex validation |
| Phone Number | `PhoneNumberFieldType` | `varchar(100)` | Phone with regex validation |
| Link Row | `LinkRowFieldType` | M2M through table | Relational link to another table |
| File | `FileFieldType` | `jsonb` | File attachments (images, documents) |
| Single Select | `SingleSelectFieldType` | `integer FK` | Dropdown with predefined options |
| Multiple Select | `MultipleSelectFieldType` | M2M through table | Multi-select tags |
| Multiple Collaborators | `MultipleCollaboratorsFieldType` | M2M through table | Assign workspace members |
| Formula | `FormulaFieldType` | varies | Computed field using formula language |
| Count | `CountFieldType` | computed | Count of linked rows |
| Rollup | `RollupFieldType` | computed | Aggregate function on linked rows |
| Lookup | `LookupFieldType` | computed | Pull values from linked rows |
| UUID | `UUIDFieldType` | `uuid` | Auto-generated UUID (read-only) |
| Autonumber | `AutonumberFieldType` | `serial` | Auto-incrementing number (read-only) |
| Password | `PasswordFieldType` | `varchar` | Hashed password storage |

## Premium Field Types

| Field Type | Class | Description |
|-----------|-------|-------------|
| AI | `AIFieldType` | AI-generated content using configured AI model (OpenAI, Anthropic, etc.) |

## Field Type Architecture

Each field type implements:

1. **`model_class`**: Django model for field configuration
2. **`get_model_field()`**: Returns Django model field for the dynamic table column
3. **`get_serializer_field()`**: DRF serializer for API requests/responses
4. **`prepare_value_for_db()`**: Transform input before database storage
5. **`get_alter_column_prepare_old/new_value()`**: SQL for field type conversion
6. **`get_export_value()`**: Export serialization
7. **`get_human_readable_value()`**: Human-readable display
8. **Filter support**: `contains_filter`, `contains_word_filter`, etc.
9. **Sort support**: Custom ordering expressions
10. **Random value generation**: For sample data

## Field Conversion

Baserow supports converting between field types (e.g., Text to Number). The `field_converters.py` module handles data migration when changing field types, preserving data where possible.

## Select Options

Single and Multiple Select fields have `SelectOption` objects with:
- `value`: Display text
- `color`: Color identifier for visual display
- `order`: Sort position

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| Field count | 30+ types | ~15 property types via JSON Schema |
| Storage | Native PostgreSQL columns | JSON properties |
| Relations | M2M through tables, FK constraints | JSON `$ref` references |
| Computed fields | Formula, Count, Rollup, Lookup | N/A (computed in PHP) |
| AI fields | Premium AI field type | N/A |
| File handling | Dedicated FileField with thumbnails | File property with Nextcloud storage |
| Field conversion | Supported with data migration | Schema change replaces validation |
| Validation | Python + PostgreSQL constraints | JSON Schema validation |
