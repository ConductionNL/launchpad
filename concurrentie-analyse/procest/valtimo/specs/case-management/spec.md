---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Case Management -- Valtimo

## Purpose
Core case (zaak) management functionality. A "case" in Valtimo is a `JsonSchemaDocument` -- a JSON document validated against a JSON Schema definition. Cases can be searched, filtered, assigned, tagged, and moved through statuses.

## Architecture Overview
- **Backend**: `case/` module (Kotlin/Java, Spring Boot, JPA)
- **Frontend**: `case/` Angular library with components for list, detail, tabs, search
- **Central entity**: `JsonSchemaDocument` stored in `json_schema_document` table
- **Definition**: `JsonSchemaDocumentDefinition` with versioned JSON Schema
- **Event sourcing**: Document changes trigger Spring domain events

## Data Model

### JsonSchemaDocument (The "Case")
| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID (embedded) | Unique document ID |
| `content` | JSON | Flexible JSON content validated against schema |
| `documentDefinitionId` | Embedded | Reference to definition name |
| `version` | Integer | JPA optimistic lock version |
| `createdOn` | LocalDateTime | Creation timestamp |
| `modifiedOn` | LocalDateTime | Last modification timestamp |
| `createdBy` | String(255) | Creator username |
| `sequence` | Long | Auto-incrementing sequence per definition |
| `internalStatus` | ManyToOne | Case status reference |
| `caseTags` | ManyToMany | Set of colored tags |
| `assigneeId` | String(64) | Assigned user ID |
| `assigneeFullName` | String(255) | Assigned user display name |
| `documentRelations` | JSON | Parent/child/related document links |
| `relatedFiles` | JSON | Attached files with metadata |

### JsonSchemaDocumentDefinition (Case Type)
| Field | Type | Description |
|-------|------|-------------|
| `id` | Embedded | Name-based composite ID |
| `schema` | Embedded | JSON Schema for validation |
| `createdOn` | LocalDateTime | Creation timestamp |

### CaseDefinition (Case Definition Container)
Managed via `CaseDefinitionResource`. Has versioning with `versionTag`, can be finalized, drafted, activated/deactivated. Settings include list columns, search fields, tabs.

## Business Logic

### Case Creation Flow
1. Client sends `NewDocumentRequest` with content JSON
2. `JsonSchemaDocumentDefinition.validate()` validates against JSON Schema
3. Sequence number generated via `DocumentSequenceGeneratorService`
4. `JsonSchemaDocument.create()` produces `CreateDocumentResult`
5. `JsonSchemaDocumentCreatedEvent` published
6. Optional: process instance started via process-document bridge

### Case Modification Flow
1. Client sends `ModifyJsonSchemaDocumentRequest` with new content
2. Content diffed against current; validation run against definition
3. Full JSON diff produces `JsonSchemaDocumentFieldChangedEvent` list
4. `JsonSchemaDocumentModifiedEvent` published with all changes
5. Optimistic lock via `@Version` prevents concurrent writes

### Case Search
- `POST /api/v1/case/{caseDefinitionName}/search` with `SearchWithConfigRequest`
- Supports configurable search fields per case definition
- Returns `Page<CaseListRowDto>` with configurable list columns
- Quick search storage per user per case type

## API Endpoints

### Case Definition
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/case-definition` | List case definitions |
| GET | `/api/management/v1/case-definition/{key}/version/{tag}` | Get specific version |
| POST | `/api/management/v1/case-definition/draft` | Create draft definition |
| DELETE | `/api/management/v1/case-definition/{key}/version/{tag}` | Delete definition |
| PATCH | `/api/management/v1/case-definition/{key}/version/{tag}` | Update name/description |
| POST | `/api/management/v1/case-definition/{key}/version/{tag}/finalize` | Finalize draft |
| POST | `/api/management/v1/case-definition/{key}/version/{tag}/active` | Set as active |
| GET | `/api/management/v1/case-definition/{key}/version/{tag}/export` | Export as ZIP |
| POST | `/api/management/v1/case/import` | Import from ZIP |

### Case Instance
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/case/{name}/search` | Search cases |
| POST | `/api/v1/case/{key}/stored-quick-search` | Save quick search |
| DELETE | `/api/v1/case/{key}/stored-quick-search/{title}` | Delete quick search |
| GET | `/api/v1/case/{key}/stored-quick-search` | List quick searches |
| POST | `/api/v1/case/{name}/export` | Export to CSV |

### Case Settings
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/case-definition/{key}/settings` | Get settings |
| PATCH | `/api/management/v1/case-definition/{key}/version/{tag}/settings` | Update settings |
| GET | `/api/v1/case/{name}/list-column` | Get list columns |
| POST | `/api/management/v1/case/{name}/list-column` | Create list column |
| PUT | `/api/management/v1/case/{name}/list-column` | Update list columns |
| DELETE | `/api/management/v1/case/{name}/list-column/{key}` | Delete list column |

## Frontend Components
- `case-list` -- Paginated case list with configurable columns, search, bulk actions
- `case-detail` -- Tabbed detail view (summary, progress, audit, documents, notes, formio, custom, widgets)
- `case-assign-user` -- User assignment dialog
- `case-bulk-assign-modal` -- Batch assignment
- `case-process-start-modal` -- Start process for case
- `case-supporting-process-start-modal` -- Start supporting process
- `case-update` -- Inline case content editing
- `case-list-actions` -- Dropdown actions menu
- `note-modal` -- Note create/edit dialog

## Requirements (as observed)
- Cases validated against JSON Schema before save
- Optimistic locking prevents concurrent modification
- Case definitions support versioning with draft/final/active states
- Cases support assignment to a single user
- Cases support multiple colored tags
- Case list columns are configurable per case type
- Search fields are configurable per case type
- Quick searches can be saved per user

## Comparison Notes -- Valtimo vs Procest

### Procest HAS
- Case list with search/filtering (via OpenRegister search)
- Case detail views with sub-cases
- Case assignment
- Deadline tracking
- Pipeline (Kanban) views -- Valtimo does NOT have this

### Procest DOES NOT HAVE
- JSON Schema validation of case content (Procest uses OpenRegister schemas)
- Case definition versioning with draft/final/active lifecycle
- Configurable list columns per case type (admin UI)
- Quick search storage per user
- CSV export of case lists
- Case-level tags with colors
- Document snapshots
- Optimistic locking with `@Version`
- Case definition import/export as ZIP archives
