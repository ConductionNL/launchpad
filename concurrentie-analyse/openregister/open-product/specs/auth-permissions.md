# Spec: Authentication & Authorization

## Feature Summary

Open Product implements two-layer authorization: class-level Django permissions for CRUD operations, and since v1.5.0, object-level permissions per ProductType for the Producten API.

## Authentication Methods

### API Token
- Generated in admin: Users > Tokens
- Passed as `Authorization: Token <value>` header
- Token linked to a user account
- Simple to set up, suitable for service-to-service integration

### OpenID Connect (OIDC)
- JWT tokens from configured OIDC provider
- Since v1.5.0: split configuration into OIDCProvider and OIDCClient models
- Supports multiple providers and clients
- Suitable for user-facing applications with SSO

## Authorization Layers

### Layer 1: Class Permissions (Django)
Standard Django model permissions for write operations:
- `producttypen.add_producttype` / `change_producttype` / `delete_producttype`
- `producten.add_product` / `change_product` / `delete_product`
- Configured via Django admin user/group management
- Read operations are unrestricted (any authenticated user can read)

### Layer 2: Object Permissions (since v1.5.0)
Per-ProductType access control for the Producten API:
- Non-superusers need explicit **read** or **read-write** permission on each ProductType
- Controls which product types a user can view/create/update/delete products for
- Configured per user in the admin interface
- Superusers bypass this check entirely

### Brute Force Protection
- Django-axes integration for login attempt monitoring
- Access attempts and access logs viewable in admin
- Rate limiting on authentication endpoints

## OpenRegister Comparison

| Aspect | Open Product | OpenRegister |
|--------|-------------|--------------|
| Auth methods | Token + OIDC | Nextcloud session + API keys + Bearer tokens |
| User management | Django admin users | Nextcloud user system |
| Permission model | Class + object (per ProductType) | RBAC on register/schema/object level |
| Granularity | Per ProductType (broad) | Per register, per schema, per object (fine-grained) |
| Multi-tenancy | None | Native via Nextcloud organizations |
| SSO | OIDC | Nextcloud SSO (SAML, OIDC, LDAP) |
| API key management | Admin-generated tokens | Self-service API keys per user |
| Audit | Access logs only | Full audit trail on data changes |

**Open Product advantage:** OIDC integration is more standards-based for government SSO; per-ProductType permissions map well to the PDC use case.

**OpenRegister advantage:** Much more granular RBAC (down to individual objects), multi-tenancy, self-service API key management, and full audit trail on all data mutations.
