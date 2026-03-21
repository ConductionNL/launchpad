---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Webhooks & Event System

## Overview

Strapi has a two-layer event system: an **Event Hub** for internal event-driven communication between modules, and a **Webhook Runner** that dispatches HTTP POST requests to external URLs when specific events occur. Webhooks are configured via the admin panel and stored in the database.

## Event Hub

The Event Hub (`packages/core/core/src/services/event-hub.ts`) is an in-process pub/sub system:

### API
```typescript
interface EventHub {
  emit(eventName: string, ...args: unknown[]): Promise<void>;
  subscribe(subscriber: Subscriber): () => void;  // Global subscriber
  on(eventName: string, listener: Listener): () => void;  // Per-event
  off(eventName: string, listener: Listener): void;
  once(eventName: string, listener: Listener): () => void;
  destroy(): EventHub;
}
```

### Key Characteristics
- **Async execution**: All listeners are awaited sequentially
- **Global subscribers**: Receive all events (used by webhook runner)
- **Per-event listeners**: Only receive specific events
- **Cleanup**: Both `unsubscribe()` and return-function patterns for cleanup
- **No event queuing**: Events fire synchronously through all listeners

### Common Events
```
entry.create          # Content entry created
entry.update          # Content entry updated
entry.delete          # Content entry deleted
entry.publish         # Content entry published
entry.unpublish       # Content entry unpublished
media.create          # File uploaded
media.update          # File metadata updated
media.delete          # File deleted
component.create      # Component schema created
component.update      # Component schema updated
component.delete      # Component schema deleted
content-type.create   # Content type schema created
content-type.update   # Content type schema updated
content-type.delete   # Content type schema deleted
```

## Webhook Runner

The Webhook Runner (`packages/core/core/src/services/webhook-runner.ts`) processes webhook dispatches:

### Architecture
- **Worker Queue**: Concurrent webhook processing (5 concurrent workers by default)
- **Event mapping**: Each webhook subscribes to specific event types
- **HTTP delivery**: POST request with JSON payload to configured URL
- **Custom headers**: Per-webhook configurable HTTP headers
- **Global headers**: Default headers applied to all webhooks

### Webhook Model
```typescript
interface Webhook {
  id: string;
  name: string;       // Display name
  url: string;        // Target URL (text field for long URLs)
  headers: object;    // Custom HTTP headers
  events: string[];   // Subscribed event types
  isEnabled: boolean; // Enable/disable toggle
}
```

### Webhook Store
Webhooks are persisted in the `strapi_webhooks` database table:
- CRUD operations for webhook management
- Event validation against allowed events list
- Plugins can register additional allowed events via `addAllowedEvent()`

### Webhook Payload
```json
{
  "event": "entry.create",
  "createdAt": "2024-01-01T00:00:00.000Z",
  "model": "article",
  "uid": "api::article.article",
  "entry": {
    "id": 1,
    "documentId": "abc123",
    "title": "Hello World",
    "...": "..."
  }
}
```

## Provider Architecture

The webhook system is initialized via a provider (`packages/core/core/src/providers/webhooks.ts`):
1. Creates the webhook store (database-backed)
2. Creates the webhook runner (event processor)
3. Wires the event hub to the webhook runner
4. Loads existing webhooks from database
5. Registers default allowed events

## Configuration

```typescript
// config/server.ts
export default {
  webhooks: {
    populate: true,  // Include relation data in webhook payloads
    defaultHeaders: {
      'X-Custom-Header': 'value',
    },
  },
};
```

## Relevance to OpenRegister

**Key differences:**
- Strapi manages webhooks in its own database; OpenRegister could use Nextcloud's event system
- Strapi's event hub is in-process; Nextcloud has `IEventDispatcher` for the same purpose
- Strapi uses HTTP POST for webhooks; OpenRegister can use n8n for complex integrations

**Features OpenRegister could adopt:**
- User-configurable webhooks via admin UI (currently OpenRegister uses hardcoded event listeners)
- Worker queue for concurrent webhook processing (prevents slow webhooks from blocking)
- Per-webhook event subscription (subscribe to specific events only)
- Event validation - plugins register allowed events, preventing invalid subscriptions
- The two-layer pattern (internal events + external webhooks) is a clean separation
