---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Authentication & RBAC - KISS

## Purpose
KISS uses OpenID Connect (OIDC) for authentication and a 3-role permission system for authorization. The BFF acts as an OIDC confidential client, handling token exchange and session management. Authorization is enforced both at the proxy level (YARP routes) and at the controller level (RequirePermissionAttribute).

## Architecture Overview
- **Frontend**: `src/features/login/` — OIDC redirect flow, token refresh, session management
- **BFF**: ASP.NET Core OIDC middleware, custom PermissionAuthorizationPolicyProvider, YARP reverse proxy with per-route authorization policies
- **Identity Provider**: Any OIDC-compatible IdP (Azure AD/EntraID, Keycloak, etc.)
- **Session**: Server-side session with encrypted cookie; OIDC tokens stored in session, not exposed to frontend

## Data Model

### Roles
```
Klantcontactmedewerker (KCM)
  - Default role for all authenticated users
  - Can create/view contactmomenten and contactverzoeken
  - Can search knowledge base, BRP, KvK, cases
  - Cannot access admin section

Redacteur (Editor)
  - All KCM permissions plus:
  - Can manage werkberichten (news/work instructions)
  - Can manage VACs (Q&A pairs)
  - Can manage kennisartikelen

Beheerder (Admin)
  - All Redacteur permissions plus:
  - Can manage kanalen (channels)
  - Can manage skills
  - Can manage links
  - Can manage gespreksresultaten (conversation results)
  - Can manage contactverzoek forms (VragenSets)
  - Can access management information API
```

### Permission Enum
```csharp
enum Policies {
    RedactiePolicy,      // Editor-level access
    BeheerPolicy         // Admin-level access
}
```

### OIDC Configuration
```
OIDC_AUTHORITY          — IdP discovery URL
OIDC_CLIENT_ID          — Confidential client ID
OIDC_CLIENT_SECRET      — Client secret
OIDC_MEDEWERKER_IDENTIFICATIE_CLAIM   — Claim for employee ID
OIDC_MEDEWERKER_IDENTIFICATIE_TRUNCATE — Truncate claim value
```

## Business Logic

### Authentication Flow
1. User navigates to KISS frontend
2. Frontend checks for valid session cookie
3. If no session: redirect to BFF `/login` endpoint
4. BFF initiates OIDC Authorization Code Flow with PKCE
5. User authenticates at the IdP (Azure AD, Keycloak, etc.)
6. IdP redirects back to BFF with authorization code
7. BFF exchanges code for tokens (access token, ID token, refresh token)
8. BFF stores tokens in server-side session, sets encrypted session cookie
9. Frontend receives session cookie, can now make API calls through BFF
10. BFF attaches appropriate API tokens when proxying requests to external APIs

### Token Refresh
The BFF handles token refresh transparently. When the access token expires, the BFF uses the refresh token to obtain new tokens. If the refresh token is also expired, the user is redirected to re-authenticate.

### Authorization Enforcement

#### Proxy-Level (YARP)
YARP reverse proxy routes are configured with authorization policies via `PermissionAuthorizationPolicyProvider`. Each proxied API route specifies which role is required. Unauthorized requests receive 403 Forbidden.

#### Controller-Level
Controllers use `[RequirePermission(Policies.BeheerPolicy)]` attribute to enforce access. This is used for BFF-internal endpoints (skills, links, gespreksresultaten).

### Application vs User Authorization
KISS supports two authorization modes for external API calls:

1. **Application-level** (default): All API calls use a shared application token. The external API sees "KISS" as the caller, not the individual user. Simpler setup, less granular audit.

2. **User-level** (optional, for e-Suite): API calls include a JWT with the user's identifier (from OIDC claim). The external API can enforce per-user permissions. Required for e-Suite integration where the zaaksysteem needs to know which medewerker is making the request.

### Employee Identification
The `OIDC_MEDEWERKER_IDENTIFICATIE_CLAIM` setting maps an OIDC claim to the employee's identifier used in the Objects API (medewerker objecten). This links the authenticated user to their employee record for features like contactverzoek assignment and management information.

## Requirements (as observed)
- Must support OIDC Authorization Code Flow with PKCE
- Must support any OIDC-compatible identity provider
- Must enforce 3-role permission model (KCM, Redacteur, Beheerder)
- Must handle token refresh transparently
- Must support both application-level and user-level API authorization
- Must map OIDC claims to employee identifiers
- Must protect admin routes at both proxy and controller level

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Authentication | OIDC (external IdP) | Nextcloud built-in (LDAP/SAML/OIDC) |
| Authorization | 3 custom roles | Nextcloud groups + share permissions |
| Session management | Server-side encrypted cookie | Nextcloud session |
| Token handling | BFF manages tokens | Nextcloud manages tokens |
| Per-user API auth | Optional (e-Suite) | Always per-user via Nextcloud |
| Admin access control | Permission attribute | Nextcloud admin/group admin |
| Employee mapping | OIDC claim -> Objects API | Nextcloud user -> contact |
| Multi-tenancy | Single org (RSIN) | Nextcloud multi-user |

**Gap for Pipelinq**: Pipelinq benefits from Nextcloud's mature auth infrastructure (LDAP, SAML, OIDC, 2FA) without needing to implement its own. The 3-role model is simpler than Nextcloud's flexible group/share system but sufficient for KISS's use case.
