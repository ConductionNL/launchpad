# Directus Data Model

**Source:** https://docs.directus.io/getting-started/data-model.html, https://docs.directus.io/guides/data-model/fields.html, https://docs.directus.io/guides/data-model/relationships.html

## Collections

Collections are database tables exposed through the Directus interface. Creating a collection:
1. Log into Data Studio as administrator
2. Go to Settings > Data Model
3. Create a new collection (e.g., `posts`)
4. Each collection automatically gets a primary key field

## Fields

Fields are database columns with additional Directus metadata (display config, validation, interfaces).

### Field Types

| Group | Types |
|-------|-------|
| Text | `String`, `Text`, `UUID`, `Hash`, `Alias` |
| Numeric | `Integer`, `Big Integer`, `Float`, `Decimal` |
| Boolean | `Boolean` |
| Date and Time | `Timestamp`, `DateTime`, `Date`, `Time` |
| Binary | `Binary` |
| Structured | `JSON`, `CSV` |
| Geospatial | `Point`, `LineString`, `Polygon`, `MultiPoint`, `MultiLineString`, `MultiPolygon` |

### Field Configuration

When creating a field, you configure:
- **Interface** — How users interact with data (text input, date selector, map, relationship selectors, etc.)
- **Key** — Unique field name within collection (immutable after creation)
- **Type** — Underlying database type (immutable after creation)

Advanced configuration sections:
- **Schema** — Database column settings (key, type, length, default value, nullable, unique, indexed, searchable)
- **Field** — Interface details (read-only, required, notes, field name translations)
- **Interface** — Form input configuration (varies per interface type)
- **Display** — How values are shown throughout Data Studio (conditional styles for colors/icons)
- **Validation** — Rules for valid input (client-side + server-side, custom error messages)
- **Conditions** — Alter field config based on other field values (conditional read-only, hidden, required)
- **Relationships & Translations** — Relational configuration, junction collections, sort fields, cascade behavior

### Field Width
Fields can be `half`, `full`, or `fill` width in the editor.

### Data Attributes
Each field includes data attributes for programmatic identification:
- `data-collection` — Collection name
- `data-field` — Field name
- `data-primary-key` — Item ID

## Relationships

### Many to One (M2O)
Multiple items from one collection linked to one item in another. Creates a foreign key field.

### One to Many (O2M)
One item linked to multiple items. Creates an `Alias` field (virtual, no database column). Requires M2O on the other side.

### Many to Many (M2M)
Uses a junction collection storing primary keys from both collections. Supports self-referencing M2M.

### Many to Any (M2A)
One collection can relate to any item in any collection. Junction collection also stores collection name. Known as "matrix field" or "replicator."

### Translations
Special relationship type: creates a `languages` collection and junction collection. All translated text stored in junction collection.

## Comparison Notes (vs OpenRegister)

| Aspect | Directus | OpenRegister |
|--------|----------|-------------|
| Schema Definition | Visual UI + database mirroring | JSON Schema based |
| Field Types | Database-native with standardization | JSON Schema types |
| Relationships | M2O, O2M, M2M, M2A, Translations | JSON Schema $ref, register-based relations |
| Geospatial | Native GeoJSON types | Via JSON Schema |
| Validation | UI-based filter rules | JSON Schema validation |
| Existing DB | Auto-discovers existing tables | Schema-first approach |
| M2A (polymorphic) | Built-in | Not natively supported |
| Translations | Built-in relationship type | Manual via schema design |
