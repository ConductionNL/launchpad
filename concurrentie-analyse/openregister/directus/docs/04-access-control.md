# Directus Access Control

**Source:** https://docs.directus.io/guides/auth/access-control.html

## Architecture

Access control in Directus follows a layered model:
**Users -> Roles -> Policies -> Permissions**

## Users
Items in `directus_users` collection representing a person, application, or service.

## Permissions
Set on a collection + action pair. Available actions: **create, read, update, delete, share**.

Access levels: all access, no access, or custom permissions.

### Custom Permissions

#### Item Permissions (Row-Level Security)
Use filter rules to define which items a user can access.
```json
{ "user_created": { "_eq": "$CURRENT_USER" } }
```

#### Field Permissions
Define which fields are accessible per action (different field sets for read vs. update).

#### Field Validation
Use filter rules to validate field values on create/update.

#### Field Presets
Define default field values on create/update.

## Policies (New in Directus 11)
A group of permissions applied to users or roles. **Additive** — each policy adds permissions, cannot take away.

### Policy Features
- Toggle Data Studio (App) access
- IP allowlist per policy
- Multiple policies per user/role

## Roles
Organizational tool defining a user's position. Can have:
- Any number of policies
- Any number of child roles
- Applied to any number of users

### Administrator Role
Complete unrestricted control. Cannot be limited. At least one admin user required.

### Public Role
Defines permissions for unauthenticated requests. All permissions off by default.

## User Statuses
- **Draft** — Incomplete user
- **Invited** — Pending invite
- **Unverified** — Registered, not verified
- **Active** — Can authenticate
- **Suspended** — Temporarily disabled
- **Archived** — Soft-deleted

## Combining Multiple Policies

### Field Permissions: Additive
Union of all field permissions from active policies.

### Item Rules: Additive
Combined with logical OR operations.

### IP Access: Subtractive
Policies whose IP restrictions aren't met are excluded entirely.

## Best Practices
1. **Restrict junction table permissions** — Unrestricted create on junction tables allows arbitrary relationships
2. **Limit user creation permissions** — Unrestricted create on `directus_users` allows role escalation
3. **Restrict `directus_settings` updates** — Can enable CSS injection via theming

## Comparison Notes (vs OpenRegister)

| Aspect | Directus | OpenRegister |
|--------|----------|-------------|
| Permission Model | Users > Roles > Policies > Permissions | Nextcloud groups + app-level permissions |
| Granularity | Per-collection, per-action, per-field, per-row | Register/schema level |
| Row-Level Security | Built-in with filter rules | Not built-in |
| Field-Level Security | Built-in per action | Not built-in |
| Policy System | Additive multi-policy (v11) | Nextcloud groups |
| Public Access | Configurable public role | Public API endpoints |
| IP Restrictions | Per-policy IP allowlists | Via Nextcloud/reverse proxy |
| Content Sharing | Built-in share action | Not available |
