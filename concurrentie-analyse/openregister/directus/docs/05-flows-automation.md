# Directus Flows (Automation Engine)

**Source:** https://docs.directus.io/guides/automate/flows.html, https://docs.directus.io/guides/automate/triggers.html, https://docs.directus.io/guides/automate/operations.html

## Overview

Flows enable custom, event-driven data processing and task automation. Each flow consists of:
- **One trigger** — The event that starts the flow
- **A series of operations** — Individual actions executed in sequence
- **A data chain** — Data passed between each step

## Flow Configuration
- Name, Status (active/inactive), Icon, Description, Color
- Activity and Logs Tracking (activity log, flow logs, or neither)

## Triggers

### 1. Event Hook
Triggered by internal platform events.
- **Filter (Blocking)** — Pauses transaction, allows payload validation/transformation or cancellation
- **Action (Non-Blocking)** — Async, doesn't block the event
- Configurable scope (item events) and collections
- Response body options: Data of Last Operation, All Data, or specific operation key

### 2. Webhook
Triggered by incoming HTTP request to `/flows/trigger/:trigger-id`
- GET or POST methods
- Synchronous or asynchronous execution
- Configurable response body and caching

### 3. Schedule (CRON)
6-point cron job syntax for scheduled execution.
```
┌── second (0-59)
│ ┌── minute (0-59)
│ │ ┌── hour (0-23)
│ │ │ ┌── day of month (1-31)
│ │ │ │ ┌── month (1-12)
│ │ │ │ │ ┌── day of week (0-7)
* * * * * *
```

### 4. Another Flow
Triggered by a Trigger Flow operation — enables flow chaining. Supports for-loops (runs once per array item).

### 5. Manual
Button click in Data Studio. Configurable per collection, location (item/collection page), with optional confirmation dialog and custom input fields.

## Operations

### Condition
Validates data using filter rules. Routes to success/failure path.

### Run Script
Custom JavaScript/TypeScript in isolated sandbox. No filesystem or network access. Return value appended to data chain.

### Create Data
Create items in a collection with configurable permissions and event emission.

### Read Data
Read items by ID or query with filter rules.

### Update Data
Update items by ID or query with configurable payload.

### Delete Data
Delete items by ID or query.

### JSON Web Token (JWT)
Sign, verify, or decode JWTs using jsonwebtoken package.

### Log to Console
Output to server console and Data Studio logs for debugging.

### Send Email
Send emails to one or more addresses with WYSIWYG, Markdown, or template body.

### Send Notification
Push notifications to Directus users.

### Webhook / Request URL
Make HTTP requests (GET, POST, PATCH, DELETE) to external URLs.

### Sleep
Delay flow execution by specified milliseconds.

### Throw Error
Halt flow with custom error code, HTTP status, and message. Cancels blocking event transactions.

### Transform Payload
Define custom JSON payload for subsequent operations. Consolidate multiple data sources.

### Trigger Flow
Start another flow with optional payload. Supports Serial, Parallel, and Batch iteration modes.

## Comparison Notes (vs OpenRegister)

| Aspect | Directus | OpenRegister |
|--------|----------|-------------|
| Automation | Built-in Flows engine | n8n integration (external) |
| Trigger Types | 5 types (event, webhook, cron, flow, manual) | n8n webhooks + event hooks |
| Visual Builder | No-code flow builder in Data Studio | n8n visual workflow builder |
| Script Execution | Sandboxed JS/TS in operations | n8n Code nodes |
| Event Hooks | Filter (blocking) + Action (non-blocking) | PHP event listeners |
| CRON | Built-in 6-point cron | n8n cron triggers |
| Manual Triggers | Button in Data Studio with confirmation | Not available |
| Flow Chaining | Built-in Trigger Flow operation | n8n sub-workflows |
| Data Operations | Built-in CRUD operations | Via n8n or API |
