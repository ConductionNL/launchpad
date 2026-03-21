---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Real-Time Collaboration

## Summary

Baserow provides real-time collaboration through Django Channels (WebSocket). Multiple users can work on the same table simultaneously, seeing each other's changes in real-time. The system uses a page-based subscription model where clients subscribe to specific resources.

## Architecture

Located at `backend/src/baserow/ws/`

```
ws/
  consumers.py     # WebSocket consumer (AsyncJsonWebsocketConsumer)
  registries.py    # Page type registry
  routers.py       # URL routing for WebSocket connections
  routing.py       # ASGI routing configuration
  signals.py       # Signal handlers for broadcasting changes
  tasks.py         # Celery tasks for async broadcasting
  auth.py          # WebSocket authentication
```

## Page Subscription Model

### PageContext
A user subscribes to "pages" which represent specific resources:
- Table page (table_id) - get row changes for a specific table
- View page (view_id) - get view-specific updates
- Workspace page - workspace-level changes
- Builder page - application builder updates

### SubscribedPages
Manages list of pages a user is subscribed to:
- Subscribe to a page to start receiving updates
- Unsubscribe when navigating away
- Multiple simultaneous subscriptions supported

## Real-Time Events

### Row Events
- Row created (new row appears in other users' views)
- Row updated (field values change in real-time)
- Row deleted (row disappears from view)
- Row order changed

### Table Events
- Field added/updated/deleted
- View created/updated/deleted
- Filter/sort changes

### Workspace Events
- Application created/updated/deleted
- User joined/left workspace

## WebSocket Consumer

The `CoreConsumer` (AsyncJsonWebsocketConsumer):
1. Authenticates user on connect
2. Joins channel groups for subscribed pages
3. Receives JSON messages for subscribe/unsubscribe
4. Broadcasts changes to all subscribed clients
5. Assigns unique `web_socket_id` per connection (used to prevent echo)

## Broadcast Mechanism

1. User performs action (e.g., update row) via API
2. Django signal fires
3. Signal handler creates broadcast task
4. Celery task or synchronous handler sends to channel group
5. All subscribed WebSocket connections receive the update
6. Frontend applies the change to the UI

## Row Comments (Premium)

Located at `premium/backend/src/baserow_premium/row_comments/`:
- Per-row comment threads
- Notification on new comments
- User attribution
- Real-time comment updates

## Row History

- Field-level change tracking with before/after values
- User attribution for each change
- Undo/redo support via action history
- History viewable per row

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| Real-time | Django Channels WebSocket | None (polling) |
| Collaboration | True real-time multi-user | Single-user CRUD |
| Row comments | Premium feature, per-row threads | N/A |
| Change history | Field-level before/after tracking | Basic audit log |
| Undo/redo | Full undo/redo with action tracking | N/A |
| Presence | Users see who's viewing same table | N/A |

Real-time collaboration is a major competitive advantage for Baserow, especially for team use cases. OpenRegister would need significant development to match this capability.
