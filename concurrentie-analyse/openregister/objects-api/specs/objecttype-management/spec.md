---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# Objecttype Management — Objects API (Documentation View)

## Purpose
Manage object type definitions with rich metadata and versioned JSON schemas. Objecttypes define the structure that Objects must conform to.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/api/usage.html
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/admin/objecttype.html

## API Reference
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v2/objecttypes` | List objecttypes (filterable by dataClassification) |
| POST | `/api/v2/objecttypes` | Create objecttype metadata |
| GET | `/api/v2/objecttypes/{uuid}` | Retrieve objecttype |
| PUT | `/api/v2/objecttypes/{uuid}` | Update objecttype metadata |
| PATCH | `/api/v2/objecttypes/{uuid}` | Partial update metadata |
| DELETE | `/api/v2/objecttypes/{uuid}` | Delete objecttype |
| GET | `/api/v2/objecttypes/{uuid}/versions` | List versions |
| POST | `/api/v2/objecttypes/{uuid}/versions` | Create new version |
| GET | `/api/v2/objecttypes/{uuid}/versions/{v}` | Get specific version |
| PUT | `/api/v2/objecttypes/{uuid}/versions/{v}` | Update version (draft only) |
| PATCH | `/api/v2/objecttypes/{uuid}/versions/{v}` | Partial update version (draft only) |
| DELETE | `/api/v2/objecttypes/{uuid}/versions/{v}` | Delete version |

### Objecttype Metadata Fields
- name, namePlural (required)
- description (max 1000 chars)
- dataClassification: open, intern, confidential, strictly_confidential
- maintainerOrganization, maintainerDepartment
- contactPerson, contactEmail
- source, providerOrganization
- updateFrequency: real_time, hourly, daily, weekly, monthly, yearly, unknown
- documentationUrl
- labels (key-value pairs)
- allowGeometry (boolean)
- createdAt, modifiedAt (auto)

### Version Workflow
1. Create version with status "draft" — editable
2. PATCH to status "published" — becomes immutable, sets publishedAt
3. Can also set to "deprecated"
4. New changes require creating a new version

## Configuration Options
- In v4.0.0, Objecttypes API will be merged into Objects API
- `import_objecttypes` management command for migration

## Integration Points
- Objects API connects to Objecttypes API as a service
- JSON schemas used for object validation
- Can be national (shared) or local (per organization)

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Schema definition | Separate Objecttypes API (merging in v4.0) | Schemas entity in same app |
| Schema format | JSON Schema (draft-07) | JSON Schema |
| Versioning | Explicit versions with draft/published/deprecated | No explicit versioning |
| Metadata | Rich: 15+ metadata fields | Minimal: name, description |
| Data classification | 4 levels (open to strictly_confidential) | Not available |
| Draft/publish workflow | Yes (immutable after publish) | No |
| National/local split | Yes (designed for national standardization) | No (single instance) |
| Allow geometry toggle | Per objecttype | Per schema property |

**Already in OpenRegister**: Schema management, JSON Schema validation, CRUD on schemas
**Not yet in OpenRegister**: Schema versioning with draft/published workflow, rich metadata (classification, contact info, update frequency), national/local objecttype concept, immutable published versions
