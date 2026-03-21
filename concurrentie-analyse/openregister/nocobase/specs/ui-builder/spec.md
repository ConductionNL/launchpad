---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# UI Builder

## Purpose

NocoBase's UI builder enables no-code construction of application interfaces through a visual drag-and-drop editor. The entire UI is schema-driven, stored as JSON in the database, and rendered at runtime by a Formily-based schema component renderer.

## Architecture Overview

```
UI Schema (JSON in DB)
    |
    v
SchemaComponent Renderer (Formily)
    |
    v
React Components (Ant Design)
    |
    v
Block Providers (data fetching)
    |
    v
Collection Manager (field metadata)
```

### Key Client Packages
- `@nocobase/client` - Main React application with schema rendering
- `plugin-ui-schema-storage` - Server-side schema persistence
- `plugin-client` - Client plugin registration

## Data Model

### UI Schema Structure
Each schema node contains:
- `name` - Unique identifier (auto-generated UID)
- `x-component` - React component name (e.g., "Table", "Form", "CardItem")
- `x-decorator` - Wrapper component (e.g., "BlockItem", "FormItem")
- `x-component-props` - Component props
- `x-decorator-props` - Decorator props
- `x-collection-field` - Bound collection field
- `properties` - Child schema nodes (tree structure)

### Schema Templates
Reusable schema patterns stored as templates:
- **Block templates (v2)** - Reusable block configurations
- **Popup templates (v2)** - Reusable dialog layouts
- **Reference template (v1)** - Shared schema references
- **Inherited template (v1)** - Schema inheritance

## Business Logic

### Page Types
1. **Classic page (v1)** - Traditional block layout with grid system
2. **Modern page (v2)** - Enhanced layout with new features
3. **Group** - Menu grouping container
4. **Link** - External URL navigation

### Data Blocks
Each block type binds to a collection and renders data:

| Block | Description | Use Case |
|-------|-------------|----------|
| **Table** | Spreadsheet-like grid | Data browsing, inline editing |
| **Form** | Input form | Record creation/editing |
| **Details** | Read-only view | Record viewing |
| **List** | Vertical card list | Content browsing |
| **Grid Card** | Card grid layout | Gallery/catalog views |
| **Calendar** | Calendar view | Date-based records |
| **Charts** | Visualizations | Data analysis, dashboards |
| **Gantt** | Timeline chart | Project management |
| **Kanban** | Board view | Status-based workflows |

### Filter Blocks
- **Filter Form** - Search form that filters connected data blocks
- **Collapse** - Collapsible filter panel with facets

### Other Blocks
- **Markdown** - Static rich text content
- **Iframe** - Embedded external content
- **Action panel** - Custom action button groups
- **Workflow todos** - Pending approval tasks

### UI Editor Mode
When enabled (toggle in top-right):
- Orange dashed borders show editable areas
- "Add block" buttons appear in empty areas
- Drag handles for reordering blocks
- Settings gear icons for block configuration
- Schema initializers for adding new elements

### Schema Initializers
Context-sensitive menus that offer appropriate options:
- Page initializer: Add block types
- Table initializer: Configure columns
- Form initializer: Configure form fields
- Action initializer: Add action buttons

### Block Actions
Each block supports configurable actions:
- **Table:** Filter, Add new, Delete, Refresh, Export, Import, Bulk edit, Bulk update
- **Form:** Submit, Reset, Custom actions
- **Details:** Edit, Delete, Duplicate, Print

## Requirements

### Functional
- Visual WYSIWYG page builder
- Drag-and-drop block placement
- Block configuration without code
- Schema persistence across sessions
- Template/reuse support
- Responsive grid layout
- Action configuration per block

### Non-functional
- Schema rendering performance (large pages)
- Undo/redo support
- Mobile-responsive layouts
- Theme-aware rendering

## UI Reference

See screenshots: `10-ui-editor-menu.png`, `11-ui-editor-page.png`, `12-block-types.png`

## Comparison Notes

### vs OpenRegister UI
- NocoBase has a full visual UI builder; OpenRegister uses Vue components in code
- NocoBase stores UI as JSON schemas; OpenRegister uses Vue templates
- NocoBase has 9+ block types; OpenRegister has list views and detail views
- NocoBase supports drag-and-drop; OpenRegister requires developer intervention
- Both use a component library (Ant Design vs Nextcloud Vue)
- NocoBase's approach enables non-developer customization at the cost of flexibility
