---
competitor: casefabric
analyzed_date: 2026-03-14
feature: CMMN 1.1 Engine
category: core
---

# CMMN 1.1 Engine

## Overview

The CaseFabric engine is the core CMMN 1.1 interpreter. It parses CMMN XML definition documents into a Java object graph and executes them at runtime using Akka persistent actors. Each case instance is an actor with its own event-sourced lifecycle, managing plan items, sentries, case file, and team.

## Implementation Details

### Definition Parsing

Case definitions are parsed from XML (`<definitions>` documents) into a Java object graph rooted at `DefinitionsDocument`:

- `CaseDefinition` -- top-level case, containing:
  - `CaseFileDefinition` -- hierarchical data model with typed items and properties
  - `CasePlanDefinition` extends `StageDefinition` -- behavioral structure (the plan)
  - `CaseTeamDefinition` -- role definitions and authorization constraints
- Each CMMN element has a `*Definition` class (67 definition classes total)
- XML element-to-class mapping via `XMLElementDefinition` base class with DOM parsing

Key definition types: `StageDefinition`, `HumanTaskDefinition`, `ProcessTaskDefinition`, `CaseTaskDefinition`, `MilestoneDefinition`, `TimerEventDefinition`, `UserEventDefinition`, `EventListenerDefinition`, `SentryDefinition`, `CriterionDefinition`, `PlanItemDefinition`, `DiscretionaryItemDefinition`

Definitions are loaded via pluggable `DefinitionProvider`:
- `FileBasedDefinitionProvider` -- reads XML from filesystem, cached with last-modified invalidation
- `StartCaseDefinitionProvider` -- accepts definitions inline with StartCase API calls
- Cache size configurable (default: 100 definitions)

### Runtime Execution Model

Each case instance runs as an Akka persistent actor (`Case extends ModelActor`):

```
Case (extends ModelActor<CaseCommand, CaseEvent>)
  +-- CasePlan (extends Stage)
  |     +-- PlanItem[] (HumanTask, ProcessTask, CaseTask, Stage, Milestone, TimerEvent, etc.)
  |           +-- (nested Stages with their own PlanItem children)
  +-- CaseFile
  |     +-- CaseFileItem[] (hierarchical data items)
  +-- Team
  |     +-- Member[] (users with roles)
  +-- SentryNetwork
        +-- Criterion[] (entry/exit criteria connected to plan items and file items)
```

The `CaseSystem` bootstraps the Akka ActorSystem with:
- Cluster sharding for distributed case instances
- `CaseMessageRouter` (local or cluster) for routing commands to case actors
- Timer service for scheduled events
- Health monitoring

### State Machine Design

Three state machine configurations are defined statically in `StateMachine.java` as lookup tables of `(State, Transition) -> (TargetState, Action)`:

**1. EventMilestone** (milestones, event listeners):
- exit via `Exit`, terminate via `ParentTerminate`
- Null -> Available (Create) -> Completed (Occur) or Terminated
- Suspended state via Suspend/ParentSuspend

**2. TaskStage** (human tasks, process tasks, case tasks, stages):
- exit and terminate both via `Exit`
- Full lifecycle: Null -> Available -> Enabled -> Active -> Completed/Failed/Terminated
- Disabled state from Enabled, Suspended from Active
- Reactivate from Failed back to Active
- Auto-repeat on Complete/Terminate when no entry criteria

**3. CasePlan** (top-level case):
- Null -> Active (Create)
- Active -> Completed/Terminated/Failed/Suspended
- Reactivate from any semi-terminal back to Active
- Close from any semi-terminal to Closed (final)

Actions are `@FunctionalInterface` callbacks: `createInstance()`, `startInstance()`, `completeInstance()`, `terminateInstance()`, `suspendInstance()`, `resumeInstance()`, `reactivateInstance()`, `failInstance()`.

### Sentry Network

The `SentryNetwork` is the reactive event propagation engine:

1. **Registration:** Plan items and case file items connect to the network on creation
2. **Criteria:** Each `Criterion` (entry or exit) consists of:
   - `OnPart[]` -- conditions on plan item transitions or case file item changes
   - `IfPart` -- optional guard expression (SpEL/XPath)
3. **Event handling:** When a `StandardEvent` occurs:
   - Pushed onto `TransitionCallStack`
   - All connected criteria evaluate their on-parts
   - If all on-parts satisfied and if-part evaluates to true, criterion fires
   - Firing triggers the target plan item's transition
   - Cascading transitions are pushed back onto the stack
4. **Recovery:** During actor recovery (event replay), sentry evaluation is skipped

OnPart types:
- `PlanItemOnPart` -- listens for a specific transition on a specific plan item
- `CaseFileItemOnPart` -- listens for case file item operations (create, update, delete, etc.)

### Expression Languages

Two expression engines via `ExpressionDefinition`:

1. **SpEL** (Spring Expression Language) -- primary:
   - Rich API context: `case`, `task`, `file`, `team`, `user`
   - Sub-contexts for constraints, mappings, plan items, workflow
   - Used for guard conditions, repetition rules, required rules, mappings, assignment, due dates

2. **XPath** -- legacy CMMN compatibility

### Case Lifecycle Events

Every state change generates an immutable event:
- `CaseStarted`, `CaseCompleted`, `CaseTerminated`, `CaseFailed`
- `PlanItemCreated`, `PlanItemTransitioned` (state, historyState, transition)
- `CaseFileItemCreated`, `CaseFileItemUpdated`, `CaseFileItemReplaced`, `CaseFileItemDeleted`
- `CaseTeamMemberAdded`, `CaseTeamMemberRemoved`
- `HumanTaskActivated`, `HumanTaskClaimed`, `HumanTaskCompleted`, etc.

### Planning Table (Discretionary Items)

CMMN planning tables enable runtime plan modification:
- `PlanningTableDefinition` contains `DiscretionaryItemDefinition` entries
- `ApplicabilityRuleDefinition` controls which items are currently available
- API: `GET /cases/{id}/discretionaryitems` lists available items
- API: `POST /cases/{id}/discretionaryitems/plan` adds an item to the plan

## Code Quality Observations

- Clean separation between definition (parse) and instance (runtime) layers
- State machines are declaratively configured, not embedded in business logic
- Event hierarchy is well-organized by domain (case, plan, file, team, task)
- Extensive debug logging with lazy evaluation (`addDebugInfo(() -> ...)`)
- XML-first approach means definitions are not easily human-editable without the IDE

## Relevance for Procest

1. **State machine pattern** -- declarative transition tables are cleaner than embedded if/else logic
2. **Sentry/criteria pattern** -- reactive evaluation is more efficient than polling for dependencies
3. **Definition vs instance separation** -- allows runtime migration and multiple definition versions
4. **Expression contexts** -- rich API objects make expressions powerful without custom code
5. **Planning table** -- runtime plan modification is valuable for adaptive case management
