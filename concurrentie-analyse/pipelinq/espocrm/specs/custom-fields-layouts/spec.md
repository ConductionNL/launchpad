---
competitor: espocrm
analyzed_date: 2026-03-14
feature: custom-fields-layouts
---

# Custom Fields & Layouts

## Overview

EspoCRM provides three admin tools for customization without code: **Entity Manager** (create/modify entity types), **Field Manager** (add/modify fields per entity), and **Layout Manager** (customize UI layouts). Changes are persisted in the `custom/` directory and survive upgrades.

## Entity Manager

### Capabilities (`Tools/EntityManager/`)
- **Create** custom entity types with configurable properties
- **Rename** entities (updates all metadata, classes, and references)
- **Delete** custom entities (with hook-based cleanup)
- **Configure** entity properties: labels, icon, color, disabled state, stream, categories

### Entity Templates
Pre-defined entity archetypes (`metadata/app/entityTemplateList.json`):
- Base (standard CRUD entity)
- BasePlus (with stream, categories, assigned user)
- Event (meeting/call-like with date range, attendees)
- Company (account-like with addresses)
- Person (contact-like with name, email, phone)
- CategoryTree (hierarchical categories)

### Hooks System
Entity lifecycle hooks (`EntityManager/Hook/`):
- CreateHook / UpdateHook / DeleteHook
- Specialized hooks: EventCreateHook, StreamUpdateHook, CategoriesUpdateHook, LockableUpdateHook, AssignedUsersUpdateHook, CollaboratorsUpdateHook

## Field Manager

### Capabilities (`Tools/FieldManager/`)
- Add custom fields to any entity
- Modify field properties (label, required, default, options, etc.)
- ~30 field types available (see Data Model spec)
- Field-level customization constraints per type (e.g., `customizationOptionsDisabled`)

### Field Properties (common)
- **required** - Validation requirement
- **default** - Default value (supports JavaScript expressions for datetime)
- **readOnly** / **readOnlyAfterCreate** - Editability control
- **audited** - Track changes in activity stream
- **min** / **max** - Numeric range validation
- **pattern** - Regex validation
- **options** / **isSorted** - Enum configuration
- **optionsReference** - Share options from another entity's field
- **displayAsLabel** / **labelType** / **style** - Visual rendering
- **notStorable** - Computed/virtual fields
- **directUpdateDisabled** - Prevent direct API updates
- **layoutAvailabilityList** - Restrict which layouts can show this field

### Dynamic Logic
Field visibility and requirement can be made dynamic based on other field values:
```json
// metadata/logicDefs/{EntityType}.json
{
    "fields": {
        "probability": {
            "visible": {
                "conditionGroup": [
                    { "type": "isNotEmpty", "attribute": "stage" }
                ]
            }
        }
    }
}
```

## Layout Manager

### Layout Types (`Tools/Layout/`, `Tools/LayoutManager/`)

1. **list** - List view column configuration
2. **detail** - Detail view panel/field arrangement
3. **listSmall** - Compact list (in relationship panels)
4. **detailSmall** - Compact detail (in popups)
5. **massUpdate** - Mass update form fields
6. **filters** - Available search filters
7. **defaultSidePanel** - Side panel field arrangement
8. **kanban** - Kanban card field display
9. **listForAccount** / **listForContact** - Context-specific list layouts

### Layout Sets (LayoutSet entity)
Groups of layouts that can be assigned to teams or portals, enabling different UI configurations for different user groups.

### Layout Storage
- Default layouts: `Resources/layouts/{EntityType}/`
- Custom layouts: stored in `LayoutRecord` entity (database)
- Layouts are JSON arrays/objects defining field placement

### Detail Layout Structure
```json
[
    {
        "label": "Overview",
        "rows": [
            [
                { "name": "name" },
                { "name": "status" }
            ],
            [
                { "name": "amount" },
                { "name": "closeDate" }
            ]
        ]
    },
    {
        "label": "Details",
        "rows": [...]
    }
]
```

## Label Manager

Customize field labels, entity names, and other UI text per language (`Tools/LabelManager/`).

## Link Manager

Create relationships between entities (`Tools/LinkManager/`):
- One-to-Many, Many-to-Many, One-to-One
- Additional columns on M:N relationships
- Layout relationship panel configuration

## Relevance to Pipelinq

### Strengths
- No-code entity and field creation
- Comprehensive layout customization
- Dynamic logic for conditional field visibility
- Layout sets for per-team/per-portal UI configuration
- Label customization per language
- Entity templates for common patterns

### Opportunities for Pipelinq
- **Schema-driven approach**: OpenRegister schemas provide similar flexibility but with JSON Schema standards instead of proprietary metadata format
- **Vue-based layouts**: Pipelinq's Vue frontend is more modern and extensible than EspoCRM's custom JS framework
- **Nextcloud integration**: Field types can include Nextcloud-native components (file pickers, user selectors, etc.)
- **No separate "Entity Manager"**: In OpenRegister, creating a new schema IS creating a new entity type, no separate tool needed
- **NL Design theming**: Custom fields automatically inherit government theming
