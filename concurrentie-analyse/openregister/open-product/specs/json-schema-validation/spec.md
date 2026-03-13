# JSON Schema Validation

## Summary

Open Product uses JSON Schema (latest draft) to validate `verbruiksobject` and `dataobject` fields on Product instances. Schemas are managed as named, reusable entities that can be assigned to product types.

## Data Model

### JsonSchema
- `naam` -- CharField, unique, max 200
- `schema` -- JSONField (the actual JSON Schema definition)

### Validation
- Schema itself is validated against the latest JSON Schema meta-schema on `clean()`
- Product's `verbruiksobject` validated against `producttype.verbruiksobject_schema`
- Product's `dataobject` validated against `producttype.dataobject_schema`
- Validation only runs when both the data field and schema are non-null

## API Endpoints
- `GET/POST /producttypen/api/v1/schemas` -- list/create
- `GET/PUT/PATCH/DELETE /producttypen/api/v1/schemas/{uuid}` -- detail CRUD

### Usage in ProductType
- `verbruiksobject_schema_naam` (write) / `verbruiksobject_schema` (read)
- `dataobject_schema_naam` (write) / `dataobject_schema` (read)
- Referenced by name (SlugRelatedField on `naam`)

### Filtering on Products
- `?dataobject_attr=key__operator__value` -- filter products by dataobject JSON content
- `?verbruiksobject_attr=key__operator__value` -- filter products by verbruiksobject JSON content
- Supports nested keys: `auto__kenteken__exact__AA-111-B`
- Operators: exact, icontains, gt, gte, lt, lte, in, isnull

## Already in OpenRegister
- JSON Schema validation on object properties (core feature)
- Schema-based object structure enforcement
- Reusable schema definitions

## Not yet in OpenRegister
- **Named, standalone schema entities** managed via dedicated API
- **Dual schema assignment** (verbruiksobject + dataobject per type)
- **Schema protection** (PROTECT on delete, cannot delete schema in use)
- **JSON attribute filtering** on product data (key__operator__value syntax)
