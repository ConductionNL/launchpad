# Authentication & Authorization -- Open Klant Feature Spec

## Overview

Open Klant supports multiple authentication mechanisms for both the admin interface and the API.

## Admin Authentication

### Username/Password
- Standard Django admin login form ("Gebruikersnaam" + "Wachtwoord")
- Password reset link available ("Wachtwoord of gebruikersnaam vergeten?")
- Brute force protection via django-axes (Access attempts, Access logs, Access failures logged)

### OIDC (OpenID Connect)
- "Login with organization account" button on login page
- Configurable OIDC provider settings:
  - Authorization, token, userinfo endpoints
  - Client ID/secret
  - Claim mappings (username, first_name, email)
  - Group sync with pattern matching
  - Superuser group assignment (e.g., "Registreerders" group)
  - Case-sensitive username handling

### Two-Factor Authentication (2FA)
- Configurable via `DISABLE_2FA` env var (disabled in Docker dev)
- TOTP devices (time-based one-time password)
- WebAuthn devices (hardware security keys)
- Static recovery codes via otp_static
- Account security page at `/admin/mfa/`

## API Authentication

### Token Authentication
- Managed via admin: Token authorizations model
- Fields per token:
  - **Identifier**: unique name for the token
  - **Token**: the actual token string
  - **Contact person**: person responsible for the integration
  - **Email**: contact email
  - **Organization**: organization using the token
  - **Application**: application name
  - **Administration**: administrative context
- Usage: `Authorization: Token <token-value>` header
- Configured via setup_configuration YAML (tokenauth_config)

### JWT/ZGW Authentication
- Autorisatiegegevens (JWT secrets) model in admin
- For ZGW (Zaakgericht Werken) service-to-service communication
- Used with Open Notificaties integration

## Session Management
- Session profiles tracked in admin (read-only)

## External Service Configuration

### ZGW Consumers (Services)
- Configure external API connections
- Fields: identifier, label, api_root, api_type, auth_type, client_id, secret
- Auth types: zgw, no_auth
- Used for Open Notificaties, Referentielijsten API

### Certificates
- TLS certificate management via simple_certmanager

### NLX Configuration
- NLX gateway integration settings

### Notifications
- Webhook subscriptions for event-driven integration
- Configurable retry policy (max_retries, backoff, backoff_max)

## Comparison with Pipelinq

### Already in Pipelinq
- Nextcloud provides username/password authentication
- Nextcloud has OIDC/SAML support
- Nextcloud has 2FA via apps (TOTP, WebAuthn)
- OpenRegister ExApp uses Nextcloud session/token auth

### Not yet in Pipelinq
- **Dedicated API token management** with organization/contact metadata per token
- **ZGW JWT authentication** for service-to-service communication
- **Brute force protection** with access attempt logging (django-axes)
- **Certificate management** for TLS client certificates
- **NLX gateway integration**
- **Webhook subscription management** with retry policies
- **Referentielijsten API integration** for standardized reference data
- **Notification service integration** (Open Notificaties)
