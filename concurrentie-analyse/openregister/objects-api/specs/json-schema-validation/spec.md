---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# JSON Schema Validation — Objects API (Documentation View)

## Purpose
Validate object data against JSON schemas defined in Objecttype versions. Ensures data consistency across all objects of the same type.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/api/usage.html

## How It Works

1. When creating/updating an Object, the `type` field references the Objecttype URL
2. The `record.typeVersion` specifies which version of the Objecttype to validate against
3. Objects API fetches the JSON schema from the Objecttypes API
4. The `record.data` is validated against the JSON schema
5. If validation fails, HTTP 400 with descriptive error message

### Validation on Objecttype Creation
When creating a new objecttype version, the JSON schema itself is validated to ensure it is a valid JSON schema (draft-07).

### Error Response Example
```json
{
  "non_field_errors": [
    "2.5 is not of type 'integer'"
  ]
}
```

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Schema standard | JSON Schema draft-07 | JSON Schema |
| Validation timing | On create and update | On create and update |
| Schema source | External API call to Objecttypes API | Local database |
| Schema validation | JSON schema is validated on creation | Schema validated on creation |
| Error messages | Standard jsonschema errors | Standard jsonschema errors |
| Cross-version support | Object can reference any version | Single schema version |

**Already in OpenRegister**: JSON Schema validation on CRUD, schema validation on creation
**Not yet in OpenRegister**: Multi-version schema validation (object specifies which version), fetching schemas from external APIs
