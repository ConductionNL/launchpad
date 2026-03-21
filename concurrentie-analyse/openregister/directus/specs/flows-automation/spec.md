---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Flows & Automation

## Overview

Directus Flows is a built-in visual automation engine that enables users to create workflows triggered by events, schedules, webhooks, or manual actions. Flows consist of a trigger and a chain of operations connected in a resolve/reject tree pattern.

## Flow Structure

```typescript
interface Flow {
  id: string;
  name: string;
  status: 'active' | 'inactive';
  trigger: 'event' | 'schedule' | 'operation' | 'webhook' | 'manual';
  options: Record<string, any>;  // Trigger-specific configuration
  operation: Operation;          // First operation in the chain
  accountability: 'all' | 'activity' | null;  // Logging level
}

interface Operation {
  id: string;
  key: string;           // Unique key within the flow
  type: string;          // Operation type (e.g., 'item-create')
  options: Record<string, any>;
  resolve: Operation;    // Next operation on success
  reject: Operation;     // Next operation on failure
  position_x: number;    // Visual position in flow editor
  position_y: number;
}
```

## Trigger Types

### Event Trigger
Fires on item CRUD actions or filter events:
- `items.create` / `items.update` / `items.delete`
- Can be scoped to specific collections
- Supports both action (after) and filter (before, can modify) modes
- The filter mode allows blocking or modifying the operation

### Schedule Trigger
Fires on a cron schedule:
- Uses standard cron syntax (e.g., `0 * * * *` for hourly)
- Cron validation before saving
- Synchronized across instances (only one instance runs the job in clustered setups)

### Webhook Trigger
Exposes an HTTP endpoint:
- `GET /flows/trigger/:flow-id`
- `POST /flows/trigger/:flow-id`
- Receives request path, query, body, method, and headers as trigger data
- Can return custom response data and control caching
- Supports both GET and POST methods
- Can optionally require authentication

### Manual Trigger
User-initiated from the admin UI:
- Appears as a button in the item detail view or collection overview
- Can be scoped to specific collections
- Receives selected item(s) as trigger data

### Operation Trigger
Called programmatically by other flows:
- Enables flow composition and reuse
- The "trigger" operation type calls another flow by ID

## Built-in Operations (15)

| Operation | Description |
|-----------|------------|
| `item-create` | Create items in any collection |
| `item-read` | Read items from any collection with filtering |
| `item-update` | Update items in any collection |
| `item-delete` | Delete items from any collection |
| `mail` | Send email via configured SMTP |
| `notification` | Create in-app notification |
| `request` | Make HTTP request to external URL |
| `transform` | Transform data using JavaScript expressions |
| `condition` | Branch flow based on conditions (if/else) |
| `exec` | Execute arbitrary JavaScript code in sandbox |
| `log` | Log message to server console |
| `sleep` | Delay execution for specified duration |
| `json-web-token` | Sign or verify JWT tokens |
| `throw-error` | Throw an error to stop the flow |
| `trigger` | Trigger another flow (composition) |

## Data Flow Between Operations

Operations communicate via a data context:
- `$trigger` - Data from the trigger event
- `$last` - Output of the previous operation
- `$accountability` - Current user context
- `$env` - Allowed environment variables (configurable via `FLOWS_ENV_ALLOW_LIST`)
- `{{operation_key}}` - Output of a specific operation by key

Data references use Mustache-style templating: `{{$trigger.body.title}}`, `{{$last.id}}`.

## Flow Manager

The `FlowManager` class orchestrates flow execution:
1. Loads all active flows from the database on startup
2. Constructs a tree of operations for each flow
3. Registers event listeners, cron jobs, and webhook handlers
4. On trigger, resolves the operation tree sequentially
5. Each operation returns success (follow resolve path) or failure (follow reject path)
6. Optionally logs each step to `directus_activity` and `directus_revisions`

Flow reloads are broadcast across cluster nodes via the message bus.

## Visual Flow Editor

The admin UI provides a visual flow builder:
- Drag-and-drop operation placement
- Connect operations via resolve/reject paths
- Configure operation options inline
- Test flows with sample data
- View execution logs

## Relevance to OpenRegister

OpenRegister uses n8n for automation, which provides:
- More operation types (900+ integrations)
- A more mature visual editor
- Better error handling and retry mechanisms
- Community-driven node ecosystem

However, Directus Flows has advantages:
- Built-in, no separate service to deploy
- Direct access to internal services (no API calls needed)
- Tighter integration with the permission system
- Lower latency for simple workflows

OpenRegister's n8n approach is more powerful for complex integrations, while Directus Flows is better for simple, self-contained automations.
