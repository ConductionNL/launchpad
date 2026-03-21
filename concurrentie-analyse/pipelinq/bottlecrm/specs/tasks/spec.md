---
competitor: bottlecrm
analyzed_date: 2026-03-14
feature: tasks
---

# Task Management

## Overview

BottleCRM has two complementary task systems: a **CRM Task** model (linked to business entities) and a **Board/Kanban** system (standalone project boards). Both support kanban views with drag-drop ordering.

## CRM Tasks

### Task Entity

| Field | Type | Description |
|-------|------|-------------|
| title | CharField(200) | Task title |
| status | CharField | New, In Progress, Completed |
| priority | CharField | Low, Medium, High |
| due_date | DateField | Due date |
| description | TextField | Notes |
| **CRM Links** (mutually exclusive) | | |
| account | FK(Account) | OR |
| opportunity | FK(Opportunity) | OR |
| case | FK(Case) | OR |
| lead | FK(Lead) | OR |
| **Kanban** | | |
| stage | FK(TaskStage) | Custom pipeline stage |
| kanban_order | DecimalField(15,6) | Drag-drop ordering |

**Validation**: A task can link to at most one parent entity (account, opportunity, case, or lead). Enforced via `clean()`.

### Task Pipeline Models

- **TaskPipeline**: Named pipeline per org (e.g., "Development", "Support", "Marketing")
- **TaskStage**: Kanban column with stage_type (open, in_progress, completed), color, wip_limit, maps_to_status

### Computed Properties

- `is_overdue`: Past due date and not completed
- `days_until_due`: Days remaining (negative if overdue), None if no due date

## Kanban Boards (Standalone)

Independent board system for project-style task management:

### Board

| Field | Type | Description |
|-------|------|-------------|
| name | CharField(255) | Board name |
| description | TextField | Board description |
| owner | FK(Profile) | Board creator |
| members | M2M(Profile via BoardMember) | Board members |
| is_archived | BooleanField | Archive flag |

### BoardMember

| Field | Type | Description |
|-------|------|-------------|
| board | FK(Board) | Parent board |
| profile | FK(Profile) | Member |
| role | CharField | owner, admin, member |

### BoardColumn

| Field | Type | Description |
|-------|------|-------------|
| board | FK(Board) | Parent board |
| name | CharField(100) | Column name |
| order | PositiveIntegerField | Display position |
| color | CharField(7) | Hex color |
| limit | PositiveIntegerField | WIP limit |

### BoardTask (Card)

| Field | Type | Description |
|-------|------|-------------|
| column | FK(BoardColumn) | Current column |
| title | CharField(255) | Task title |
| description | TextField | Details |
| order | PositiveIntegerField | Position in column |
| priority | CharField | low, medium, high, urgent |
| assigned_to | M2M(Profile) | Assignees |
| due_date | DateTimeField | Deadline |
| completed_at | DateTimeField | Completion timestamp |
| account | FK(Account) | Optional CRM link |
| contact | FK(Contact) | Optional CRM link |
| opportunity | FK(Opportunity) | Optional CRM link |

## Frontend Views

```
/tasks                    -- CRM task list
/tasks/board/[boardId]    -- Kanban board view
/tasks/calendar           -- Calendar view
```

## Relevance to Pipelinq

1. **Dual task system** (CRM-linked + standalone boards) serves different use cases
2. **Mutually exclusive parent** validation prevents data ambiguity
3. **Board membership with roles** (owner/admin/member) enables team collaboration
4. **Calendar view** for time-based task planning
5. The **CRM entity linking** on BoardTask is interesting -- it bridges project management with CRM context
