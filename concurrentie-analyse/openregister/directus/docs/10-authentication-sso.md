# Directus Authentication & SSO

**Source:** https://docs.directus.io/guides/auth/sso/, https://docs.directus.io/configuration/auth-sso.html

## Authentication Methods

Directus supports multiple authentication methods:
1. **Local email/password** — Default, built-in
2. **OAuth 2.0** — External provider authentication
3. **OpenID Connect** — Standard identity layer
4. **LDAP** — Directory-based authentication
5. **SAML** — Enterprise SSO standard

## Session-Based Authentication
Since Directus 10.10.0, `cookie` mode replaced by `session` mode. API still supports cookie mode for backward compatibility, but Data Studio uses session mode only.

## SSO Configuration

### Environment Variables
```
AUTH_PROVIDERS=google,github,ldap
AUTH_DISABLE_DEFAULT=false
AUTH_ALLOWED_PUBLIC_URLS=https://app1.example.com,https://app2.example.com
```

### Per-Provider Configuration

#### OAuth 2.0
```
AUTH_<PROVIDER>_DRIVER=oauth2
AUTH_<PROVIDER>_CLIENT_ID=
AUTH_<PROVIDER>_CLIENT_SECRET=
AUTH_<PROVIDER>_AUTHORIZE_URL=
AUTH_<PROVIDER>_ACCESS_URL=
AUTH_<PROVIDER>_PROFILE_URL=
AUTH_<PROVIDER>_SCOPE=
AUTH_<PROVIDER>_IDENTIFIER_KEY=
AUTH_<PROVIDER>_ALLOW_PUBLIC_REGISTRATION=
AUTH_<PROVIDER>_DEFAULT_ROLE_ID=
AUTH_<PROVIDER>_ICON=
AUTH_<PROVIDER>_LABEL=
```

#### OpenID Connect
```
AUTH_<PROVIDER>_DRIVER=openid
AUTH_<PROVIDER>_CLIENT_ID=
AUTH_<PROVIDER>_CLIENT_SECRET=
AUTH_<PROVIDER>_ISSUER_URL=
AUTH_<PROVIDER>_SCOPE=openid profile email
AUTH_<PROVIDER>_IDENTIFIER_KEY=
AUTH_<PROVIDER>_ALLOW_PUBLIC_REGISTRATION=
AUTH_<PROVIDER>_DEFAULT_ROLE_ID=
```

#### LDAP
```
AUTH_<PROVIDER>_DRIVER=ldap
AUTH_<PROVIDER>_CLIENT_URL=ldap://ldap.example.com
AUTH_<PROVIDER>_BIND_DN=cn=admin,dc=example,dc=com
AUTH_<PROVIDER>_BIND_PASSWORD=
AUTH_<PROVIDER>_USER_DN=ou=users,dc=example,dc=com
AUTH_<PROVIDER>_USER_SCOPE=one
AUTH_<PROVIDER>_USER_ATTRIBUTE=uid
AUTH_<PROVIDER>_MAIL_ATTRIBUTE=mail
AUTH_<PROVIDER>_GROUP_DN=
AUTH_<PROVIDER>_GROUP_ATTRIBUTE=
AUTH_<PROVIDER>_GROUP_SCOPE=
```

#### SAML
```
AUTH_<PROVIDER>_DRIVER=saml
AUTH_<PROVIDER>_SP_METADATA=
AUTH_<PROVIDER>_IDP_METADATA=
```

### Common Options Per Provider
- `ALLOW_PUBLIC_REGISTRATION` — Auto-create users on first login
- `DEFAULT_ROLE_ID` — Role assigned to auto-created users
- `SYNC_USER_INFO` — Keep user info in sync with provider
- `ICON` / `LABEL` — Customize login button appearance

## Proxy Support
For Directus behind HTTP(S) proxy, use `global-agent` npm package via custom Docker image.

## Comparison Notes (vs OpenRegister)

| Aspect | Directus | OpenRegister |
|--------|----------|-------------|
| Auth Methods | Email/password, OAuth2, OpenID, LDAP, SAML | Nextcloud auth (all Nextcloud-supported methods) |
| SSO | Built-in multi-provider | Via Nextcloud SSO apps |
| Token Types | Access tokens, cookies, sessions | Nextcloud session + API tokens |
| 2FA | Supported | Via Nextcloud 2FA |
| Public Registration | Configurable per provider | Not applicable |
| Role Assignment | Auto-assign on registration | Nextcloud group-based |
| Session Management | Session-based (v10.10+) | Nextcloud sessions |
