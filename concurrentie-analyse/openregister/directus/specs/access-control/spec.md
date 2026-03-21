---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Access Control

## Overview

Directus implements a sophisticated Attribute-Based Access Control (ABAC) system with hierarchical roles, reusable policies, and filter-based permission rules. Permissions are enforced at the service layer, with AST-level query rewriting for read operations.

## Architecture

The access control system consists of four layers:

1. **Users** - Individual accounts with authentication credentials
2. **Roles** - Hierarchical groupings (roles can have parent roles, inheriting permissions)
3. **Policies** - Reusable permission bundles attached to roles or individual users
4. **Permissions** - Granular per-collection, per-action rules within a policy

```
User --> Role --> Policy --> Permission
  |                           |
  +-----> Policy ------------> Permission (direct user-policy attachment)
```

## Roles

- **Hierarchical**: Roles can have a parent role, creating a tree
- **Admin flag**: Roles with admin access bypass all permission checks
- **App access**: Controls whether the role can access the admin UI
- The role tree is traversed to aggregate all applicable policies

## Policies

Policies are the bridge between roles/users and permissions:

- **IP Access**: Restrict policy to specific IP addresses/ranges (CIDR notation)
- **Admin Access**: Flag that grants full admin permissions
- **App Access**: Flag that grants admin UI access
- **Enforcement Type**: Whether the policy is `block` (deny list) or `allow` (allow list) semantics

Policies can be attached to:
- A role (applies to all users in that role)
- An individual user (user-specific overrides)

## Permissions

Each permission record defines access for one action on one collection:

```typescript
type Permission = {
  policy: string;           // Which policy this belongs to
  collection: string;       // Target collection
  action: 'create' | 'read' | 'update' | 'delete' | 'share';
  permissions: Filter;      // Row-level filter (which items)
  validation: Filter;       // Payload validation rules
  presets: Record<string, any>; // Default values for create/update
  fields: string[];         // Field-level access (which columns)
};
```

### Row-Level Security

The `permissions` filter defines which rows the user can access. This filter is applied as a WHERE clause on read queries and validated before mutations:

```json
{
  "permissions": {
    "_and": [
      { "status": { "_eq": "published" } },
      { "department": { "_eq": "$CURRENT_USER.department" } }
    ]
  }
}
```

Dynamic variables available in filters:
- `$CURRENT_USER` - Current user's fields
- `$CURRENT_ROLE` - Current role ID
- `$CURRENT_ROLES` - All roles in the hierarchy
- `$CURRENT_POLICIES` - All applicable policies
- `$NOW` - Current timestamp

### Field-Level Security

The `fields` array specifies which fields can be accessed:
- `["*"]` - All fields
- `["title", "body", "status"]` - Specific fields only
- `null` - No field access (effectively denying the action)

### Validation Rules

The `validation` filter validates mutation payloads before they reach the database:

```json
{
  "validation": {
    "title": { "_regex": "^[A-Z]" },
    "status": { "_in": ["draft", "review"] }
  }
}
```

### Presets (Default Values)

Presets are automatically merged into create/update payloads:

```json
{
  "presets": {
    "status": "draft",
    "department": "$CURRENT_USER.department"
  }
}
```

## Permission Resolution

1. **Accountability** is determined from the request (JWT token, session, API key, or public)
2. **Role tree** is traversed to collect all roles
3. **Policies** are fetched for all roles + direct user policies
4. **IP access** is checked against the request IP
5. **Permissions** are aggregated across all applicable policies
6. For **reads**: permissions are injected into the AST as additional WHERE conditions
7. For **mutations**: item access is validated before executing, payload is validated, presets are applied

## Public Access

Directus supports a special "public" role for unauthenticated access. Policies attached to the public role define what anonymous users can see/do. This is distinct from having no role at all (which results in no access).

## Share-Based Access

The `share` permission action controls whether users can create share links for items. Share recipients get a scoped authentication token with read-only access to the shared item and its related data.

## Relevance to OpenRegister

OpenRegister's permission model is currently simpler:
- Leverages Nextcloud's user/group system
- Row-level access based on organization/ownership
- No filter-based permission rules
- No field-level access control
- No presets/validation in permissions

Directus's ABAC model is significantly more powerful and could inspire:
- Filter-based row-level security for OpenRegister
- Field-level access control per schema
- Preset/default value injection based on user context
- Reusable policy bundles
