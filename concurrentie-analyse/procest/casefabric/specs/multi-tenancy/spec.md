---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Multi-Tenancy
category: architecture
---

# Multi-Tenancy

## Overview

CaseFabric has built-in multi-tenancy at every level -- cases, users, teams, and queries are all tenant-scoped. Tenants provide logical isolation within a single engine deployment.

## Implementation Details

### Tenant Model

- Tenants are created by platform owners via `POST /tenant/{name}`
- Each tenant has owners (can manage users) and users (can work on cases)
- Users are registered per tenant with tenant-specific roles
- A platform user can be registered in multiple tenants with different roles
- Default tenant configurable: `cafienne.platform.default-tenant`

### Tenant Isolation

- Every case belongs to exactly one tenant
- Case teams can only include users registered in the case's tenant
- Queries automatically filter by user's tenant memberships
- `CafienneTenantTable` base class adds `tenant` column to all query tables
- Cross-tenant queries possible only for platform owners

### Database Schema

All read-side tables inherit from `CafienneTenantTable`, which adds:
- `tenant` column (indexed) on every table
- Query methods automatically join on tenant membership

Tenant-specific tables:
- `tenant_owners` -- tenant owner user IDs
- `tenant_users` -- registered users with roles per tenant

### Tenant Events

| Event | Description |
|-------|-------------|
| `TenantCreated` | New tenant registered |
| `TenantModified` | Tenant configuration updated |
| `TenantUserAdded` | User registered in tenant |
| `TenantUserChanged` | User roles updated |
| `TenantUserRemoved` | User removed from tenant |

### Tenant Actor

`TenantActor` is an Akka persistent actor managing tenant state:
- Handles tenant commands (create, modify, add/remove users)
- Generates tenant events
- Projected to query tables by `TenantProjectionsWriter`

### Configuration

```hocon
cafienne.platform {
  owners = ["admin"]                    # Platform admin user IDs
  default-tenant = "world"              # Default tenant name
}
```

## Relevance for Procest

Procest operates within Nextcloud's multi-user/group system. CaseFabric's tenant model offers insights:

1. **Tenant as isolation boundary** -- maps to Nextcloud organizations/groups
2. **User-per-tenant roles** -- same user can have different roles in different contexts
3. **Tenant-scoped queries** -- automatic filtering prevents data leakage
4. **Platform vs tenant admin** -- clear separation of administration levels
