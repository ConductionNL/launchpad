---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Permissions and RBAC

## Summary

Baserow has a multi-tier permission system. The open-source version provides workspace-level roles (Admin/Member) and API token permissions. Premium adds row-level features. Enterprise adds full RBAC with custom roles, field-level permissions, and teams.

## Open Source Permissions

### Workspace Roles
- **Admin**: Full workspace control (settings, members, applications)
- **Member**: Can create/edit within workspace, limited admin access

### API Token Permissions
Located at `backend/src/baserow/contrib/database/tokens/`:
- Per-token CRUD permission matrix
- Scoping levels:
  - All tables in workspace
  - All tables in specific database
  - Specific table only
- Operations: Create, Read, Update, Delete (individually togglable)
- Token tracks usage metrics

### Operation-Based Permission Checks
- `OperationType` classes define granular operations
- `PermissionManager` classes check permissions
- Hierarchical: Workspace > Application > Table > View/Field

## Premium Features

### View Ownership
- Personal views (only creator can see)
- Collaborative views (shared with team)

### Row Comments
- Per-row discussion threads
- Comment notification preferences

## Enterprise RBAC

Located at `enterprise/backend/src/baserow_enterprise/role/`

### Custom Roles
From `default_roles.py`:
- **Admin** - Full control
- **Builder** - Can modify structure (tables, fields, views)
- **Editor** - Can edit data
- **Commenter** - Can view and comment
- **Viewer** - Read-only access
- **No Access** - Explicitly denied

### Role Assignment Levels
Roles can be assigned at:
- Workspace level (default for all content)
- Database level (override for specific database)
- Table level (override for specific table)

### Field-Level Permissions
Located at `enterprise/backend/src/baserow_enterprise/field_permissions/`:
- Hide fields from specific roles
- Read-only fields for specific roles
- Per-field, per-role permission matrix

### Teams
Located at `enterprise/backend/src/baserow_enterprise/teams/`:
- Group users into teams
- Assign roles to teams
- Team-based access control

### SSO (Single Sign-On)
Located at `enterprise/backend/src/baserow_enterprise/sso/`:
- SAML support
- OAuth2 support
- Auth provider configuration

### Audit Log
Located at `enterprise/backend/src/baserow_enterprise/audit_log/`:
- Track all user actions
- Filterable audit trail
- Workspace-scoped logging

## Permission Check Flow

```
1. Request comes in
2. PermissionManager checks workspace-level permissions
3. Check role assignments (Enterprise: cascading table > database > workspace)
4. Check field-level permissions (Enterprise)
5. Check view ownership (Premium)
6. Check token permissions (API tokens)
7. Allow/deny
```

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| Base permissions | Workspace Admin/Member | Nextcloud sharing + groups |
| API token perms | Per-table CRUD matrix | Per-register access |
| RBAC roles | 6 roles (enterprise) | Nextcloud groups |
| Field permissions | Per-field, per-role (enterprise) | N/A |
| Teams | Team-based access (enterprise) | Nextcloud groups |
| SSO | SAML + OAuth2 (enterprise) | Nextcloud SSO |
| Audit log | Full action tracking (enterprise) | Nextcloud activity |
| Row-level | Via view filters | N/A |

Baserow's enterprise tier has a very mature RBAC system. OpenRegister inherits Nextcloud's user/group system, which is simpler but well-integrated.
