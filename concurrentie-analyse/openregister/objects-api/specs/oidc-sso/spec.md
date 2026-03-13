---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# OpenID Connect SSO — Objects API (Documentation View)

## Purpose
Single Sign On for the admin interface using OpenID Connect, enabling login with organizational identity providers like Keycloak.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/installation/oidc.html

## Flow
1. User clicks "Login with organization account" in admin
2. Redirected to OIDC provider (e.g., Keycloak)
3. User authenticates with username/password + optional MFA
4. Redirected back to Objects API (account created if new)
5. Admin assigns permissions for first-time users

## Configuration
- Client ID and Secret from OIDC provider
- Discovery endpoint: `https://login.municipality.nl/auth/realms/{realm}/`
- Sign algorithm: RS256
- Redirect URI: `https://objects.municipality.nl/oidc/callback`

## Key Behavior
- New accounts created automatically but without admin access
- Permissions must be configured by an admin
- 2FA can be disabled via `DISABLE_2FA` env var

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| SSO | OIDC for admin interface | Nextcloud SSO (SAML/OIDC via NC) |
| Identity provider | Keycloak, etc. | Nextcloud identity |
| Auto-provisioning | Yes (without permissions) | Via Nextcloud |
| MFA | Via OIDC provider + optional 2FA | Via Nextcloud |

**Already in OpenRegister**: SSO via Nextcloud's own OIDC/SAML support
**Not yet in OpenRegister**: N/A (handled by Nextcloud platform)
