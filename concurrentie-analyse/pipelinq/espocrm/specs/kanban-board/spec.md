---
competitor: espocrm
analyzed_date: 2026-03-14
feature: kanban-board
---

# Kanban Board

## Overview

EspoCRM provides a **generic Kanban board view** that works with any entity that has an enum-type status/stage field. This is not limited to Opportunities - it can be used for Tasks, Leads, Cases, or any custom entity. The Kanban system includes per-user card ordering and drag-and-drop stage transitions.

## Architecture

### Backend Components

#### KanbanService (`Tools/Kanban/KanbanService.php`)
Entry point that:
- Validates entity type access (must be an "object" scope, not kanbanDisabled)
- Checks ACL read permission
- Delegates to the Kanban class for data retrieval
- Handles card ordering via the Orderer class

#### Kanban Class
Builds the Kanban result:
- Groups records by the status/stage enum field
- Returns per-column record lists with total counts
- Respects search filters (same as list view)
- Supports `countDisabled` for performance on large datasets

#### Orderer Class
Manages card ordering within columns:
- Persists order via `KanbanOrder` entity
- Per-user ordering (each user sees their own card arrangement)
- Configurable max order number (`kanbanMaxOrderNumber` config)

#### KanbanOrder Entity (`Entities/KanbanOrder.php`)
Stores per-user, per-entity-type, per-group ordering:
- entityType (varchar)
- entityId (varchar)
- group (varchar) - the status/stage value
- userId (varchar)
- order (int)

### Configuration

Entity-level configuration in metadata:
```json
// scopes/{EntityType}.json
{
    "kanbanOrderDisabled": false  // disable manual ordering
}

// recordDefs/{EntityType}.json
{
    "kanbanDisabled": false       // disable kanban entirely
}
```

System-level configuration:
- `kanbanMaxOrderNumber` - Maximum number of manually ordered cards

## API Endpoints

```
GET  /:controller/action/listKanban   - Get Kanban data (grouped by columns)
PUT  /:controller/action/orderKanban  - Update card order within a column
```

The Kanban data endpoint accepts the same search/filter parameters as the regular list endpoint, enabling filtered Kanban views.

## Frontend Implementation

The Kanban view is implemented in the client JavaScript layer:
- Column headers show the status label and record count
- Cards are draggable between columns (triggers status update)
- Cards are sortable within columns (triggers order update)
- Supports all list view features (search, filters, sorting)
- Portal users cannot reorder cards

## Which Entities Support Kanban

Any entity with:
1. An enum-type field used as a status/stage indicator
2. The entity scope is an "object" type
3. `kanbanDisabled` is not set to true in recordDefs

Default kanban-capable entities include:
- **Opportunity** (by stage: Prospecting -> Closed Won/Lost)
- **Task** (by status: Not Started -> Completed)
- **Lead** (by status: New -> Converted/Dead)
- **Case** (by status)
- Any custom entity with an enum status field

## Relevance to Pipelinq

### Strengths
- Generic implementation works for any entity
- Per-user card ordering
- Full search/filter compatibility
- Clean separation of kanban logic from entity logic

### Opportunities for Pipelinq
- **Multi-pipeline Kanban**: EspoCRM has one Kanban per entity type. Pipelinq could support multiple Kanban boards with different column configurations for the same data
- **Swimlanes**: EspoCRM Kanban is single-dimension (columns only). Pipelinq could add row grouping (e.g., by team, priority, or assigned user)
- **WIP limits**: No work-in-progress limits in EspoCRM. Pipelinq could enforce column capacity limits
- **Kanban automation**: No triggers on column change in EspoCRM open-source. Pipelinq + n8n can automate actions on stage transitions
- **Board templates**: Pre-configured board layouts for common workflows
