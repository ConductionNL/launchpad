---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Authentication

## Purpose

NocoBase provides a pluggable authentication system through `plugin-auth` that supports multiple authentication providers. The default provider is password-based (username/email + password), with SMS verification available as an additional plugin.

## Architecture Overview

```
Sign-in Request
    |
    v
AuthManager (selects authenticator by name)
    |
    v
Authenticator (e.g., BasicAuth, SMSAuth)
    |
    v
Token Generation (JWT)
    |
    v
Token Storage + Blacklist
```

## Data Model

### Authenticators Collection
- `name` - Unique identifier (e.g., "basic")
- `authType` - Authentication type (e.g., "Password")
- `title` - Display name
- `description` - Description shown on sign-in page
- `enabled` - Whether active
- `options` - Type-specific configuration (JSON)

### Token Management
- JWT-based authentication tokens
- Token controller manages issuance and validation
- Token blacklist for revocation (logout, password change)
- Storer interface for token persistence

## Business Logic

### Built-in Authenticators

1. **Password (BasicAuth)**
   - Username or email + password
   - Password hashing (bcrypt)
   - Sign-up support (optional)
   - Password change functionality

2. **SMS Verification** (`plugin-auth-sms`)
   - Phone number + SMS verification code
   - Requires verification plugin configuration
   - Integrates with SMS providers

### Authentication Flow
1. Client sends credentials to `/api/auth:signIn`
2. AuthManager looks up authenticator by `X-Authenticator` header
3. Authenticator validates credentials
4. JWT token generated and returned
5. Client includes `Authorization: Bearer <token>` in subsequent requests
6. `auth:check` middleware validates token on each request

### API Keys
`plugin-api-keys` provides long-lived API keys for programmatic access:
- Generated per user
- Role-scoped
- Revocable

### Verification Plugin
`plugin-verification` adds multi-factor support:
- SMS verification codes
- Configurable providers
- Used by auth-sms and custom workflows

## Requirements

### Functional
- Multiple authentication providers
- Username/email + password authentication
- SMS-based authentication
- API key generation for programmatic access
- Sign-up and password reset flows
- Token revocation (logout)

### Non-functional
- Secure password storage (bcrypt)
- JWT token expiration
- Token blacklist for immediate revocation
- Multiple authenticators active simultaneously

## UI Reference

See screenshot: `14-authentication.png`

## Comparison Notes

### vs Nextcloud Authentication
- Nextcloud supports LDAP, SAML, OAuth2, WebAuthn; NocoBase has password + SMS
- Nextcloud has two-factor authentication apps; NocoBase has basic verification
- Nextcloud has brute-force protection built-in; NocoBase relies on middleware
- NocoBase manages its own user database; Nextcloud can federate with external directories
- Both support API tokens for programmatic access
- Nextcloud's auth ecosystem is vastly more mature and comprehensive
