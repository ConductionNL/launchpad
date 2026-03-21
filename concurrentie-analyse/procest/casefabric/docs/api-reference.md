# CaseFabric API Reference Documentation

**Source:** https://guide.cafienne.io/docs/api/overview

## Overview

The CaseFabric Engine exposes a REST API on port 2027 (configurable). The API is used by the Generic UI, Case Designer, and can be embedded into custom solutions. Ships with Swagger UI for interactive exploration.

## Authentication

- Every request requires a JWT authorization token
- Tokens obtained from configured OpenID Connect Identity Provider
- Swagger UI integrates with IdP login flow via "Authorize" button

## API Categories

### `/case` - Case Management
- Start new cases
- List cases (with filters)
- Get individual case instances
- Set/manage case teams
- Case lifecycle operations (complete, terminate, suspend, re-activate)

### `/task` - Task Management
- Get tasks (assigned to user, by case, etc.)
- Claim tasks
- Assign/delegate tasks
- Complete tasks (with output data)
- Revoke task assignments
- Task lifecycle sub-states: Unassigned, Assigned, Delegated

### `/repository` - Case Definitions
- Store case definitions (CMMN XML)
- Retrieve case definitions
- Deploy case models from designer

### `/tenant` - Tenant Administration
- Add/manage tenant users
- Add/remove roles
- Enable/disable users
- List tenant users

### `/platform` - Platform Administration
- Add tenants
- Change platform owners
- Check platform health
- Enable/disable tenants

## Query Parameters

### Case Queries
- Filter by state (Active, Completed, Terminated, etc.)
- Filter by case definition type
- Filter by business identifiers (custom indexed fields)

### Task Queries
- Filter by assignment (my tasks, unassigned, etc.)
- Filter by due date
- Filter by case type
- Filter by task state (Unassigned, Assigned, Delegated)

### Business Identifier Filtering
Advanced filtering on custom-indexed case data fields:
```
GET /cases?identifiers=Nationality=Netherlands
GET /tasks?identifiers=CustomerLevel=Gold,CustomerLevel=Silver
GET /cases?identifiers=Nationality!=Netherlands
```

Operators:
- `name=value` - Equality
- `name!=value` - Inequality (exclusion)
- `name` - Any value (existence check)
- Comma-separated for multiple filters (intersection for different identifiers, union for same identifier)

## Case File API
- Read case file data
- Update case file items directly (outside task context)
- Transitions: create, update, replace, delete

## Workflow Extensions on Tasks

Additional task lifecycle operations beyond CMMN:
- **Claim** - User takes ownership (Unassigned -> Assigned)
- **Assign** - Direct assignment to user
- **Delegate** - Forward to another user (original user stays owner)
- **Revoke** - Undo claim/delegation
- **Complete** - Finish task with output data

Task output handling:
- Mandatory output parameters (validation)
- Store partial output without completing
- Validate output before completing

## Mail Service

Built-in mail sending via Process Tasks:
- Configurable Jakarta Mail properties
- SMTP host/port configuration in engine config
