---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Activity Log & Revisions

## Overview

Directus maintains a comprehensive audit trail through two system collections: `directus_activity` (event log) and `directus_revisions` (data snapshots). Together they provide a complete history of who did what, when, and what changed.

## Activity Service

The `ActivityService` tracks all significant events:
- Item CRUD operations
- Authentication events (login/logout)
- Comments on items
- Version operations

Each activity record includes:
- **Action**: `create`, `update`, `delete`, `login`, `comment`
- **User**: Who performed the action
- **Timestamp**: When it occurred
- **IP**: Client IP address
- **User Agent**: Client information
- **Collection**: Which collection was affected
- **Item**: Primary key of the affected item

## Revisions Service

The `RevisionsService` stores complete data snapshots:
- **Data**: Full item state after the change (JSON)
- **Delta**: What changed compared to the previous state (JSON)
- **Parent**: Link to the previous revision (enabling version chain)
- **Collection/Item**: What was changed
- **Activity**: Link to the activity record

### Revert Capability
The `RevisionsService.revert(pk)` method allows reverting an item to a previous state:
1. Reads the revision's stored `data` field
2. Updates the current item with the revision's data via `ItemsService.updateOne`
3. This creates a new revision (documenting the revert itself)

## Accountability Modes

Collections can be configured with different accountability levels:
- **`all`**: Track activity and store revisions (full audit trail)
- **`activity`**: Track activity only (no data snapshots)
- **`null`**: No tracking

This allows balancing between audit completeness and storage/performance.

## Comments

Directus supports comments on items:
- Comments are stored as activity records with action `comment`
- Users can reply to comments (threaded)
- Comments support markdown formatting
- Notification sent to mentioned users

## API Access

```
GET /activity                    # List all activity
GET /activity/:id                # Read activity record
GET /revisions                   # List all revisions
GET /revisions/:id               # Read revision with data
POST /comments                   # Create comment
```

Activity supports all standard query parameters (filter, sort, limit, etc.).

## Performance Considerations

- Revisions can grow large for frequently updated items
- The `delta` field helps identify what changed without comparing full snapshots
- Index on `(collection, item)` for efficient per-item history queries
- Recent migration (20260113A) adds additional indexes for revision queries

## Relevance to OpenRegister

OpenRegister has basic audit logging but could enhance it with:
- **Full data snapshots**: Store complete object state at each change (currently limited)
- **Revert capability**: Allow reverting objects to previous states
- **Delta computation**: Show exactly what changed between versions
- **Configurable accountability**: Per-register audit level settings
- **Comments on objects**: Allow discussions on specific data items

The revision storage pattern (full state + computed delta) is a well-proven approach that OpenRegister could adopt.
