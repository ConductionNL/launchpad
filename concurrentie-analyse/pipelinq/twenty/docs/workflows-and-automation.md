# Twenty CRM - Workflows & Automation

**Analyzed:** 2026-03-14

## Overview

Twenty workflows automate business processes and integrate with external tools. They follow a trigger-action model with branching, iteration, and code execution capabilities.

## Trigger Types (7)

| Trigger | Description | Configuration |
|---------|-------------|---------------|
| **Record Created** | New record added | Select object type. Not recommended for manual creation (auto-save fires too early) |
| **Record Updated** | Record data changes | Select object type + optional specific fields to monitor |
| **Record Updated or Created** | Combined trigger | Same as update with optional field monitoring |
| **Record Deleted** | Record removed | Select object type |
| **Manual** | User-initiated | Three modes: Global (anywhere), Single (selected record), Bulk (multiple records) |
| **Scheduled** | Time-based | Interval (minutes/hours/days) or cron expression. Runs in UTC |
| **Webhook** | External HTTP call | GET or POST with defined body structure |

**Supported objects:** People, Companies, Opportunities, Workspace Members, Calendar Events, Messages, Tasks, Notes, and custom objects.

## Action Types (12)

### Record Actions
| Action | Description | Limits |
|--------|-------------|--------|
| **Create Record** | Add new records | Manual input or data from prior steps |
| **Update Record** | Modify existing records | Cannot search by criteria at this stage |
| **Delete Record** | Remove records | Deleted data accessible in subsequent steps |
| **Search Records** | Find records by criteria | Maximum 200 records returned |
| **Upsert Record** | Create or update by identifier | Matches on email, domain, ID, or unique fields |

### Flow Control
| Action | Description |
|--------|-------------|
| **Iterator** | Loop through arrays; multiple actions per iteration |
| **Filter** | Conditional gate; passes or blocks execution |
| **Delay** | Pause for duration or until date; 1 credit consumed regardless of duration |

### Communication
| Action | Description | Limitations |
|--------|-------------|-------------|
| **Send Email** | Templated emails from synced mailbox | Single recipient only, no HTML signatures |
| **Form** | Collect user input in UI | Manual triggers only |

### Integration
| Action | Description |
|--------|-------------|
| **Code** | Custom JavaScript execution with access to prior step data |
| **HTTP Request** | External API calls (GET/POST/PUT/PATCH/DELETE) |

### AI (Coming Soon)
| Action | Description |
|--------|-------------|
| **AI Agent** | Prompt-based analysis, classification, text generation |

## Workflow Features

- **Step chaining:** Use fields from records returned by any previous step
- **Versioning:** Draft and published versions
- **Run monitoring:** Track workflow executions
- **Credits system:** Workflow actions consume credits (details in billing)

## Common Automation Patterns

- Closed-won deal automations (post-win activities)
- Stale opportunity detection and notification
- Email count tracking per contact
- Formula fields via workflow (until native support)
- Lead categorization and enrichment
- PDF/quote/invoice generation
- External data sync (Typeform, data warehouses)
- Task due date email alerts
