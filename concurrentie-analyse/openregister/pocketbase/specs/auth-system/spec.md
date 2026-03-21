---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# Authentication System

## Summary
PocketBase has a comprehensive built-in auth system supporting multiple authentication methods. Any collection of type "Auth" automatically gets auth endpoints, allowing multiple user types (e.g., users, admins, staff) with different auth configurations.

## Key Features
- **Email/Password** authentication with configurable identity fields
- **OAuth2** with 20+ providers (Google, GitHub, Apple, Microsoft, etc.)
- **OTP** (One-Time Password) via email
- **MFA** (Multi-Factor Authentication) with configurable duration
- **Email verification** with customizable templates
- **Password reset** flow
- **Email change** confirmation
- **Impersonation** (superuser can impersonate any auth record)
- **Auth alerts** on login from new locations
- **Rate limiting** per auth endpoint
- JWT tokens with configurable durations (auth, file, verification, password reset)

## Architecture
- `apis/record_auth.go` - Route registration for all auth endpoints
- `apis/record_auth_with_password.go` - Password auth with identity field lookup
- `apis/record_auth_with_oauth2.go` - OAuth2 flow handler
- `core/collection_model_auth_options.go` - Auth collection configuration
- `core/record_model_auth.go` - Auth-specific record methods
- `core/record_tokens.go` - JWT token generation

## Relevance to OpenRegister
OpenRegister delegates auth to Nextcloud. PocketBase's per-collection auth rules and OAuth2 support offer more granular control, but OpenRegister benefits from Nextcloud's enterprise SSO and LDAP integration.
