---
competitor: krayin
analyzed_date: 2026-03-14
feature: automation-workflows
priority: high
---

# Workflow Automation & Webhooks

## Overview

Krayin provides event-driven workflow automation and outbound webhooks. Workflows trigger on entity events (create/update/delete) for leads, activities, persons, and quotes. Each workflow has conditions (AND/OR) and a list of actions to execute.

## Data Model

### Workflow (`workflows` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| name | string | Workflow name |
| description | text | Description |
| entity_type | string | Target entity (leads, activities, persons, quotes) |
| event | string | Trigger event (e.g., lead.create.after) |
| condition_type | string | all (AND) or any (OR) |
| conditions | JSON | Array of condition rules |
| actions | JSON | Array of actions to execute |

### Webhook (`webhooks` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| name | string | Webhook name |
| entity_type | string | Entity type |
| description | text | Description |
| method | string | HTTP method (GET, POST, PUT, DELETE) |
| end_point | string | Target URL |
| query_params | JSON | URL query parameters |
| headers | JSON | HTTP headers |
| payload_type | string | Payload format type |
| raw_payload_type | string | Raw content type |
| payload | JSON | Request body template |

## Trigger Events

```
Leads: lead.create.after, lead.update.after, lead.delete.before
Activities: activity.create.after, activity.update.after, activity.delete.before
Persons: contacts.person.create.after, contacts.person.update.after, contacts.person.delete.before
Quotes: quote.create.after, quote.update.after, quote.delete.before
```

## Available Actions (Lead Entity)

| Action ID | Description |
|-----------|-------------|
| update_lead | Update any lead attribute |
| update_person | Update the lead's contact person |
| send_email_to_person | Send email template to contact |
| send_email_to_sales_owner | Send email template to sales owner |
| add_tag | Auto-tag (creates tag if not exists) |
| add_note_as_activity | Add a note activity to the lead |
| trigger_webhook | Fire an outbound webhook |

## Webhook Service

The WebhookService supports:
- All HTTP methods (GET, POST, PUT, DELETE, PATCH)
- Content types: JSON, form-urlencoded, multipart, XML, plain text
- Auto-detection of payload format when content-type not specified
- Placeholder replacement: `{%leads.title%}` replaced with actual values
- Query parameter appending
- Header management with enable/disable per header
- 30s timeout, 10s connect timeout

## Architecture

```
Event Dispatched (e.g., lead.create.after)
    -> WorkflowServiceProvider listener
    -> Entity helper resolves entity
    -> Evaluate conditions (AND/OR matching)
    -> executeActions() iterates action array
        -> update_lead: LeadRepository::update()
        -> send_email: Mail::queue(Common mailable)
        -> add_tag: TagRepository::create() + attach
        -> add_note: ActivityRepository::create() + attach
        -> trigger_webhook: WebhookService::triggerWebhook()
```

## Pipelinq Comparison Notes

- Workflow system is functional but basic compared to n8n-style visual builders
- Placeholder system for webhook payloads is useful
- No delay/wait actions -- everything executes synchronously
- No conditional branching within a workflow
- No workflow execution history or logging
- Pipelinq has n8n integration which is far more powerful for automation
