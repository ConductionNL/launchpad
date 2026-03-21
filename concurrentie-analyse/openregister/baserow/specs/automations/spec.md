---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Automations

## Summary

Baserow has a built-in automation system with trigger nodes, action nodes, and workflow management. Automations can be triggered by events, schedules, or HTTP requests, and can perform database operations, send emails, make HTTP requests, invoke AI agents, and more.

## Architecture

Located at `backend/src/baserow/contrib/automation/`

```
automation/
  workflows/          # Workflow management (CRUD, ordering, publishing)
  nodes/              # Node types (triggers + actions)
  history/            # Execution history
  data_providers/     # Data context for automation steps
  automation_dispatch_context.py  # Runtime execution context
```

## Workflow Model

- An automation is an Application containing workflows
- Each workflow has a trigger node and one or more action nodes
- Workflows can be published/unpublished
- Execution history tracks runs

## Trigger Node Types

### Row-based Triggers (via webhook events)
- Row created in table
- Row updated in table
- Row deleted in table
- Row enters view (matches view filters)
- Row leaves view (no longer matches view filters)

### Scheduled Triggers
- **CorePeriodicTriggerNodeType** - Cron-based periodic execution

### HTTP Triggers
- **CoreHTTPTriggerNodeType** - Webhook endpoint for external triggers

## Action Node Types

### Database Operations
- **LocalBaserowCreateRowNodeType** - Create a row in a table
- **LocalBaserowUpdateRowNodeType** - Update an existing row
- **LocalBaserowDeleteRowNodeType** - Delete a row
- **LocalBaserowGetRowNodeType** - Retrieve a single row
- **LocalBaserowListRowsNodeType** - List/query rows
- **LocalBaserowAggregateRowsNodeType** - Aggregate row data

### Communication
- **CoreSMTPEmailNodeType** - Send email via SMTP
- **SlackWriteMessageActionNodeType** - Post to Slack channel

### Logic
- **CoreIteratorNodeType** - Loop over items (container node)
- **CoreRouterActionNodeType** - Conditional branching

### External
- **CoreHttpRequestNodeType** - Make arbitrary HTTP requests
- **AIAgentActionNodeType** - Invoke AI agent for processing

## Execution

- Automations run as Celery tasks
- Each execution creates a history entry
- Node outputs feed into subsequent node inputs
- Data providers give access to trigger data and previous node results

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| Automation | Built-in workflow engine | n8n ExApp (external) |
| Triggers | Row events, schedule, HTTP | n8n triggers |
| Actions | 12 node types | n8n node ecosystem (500+) |
| AI integration | Built-in AI agent node | N/A |
| Visual editor | Node-based workflow builder | n8n visual editor |
| Execution | Celery task queue | n8n execution engine |

OpenRegister delegates automation to n8n, which has a vastly larger node ecosystem. Baserow's built-in automations are simpler but more tightly integrated.
