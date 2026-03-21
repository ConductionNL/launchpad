---
competitor: bottlecrm
analyzed_date: 2026-03-14
feature: multi-tenancy
---

# Multi-Tenancy and Security

## Overview

BottleCRM implements enterprise-grade multi-tenancy using PostgreSQL Row-Level Security (RLS) combined with application-level organization scoping. This provides defense-in-depth: even if application code has a bug that omits an org filter, the database will still prevent cross-tenant data access.

## Architecture

### Organization Model

```
User (global identity, email-based)
  |
  +-- Profile (per-org membership)
        |-- org (FK to Org)
        |-- role (ADMIN or USER)
        |-- has_sales_access
        |-- has_marketing_access
        |-- is_organization_admin
        |-- date_of_joining
```

- A User can belong to multiple Organizations via Profile
- Each Profile grants org-specific role and permissions
- Org switching is tracked via SecurityAuditLog

### Organization (Org) Entity

| Field | Type | Description |
|-------|------|-------------|
| name | CharField(100) | Organization name |
| api_key | TextField | Auto-generated UUID, unique |
| is_active | BooleanField | Active flag |
| company_name | CharField(255) | Legal name for invoices |
| logo | ImageField | Company logo |
| address_line, city, state, postcode, country | Address | Company address |
| phone, email, website | Contact info | Company contact |
| tax_id | CharField(50) | Tax/VAT/registration number |
| default_currency | CharField(3) | Default currency (USD) |
| default_country | CharField(2) | Default country |

### Row-Level Security (RLS)

Every data table has an `org_id` column. PostgreSQL RLS policies automatically filter all queries by the current organization context.

**How it works:**
1. Middleware sets `app.current_org` PostgreSQL session variable on each request
2. RLS policies on each table filter rows where `org_id = current_setting('app.current_org')`
3. A non-superuser database role (`crm_app`) is used -- superusers bypass RLS

**Management commands:**
```bash
python manage.py manage_rls --status    # Check RLS policy status
python manage.py manage_rls --verify-user  # Verify DB user config
python manage.py manage_rls --test      # Test data isolation
```

### Application-Level Scoping

- `BaseOrgModel`: Base class for all org-scoped models -- requires org, provides OrgScopedManager
- `OrgScopedQuerySet.for_org(org)` / `.for_request(request)`: Convenience filters
- Every model has `org = FK(Org, CASCADE)` -- deletion cascades on org removal

### Authentication

**JWT with session tracking:**
- `SessionToken` model tracks active JWT sessions (JTI, refresh JTI, IP, user agent)
- Token revocation support (`revoke()` method)
- Automatic cleanup of expired tokens

**Magic Links:**
- `MagicLinkToken` for passwordless login
- One-time use, time-limited, IP-tracked

**External Auth:**
- Google OAuth and Microsoft OAuth support (via `external_auth.py`)

### Security Audit Logging

Dedicated `SecurityAuditLog` model (separate from business Activity):

| Event Type | Description |
|-----------|-------------|
| LOGIN_SUCCESS / LOGIN_FAILURE | Auth attempts |
| LOGOUT | Session end |
| ORG_SWITCH | Organization context change |
| TOKEN_REFRESH / TOKEN_REVOKED | JWT lifecycle |
| PERMISSION_DENIED | Authorization failures |
| CROSS_ORG_ATTEMPT | Attempted cross-tenant access |
| API_KEY_USED / API_KEY_INVALID | API key usage |
| MEMBERSHIP_REVOKED | Org membership removal |
| SUSPICIOUS_ACTIVITY | Anomaly detection |

Each event captures: user, org, IP, user agent, request path/method, success flag, metadata (JSON).

### Comment/Attachment Cross-Org Validation

Comments and Attachments validate that their `org` matches the referenced content object's `org` on save. This prevents a comment in org_a from referencing an object in org_b.

## Relevance to Pipelinq

1. **RLS is significantly stronger** than application-only org filtering -- worth studying for Nextcloud multi-org scenarios
2. **Security audit logging** as a separate concern from business activity tracking is a clean pattern
3. **Cross-org validation** on generic relations (comments, attachments) prevents subtle data leaks
4. **User-per-org Profile** model (vs global user) is a well-proven pattern for role differentiation across organizations
