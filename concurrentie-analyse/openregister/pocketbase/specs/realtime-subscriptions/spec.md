---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# Realtime Subscriptions

## Summary
PocketBase provides realtime data synchronization via Server-Sent Events (SSE). Clients can subscribe to changes on entire collections or individual records, receiving push notifications for create, update, and delete operations.

## Key Features
- SSE-based (not WebSocket) for simplicity and proxy compatibility
- Subscribe to entire collection (`*`) or specific record by ID
- Events include action type (`create`/`update`/`delete`) and full record data
- Collection ListRule applies to collection subscriptions, ViewRule to record subscriptions
- 5-minute idle timeout with automatic reconnection
- Client chunking (150 clients per broadcast batch) for performance
- Auth-aware: subscriptions respect the authenticated user's permissions

## Architecture
- `apis/realtime.go` - SSE connection handler and subscription management
- `tools/subscriptions/` - Broker pattern for client registration and message dispatch
- Uses Go's `errgroup` for concurrent client notification

## Protocol
```
GET /api/realtime -> SSE connection established, receive clientId
POST /api/realtime -> Set subscriptions: { "clientId": "...", "subscriptions": ["products", "products/RECORD_ID"] }
```

## Relevance to OpenRegister
OpenRegister currently lacks realtime capabilities. PocketBase's SSE approach is simpler than WebSockets and could be adapted for OpenRegister to notify frontends of data changes, useful for collaborative editing scenarios.
