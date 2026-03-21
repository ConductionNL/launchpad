---
competitor: espocrm
analyzed_date: 2026-03-14
feature: Entity Customization (No-Code)
relevance: high
pipelinq_equivalent: OpenRegister schemas, register configuration
---

# Entity Customization (No-Code)

## Overview

EspoCRM's Entity Manager provides a comprehensive no-code customization system. Administrators can create custom entities, fields, relationships, and layouts without writing any code. This is one of EspoCRM's strongest features and a key differentiator.

## Entity Manager

### Create Custom Entities
- Define new entity types (e.g., "Project", "Product", "Warranty")
- Configure entity scope (activities, stream, portal access, etc.)
- Set up navigation tab ordering
- Choose icon for the entity

### Field Types Available
- **Text:** Varchar, Text, Wysiwyg, URL, Email, Phone
- **Numeric:** Int, Float, Currency, Currency Converted, Auto-increment
- **Date/Time:** Date, DateTime, Date-Time Optional
- **Choice:** Enum, Multi-Enum, Checklist, Array
- **Relationship:** Link, Link-Multiple, Link-Parent
- **File:** File, Image, Attachment Multiple
- **Special:** Boolean, Address, Barcode, Color-picker, Foreign, Map
- **Computed:** Formula-calculated fields

### Field Properties
- Required/optional
- Read-only
- Audited (track changes in stream)
- Default value (static or formula)
- Tooltip text
- Pattern validation
- Min/max values
- Options list (for enums)

## Layout Manager

### Available Layout Types
- **List** - Column selection and ordering for list views
- **Detail** - Panel/field arrangement for record detail views
- **List (Small)** - Compact list for relationship panels
- **Detail (Small)** - Compact detail for quick views
- **Bottom Panels** - Configure relationship panels on detail view
- **Side Panels** - Configure side panels
- **Search Filters** - Configure available search filters
- **Mass Update** - Fields available for bulk updates
- **Kanban** - Configure kanban view columns

### Layout Features
- Drag-and-drop field arrangement
- Multi-column panels
- Collapsible panels
- Custom panel labels
- Field width configuration

## Dynamic Logic

### Conditional Field Behavior
- **Visible** - Show/hide fields based on other field values
- **Required** - Make fields required conditionally
- **Read-only** - Make fields read-only conditionally
- **Options filtering** - Filter enum options based on conditions

### Condition Builder
- Visual UI for building conditions
- Supports AND/OR groups
- Field comparisons (equals, not equals, contains, greater than, etc.)
- Related record field checks

## Formula Engine

### Capabilities
- **Calculated fields** - Auto-compute values on save
- **Before-save scripts** - Run logic before record persistence
- **Workflow/BPM actions** - Execute formulas in automation
- **Email templates** - Dynamic content generation

### Function Categories
- General (ifThenElse, switch, while, list)
- String (concatenation, replace, trim, length, etc.)
- DateTime (add/subtract, format, diff, etc.)
- Number (round, floor, ceil, abs, etc.)
- Entity (get/set attributes, related records)
- Record (CRUD operations on any entity)
- Environment (current user, date, time)
- Array/Object manipulation
- JSON parsing
- Language/translation
- Logging
- Extension functions (BPM, workflow-specific)

## API Before-Save Script

- Formula scripts that execute before any API save operation
- Can validate data, calculate fields, set defaults
- Applies to both UI and API operations
- Per-entity configuration

## Strengths

- Full no-code entity creation and customization
- Rich set of field types (30+)
- Visual layout editor with drag-and-drop
- Dynamic logic for conditional UI
- Powerful formula engine with 100+ functions
- Changes apply immediately (no deployment needed)
- Custom entities are first-class citizens (same features as built-in)
- API Before-Save scripts for validation

## Weaknesses

- No versioning of customizations
- No multi-tenant schema isolation
- No schema migration tools
- No visual data modeling (ERD)
- Formula language is proprietary (not JavaScript/Python)
- No custom field type creation via UI (requires code)
- No form builder for public-facing forms (only Web-to-Lead)

## Comparison with Pipelinq

| Aspect | EspoCRM Entity Manager | Pipelinq (OpenRegister) |
|--------|----------------------|------------------------|
| Custom entities | Full UI creation | Schema-based definition |
| Field types | 30+ built-in | JSON Schema types |
| Layout editor | Visual drag-and-drop | Layout configuration |
| Dynamic forms | Dynamic Logic rules | Vue component logic |
| Formula engine | Proprietary script | OpenRegister formulas / n8n |
| Validation | Before-save scripts | Schema validation |
| API | Auto-generated REST | OpenRegister API |
| Relationships | UI-configurable | Schema references |
| No-code level | Very high | Medium (requires schema knowledge) |
| Versioning | None | Git-based schemas |
