---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Task Workflow Extensions
category: core
relevance: high
---

# Task Workflow Extensions

## Summary

CaseFabric extends CMMN's basic task lifecycle with practical workflow operations: claim, assign, delegate, revoke. It adds sub-states within the Active state, due dates, dynamic assignment, four-eyes and rendez-vous patterns, and mandatory output validation.

## Key Capabilities

### Extended Task Sub-States (within Active)
- **Unassigned** - Task is active, no one has picked it up
- **Assigned** - User claimed or was assigned (user = owner + assignee)
- **Delegated** - Forwarded to another user (original = owner, delegated = assignee)

### Workflow Operations
| Operation | Effect |
|-----------|--------|
| Claim | User takes ownership (Unassigned -> Assigned) |
| Assign | Direct assignment by case owner |
| Delegate | Forward to another user, keep original owner |
| Revoke | Undo claim or delegation |
| Complete | Finish task with output data |

All operations are optional - a user with proper authorization can complete a task without claiming it first.

### Role-Based Authorization
- **Performer role** (CMMN) - restricts task visibility/execution to role members
- Case owners can override: revoke assigned tasks, directly complete tasks
- Authorized roles on discretionary items and user events

### Due Date
- Set via SpEL expression
- Evaluated in context of a Case File Item
- No engine behavior triggered (informational only)
- Stored in query database for filtering, sorting, and UI display

### Dynamic Assignment
- SpEL expression evaluated on task activation
- Automatically assigns task to computed user
- Can be combined with REST API assignment
- Allows unrecognized user IDs (IdP-agnostic)

### Four-Eyes Pattern
- Ensures two different users handle related tasks
- Built-in support in case model design
- Enforcement at engine level

### Rendez-Vous Pattern
- Multiple tasks must be completed by different users
- Synchronization point in case execution

### Task Data Handling
- **Mandatory output parameters** - validation before completion
- **Store** - save partial output without completing
- **Validate** - check output against rules before final submit
- **Complete** - final submission with all output data
- Task output mapped back to Case File Items

### Reusable Task Implementations
- HumanTask Models shared across case definitions
- Define input/output parameters once
- React JSON Schema Forms for UI rendering

## Fault Handling (CaseFabric Extension)

### Failure Propagation
- Task failures bubble up to parent stages and case
- Prevents premature case completion when tasks fail
- Configurable via `interpreter.cmmn-fault-handling`

### Reactivation Criterion
- New sentry type for failed tasks
- When conditions are met, engine reactivates the failed task
- Reactivation cascades up the hierarchy
- Eliminates need for repetition rules for error handling

## Relevance to Procest

**High relevance.** Task workflow is central to case management. These patterns are directly applicable to Procest's design.

### What to adopt:
- Claim/assign/delegate/revoke lifecycle is industry standard
- Due dates on tasks are essential
- Four-eyes and rendez-vous patterns are important for government processes
- Mandatory output validation prevents incomplete task completion
- Partial save (store without completing) is user-friendly

### What to adapt:
- Sub-states can be simplified for Nextcloud context
- Dynamic assignment could use Nextcloud user/group system instead of SpEL
- Task forms should use Nextcloud Vue components instead of React JSON Schema Forms
- Due dates should integrate with Nextcloud calendar/notifications
