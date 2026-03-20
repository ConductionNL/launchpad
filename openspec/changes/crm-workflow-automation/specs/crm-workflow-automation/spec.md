# CRM Workflow Automation Specification (Cross-App)

## Purpose

Expose n8n workflow automation capabilities within the application UI across Conduction apps. Visual workflow builder for automation: trigger-action workflows, conditional branching, and scheduled actions. Bridges the gap between n8n's powerful backend automation and the user-facing interfaces of Pipelinq (CRM automation), Procest (case workflow automation), and other apps.

Built-in automation is a standard expectation in modern platforms. Our architecture uses n8n as the workflow engine (via MCP), which is more powerful than typical built-in automation. The gap is in surfacing these automations in each app's UI so users can create and manage automations without directly accessing n8n.

**Consuming apps**: Pipelinq (CRM automation: lead/request triggers), Procest (case automation: status/milestone triggers), OpenRegister (generic object triggers), OpenConnector (integration triggers)
**Tender frequency**: 38% workflow/procesautomatisering (26/69); combined with klantinteractie (65%) makes it a high-value differentiator
**Standards**: n8n Workflow API, Nextcloud Activity/Notification APIs, OpenRegister Event System

---

## Requirements

### Requirement 1: App-specific automation triggers

Each consuming app MUST expose its domain events as automation triggers selectable in the app's UI.

#### Scenario 1.1: CRM triggers for leads (Pipelinq)
- GIVEN the automation builder in Pipelinq
- THEN lead triggers MUST include: created, stage changed, assigned, value changed, stale (configurable days), won, lost

#### Scenario 1.2: CRM triggers for requests (Pipelinq)
- GIVEN the automation builder in Pipelinq
- THEN request triggers MUST include: created, status changed, assigned

#### Scenario 1.3: Case triggers (Procest)
- GIVEN the automation builder in Procest
- THEN case triggers MUST include: created, status changed, milestone reached, deadline approaching, assigned, escalated

#### Scenario 1.4: Scheduled triggers (all apps)
- GIVEN the automation builder in any app
- THEN scheduled triggers MUST include: daily schedule, weekly schedule, custom cron

#### Scenario 1.5: Trigger integration with existing event system
- GIVEN the existing ObjectEventListener in any app
- WHEN an entity is created or updated
- THEN the existing event detection pipeline MUST be extended to also fire n8n webhooks
- AND the webhook MUST fire after Activity and Notification dispatchers complete

---

### Requirement 2: Trigger conditions

Users MUST be able to add conditions to triggers so automations only fire for specific scenarios.

#### Scenario 2.1: Stage/status filter
- GIVEN a trigger "Lead stage changed"
- THEN conditions MUST support: "Only when stage changes to [stage]", "Only for pipeline [pipeline]", "Only when previous stage was [stage]"
- AND multiple conditions MUST combine with AND logic

#### Scenario 2.2: Value filter
- GIVEN a trigger on entity creation or value change
- THEN value conditions MUST support: "> EUR [amount]", "< EUR [amount]", "between EUR [min] and [max]"

#### Scenario 2.3: Assignee filter
- GIVEN any entity trigger
- THEN assignee conditions MUST support: "assigned to [user]", "assigned to group [group]", "unassigned"

#### Scenario 2.4: Case type filter (Procest)
- GIVEN a case trigger in Procest
- THEN conditions MUST support: "Only for zaaktype [type]", "Only for department [department]"

#### Scenario 2.5: Condition evaluation uses diff data
- GIVEN a stage change trigger with condition "Only when stage changes to Qualified"
- WHEN a lead's stage changes from "New" to "In Progress"
- THEN the automation MUST NOT fire
- WHEN the stage changes from "In Progress" to "Qualified"
- THEN the automation MUST fire

---

### Requirement 3: Automation actions

Each app MUST expose its domain actions that automations can execute.

#### Scenario 3.1: Entity management actions
- GIVEN the automation builder
- THEN entity actions MUST include: assign entity (specific user or round-robin), change status/stage, update field value

#### Scenario 3.2: Communication actions
- GIVEN the automation builder
- THEN communication actions MUST include: send Nextcloud notification, send email via n8n, add note to entity

#### Scenario 3.3: Task and workflow actions
- GIVEN the automation builder
- THEN task actions MUST include: create Nextcloud task, call external webhook, trigger another automation

#### Scenario 3.4: Round-robin assignment
- GIVEN an automation with round-robin assignment configured for users jan, maria, pieter
- WHEN 3 new entities are created
- THEN they MUST be assigned to jan, maria, pieter in sequence
- AND round-robin state MUST persist across server restarts

#### Scenario 3.5: Procest-specific actions
- GIVEN the automation builder in Procest
- THEN additional actions MUST include: create sub-case, link document, advance milestone, send MijnOverheid notification

---

### Requirement 4: Multi-step action chains

Automations MUST support executing multiple actions in sequence with conditional branching.

#### Scenario 4.1: Sequential action chain
- GIVEN a "Lead won" automation with 3 actions: notify assignee, send email to client, create task
- WHEN the trigger fires
- THEN all 3 actions MUST execute in sequence with fail-forward (failed actions don't block subsequent)
- AND each action's result MUST be logged individually

#### Scenario 4.2: Conditional branching
- GIVEN a "Lead created" automation
- THEN the user MUST be able to add if/else branching:
  - "If value > EUR 50,000: assign to senior-team AND priority notification"
  - "Else: round-robin assignment AND standard notification"

#### Scenario 4.3: Delay between actions
- GIVEN an action chain
- THEN "Wait" actions MUST be configurable (minutes, hours, days) via n8n's Wait node

---

### Requirement 5: Automation builder UI

Each consuming app MUST provide a visual automation builder within its interface.

#### Scenario 5.1: Create a new automation
- GIVEN a user with admin permissions in any app
- WHEN they navigate to Settings > Automatisering > Nieuw
- THEN a visual builder MUST display: trigger selection, condition configuration, and action chain
- AND the user MUST be able to name, describe, and toggle active/inactive status

#### Scenario 5.2: Builder uses shared UI patterns
- GIVEN the automation builder UI
- THEN it MUST use Nextcloud Vue components and follow existing form layout patterns
- AND it MUST support Dutch and English via i18n

#### Scenario 5.3: Preview automation
- GIVEN a configured automation
- WHEN the user clicks "Testen"
- THEN matching entities MUST be shown (limited to 10)
- AND a dry-run MUST show what actions would execute without running them

---

### Requirement 6: Automation management

Each app MUST provide a list view for managing all automations.

#### Scenario 6.1: Automation list view
- THEN all automations MUST be listed with: name, trigger summary, status (active/inactive), last run, total runs
- AND actions MUST include: edit, activate/deactivate, duplicate, delete, view history

#### Scenario 6.2: Execution history
- GIVEN an automation that has fired 25 times
- THEN each execution MUST show: timestamp, trigger entity, actions executed, result per action, duration

#### Scenario 6.3: Bulk management
- GIVEN multiple automations selected
- THEN bulk activate, deactivate, and delete MUST be supported

---

### Requirement 7: n8n backend integration

Automations MUST be stored and executed as n8n workflows via the n8n MCP integration.

#### Scenario 7.1: Automation creates n8n workflow
- GIVEN a user saves an automation
- THEN a corresponding n8n workflow MUST be created via MCP
- AND the workflow MUST use a webhook trigger node
- AND the automation record MUST store the n8n workflow ID

#### Scenario 7.2: Events trigger n8n via webhook
- GIVEN an active automation
- WHEN the trigger condition matches
- THEN the system MUST POST to the n8n webhook with: event name, entity data, changes diff, user, timestamp

#### Scenario 7.3: Workflow synchronization
- GIVEN an automation linked to n8n workflow ID
- WHEN the automation is edited
- THEN the n8n workflow MUST be updated (not recreated)
- AND if the workflow was deleted externally, the system MUST recreate it

#### Scenario 7.4: Deactivation syncs to n8n
- GIVEN an active automation deactivated by user
- THEN the n8n workflow MUST also be deactivated
- AND re-activation MUST re-activate the n8n workflow

---

### Requirement 8: Automation data storage

Automation configurations MUST be stored as OpenRegister objects.

#### Scenario 8.1: Automation schema
- THEN an `automation` schema MUST be defined with: title, description, active (boolean), trigger (object), actions (array), n8nWorkflowId, lastRunAt, runCount, sourceApp

#### Scenario 8.2: Execution log storage
- GIVEN an execution completes
- THEN a log entry MUST be stored with: automationId, triggeredAt, triggerEntity, actions results, status
- AND logs older than 90 days MUST be auto-purged

#### Scenario 8.3: Automation CRUD via API
- THEN automations MUST be queryable via standard OpenRegister API
- AND the frontend MUST use the same store pattern as other entities

---

### Requirement 9: SLA escalation automation

The system MUST support SLA-based escalation automations for time-sensitive deadlines.

#### Scenario 9.1: Stale entity detection
- GIVEN a "Lead stale" trigger with 7-day threshold
- WHEN a lead has not been updated for 7 days
- THEN the automation MUST fire
- AND if still stale at 14 days, a second escalation MUST fire to the manager

#### Scenario 9.2: Response time SLA
- GIVEN an SLA rule "respond within 24 hours"
- WHEN an entity has status "new" for more than 24 hours
- THEN the automation MUST: notify assignee, update priority to "high", add escalation note

#### Scenario 9.3: Case deadline escalation (Procest)
- GIVEN a case with a legal deadline (wettelijke termijn)
- WHEN the deadline is within 5 business days
- THEN the automation MUST notify the case handler and their supervisor
- AND the case priority MUST be visually elevated

#### Scenario 9.4: Configurable SLA thresholds
- THEN thresholds MUST support: hours, business days, and calendar days
- AND business days MUST exclude weekends
- AND thresholds MUST be configurable per pipeline/zaaktype

---

### Requirement 10: Email sequence automation

The system MUST support multi-step email sequences (Enterprise tier).

#### Scenario 10.1: Lead nurture email sequence
- GIVEN a "Lead created" trigger with source "website"
- THEN an email sequence MUST execute: Day 0 welcome, Day 3 product info, Day 7 case study, Day 14 follow-up
- AND the sequence MUST stop if the lead stage changes to "Qualified" or "Lost"

#### Scenario 10.2: Email sequence opt-out
- GIVEN a lead in an active sequence
- WHEN the contact clicks "Uitschrijven"
- THEN the sequence MUST stop immediately and the lead tagged "email-opted-out"

#### Scenario 10.3: Email sequence analytics
- THEN per step: emails sent, open rate, click rate, unsubscribe rate MUST be available

---

### Requirement 11: Permission control

Automation management MUST be restricted to authorized users.

#### Scenario 11.1: Admin-only automation management
- GIVEN a non-admin user
- THEN the "Automatisering" section MUST NOT be visible
- AND API requests MUST return 403

#### Scenario 11.2: Execution respects entity permissions
- GIVEN an automation that updates entities
- THEN n8n MUST authenticate as a service account
- AND audit trail MUST record the automation as the author

#### Scenario 11.3: Non-admin visibility
- GIVEN a non-admin viewing an entity affected by automation
- THEN the activity timeline MUST show the automation action
- AND the user MUST NOT be able to edit or disable the automation

---

### Requirement 12: Error handling and monitoring

The system MUST handle automation failures gracefully.

#### Scenario 12.1: Webhook delivery failure
- GIVEN a webhook delivery fails
- THEN the system MUST retry up to 3 times with exponential backoff (1s, 5s, 30s)
- AND if all retries fail, the execution MUST be logged as "failed" with admin notification

#### Scenario 12.2: Workflow execution error
- GIVEN n8n reports a workflow error
- THEN the error MUST be logged
- AND after 5 consecutive failures, a warning MUST be sent to admin

#### Scenario 12.3: Loop detection
- GIVEN automation A triggers automation B which triggers automation A
- THEN the system MUST detect the loop after 5 chained executions
- AND MUST halt with error "Automatiseringslus gedetecteerd"

---

## Data Model

### Automation Schema (OpenRegister)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `title` | string | YES | Automation name |
| `description` | string | no | Description |
| `active` | boolean | YES | Whether active |
| `trigger` | object | YES | `{type, entityType, conditions[]}` |
| `actions` | array | YES | Ordered list of `{type, config}` |
| `n8nWorkflowId` | string | no | Reference to n8n workflow |
| `lastRunAt` | datetime | no | Last execution timestamp |
| `runCount` | integer | no | Total execution count (default 0) |
| `sourceApp` | string | YES | App that owns this automation |

---

## Dependencies

- n8n MCP integration (workflow creation and execution)
- Each app's event system (ObjectEventListener, ObjectEventHandlerService)
- OpenRegister (automation and execution log storage)
- Nextcloud Notification API
- Nextcloud Activity API

## Standards & References

- n8n Workflow API -- programmatic workflow creation and execution
- n8n MCP (Model Context Protocol) -- stdio-based integration
- Nextcloud Activity API -- event publishing
- Nextcloud Notification API -- user notifications
- OpenRegister Event System -- triggers for state changes
- EspoCRM, Krayin, Twenty CRM -- competitive reference implementations
