---
status: competitor-analysis
source: https://github.com/maykinmedia/open-vtb
competitor: Maykin Media / Open VTB
date: 2026-03-13
---

# OIDC Authentication

## Purpose

Open VTB uses OpenID Connect for both admin and API authentication, with Token Authentication as fallback for API clients. The OIDC configuration is stored in the database via mozilla-django-oidc-db, supporting dynamic provider configuration without redeployment.

## Architecture

- **Admin login**: mozilla-django-oidc with 2FA (maykin-2fa with WebAuthn support)
- **API authentication**: Custom OIDC DRF middleware + Token Authentication
- **OIDC backend**: Custom `OIDCAuthenticationBackend` in `openvtb.utils.oidc_auth`
- **Setup configuration**: `AdminOIDCConfigurationStep` for automated OIDC setup
- **All API endpoints require authentication** (`IsAuthenticated` permission class)

### Authentication Classes (priority order)
1. `OIDCAuthentication` (OIDC bearer tokens)
2. `TokenAuthentication` (DRF token auth)

### Admin Security
- 2FA via maykin-2fa
- WebAuthn hardware token support
- Brute-force protection
- OIDC login failure handling

## Pipelinq Comparison

### Already in Pipelinq
- Nextcloud authentication (session-based)
- API token support

### Not yet in Pipelinq
- **OIDC authentication** for API access
- **Database-configurable OIDC providers** (no redeployment needed)
- **WebAuthn/hardware token** support
- **Client credentials flow** for machine-to-machine auth
