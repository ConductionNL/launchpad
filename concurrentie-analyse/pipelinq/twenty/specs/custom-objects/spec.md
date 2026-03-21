---
competitor: twenty
analyzed_date: 2026-03-14
feature: custom-objects
---

# Custom Objects & Field System

## Overview

Twenty allows users to create custom object types with custom fields, extending the CRM data model beyond the standard objects (Company, Person, Opportunity, etc.). Custom objects are first-class citizens — they get the same API exposure, view system, workflow triggers, and timeline tracking as standard objects.

## Object Metadata

The `ObjectMetadataEntity` defines an object type (stored in the shared `core` schema):

### Properties
- `nameSingular` / `namePlural` — Machine names (unique per workspace)
- `labelSingular` / `labelPlural` — Human-readable display names
- `description`, `icon`, `shortcut`
- `isCustom` — true for user-created objects
- `isRemote` — true for externally-sourced objects
- `isActive` — Whether the object is enabled
- `isSystem` — System objects that users cannot modify
- `isUIReadOnly` — Visible but not editable in UI
- `isAuditLogged` — Whether changes are tracked
- `isSearchable` — Whether included in global search
- `isLabelSyncedWithName` — Auto-sync label from name
- `duplicateCriteria` — Rules for detecting duplicate records
- `labelIdentifierFieldMetadataId` — Which field represents the record's name
- `imageIdentifierFieldMetadataId` — Which field represents the record's image

### Relations
- `fields` — FieldMetadataEntity[] (columns/properties)
- `indexMetadatas` — Database indexes
- `views` — Default and custom views
- `objectPermissions` — Role-based access rules
- `fieldPermissions` — Field-level access rules

## Creating Custom Objects

Via the `CreateObjectInput` GraphQL mutation:

```typescript
{
  nameSingular: "invoice",
  namePlural: "invoices",
  labelSingular: "Invoice",
  labelPlural: "Invoices",
  description: "Customer invoices",
  icon: "IconReceipt",
  isLabelSyncedWithName: true,
  skipNameField: false    // Auto-creates a 'name' field
}
```

When a custom object is created:
1. Object metadata is stored in the `core` schema
2. A database table is created in the workspace schema
3. Default fields are auto-created (id, createdAt, updatedAt, deletedAt, name, position, createdBy, updatedBy)
4. Default relations are created (favorites, attachments, timeline activities, task targets, note targets)
5. GraphQL schema is regenerated
6. REST endpoints become available
7. MCP tools are created

## Field Metadata

Each object has fields defined by `FieldMetadataEntity`:

### 27 Field Types
Twenty supports a rich set of field types (see data-model spec for full list). Key types for custom objects:
- TEXT, NUMBER, BOOLEAN, DATE, DATE_TIME — Basic types
- SELECT, MULTI_SELECT — Enumerated options (configured via FieldMetadataOptions)
- CURRENCY — Amount + currency code
- RELATION — Link to another object
- MORPH_RELATION — Polymorphic link to any object
- EMAILS, PHONES, LINKS — Multi-value composite types
- ADDRESS, FULL_NAME — Structured composite types
- RICH_TEXT — BlockNote document editor
- RATING — Star rating (1-5)
- FILES — File attachments
- RAW_JSON — Arbitrary JSON data

### Field Settings
Fields can have type-specific settings:
- SELECT/MULTI_SELECT: Option definitions (value, label, color)
- NUMBER: decimal places, min/max
- CURRENCY: default currency
- RELATION: target object, relation type

### Field-Level Permissions
Fields can have per-role visibility/editability controlled by `FieldPermissionEntity`.

## Standard Overrides

Standard objects can be customized per workspace via `standardOverrides`:
- Change labels, descriptions, icons
- Cannot rename standard fields but can add custom fields to standard objects
- Custom fields on standard objects are tracked separately

## Remote Objects

Twenty supports connecting to external data sources:
- `isRemote: true` objects represent data from external databases
- Connected via `DataSourceEntity` configuration
- Queries are proxied to the remote database
- Read-only by default

## Default Fields for Custom Objects

Every custom object automatically gets:
- `id` (UUID primary key)
- `name` (TEXT, label identifier)
- `position` (POSITION, for ordering)
- `createdAt`, `updatedAt`, `deletedAt` (timestamps)
- `createdBy`, `updatedBy` (ACTOR, who created/modified)
- `searchVector` (TS_VECTOR, for full-text search)

Plus default relations:
- Favorites (users can favorite any record)
- Attachments (file attachments)
- Timeline activities (audit log)
- Task targets (link tasks to this object)
- Note targets (link notes to this object)

## Pipelinq Comparison

| Aspect | Twenty | Pipelinq (OpenRegister) |
|--------|--------|------------------------|
| Custom object creation | GraphQL mutation + auto-migration | Register schema definition |
| Field types | 27 built-in composite types | JSON Schema types + formats |
| Auto-generated API | GraphQL + REST + MCP | REST routes per register |
| Default fields | 8 auto-fields + 5 relations | Configurable per schema |
| Enum/Select fields | FieldMetadataOptions | JSON Schema enum |
| Relations | Typed relation fields | Schema $ref + object links |
| Remote data | Remote objects (proxy queries) | Source/sync system |
| Field permissions | Per-role field visibility | Register-level permissions |
| Duplicate detection | Built-in criteria system | Not yet implemented |

## Key Takeaway

Twenty's custom object system is developer-friendly and fully automated — creating a custom object immediately gives you a full CRUD API, views, search, workflow triggers, and permissions. OpenRegister achieves similar flexibility through JSON Schema but with more manual configuration needed for each new schema type.
