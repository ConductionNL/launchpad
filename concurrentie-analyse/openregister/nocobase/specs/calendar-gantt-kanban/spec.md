---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Calendar, Gantt & Kanban Views

## Purpose

NocoBase provides three specialized data visualization blocks for project management and scheduling: Calendar, Gantt, and Kanban. Each is implemented as a separate plugin that adds a block type to the UI builder.

## Architecture Overview

Each view is a client-side block component backed by collection data:

```
Collection Data (via API)
    |
    v
Block Provider (fetches + filters data)
    |
    v
View Component (Calendar / Gantt / Kanban)
    |
    v
User Interactions (drag, click, resize)
    |
    v
API Updates (PATCH to collection)
```

## Data Model

### Calendar (`plugin-calendar`)
Requires a collection with date/datetime fields:
- `startField` - Event start date/time
- `endField` - Event end date/time (optional)
- `titleField` - Event display name

Server-side collections define calendar-specific metadata.

### Gantt (`plugin-gantt`)
Requires a collection with:
- `startField` - Task start date
- `endField` - Task end date
- `titleField` - Task name
- `progressField` - Completion percentage (optional)

Server-side plugin is minimal (collections + plugin registration).

### Kanban (`plugin-kanban`)
Requires a collection with:
- `groupField` - Single-select field for column grouping
- `sortField` - Sort field for card ordering within columns

Server-side plugin defines kanban-specific collections.

## Business Logic

### Calendar Block
- Month/week/day views
- Event creation by clicking on date
- Event dragging to reschedule
- Event click opens detail/edit popup
- Filter integration with filter blocks
- Color coding by field value

### Gantt Block
- Timeline visualization with task bars
- Task dependency visualization
- Drag to resize (change duration)
- Drag to move (reschedule)
- Progress bar within task bars
- Zoom levels (day, week, month)
- Integration with filter and form blocks

### Kanban Block
- Column-based card layout grouped by select field values
- Drag-and-drop cards between columns (updates the group field)
- Card ordering within columns
- Card click opens detail popup
- Configurable card content (which fields to display)
- Integration with filter blocks

## Requirements

### Functional
- Calendar: month/week/day views, event CRUD, drag reschedule
- Gantt: timeline bars, drag resize/move, progress tracking
- Kanban: column grouping, card drag-and-drop, configurable cards
- All: filter integration, popup details, real-time updates

### Non-functional
- Smooth drag-and-drop interactions
- Responsive to window size changes
- Efficient rendering for large datasets

## Comparison Notes

### vs OpenRegister
- OpenRegister has no built-in calendar/gantt/kanban views
- Nextcloud has a Calendar app but it's CalDAV-based, not data-driven
- These views would be valuable additions to OpenRegister for project/task management use cases
- The Kanban pattern maps well to pipeline views in Pipelinq
- Gantt could enhance Procest (process management) with timeline visualization
