---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# Webhooks

## Overview

NocoDB provides a table-level webhook system for automating responses to data changes. Webhooks send HTTP notifications when records are inserted, updated, or deleted. The system includes filtering conditions, retry logic, and execution logs.

## Configuration

### Trigger Events
- **After Insert** — New record created
- **After Update** — Record modified
- **After Delete** — Record removed
- **After Bulk Insert** — Multiple records created
- **After Bulk Update** — Multiple records modified
- **After Bulk Delete** — Multiple records removed

### Webhook Properties (Hook Model)
- `title` — Webhook name
- `event` — Trigger event type
- `operation` — Operation type (insert/update/delete)
- `url` — Target URL for HTTP POST
- `headers` — Custom HTTP headers (JSON)
- `payload` — Custom payload template
- `condition` — Optional filter condition
- `retries` — Number of retry attempts
- `retry_interval` — Interval between retries
- `timeout` — Request timeout
- `active` — Enable/disable toggle
- `version` — Webhook version (v1, v2, v3)

### Notification Types
- **URL** — HTTP POST to a URL
- **Script** — Execute a script
- **Slack/Teams/Discord** — Via App Store plugins (being deprecated)

### V3 Improvements
- `trigger_field` — Trigger only when specific fields change
- `trigger_fields` — Array of field IDs to watch
- Field-level granularity for update triggers

## Filtering

Webhooks support the same filter system as views:
- `HookFilter` model stores conditions per webhook
- Conditions evaluated before sending notification
- Only matching records trigger the webhook

## Logging

- `HookLog` model stores execution history
- Tracks: request payload, response status, error messages
- Accessible via API and UI

## Architecture

### Execution Flow
1. Data operation completes (insert/update/delete)
2. `AppHooksService` emits event
3. `HookHandlerService` evaluates hook conditions
4. Matching hooks queued as jobs
5. `invokeWebhook` helper sends HTTP request
6. Response logged to HookLog

### Key Files
- `models/Hook.ts` — Hook entity (fields, CRUD, conditions)
- `models/HookFilter.ts` — Filter conditions for hooks
- `models/HookLog.ts` — Execution logs
- `services/hooks.service.ts` — Hook business logic
- `services/hook-handler.service.ts` — Event handling and execution
- `helpers/webhookHelpers.ts` — HTTP invocation

## Relevance to OpenRegister

OpenRegister delegates automation to n8n rather than built-in webhooks:
1. **Built-in webhooks** are simpler for basic use cases
2. **n8n integration** is more powerful for complex workflows
3. **Field-level triggers** (V3) are a useful refinement
4. **Webhook logs** are essential for debugging
5. NocoDB's approach is self-contained; OpenRegister's is more composable
