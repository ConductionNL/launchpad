---
competitor: flowable
analyzed_date: 2026-03-14
feature: cmmn-engine
module_path: modules/flowable-cmmn-engine, modules/flowable-cmmn-api, modules/flowable-cmmn-model
---

# CMMN Engine (Case Management)

## Overview

Flowable's CMMN engine is a full implementation of the CMMN 1.1 (Case Management Model and Notation) standard. It is the most relevant module for Procest competitive analysis as it directly addresses case management workflows.

## Core Concepts

### Case Definition & Deployment
- Case definitions are written in CMMN 1.1 XML (`.cmmn` files)
- Deployed as versioned artifacts via `CmmnRepositoryService`
- Each deployment creates a new version; old versions kept for running instances
- Case definitions have: id, key, version, category, tenant, description, graphical notation flag

### Case Instance
The runtime representation of a case. Properties:
- `id`, `businessKey`, `businessStatus`, `name`
- `parentId` (for nested cases)
- `state` (active, completed, terminated, suspended)
- `startTime`, `startUserId`
- `callbackId/callbackType` (for BPMN-CMMN integration)
- `referenceId/referenceType` (external system links)
- `tenantId` (multi-tenancy)
- `lastReactivationTime/lastReactivationUserId` (case reactivation)
- `isCompletable` flag
- Optimistic locking via `REV_` column
- Lock-based concurrency via `LOCK_TIME_` / `LOCK_OWNER_`

### Plan Item Instance
The runtime representation of a plan item within a case:
- Tracks 11 timestamp columns for full lifecycle auditing: `createTime`, `lastAvailableTime`, `lastUnavailableTime`, `lastEnabledTime`, `lastDisabledTime`, `lastStartedTime`, `lastSuspendedTime`, `completedTime`, `occurredTime`, `terminatedTime`, `exitTime`, `endedTime`
- `assignee`, `completedBy`, `startUserId`
- `isStage` flag, `stageInstanceId` (parent stage)
- `entryCriterionId`, `exitCriterionId`
- `itemDefinitionId`, `itemDefinitionType` (link to model)
- Sentry part instance counting for optimization

### Plan Item States (CMMN 1.1 + extensions)
Standard states: `active`, `available`, `enabled`, `disabled`, `completed`, `failed`, `suspended`, `terminated`

Flowable extensions:
- `unavailable` -- event listeners not yet available
- `wait_repetition` -- waiting for repetition trigger
- `async-active` -- scheduled for async activation
- `async-active-leave` -- scheduled for async terminal transition

Terminal states: `completed`, `terminated`, `failed`

### Plan Item Transitions
Standard CMMN transitions: `close`, `complete`, `create`, `disable`, `enable`, `exit`, `fault`, `manualStart`, `occur`, `parentResume`, `parentSuspend`, `reactivate`, `reenable`, `resume`, `start`, `suspend`, `terminate`

Flowable extensions: `async-activate`, `async-leave-active`, `initiate`, `dismiss`

## CMMN Model Elements

### Task Types (Plan Item Definitions)
| Type | Class | Description |
|------|-------|-------------|
| HumanTask | `HumanTask` | User-facing task with assignee, owner, candidateUsers/Groups, formKey, dueDate, priority, category |
| CaseTask | `CaseTask` | Starts a sub-case (with in/out parameter mapping) |
| ProcessTask | `ProcessTask` | Starts a BPMN process (CMMN-BPMN integration) |
| DecisionTask | `DecisionTask` | Executes a DMN decision table |
| ServiceTask | `ServiceTask` | Java delegate, expression, or delegate expression |
| ScriptServiceTask | `ScriptServiceTask` | Script execution (Groovy, etc.) |
| HttpServiceTask | `HttpServiceTask` | HTTP call with request/response handlers |
| ExternalWorkerServiceTask | `ExternalWorkerServiceTask` | External worker pattern (poll-based) |
| SendEventServiceTask | `SendEventServiceTask` | Sends event to event registry |
| CasePageTask | `CasePageTask` | UI page task (Flowable Design) |

### Structural Elements
| Type | Description |
|------|-------------|
| Stage | Container for plan items, supports nesting, autoComplete, exit criteria |
| Milestone | Named achievement point within a case |
| PlanFragment | Reusable fragment of plan items |

### Event Listeners
| Type | Description |
|------|-------------|
| TimerEventListener | Time-based triggers |
| UserEventListener | Manual user triggers |
| SignalEventListener | Signal-based triggers |
| GenericEventListener | Generic event triggers |
| VariableEventListener | Variable change triggers |
| IntentEventListener | Intent-based triggers |
| EventRegistryEventListener | Event registry integration |
| ReactivateEventListener | Case reactivation triggers |

### Control Rules
| Rule | Description |
|------|-------------|
| RepetitionRule | Controls if/when plan items repeat |
| RequiredRule | Marks plan items as required for stage/case completion |
| ManualActivationRule | Requires manual start (moves to `enabled` not `active`) |
| CompletionNeutralRule | Plan item doesn't affect stage completion |
| ParentCompletionRule | Controls how parent completes relative to children |
| ReactivationRule | Controls behavior on case reactivation |

### Sentries (Entry/Exit Criteria)
- **Sentry** = combination of OnParts + IfPart
- **OnPart** watches for plan item lifecycle transitions
- **IfPart** evaluates condition expressions
- Both entry and exit criteria supported on stages, tasks, and milestones

## Services API

### CmmnRuntimeService
Core operations:
- `createCaseInstanceBuilder()` -- start new cases with variables, business key, tenant
- `triggerPlanItemInstance()` / `enablePlanItemInstance()` / `startPlanItemInstance()` / `disablePlanItemInstance()`
- `completeStagePlanItemInstance()` / `completeCaseInstance()` / `terminateCaseInstance()`
- `bulkTerminateCaseInstances()` / `bulkDeleteCaseInstances()`
- Variable management (get/set/remove at case and plan item scope, sync and async)
- `getStageOverview()` -- visual progress of stages
- `setOwner()` / `setAssignee()` -- case-level assignment
- Identity links (user/group involvement)
- Entity links (cross-engine references)
- `updateBusinessKey()` / `updateBusinessStatus()` -- business state tracking
- `createChangePlanItemStateBuilder()` -- dynamic state changes
- Event subscription management (start/modify/delete)
- `evaluateCriteria()` -- force sentry evaluation

### CmmnTaskService
Rich task operations:
- CRUD: `newTask()`, `saveTask()`, `deleteTask()`, `bulkSaveTasks()`
- Lifecycle: `complete()`, `completeTaskWithForm()`, `claim()`, `unclaim()`, `startProgress()`, `suspendTask()`, `activateTask()`
- Delegation: `delegateTask()`, `resolveTask()`
- Variables: full get/set/remove at task and parent scope
- Forms: `getTaskFormModel()`
- Assignment: `setAssignee()`, `setOwner()`, `setPriority()`, `setDueDate()`
- Identity links: user/group participation
- Sub-tasks: `getSubTasks()`

### CmmnHistoryService
Full audit capability:
- `createHistoricCaseInstanceQuery()` -- query completed/running cases
- `createHistoricMilestoneInstanceQuery()` -- milestone tracking
- `createHistoricPlanItemInstanceQuery()` -- plan item audit trail
- `createHistoricTaskInstanceQuery()` -- task history
- `createHistoricVariableInstanceQuery()` -- variable history
- `getStageOverview()` -- historic stage progress
- `createCaseReactivationBuilder()` -- reopen completed cases
- `createHistoricTaskLogEntryQuery()` -- task log entries
- Identity/entity link history

### CmmnRepositoryService
Definition management:
- Deploy `.cmmn` files as versioned artifacts
- Query case definitions by key, version, tenant, category
- Manage identity links on definitions (access control)

### CmmnMigrationService
Live migration:
- Migrate running case instances to new case definition versions
- Validate migration before applying
- Batch migration support

## Database Schema

### Runtime Tables
| Table | Purpose |
|-------|---------|
| `ACT_CMMN_RU_CASE_INST` | Active case instances |
| `ACT_CMMN_RU_PLAN_ITEM_INST` | Active plan item instances |
| `ACT_CMMN_RU_SENTRY_PART_INST` | Sentry evaluation state |
| `ACT_CMMN_RU_MIL_INST` | Active milestone instances |

### Definition Tables
| Table | Purpose |
|-------|---------|
| `ACT_CMMN_DEPLOYMENT` | Deployment metadata |
| `ACT_CMMN_DEPLOYMENT_RESOURCE` | Deployed files (XML, images) |
| `ACT_CMMN_CASEDEF` | Case definition versions |

### History Tables
| Table | Purpose |
|-------|---------|
| `ACT_CMMN_HI_CASE_INST` | Historic case instances |
| `ACT_CMMN_HI_MIL_INST` | Historic milestones |
| `ACT_CMMN_HI_PLAN_ITEM_INST` | Historic plan item instances |

## Agenda Pattern

The CMMN engine uses an **agenda-based** execution pattern (command pattern + operation queuing):
- Each state change is an operation on the `CmmnEngineAgenda`
- Operations are queued and executed sequentially within a transaction
- After each operation, sentry criteria are re-evaluated
- This ensures correct cascading of state changes through the case model

Key agenda operations: `planInitPlanModelOperation`, `planActivatePlanItemInstanceOperation`, `planCompletePlanItemInstanceOperation`, `planExitPlanItemInstanceOperation`, `planEvaluateCriteriaOperation`, `planTerminateCaseInstanceOperation`, `planReactivateCaseInstanceOperation`

## Procest Comparison

| Feature | Flowable CMMN | Procest |
|---------|--------------|---------|
| Case lifecycle | 8 states + 3 extensions | Simpler state model |
| Plan items | 10 task types + events + milestones | Tasks via OpenRegister |
| Sentries | Full entry/exit criteria with conditions | n8n conditions |
| Stage nesting | Unlimited depth | Flat structure |
| Human tasks | Full delegation, claiming, progress tracking | Basic task assignment |
| History | Dedicated history tables with 11+ timestamps per plan item | OpenRegister audit |
| Migration | Formal migration service | Manual |
| Reactivation | Built-in case reactivation | Not available |
| Multi-tenancy | Native tenant isolation | Nextcloud org-based |
