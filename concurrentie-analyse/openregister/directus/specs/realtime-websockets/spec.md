---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Real-time WebSockets

## Overview

Directus provides WebSocket connectivity for real-time data subscriptions, GraphQL subscriptions, and server log streaming. The WebSocket system supports authentication, permission-filtered subscriptions, and broadcasting.

## WebSocket Controllers

### REST WebSocket (`/websocket`)
- Custom JSON message-based protocol
- Supports CRUD operations over WebSocket
- Subscribe/unsubscribe to collection changes
- Broadcasts filtered by user/role
- UID-based subscription management for multiple concurrent subscriptions

### GraphQL WebSocket (`/graphql`)
- Standard `graphql-ws` protocol
- Schema-based subscriptions for mutation events
- Type-safe subscription payloads

### Logs WebSocket
- Server log streaming for administrators
- Useful for debugging and monitoring

## Authentication

WebSocket connections authenticate via:
1. **Connection handshake** - Token passed as query parameter or in the initial message
2. **In-flight re-auth** - Clients can send auth messages to refresh credentials
3. **Session-based** - Linked to existing HTTP session

## REST WebSocket Protocol

### Subscribe to Collection Changes
```json
{
  "type": "subscribe",
  "collection": "articles",
  "event": "create",
  "query": {
    "fields": ["id", "title", "status"],
    "filter": { "status": { "_eq": "published" } }
  },
  "uid": "published-articles"
}
```

### Change Notification
```json
{
  "type": "subscription",
  "event": "create",
  "data": [{ "id": "abc", "title": "New Article", "status": "published" }],
  "uid": "published-articles"
}
```

### CRUD over WebSocket
```json
{ "type": "items", "action": "create", "collection": "articles", "data": { "title": "..." } }
{ "type": "items", "action": "read", "collection": "articles", "query": { "limit": 10 } }
{ "type": "items", "action": "update", "collection": "articles", "id": "abc", "data": { ... } }
{ "type": "items", "action": "delete", "collection": "articles", "id": "abc" }
```

## GraphQL Subscriptions

```graphql
subscription {
  articles_mutated {
    key
    event
    data {
      id
      title
      status
    }
  }
}
```

## WebSocket Service (for Extensions)

Extensions can use the `WebSocketService` to:
- Listen for connect/message/error/close events
- Broadcast messages to all connected clients
- Filter broadcasts by user or role
- Access the set of connected clients

## Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `WEBSOCKETS_ENABLED` | `false` | Enable WebSocket server |
| `WEBSOCKETS_REST_ENABLED` | `true` | Enable REST WS controller |
| `WEBSOCKETS_REST_PATH` | `/websocket` | REST WS endpoint path |
| `WEBSOCKETS_GRAPHQL_ENABLED` | `true` | Enable GraphQL WS controller |
| `WEBSOCKETS_GRAPHQL_PATH` | `/graphql` | GraphQL WS endpoint path |
| `WEBSOCKETS_HEARTBEAT_ENABLED` | `true` | Enable heartbeat pings |
| `WEBSOCKETS_HEARTBEAT_PERIOD` | `30s` | Heartbeat interval |

## Relevance to OpenRegister

OpenRegister does not currently support real-time subscriptions. Adding WebSocket support would enable:
- Live data updates in the admin UI
- Real-time collaborative editing
- External application push notifications
- Event-driven integrations without polling

Options for OpenRegister:
1. Leverage Nextcloud's existing notification and push infrastructure
2. Server-Sent Events (SSE) for simpler one-way real-time updates
3. Full WebSocket support as a Nextcloud app/ExApp
4. Integration with n8n webhooks for event-driven updates
