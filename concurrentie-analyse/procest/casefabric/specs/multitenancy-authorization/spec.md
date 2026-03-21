---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Multitenancy and Authorization
category: security
relevance: medium
---

# Multitenancy and Authorization

## Summary

CaseFabric implements a comprehensive multi-level authorization model with built-in multitenancy. Authorization is enforced at platform, tenant, case, and CMMN language levels, with an ownership concept at each level.

## Authorization Levels

### 1. Platform Level
- **Platform owners:** configured in `local.conf` (list of user IDs)
- Privileges: create/disable/enable tenants, manage other platform owners
- Platform owners have NO rights inside any tenant (separation of concerns)
- Bootstrap mechanism: auto-create default tenant on engine startup

### 2. Tenant Level
- **Tenant owners:** manage users and roles within their tenant
- **Tenant users:** work on cases within the tenant
- Operations on users: create, add role, remove role, enable, disable
- Users cannot be deleted, only disabled
- Same user can have different roles in different tenants
- Tenant user properties: user ID, roles (set), name (optional), email (optional)

### 3. Case Level (Per-Instance)
- Each case has its own team
- Case team members assigned from tenant users
- Can include people from outside the organization
- Case owners can override any operation on tasks

CMMN-defined authorizations:
- Human Task execution limited to specific performer role
- User Event raising limited to authorized roles
- Discretionary Item planning limited to authorized roles

### 4. CaseFabric-Specific Case Authorizations
Beyond CMMN:
- Consent groups and consent-based access
- Granular case team management
- Cross-organization team composition

## Multitenancy Architecture

- Strict data isolation between tenants
- Resource sharing at infrastructure level
- Users belong to one or more tenants
- Cross-tenant queries possible (e.g., GetMyCases across all tenants)
- Tenant context required for case operations (or default-tenant used)

## Authentication

- Delegated to external Identity Provider via OpenID Connect
- JWT tokens with mandatory `sub` claim
- Multiple OIDC providers configurable simultaneously
- Token -> Platform User -> Tenant User(s) mapping
- CaseFabric stores no passwords or authentication state

## Relevance to Procest

**Medium relevance.** Procest operates within Nextcloud's existing auth and multitenancy model, which handles most of these concerns differently.

### What to learn:
- Per-case team composition is valuable for government case handling
- Role-based task authorization (performer roles) is important
- Separation of platform/tenant/case authorization levels is clean
- Cross-organization team membership is relevant for inter-agency cooperation

### What differs:
- Nextcloud handles authentication natively (no external IdP needed)
- Nextcloud groups/circles provide tenant-like isolation
- Nextcloud shares/permissions model differs from CaseFabric's approach
- Procest can leverage Nextcloud's existing user management
