---
competitor: flowable
analyzed_date: 2026-03-14
feature: task-service
module_path: modules/flowable-task-service-api, modules/flowable-task-service
---

# Shared Task Service

## Overview

Flowable uses a shared task service across BPMN and CMMN engines. This means tasks created from both process activities and case plan items share the same data model, same database tables, and same query API. This is a key architectural decision that enables unified task lists.

## Task Model (TaskInfo)

Core task properties:
- `id` -- unique identifier
- `name` -- task title
- `description` -- free text description
- `priority` -- integer priority (higher = more urgent)
- `owner` -- responsible user
- `assignee` -- currently assigned user
- `processInstanceId` / `executionId` -- BPMN context
- `scopeId` / `subScopeId` / `scopeType` / `scopeDefinitionId` -- CMMN context
- `propagatedStageInstanceId` -- stage context (works across BPMN-in-CMMN boundaries)
- `taskDefinitionId` / `taskDefinitionKey` -- link to definition
- `state` -- task state (created, claimed, in_progress, suspended, completed)
- `createTime`, `claimTime`, `inProgressStartTime`, `suspendedTime`
- `claimedBy`, `inProgressStartedBy`, `suspendedBy`
- `inProgressStartDueDate`, `dueDate` -- two-level due dates
- `category` -- task categorization
- `parentTaskId` -- sub-task support
- `tenantId` -- multi-tenancy
- `formKey` -- linked form definition
- Identity links (assignee, candidates, participants)

## Task Lifecycle

```
Created --> Claimed --> In Progress --> Completed
  |            |           |
  |            v           v
  |         Unclaimed   Suspended
  |            |           |
  v            v           v
Delegated --> Resolved   Activated
```

### States
- **Created** -- task exists, not yet claimed
- **Claimed** -- user has claimed responsibility
- **In Progress** -- work has started (`startProgress()`)
- **Suspended** -- work paused (`suspendTask()`)
- **Completed** -- task done

### Operations
- `claim(taskId, userId)` -- take ownership (fails if already claimed)
- `unclaim(taskId)` -- release ownership
- `startProgress(taskId, userId)` -- begin work
- `suspendTask(taskId, userId)` -- pause work
- `activateTask(taskId, userId)` -- resume from suspended
- `delegateTask(taskId, userId)` -- delegate to another user
- `resolveTask(taskId)` -- mark delegated task as resolved
- `complete(taskId)` -- finish task (with optional variables, form, outcome)

## Task Query (TaskQuery)

Extremely rich query API supporting filtering by:
- Task properties (name, description, priority, state, category)
- Assignment (assignee, owner, candidate user/group, unassigned)
- Process context (processInstanceId, processDefinitionKey)
- Case context (scopeId, scopeType, scopeDefinitionId)
- Dates (created before/after, due before/after, claim time)
- Variables (task-local, process, case variables with operators)
- Identity links
- Tenant
- Custom interceptor-based filtering

## Sub-tasks

Tasks support parent-child relationships:
- `parentTaskId` links to parent task
- `getSubTasks(parentTaskId)` retrieves children
- Enables task decomposition patterns

## Historic Task Logging

`HistoricTaskLogEntry` tracks detailed task events:
- Log number (sequential)
- Task ID, execution/scope IDs
- Log data (JSON)
- Timestamp, user ID
- Entry type (`HistoricTaskLogEntryType`):
  - USER_TASK_CREATED, COMPLETED, DELETED
  - USER_TASK_ASSIGNEE_CHANGED, OWNER_CHANGED
  - USER_TASK_PRIORITY_CHANGED, DUEDATE_CHANGED
  - USER_TASK_IDENTITY_LINK_ADDED/REMOVED
  - USER_TASK_SUSPENDEDCHANGE

## Procest Comparison

| Feature | Flowable Tasks | Procest Tasks |
|---------|---------------|---------------|
| Unified task list | Single list across BPMN + CMMN | Per-case task list |
| Task states | 5 lifecycle states | Basic open/completed |
| Claiming | Claim/unclaim pattern | Direct assignment |
| In-progress tracking | startProgress + suspend/activate | Not available |
| Delegation | Full delegation workflow | Not available |
| Sub-tasks | Parent-child task hierarchy | Not available |
| Two-level due dates | inProgressStartDueDate + dueDate | Single due date |
| Task log | Detailed event log per task | OpenRegister audit |
| Candidate assignment | Users + groups | User assignment |
| Form integration | formKey reference | Custom forms |
| Variable scoping | Task-local + parent scope | Flat variables |
