# CaseFabric Extensions Documentation

**Source:** https://guide.cafienne.io/docs/extensions/*

## Overview

CaseFabric provides extensions to the CMMN standard where the language has gaps for practical use. CMMN was designed with extensibility in mind, using XML `extensionElements` within any CMMN element.

## 1. Fault Handling Extensions

CMMN has very limited support for task failure handling.

### CMMN Limitations
- **Multiple failing instances:** The only CMMN way to handle failures is creating new task instances via repetition rules until one succeeds
- **Failed is semi-terminal:** The "Failed" state triggers the parent stage's completion rule, potentially causing the entire case to complete even though a task failed
- **No upward propagation:** Stage state transitions only propagate top-down; failures don't bubble up

### CaseFabric Improvements

**Bubbling failures:**
- Engine bubbles fault transitions up to parent stages and case
- Prevents completion of cases with failed tasks
- Failures gain visibility at case level
- Can be disabled with `interpreter.cmmn-fault-handling = true`

**Reactivation Criterion:**
- New sentry type for failed tasks
- When conditions are met, the engine triggers `reactivate` on the failed task
- Reactivation also bubbles up (surrounding stage/case return to Active)
- Eliminates need for repetition rules for fault handling

## 2. Workflow Extensions (Human Tasks)

Enhanced workflow handling beyond CMMN's basic task lifecycle.

### Extended Task Lifecycle
CMMN defines: Available, Active, Completed, Failed, Terminated, Suspended, Disabled.

CaseFabric adds sub-states within Active:
- **Unassigned** - Task is active but no one has picked it up
- **Assigned** - User has claimed the task (user = owner + assignee)
- **Delegated** - Task forwarded to another user (original = owner, new = assignee)

### Workflow Operations
- **Claim** - User takes a task
- **Assign** - Direct assignment to a user
- **Delegate** - Forward task, keeping original owner
- **Revoke** - Undo claim or delegation

### Design-time Properties
- **Performer role** (CMMN standard) - restricts task to role members
- **Due Date** (CaseFabric extension) - SpEL expression, stored in query DB for filtering/sorting
- **Dynamic Assignment** - SpEL expression to auto-assign on activation
- **Four-Eyes pattern** - Ensures two different users handle related tasks
- **Rendez-Vous pattern** - Multiple tasks must be completed by different users before proceeding

### Task Data Handling
- **Mandatory output parameters** - Validation before completion
- **Store** - Save partial output without completing
- **Validate** - Check output against rules
- **Complete** - Final submission with output

### Reusable HumanTask Implementations
- Task models can be shared across case definitions
- Includes input/output parameter definitions
- UI rendering via React JSON Schema Forms

## 3. Business Identifiers

Custom indexing of Case File Item properties for efficient querying.

### Concept
- Mark any Case File Item property as a "Business Identifier"
- Engine detects changes and stores values in a queryable index
- Enables domain-specific queries beyond basic case/task filtering

### Query Capabilities
```
GET /cases?identifiers=Nationality=Netherlands
GET /tasks?identifiers=CustomerLevel=Gold
GET /cases?identifiers=Nationality=Netherlands,CustomerLevel=Gold
GET /cases?identifiers=CustomerLevel=Gold,CustomerLevel=Silver  (union)
GET /cases?identifiers=Nationality!=Netherlands  (exclusion)
GET /cases?identifiers=CustomerLevel  (any value / existence)
```

### Storage
- Stored in `business_identifier` table in query database
- Fields: case instance ID, identifier name, identifier value
- Available in both `/cases` and `/tasks` endpoints

## 4. Mendix Integration

CaseFabric DCM Add-On embeds the case engine inside Mendix applications.

### Architecture
- Java actions with supporting JARs added to Mendix App
- Case engine starts via startup microflow action
- Data stored in Mendix database (prefixed `casemanagement$`)
- No additional servers/services needed
- No HTTP API exposed (interaction via Java Actions)

### Key Concepts
- Mendix Entities map to Case File Items
- Entity changes require explicit "Update Case Context" java action
- Task execution queue ensures Mendix transactions complete before DCM processing
- Case models deployed to `resources/casemanagement/` directory
- Hot-reload: updated models available without restarting Runtime
