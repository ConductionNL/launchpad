---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Token Authentication -- Open Klant

## Purpose

Open Klant uses a custom token-based authentication system. Every API request must include a valid token. There is NO role-based access control -- any valid token grants full read/write access to all endpoints.

- **Product**: Open Klant
- **Category**: Authentication & Authorization
- **Relevance to Pipelinq**: Shows a simpler auth model than Pipelinq's Nextcloud-based auth.

## Data Model

### TokenAuth

| Field | Type | Description |
|-------|------|-------------|
| identifier | SlugField (unique) | Human-friendly label |
| token | CharField(40, unique) | The actual token value (auto-generated if empty) |
| contact_person | CharField(200) | Name of person who can access the API |
| email | EmailField | Contact email |
| organization | CharField(200) | Organisation name |
| application | CharField(200) | Application name |
| administration | CharField(200) | Administration name |
| last_modified | DateTimeField (auto) | |
| created | DateTimeField (auto) | |

### Authentication Flow

1. Client sends `Authorization: Token <token>` header
2. `TokenAuthentication.authenticate_credentials()` looks up `TokenAuth.objects.get(token=key)`
3. Returns `(None, token_auth_instance)` -- note: user is None, auth is the TokenAuth object
4. `TokenPermissions.has_permission()` checks `request.auth is not None`

### Key Characteristics

- **No RBAC**: Any valid token has full access to everything
- **No user association**: Tokens are not linked to Django users
- **Auto-generation**: Token value auto-generated if not provided
- **Audit trail**: Token identifier and application are logged with every operation
- **Setup configuration**: Tokens can be provisioned via `TokenAuthConfigurationStep` in YAML config files

## Pipelinq Comparison

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| Auth method | Custom token (no user) | Nextcloud session/app password |
| RBAC | None -- full access for any token | Nextcloud groups + share permissions |
| User association | No user link | Full Nextcloud user integration |
| Token provisioning | Admin UI + setup configuration YAML | Nextcloud app passwords |
| Audit | Token identifier logged per operation | Nextcloud audit log |
| OIDC support | Admin login only (Keycloak) | Via Nextcloud OIDC |

**Already in Pipelinq**: Full user authentication via Nextcloud (more sophisticated)
**Not yet in Pipelinq**: Machine-to-machine token auth for external system integrations
