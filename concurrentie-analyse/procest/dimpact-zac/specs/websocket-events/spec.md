---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# WebSocket Events -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC uses WebSockets for real-time UI updates.
- **Product**: Dimpact ZAC
- **Category**: Real-time Communication
- **Relevance to Procest**: Real-time updates improve user experience in collaborative case management

## Architecture Overview
ZAC uses WebSocket connections to push screen events from the backend to Angular frontend. Events trigger UI refreshes for specific views or entities.

Key components:
- `EventingService` -- sends events
- `ScreenEventType` -- defines event types
- Frontend `WebsocketService` -- subscribes to events

## Data Model

### ScreenEventType (Backend Event Types)
| Event | Description | Trigger |
|-------|-------------|---------|
| ZAAK | Case updated | Case data changed |
| ZAAK_TAKEN | Case's tasks updated | Task added/completed/assigned |
| ZAAK_ROLLEN | Case roles updated | Assignment changed |
| TAAK | Task updated | Task data changed |
| ENKELVOUDIG_INFORMATIEOBJECT | Document updated | Document locked/signed/etc |
| SIGNALERINGEN | Notifications updated | New notification |
| ZAKEN_VERDELEN | Bulk case assignment done | Async operation completed |
| ZAKEN_VRIJGEVEN | Bulk case release done | Async operation completed |
| TAKEN_VERDELEN | Bulk task assignment done | Async operation completed |
| TAKEN_VRIJGEVEN | Bulk task release done | Async operation completed |

### Event Operations
- `updated(entity)` -- entity was changed
- `skipped(entity)` -- entity was skipped in bulk operation

### Screen Event Lifecycle
```
Backend operation -> EventingService.send(event) -> WebSocket -> Angular subscription -> UI refresh
```

## Business Logic

### Event Publishing
Events are sent at key points:
1. After case/task assignment changes
2. After document state changes (lock/unlock/sign)
3. After notification creation/deletion
4. After bulk operation completion
5. After case status changes

### Bulk Operation Events
Async operations (case/task distribution) use a resource ID pattern:
1. Client starts bulk operation with a generated UUID
2. Client subscribes to WebSocket events with that UUID
3. Backend processes items asynchronously
4. On completion, sends `ZAKEN_VERDELEN.updated(uuid)` or `TAKEN_VERDELEN.updated(uuid)`
5. Client receives event, refreshes UI, removes progress indicator

### Frontend Subscription
Angular components subscribe to relevant event types and refresh data when events arrive. This enables:
- Live updates when colleagues change cases
- Progress tracking for bulk operations
- Notification badge updates

## Requirements (as observed)

1. WebSocket events provide real-time UI updates without polling
2. Events are entity-scoped (case, task, document, notification)
3. Bulk operations use UUID-based tracking for progress
4. Events are fire-and-forget (no guaranteed delivery)
5. Frontend must handle reconnection on WebSocket disconnection
6. Events carry entity identifiers for targeted refresh

## Comparison Notes
- ZAC's WebSocket system is well-designed for collaborative use
- The UUID-based bulk operation tracking is a clever pattern
- Procest currently relies on polling; WebSocket would improve UX
- Nextcloud has its own notification system that Procest could leverage
