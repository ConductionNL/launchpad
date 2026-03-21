---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# View Types

## Summary

Baserow supports 6 view types across its tiers: Grid, Gallery, and Form (open source), plus Kanban, Calendar, and Timeline (premium). Views are different visual representations of the same table data, each with its own filter/sort/group configuration and field visibility settings.

## View Types

### Grid View (Open Source)
- **Class**: `GridViewType` in `backend/src/baserow/contrib/database/views/view_types.py`
- Default spreadsheet-like view
- Features: column resize, row height, field ordering, hide columns
- Supports aggregations per column (sum, avg, count, etc.)
- Supports grouping by field values
- Can be shared publicly (public link)
- Real-time updates via WebSocket
- Row identifier: row number or first field value

### Gallery View (Open Source)
- **Class**: `GalleryViewType` in `backend/src/baserow/contrib/database/views/view_types.py`
- Card-based visual layout
- Cover image from file fields
- Configurable visible fields per card
- Public sharing support

### Form View (Open Source)
- **Class**: `FormViewType` in `backend/src/baserow/contrib/database/views/view_types.py`
- Data collection form
- Conditional field visibility (show/hide fields based on conditions)
- Custom submit button text and redirect URL
- Notification on submission
- Public sharing (no auth required)
- Form view mode types for different layouts
- Field-level options: required, description, conditions

### Kanban View (Premium)
- **Class**: `KanbanViewType` in `premium/backend/src/baserow_premium/views/view_types.py`
- Drag-and-drop board organized by Single Select field
- Configurable stack field
- Card field visibility settings
- Cover image support

### Calendar View (Premium)
- **Class**: `CalendarViewType` in `premium/backend/src/baserow_premium/views/view_types.py`
- Date-based calendar layout
- Requires a date field for positioning
- Month/week/day views

### Timeline View (Premium)
- **Class**: `TimelineViewType` in `premium/backend/src/baserow_premium/views/view_types.py`
- Gantt-chart-like horizontal timeline
- Requires start and end date fields
- Visual duration representation

## View Features (Common)

### Filters
- Per-view filter configuration
- Filter groups with AND/OR logic
- 40+ filter types: equal, not_equal, contains, higher_than, date_before, etc.
- Adhoc filters via API query parameters
- Array-aware filters for multi-select and link row fields

### Sorting
- Multi-field sorting
- ASC/DESC per sort rule
- Collation-aware sorting for text fields

### Grouping
- Group rows by field values (Grid view)
- Multiple group levels

### Decorators
- Visual row decorators (e.g., color coding)
- Conditional decorator rules

### Field Options
- Per-view field visibility (show/hide)
- Per-view field ordering
- Per-view field width (Grid)
- Aggregation type per field (Grid)

### Public Sharing
- Grid, Gallery, and Form views can be shared via public link
- Password-protected sharing option
- Slug-based public URLs

## View Ownership (Enterprise)
- Personal views (visible only to creator)
- Collaborative views (shared with workspace)
- `view_ownership_types.py` in enterprise module

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| View types | 6 (Grid, Gallery, Form, Kanban, Calendar, Timeline) | 2 (Table/List, Detail) |
| Filtering | 40+ filter types, groups, adhoc | Basic API filtering |
| Sorting | Multi-field, collation-aware | API sort parameter |
| Grouping | Multi-level field grouping | N/A |
| Public sharing | Per-view public links | Public API endpoints |
| Aggregations | Sum, avg, count per column | N/A in views |
| Form builder | Dedicated FormView | N/A |
