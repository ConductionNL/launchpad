---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# Object CRUD — Objects API (Documentation View)

## Purpose
Core CRUD operations for managing Objects. Each Object belongs to an Objecttype and stores its state in Records. Updates create new Records rather than mutating existing ones.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/api/usage.html
- OpenAPI spec: `src/objects/api/v2/openapi.yaml`

## API Reference
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v2/objects` | List objects with current record |
| POST | `/api/v2/objects` | Create object with initial record |
| GET | `/api/v2/objects/{uuid}` | Retrieve single object |
| PUT | `/api/v2/objects/{uuid}` | Full update (creates new record) |
| PATCH | `/api/v2/objects/{uuid}` | Partial update (recursive merge) |
| DELETE | `/api/v2/objects/{uuid}` | Delete object and all records |

### Key Behaviors
- POST creates both the Object and its first Record
- PUT creates a new Record with full replacement
- PATCH creates a new Record with recursive merge of data
- DELETE removes the Object and ALL Records (privacy compliance)
- Objects reference their Objecttype via URL (`type` field)
- Each Record specifies which ObjecttypeVersion it uses (`typeVersion`)
- Data is validated against the JSON schema of the referenced ObjecttypeVersion
- Supports sparse field selection via `fields` query parameter
- Supports pagination (default 100, max 500)
- Supports ordering by any field including nested data attributes
- Experimental: DELETE with `?zaak=<url>` for archive destruction workflows

### Object Schema
```json
{
  "url": "auto-generated URL",
  "uuid": "UUID4",
  "type": "URL to objecttype",
  "record": {
    "index": "auto-increment",
    "typeVersion": "integer (required)",
    "data": "object (validated against JSON schema)",
    "geometry": "GeoJSON or null",
    "references": ["zaak URLs"],
    "startAt": "date (required)",
    "endAt": "date (auto)",
    "registrationAt": "date (auto)",
    "correctionFor": "integer or null",
    "correctedBy": "integer (auto)"
  }
}
```

## Configuration Options
- `NOTIFICATIONS_DISABLED` — Disable notification on CRUD operations
- `ENABLE_CLOUD_EVENTS` — Enable experimental cloud events on zaak link/unlink

## Integration Points
- Validates against Objecttypes API JSON schemas
- Sends notifications to Notificaties API
- Can link to zaken for archiving workflows

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| CRUD operations | Full REST (GET/POST/PUT/PATCH/DELETE) | Full REST (GET/POST/PUT/PATCH/DELETE) |
| Data model | Object + Records (immutable history) | Object entity with direct mutation |
| Schema validation | JSON schema from external Objecttypes API | JSON schema from local Schema entity |
| Identification | UUID | UUID |
| Field selection | `fields` query parameter | Not yet implemented |
| Recursive PATCH merge | Yes | Not yet implemented |
| Pagination | page/pageSize (max 500) | page-based |
| Ordering | Any field including nested data | Supported |

**Already in OpenRegister**: CRUD operations, UUID identification, schema validation, pagination, ordering
**Not yet in OpenRegister**: Field selection (sparse responses), recursive PATCH data merge, immutable record history, correction records, references/zaak linking
