---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Case File Management
category: core
---

# Case File Management

## Overview

The CaseFabric Case File is CMMN's data container -- a hierarchical structure of typed items and properties that holds all data relevant to a case. Changes to case file items trigger sentries, enabling data-driven case progression.

## Implementation Details

### Definition Layer

- `CaseFileDefinition` -- root of the case file model, contains top-level items
- `CaseFileItemDefinition` -- defines a data item with name, multiplicity, and children
- `CaseFileItemDefinitionDefinition` -- defines the type/structure (JSON, XML, complex)
- `PropertyDefinition` -- individual properties with name and type
- `CaseFileItemCollectionDefinition` -- shared base for items and collections
- `ImportDefinition` -- external type references

Supported definition types (`DefinitionType` interface):
- `JSONType` -- JSON schema-based
- `XMLComplexType` / `XMLSimpleType` / `XMLElementType` -- XML schema-based
- `UnspecifiedType` / `UnknownType` -- generic

### Runtime Layer

- `CaseFile` -- case-level data container, holds top-level `CaseFileItem` instances
- `CaseFileItem` -- individual data item, holds a JSON `Value`
- `CaseFileItemArray` -- array container for multi-valued items
- `EmptyCaseFileItem` -- placeholder for uninitialized items
- `Path` -- dot-notation path for navigating the hierarchy (e.g., `Order/Items[0]/Name`)

### Transitions

Case file items support these transitions (from `CaseFileItemTransition` enum):
- `Create` -- item created with initial value
- `Replace` -- item value fully replaced
- `Update` -- item value merged/updated
- `Delete` -- item removed
- `AddChild` -- child item added to collection
- `RemoveChild` -- child item removed from collection
- `AddReference` -- reference to external item added
- `RemoveReference` -- reference removed

Each transition generates a `CaseFileEvent` and notifies the `SentryNetwork`, enabling criteria like "when Order is created, activate the Review stage."

### Business Identifiers

Properties can be marked as business identifiers in the definition:
- `BusinessIdentifier` class tracks changes to marked properties
- Values stored in `case_business_identifier` query table
- Enables cross-case search: `GET /cases?identifiers=CustomerLevel=Gold`
- Supports equality, inequality, any-value, and combination queries

### Case File API

| Method | Path | Description |
|--------|------|-------------|
| GET | `/cases/{id}/casefile` | Get full case file |
| PUT | `/cases/{id}/casefile/{path}` | Replace item at path |
| POST | `/cases/{id}/casefile/{path}` | Create/update item at path |
| DELETE | `/cases/{id}/casefile/{path}` | Delete item at path |

### Data Binding

Case file items are bound to task parameters via `ParameterMappingDefinition`:
- Input parameters map case file data to task input
- Output parameters map task output back to case file
- `BindingOperation` and `BindingRefinementDefinition` control the mapping behavior

### Migration Support

When a case definition is migrated:
- `CaseFile.migrateDefinition()` updates the file model
- Items matching the new definition are preserved
- Items not in the new definition generate `CaseFileItemDropped` events
- New items generate `CaseFileItemMigrated` events

## Relevance for Procest

1. **Hierarchical data model** -- structured case data with typed properties
2. **Business identifiers** -- cross-case search on key properties (valuable for government dossiers)
3. **Data-driven triggers** -- case file changes activating stages/tasks
4. **Path-based access** -- dot-notation for navigating nested data
5. **Parameter mapping** -- declarative data flow between case file and task I/O
