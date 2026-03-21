---
status: draft
source: competitive-analysis
competitor: xxllnc-zaken
analyzed_date: 2026-03-14
---
# Authentication & Authorization -- xxllnc Zaken

## Purpose

Multi-layer authentication supporting local accounts, SAML2 SSO (via Keycloak), and OAuth2 (Auth0). Role-based access control at case, department, and system levels.

## Architecture Overview

- **HTTP Service:** `zsnl_auth_http` (path `/api/v2/auth/`)
- **Domain:** `zsnl_domains/auth/`
- **Perl Auth:** `Zaaksysteem::Auth` (SAML2, sessions)
- **External:** Keycloak (optional, for SAML2/OAuth2 IdP)
- **Session Storage:** Redis

## Data Model

### Auth Entities

- **User** -- authentication user identity
- **Subject** -- authorization subject (linked to person/employee/org)
- **Role** -- authorization role with permissions
- **Event** -- auth event tracking
- **SubjectLoginHistory** -- login audit trail
- **TokenResponse** -- OAuth2 token response data

### Authorization Levels

**System level:** `read`, `readwrite`, `admin`

**Case level (per case):** `search`, `read`, `write`, `manage`

Case-level authorization is stored as `entity_meta_authorizations` on each Case entity, computed per-user.

### Account Types

| Username | Role | Description |
|----------|------|-------------|
| admin | administrator | Full system access |
| beheerder | administrator | Secondary admin |
| gebruiker | normal user | Limited rights |

## Business Logic

### Authentication Flows

```mermaid
flowchart TD
    Login[Login Request] --> Type{Auth Type?}
    Type --> |Local| LocalAuth[Username/Password]
    Type --> |SAML2| SAMLRedirect[SAML2 IdP Redirect]
    Type --> |OAuth2/Auth0| Auth0Flow[Authorization Code Flow]

    LocalAuth --> Session[Create Redis Session]
    SAMLRedirect --> SAMLCallback[SAML Response Validation]
    SAMLCallback --> Session
    Auth0Flow --> Auth0Callback[/api/v2/auth/auth0/authorization_code_flow/callback]
    Auth0Callback --> Session

    Session --> SetCookie[Set Session Cookie]
```

### SAML2 Integration

- Full SAML2 Service Provider implementation in Perl
- Support for multiple Identity Providers
- Metadata URL configuration
- SP/IdP certificate management (PKCS#12 import)
- Development testing via bundled Keycloak

### OAuth2 Integration

- Auth0 authorization code flow
- Email OAuth2 flow for admin integrations (start/finish)

### Session Management

- Redis-based session storage
- XSRF token validation (configurable, disabled in dev)
- Client certificate validation (configurable)

### Case-Level Authorization

Each case carries `entity_meta_authorizations` indicating what the current user can do:
- `search` -- can find the case in search results
- `read` -- can view case details
- `write` -- can modify the case
- `manage` -- full control including deletion

Authorization is computed from:
- User's system role
- Department membership
- Case type permissions
- Direct case assignment (coordinator/assignee)

## Requirements (as observed)

1. Multiple auth methods: local, SAML2, OAuth2/Auth0
2. Redis-based session management
3. Case-level granular authorization (search/read/write/manage)
4. Department-based access control
5. SAML2 SP with configurable IdP metadata
6. OAuth2 authorization code flow
7. Login history audit trail
8. BSN retrieval logging (privacy compliance)

## Comparison Notes

**vs Procest:**
- xxllnc implements its own auth stack; Procest inherits Nextcloud's auth (LDAP, SAML, OAuth2)
- Case-level authorization in xxllnc is fine-grained; Nextcloud provides user/group-level permissions
- The BSN retrieval logging shows privacy compliance awareness (logging who accessed citizen data)
- Procest can leverage Nextcloud's existing enterprise auth integrations without custom implementation
