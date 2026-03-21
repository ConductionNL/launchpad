---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# View System

## Overview

NocoDB supports 5 view types per table: Grid, Form, Gallery, Kanban, and Calendar. Each view has independent field visibility, sorting, filtering, and grouping. Views are stored in the `nc_views` meta table with type-specific configuration in dedicated tables (GridView, FormView, GalleryView, KanbanView, CalendarView).

## View Types

### Grid View
- **Rendering:** HTML5 Canvas (not DOM tables)
- **Features:** Inline editing, row expansion, column resizing, column reordering
- **Toolbar:** Fields visibility, Filter, Group by, Sort, Search, Fullscreen
- **Row operations:** Add row, expand row, checkbox select, row numbering
- **Column operations:** Right-click context menu (edit, duplicate, sort, filter, group, insert)
- **Bulk actions:** Multi-select rows, bulk delete/update
- **Aggregation:** Column footer with sum/avg/count/min/max

### Form View
- **Builder:** Drag-and-drop field ordering
- **Customization:** Banner image, logo upload, form title, rich text description
- **Field options:** Required, help text, default value per field
- **Submission:** Submit button with configurable success message
- **Sharing:** Public form URL for external data collection
- **Branding:** NocoDB branding in footer (removable in paid plans)

### Gallery View
- **Layout:** Responsive card grid
- **Cover image:** Configurable attachment field as card cover
- **Display:** Shows display value (title) and visible fields
- **Click action:** Opens expanded row view

### Kanban View
- **Stack by:** Requires a SingleSelect field
- **Cards:** Show configurable fields per card
- **Operations:** Drag-and-drop between stacks, add new records per stack
- **Management:** Add/remove stacks (adds/removes select options)
- **Count:** Per-stack record count display

### Calendar View
- **Organize by:** Requires a Date or DateTime field
- **Views:** Month, Week, Day toggles
- **Sidebar:** Record list with sort and filter options
- **Navigation:** Previous/next, "Today" button, month picker
- **Display:** Records shown as event indicators on calendar dates

## View-Specific Models

Each view type has dedicated models for configuration:
- `GridView` + `GridViewColumn` — Column widths, visibility, ordering
- `FormView` + `FormViewColumn` — Field labels, help text, required, order
- `GalleryView` + `GalleryViewColumn` — Cover image, visible fields
- `KanbanView` + `KanbanViewColumn` — Stack field, card fields
- `CalendarView` + `CalendarViewColumn` + `CalendarRange` — Date field, range configuration

## View-Level Features

All views support:
- **Independent field visibility** — Hide/show columns per view
- **Filters** — Per-view filter conditions
- **Sorts** — Per-view sort configuration
- **Row coloring** — Conditional row colors (RowColorCondition model)
- **Sharing** — Each view can be shared independently with a public URL
- **Locking** — Views can be locked to prevent edits

## Relevance to OpenRegister

Key takeaways for OpenRegister:
1. **View independence** is powerful — same data, different presentations
2. **Kanban** requires a specific field type (SingleSelect) - smart constraint
3. **Calendar** auto-detects date fields for organization
4. **Form view** as a built-in public data collection tool is very useful
5. **Canvas grid** sacrifices accessibility for performance - a trade-off to consider
