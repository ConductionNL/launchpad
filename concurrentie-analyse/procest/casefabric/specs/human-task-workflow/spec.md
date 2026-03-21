---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Human Task Workflow
category: core
---

# Human Task Workflow

## Overview

CaseFabric extends the CMMN HumanTask with a full workflow lifecycle layer. While CMMN defines task states (Available, Active, Completed, etc.), CaseFabric adds sub-states within Active (Unassigned, Assigned, Delegated) and operations (claim, assign, delegate, revoke) for practical task management.

## Implementation Details

### Workflow Sub-States (within CMMN Active)

The `WorkflowTask` class manages a parallel lifecycle:

```
TaskState.Null -> TaskState.Unassigned (when task becomes Active)
Unassigned -> Assigned (via claim or assign)
Assigned -> Delegated (via delegate)
Delegated -> Assigned (via revoke - returns to owner)
Assigned -> Unassigned (via revoke - clears assignment)
Any -> Completed (via complete)
```

Key fields tracked:
- `currentOwner` -- the user who claimed/was assigned the task
- `currentAssignee` -- the user currently responsible (may differ after delegation)
- `currentDueDate` -- calculated or set due date
- `currentTaskState` -- Unassigned/Assigned/Delegated
- `lastAction` -- Null/Claim/Assign/Delegate/Revoke/Complete

### Task Operations

| Operation | API | Effect |
|-----------|-----|--------|
| Claim | `PUT /tasks/{id}/claim` | Current user becomes owner + assignee |
| Assign | `PUT /tasks/{id}/assign` | Specified user becomes owner + assignee |
| Delegate | `PUT /tasks/{id}/delegate` | Specified user becomes assignee, original stays owner |
| Revoke | `PUT /tasks/{id}/revoke` | If Delegated: returns to owner. If Assigned: goes to Unassigned |
| Save Output | `PUT /tasks/{id}` | Persist intermediate task data |
| Validate | `POST /tasks/{id}` | Validate output without completing |
| Complete | `POST /tasks/{id}/complete` | Validate output + complete task |

### Authorization

- **Performer role:** CMMN standard -- only team members with the configured role can complete
- **Case owners:** can override any task operation (claim, revoke, complete for others)
- **Auto-assignment:** SpEL expression evaluated on task activation
- **Auto-team-add:** assigning/delegating to a non-team-member auto-adds them with the performer role

### Four-Eyes Principle

Enforces that two related tasks must be performed by different users. Configured at design time in the IDE. The engine validates on task completion that the performing user differs from the user who completed the related task.

### Rendez-Vous Principle

Opposite of Four-Eyes -- enforces that related tasks must be performed by the same user. Can be combined with Four-Eyes for complex authorization patterns (e.g., Task A and C by the same user, Task B by a different user).

### Due Date

- Calculated via SpEL expression at task activation
- Stored in the query database for filtering and sorting
- No automatic behavior (no escalation, no reminders) -- purely informational

### Task Output Validation

Two validation mechanisms:
1. **Mandatory parameters:** output parameters marked as required must have values
2. **External validation:** REST service call to validate output (experimental)
   - POST data to external service
   - Empty response = valid
   - Non-empty JSON response = invalid (returned to caller)
   - Service error = 400 Bad Request

### Task Model / Form Rendering

- Task model stored as JSON blob (JSON Schema format)
- Contains `schema` and optional `uiSchema` properties
- Rendered by CaseFabric UI using JSON Forms or React JSON Schema Forms
- Engine passes the model through without interpretation

### Events Generated

| Event | Trigger |
|-------|---------|
| `HumanTaskActivated` | Task enters Active state |
| `HumanTaskInputSaved` | Mapped input parameters stored |
| `HumanTaskClaimed` | User claims task |
| `HumanTaskAssigned` | Task assigned to user |
| `HumanTaskDelegated` | Task delegated to another user |
| `HumanTaskRevoked` | Task revoked from assignee |
| `HumanTaskOwnerChanged` | Task owner changes |
| `HumanTaskDueDateFilled` | Due date calculated/set |
| `HumanTaskOutputSaved` | Intermediate output saved |
| `HumanTaskCompleted` | Task completed with output |
| `HumanTaskSuspended` | Task suspended |
| `HumanTaskResumed` | Task resumed |
| `HumanTaskTerminated` | Task terminated |

## Relevance for Procest

The workflow sub-state pattern is practical and well-designed. Procest should consider:

1. **Sub-states within Active** -- Unassigned/Assigned/Delegated is more useful than a single Active state
2. **Owner vs Assignee distinction** -- enables proper delegation tracking
3. **Claim/Revoke pattern** -- self-service task pickup is essential for team-based work
4. **Four-Eyes / Rendez-Vous** -- valuable for compliance scenarios in government
5. **Save intermediate output** -- users can save work without completing
6. **Auto-assignment expressions** -- reduces manual task distribution
