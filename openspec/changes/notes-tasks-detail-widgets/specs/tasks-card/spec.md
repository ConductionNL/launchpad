# Tasks Card Specification

## Purpose
Provides an inline card widget (`CnTasksCard`) for detail pages that displays tasks associated with the current object, giving users immediate visibility into task status without opening the sidebar.

## Current Behavior
Today, tasks are displayed in the CnObjectSidebar's Tasks tab as `NcListItem` components. The sidebar shows `task.title || task.name`, a status icon (completed checkmark or blank outline), and a subname with assignee and due date. The sidebar is read-only for tasks — there is no creation, edit, or status change UI.

Procest's CaseDetail has a separate inline tasks table (not using any shared component) that shows title, status badge, assignee, due date, and priority with overdue highlighting and click-to-navigate. The `CnTasksCard` should provide a reusable version of this pattern.

The task API returns objects with `title`/`name` (display name), `status` (string: `available`, `active`, `completed`, `terminated`, `disabled`), `assignee` (plain string userId, NOT an object), and `dueDate` (ISO datetime string).

## Requirements

### Requirement: Inline tasks display
The system MUST render a `CnDetailCard` with title "Tasks" that displays tasks for the current object.

#### Scenario: Tasks card shows recent tasks
- GIVEN a detail page with a `CnTasksCard` component
- WHEN the card is rendered with a valid register, schema, and object ID
- THEN the card MUST display up to 5 tasks sorted by due date (soonest first)
- AND each task MUST show a status indicator, summary text, assignee name, and due date

#### Scenario: Empty state
- GIVEN a detail page with a `CnTasksCard` component
- WHEN the object has no tasks
- THEN the card MUST display an empty state message "No tasks"

### Requirement: Task status indicators
Each task MUST display a visual status indicator reflecting its current state.

#### Scenario: Task status rendering
- GIVEN a task with status "available" or "needs-action"
- WHEN the task is rendered in the card
- THEN it MUST display a `CheckboxBlankOutline` icon (open circle)
- AND a task with status "completed" MUST display a `CheckboxMarkedOutline` icon with `--color-success` styling
- AND a task with status "active" or "in-process" MUST display a progress indicator (e.g., `ProgressClock` icon)
- AND a task with status "terminated" MUST display a distinct icon (e.g., `CloseCircleOutline` with `--color-error`)
- NOTE: Status values in Procest are: `available`, `active`, `completed`, `terminated`, `disabled`. The sidebar currently only distinguishes `completed` vs. everything else — the card should be more granular.

#### Scenario: Overdue task highlighting
- GIVEN a task with a due date in the past and status not "completed"
- WHEN the task is rendered
- THEN the due date MUST be displayed in a warning color (NL Design System error token)

### Requirement: Show all link
The system MUST provide a way to navigate to the full tasks list in the sidebar.

#### Scenario: More tasks available than displayed
- GIVEN an object with more than 5 tasks
- WHEN the `CnTasksCard` is rendered
- THEN a "Show all ({count})" link MUST appear in the card footer
- AND clicking it MUST open the sidebar on the Tasks tab

### Requirement: Assignee name is interactive
Each task's assignee name MUST be clickable and trigger the `CnUserActionMenu` for that user.

#### Scenario: Clicking a task assignee
- GIVEN a task assigned to a different user
- WHEN the current user clicks the assignee's display name
- THEN the `CnUserActionMenu` MUST open for that assignee

#### Scenario: Unassigned task
- GIVEN a task with no assignee
- WHEN the task is rendered
- THEN "Unassigned" MUST be displayed in place of an assignee name
- AND it MUST NOT be clickable

### Requirement: API response format handling
The component MUST handle the response format from the OpenRegister tasks API.

#### Scenario: Task field mapping
- GIVEN the API returns tasks with `title` and/or `name` fields
- WHEN the card renders
- THEN it MUST use `task.title || task.name` for display (matching sidebar behavior)
- AND it MUST use `task.assignee` as a plain string userId (NOT an object with id/displayName)
- AND it MUST use `task.dueDate` for the due date (NOT `task.due`)

#### Scenario: Wrapped response
- GIVEN the API returns `{ "results": [...] }` instead of a raw array
- WHEN the card processes the response
- THEN it MUST handle both `data.results` and raw array formats

### Requirement: Edge cases

#### Scenario: Tasks with no due date
- GIVEN a task with `dueDate` set to null or absent
- WHEN the card renders
- THEN no due date MUST be shown (not "Invalid Date" or "NaN")
- AND overdue highlighting MUST NOT apply

#### Scenario: All tasks completed
- GIVEN an object where all tasks have status "completed"
- WHEN the card renders
- THEN all tasks MUST display with the completed checkmark icon
- AND the "Show all" footer MUST still appear if total count exceeds the display limit

#### Scenario: Empty assignee string
- GIVEN a task with `assignee` set to an empty string `""`
- WHEN the card renders
- THEN it MUST treat this the same as null/undefined — display "Unassigned"
