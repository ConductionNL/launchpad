# Authentication & Session Management

## Feature Summary

Django session-based authentication with CSRF protection, OIDC support, 2FA enforcement, and brute-force protection. The frontend uses cookie-based sessions rather than JWT tokens.

## How It Works in Open Beheer

### Login Flow
1. Frontend calls `GET /api/v1/auth/ensure-csrf-token/` to get a CSRF cookie
2. User submits username + password
3. Frontend calls `POST /api/v1/auth/login/` with credentials + CSRF token
4. Backend authenticates via Django's auth system, creates session
5. Session cookie (`openbeheer_sessionid`) returned
6. All subsequent requests include session cookie + CSRF token

### OIDC Flow
- `GET /api/v1/oidc-info/` returns whether OIDC is enabled + login URL
- Frontend can redirect to OIDC provider via `mozilla-django-oidc`
- OIDC config managed via `mozilla-django-oidc-db` (OIDCClient model)
- OIDC users bypass 2FA (configured via `MAYKIN_2FA_ALLOW_MFA_BYPASS_BACKENDS`)

### CSRF Protection
- Every mutating request (POST/PUT/PATCH/DELETE) requires X-CSRFToken header
- `ensureCSRFToken()` called before each mutation to refresh the cookie
- CSRF cookie settings: SameSite=Strict, Secure=False (dev), configurable for production

### 2FA
- Django admin requires 2FA (TOTP or WebAuthn/hardware tokens)
- API endpoints do NOT require 2FA (session auth only)
- `maykin-2fa` library with `OTPMiddleware`

### Brute-Force Protection
- `django-axes`: 10 failed attempts before lockout
- 1 hour cooloff period
- Rate-based on IP + User-Agent + username combination
- Redis-backed cache

### Session Management
- Session engine: Redis-backed cache
- Session cookie: HttpOnly, configurable Secure/SameSite
- `whoAmI` endpoint returns current user info (username, first_name, last_name, email)

## Technical Implementation

### Backend
- `LoginView`: Validates credentials via DRF serializer, calls `django.contrib.auth.login()`
- `LogoutView`: Calls `django.contrib.auth.logout()`
- `EnsureCSRFTokenView`: Returns 204 with CSRF cookie set
- `WhoAmIView`: Returns current user from `request.user`
- `AnonCSRFSessionAuthentication`: Custom auth class for login endpoint (allows unauthenticated with CSRF)

### Frontend
- `login()`, `logout()`, `whoAmI()` API functions
- `loginRequired.loader.ts`: Redirect to login if not authenticated
- `getOIDCInfo()`: Check OIDC availability
- `LoginPage`: Username/password form with OIDC button if enabled
- CSRF token from cookie via `getCookie("csrftoken")`

## Already in OpenRegister

- **Nextcloud authentication**: Handles all auth (LDAP, SAML, OIDC) at the platform level
- **Session management**: Nextcloud manages sessions
- **CSRF protection**: Nextcloud provides CSRF tokens for all apps
- **Rate limiting**: OpenRegister has own APCu-based rate limiting
- **User info**: Available via Nextcloud's OCS API

## Not Yet in OpenRegister

- **Standalone 2FA enforcement**: Nextcloud has its own 2FA, but OpenRegister doesn't add an app-level 2FA layer
- **Brute-force protection at app level**: OpenRegister relies on Nextcloud's built-in brute force protection (security:bruteforce) rather than having its own
- **OIDC configuration UI**: Open Beheer exposes OIDC info via API; OpenRegister relies on Nextcloud's OIDC app
