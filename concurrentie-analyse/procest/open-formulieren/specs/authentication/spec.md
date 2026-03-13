# Authentication

## What Open Forms Does

### Plugin Architecture
Authentication uses a registry-based plugin system (`authentication.registry.Registry`). Each form can have multiple authentication backends configured via `FormAuthenticationBackend` M2M.

### Authentication Plugins

| Plugin | Provides | Protocol |
|--------|----------|----------|
| `digid` | BSN (citizen ID) | SAML 2.0 |
| `eherkenning` | KvK number (business ID) | SAML 2.0 |
| `digid_eherkenning_oidc` | BSN or KvK | OpenID Connect (via OpenZaak/broker) |
| `digid_mock` | BSN | Mock for development |
| `org_oidc` | Employee login | OpenID Connect |
| `yivi_oidc` | Various attributes | OIDC with Yivi (formerly IRMA) |
| `demo` | Configurable | Demo/test plugin |
| `outage` | None | Placeholder showing maintenance message |

### Auth Attributes
- `AuthAttribute` enum: `bsn`, `kvk`, `pseudo`
- `AuthInfo` model stores per-submission authentication details
- `RegistratorInfo` supports "employee filling form on behalf of citizen" flows

### Key Flows
1. Form displays available auth options (filtered by form config)
2. User clicks auth option -> `start_login()` redirects to IdP
3. IdP authenticates user -> redirects back to `handle_return()`
4. Auth data stored in session (`FORM_AUTH_SESSION_KEY`)
5. `AuthInfo` record created linking BSN/KvK to the submission

### Co-sign Authentication
- Separate auth flow for co-signers
- `handle_co_sign()` extracts identifier from session
- `CosignSlice` TypedDict returned with `identifier` and optional `fields`

### Auto-login
- Forms can configure `auto_login_authentication_backend` to automatically start a specific auth flow

### Level of Assurance
- DigiD supports assurance levels (Basis, Midden, Substantieel, Hoog)
- Configurable per form via `DigidOptions`
- eHerkenning similar with its own assurance levels

## Already in Procest

- Nextcloud authentication (username/password, LDAP, SAML)
- User session management

## Not Yet in Procest

- **DigiD integration** -- No BSN-based citizen authentication
- **eHerkenning integration** -- No KvK-based business authentication
- **Plugin-based auth system** -- No pluggable authentication backends per form
- **Per-form auth configuration** -- No ability to require different auth per form
- **Level of Assurance** -- No LoA requirement enforcement
- **Co-sign authentication** -- No second-actor auth flow
- **Auto-login** -- No automatic auth redirect on form load
- **Auth attribute to submission binding** -- No BSN/KvK stored per submission for registration use
- **Yivi/IRMA attribute-based auth** -- No self-sovereign identity integration
