# Open Klant -- Authentication & Authorization

## Token-Based Authentication

All API endpoints use custom token-based authentication (NOT Django's built-in Token model).

### Token Format
- 40-character string
- Sent as: `Authorization: Token <token>`

### TokenAuth Model Fields

| Field | Type | Description |
|-------|------|-------------|
| token | CharField(40) | The token string |
| identifier | SlugField | Machine-readable slug for identification |
| contact_person | CharField | Contact person name |
| email | EmailField | Contact email |
| organization | CharField | Organisation name |
| application | CharField | Application name |
| administration | CharField | Administration context |

### Authorization Model

- **No RBAC**: Any valid token grants full read/write access to ALL endpoints
- **No scoping**: Tokens cannot be restricted to specific resources or operations
- **No user context**: Tokens are application-level, not user-level

### Token Management

- Tokens can be created via:
  - Django admin interface
  - Management commands (CLI)
  - Environment variables (since v2.11.1)
  - Setup configuration (since v2.5.0)

### Structured Audit Logging

Every API operation logs:
- The token's `identifier` field
- The token's `application` field
- The entity UUID affected
- Related entity UUIDs
- Operation type (create/update/delete)

This is how Open Klant tracks "who did what" even without user-level authentication.

## Admin Authentication

### Django Admin
- Username/password login
- Two-factor authentication (2FA) enabled by default since v2.1.0
- WebAuthn support

### OIDC (Single Sign-On)
- Mozilla Django OIDC integration
- Keycloak support
- Since v2.12.0: Restructured into separate `OIDCProvider` and `OIDCClient` models

## Comparison with Pipelinq

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| API auth | Custom 40-char token | Nextcloud session/app passwords |
| Authorization | No RBAC (full access per token) | Nextcloud sharing + OpenRegister ACL |
| User context | Application-level tokens | User-level Nextcloud sessions |
| Admin SSO | OIDC/Keycloak | Nextcloud OIDC |
| 2FA | Django-based | Nextcloud-based |
| Audit trail | Structured logs with token ID | Nextcloud audit log |

**Already in Pipelinq**: User-level authentication via Nextcloud (more granular than Open Klant), RBAC via Nextcloud groups

**Not yet in Pipelinq**: Application-level API tokens for service-to-service communication, structured operation audit logging with caller identification
