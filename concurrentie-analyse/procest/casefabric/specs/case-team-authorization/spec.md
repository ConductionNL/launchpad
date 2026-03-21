---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Case Team and Authorization
category: core
---

# Case Team and Authorization

## Overview

CaseFabric implements CMMN's case team concept with a multi-layered authorization model: Platform Users, Tenant Users, and Case Team Members. Each case has its own team with role-based access control.

## Implementation Details

### Identity Hierarchy

1. **Platform User** -- derived from JWT `sub` claim. Has no inherent rights. Merely identifies a user across the platform.
2. **Tenant User** -- a platform user registered in a specific tenant. Has tenant-level roles (e.g., Employee, Manager).
3. **Case Team Member** -- a tenant user added to a specific case's team. Has case-level roles matching CMMN role definitions.

### Authentication

- OpenID Connect with external Identity Provider (Dex, Keycloak, etc.)
- JWT token validation via `TokenVerifier`
- `sub` claim -> Platform User -> Tenant User lookup via `IdentityCache`
- Cache size configurable (default: 1000 entries)
- No internal user store -- all identity from IDP

### Case Team Model

Definition (`CaseTeamDefinition`):
- `CaseRoleDefinition` -- roles defined in the CMMN model (e.g., Doctor, Patient, Approver)

Runtime (`Team` class):
- `Member` -- user with role assignments and ownership flag
- `CurrentMember` -- the team member making the current request
- Auto-add: assigning or delegating tasks to non-members adds them automatically

Database tables:
- `case_instance_role` -- roles defined for each case instance
- `case_instance_team_member` -- members with roles, tenant user flag, owner flag, active flag

### Authorization Rules

| Context | Rule |
|---------|------|
| Start Case | Any authenticated tenant user |
| Access Case | Must be a team member |
| Complete Task | Must have performer role (or be case owner) |
| Manage Team | Case owners only |
| Claim Task | Team members with performer role |
| Assign/Delegate Task | Case owners (adds user to team if needed) |
| Revoke Task | Current assignee or case owner |
| Cross-tenant | Users see only cases in their registered tenants |

### Platform Administration

- Platform owners configured in `cafienne.platform.owners` (user IDs)
- Can create/disable/enable tenants
- Can update platform user information across all cases
- `PlatformUpdate` command propagates user ID changes to all affected cases

### Case Team API

| Method | Path | Description |
|--------|------|-------------|
| GET | `/cases/{id}/team` | Get team members |
| PUT | `/cases/{id}/team` | Update team (add/remove members, change roles) |

### Tenant API

| Method | Path | Description |
|--------|------|-------------|
| GET | `/tenant/{name}/users` | List tenant users |
| POST | `/tenant/{name}` | Create/update tenant |
| PUT | `/tenant/{name}/owners` | Set tenant owners |

## Relevance for Procest

1. **Three-tier identity** -- platform/tenant/case separation provides good isolation
2. **Auto-team-add** -- practical for task delegation without manual team management
3. **Case-scoped roles** -- same user can have different roles in different cases
4. **Owner privileges** -- case owners can override normal authorization rules
5. **Platform updates** -- user ID migration across all cases when IDP changes
