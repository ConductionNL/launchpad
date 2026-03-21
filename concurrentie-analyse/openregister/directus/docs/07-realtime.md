# Directus Real-time (WebSockets & Subscriptions)

**Source:** https://docs.directus.io/guides/realtime/subscriptions.html

## Overview

WebSocket subscriptions allow real-time notification of item creations, edits, and deletions in collections.

## WebSocket Subscriptions

### Subscribe to Collection Changes
```json
{
    "type": "subscribe",
    "collection": "messages"
}
```

Response:
```json
{
    "type": "subscription",
    "event": "init"
}
```

### Change Events
Events: `create`, `update`, `delete`

```json
{
    "type": "subscription",
    "event": "create",
    "data": [/* item objects */]
}
```

For `delete` events, only the `id` is returned.

### Filter by Event Type
```json
{
    "type": "subscribe",
    "collection": "messages",
    "event": "create"
}
```

### Select Specific Fields
```json
{
    "type": "subscribe",
    "collection": "messages",
    "query": { "fields": ["text"] }
}
```

### UIDs for Multiple Subscriptions
```json
{
    "type": "subscribe",
    "collection": "messages",
    "uid": "any-string-value"
}
```

UIDs must be unique per WebSocket connection. Auto-generated if not provided.

### Unsubscribe
```json
{ "type": "unsubscribe", "uid": "identifier" }
```
Omit `uid` to stop all subscriptions.

## GraphQL Subscriptions

### Connection Setup
```js
import { createClient } from "graphql-ws";

const client = createClient({
    url: "ws://your-directus-url/graphql",
    keepAlive: 30000,
    connectionParams: async () => {
        return { access_token: "MY_TOKEN" };
    },
});
```

### Subscribe
```graphql
subscription {
    posts_mutated {
        key
        event
        data {
            id
            text
        }
    }
}
```

### Filter by Event
```graphql
subscription {
    posts_mutated(event: create) {
        key
        data { text }
    }
}
```

## Heartbeat / Keep-Alive

Periodic `ping` messages sent to:
1. Prevent connection closing due to inactivity
2. Verify connection is active

Reply with `pong` to maintain connection.

Configurable via:
- `WEBSOCKETS_HEARTBEAT_ENABLED`
- `WEBSOCKETS_HEARTBEAT_PERIOD`

## Comparison Notes (vs OpenRegister)

| Aspect | Directus | OpenRegister |
|--------|----------|-------------|
| WebSockets | Native support | Not available |
| Subscriptions | Collection-level with field selection | Not available |
| GraphQL Subscriptions | Native support | Not available |
| Event Types | create, update, delete | Not applicable |
| Heartbeat | Configurable ping/pong | Not applicable |
| Real-time Collaboration | Collaborative editing (separate feature) | Not available |
