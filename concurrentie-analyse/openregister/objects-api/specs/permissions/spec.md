# Objects API — Permission Model

## Overview

The Objects API has a **token-based permission model** with per-objecttype granularity.

## Components

### 1. Token Authorization
Each API consumer gets a token with metadata:
- **Identifier** — Unique name (e.g., "token-1")
- **Contact person** — Responsible person
- **Email** — Contact email
- **Organization** — Organization name
- **Application** — Application name
- **Administration** — Administration identifier
- **Is superuser** — Bypasses ALL permission checks (full access to all types)
- **Token** — Auto-generated hex string (displayed read-only after creation)

### 2. Permission (per Object Type)
Each token has zero or more permissions, each granting access to one object type:
- **Token auth** — Which token this permission belongs to
- **Object type** — Which object type this grants access to
- **Mode** — `Read-only` or `Read and write`
- **Use field-based authorization** — Optional per-field access control

### Permission Matrix

| Scenario | List | Read | Create | Update | Delete |
|----------|------|------|--------|--------|--------|
| No token | 401 | 401 | 401 | 401 | 401 |
| Token, no permission for type | Empty list | 403 | 403 | 403 | 403 |
| Token, read-only permission | Filtered | OK | 403 | 403 | 403 |
| Token, read-write permission | Filtered | OK | OK | OK | OK |
| Superuser token | All | OK | OK | OK | OK |

### Behavior Observed

1. **List endpoint with limited token** — Returns `count: 0` (not 403) — silently filters out inaccessible objects
2. **Create with wrong type** — Returns 403 "You do not have permission to perform this action"
3. **No auth** — Returns 401 "Authentication credentials were not provided"
4. **Superuser token** — Has access to ALL object types without explicit permissions

### Field-Based Authorization
When "Use field-based authorization" is enabled on a permission:
- Individual JSON fields within the object data can be restricted
- Specific fields can be made read-only while others are read-write
- This provides column-level access control within a JSON schema

## Comparison with OpenRegister

| Feature | Maykin Objects API | OpenRegister |
|---------|-------------------|-------------|
| Auth mechanism | API tokens (hex strings) | Nextcloud user sessions + API tokens |
| Permission granularity | Per object type | Per register (broader scope) |
| Permission modes | Read-only, Read-write | Via Nextcloud groups/shares |
| Field-level auth | Yes (optional per permission) | No |
| Superuser concept | Explicit "is_superuser" flag | Nextcloud admin role |
| Multi-tenancy | Via token organization field | Via Nextcloud user/group system |
| OIDC support | Yes (admin login) | Via Nextcloud OIDC |
