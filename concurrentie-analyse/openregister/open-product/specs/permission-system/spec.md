# Permission System

## Summary

Open Product has a two-tier permission system: Django model permissions for CRUD operations, plus a per-user per-ProductType permission model that controls which products a user can access.

## Model Permissions (Django Standard)
- `producttypen.add_producttype` / `change_producttype` / `delete_producttype`
- `producten.add_product` / `change_product` / `delete_product`
- Applied via `DjangoModelPermissions` on all viewsets

## ProductType Permissions

### ProductTypePermission Model
- `user` -- FK to User
- `producttype` -- FK to ProductType
- `mode` -- enum: `read_only`, `read_and_write`
- Unique together: (user, producttype)

### Enforcement (ProductTypeObjectPermission)

#### List endpoint:
- Superusers: see all products
- Regular users: only see products where they have a ProductTypePermission for the product's type

#### Create endpoint:
- `producttype_uuid` in request data checked against user's read_and_write permissions

#### Retrieve:
- Any permission mode (read_only or read_and_write) is sufficient

#### Update/Delete:
- Requires `read_and_write` mode

#### ProductType change on update:
- If changing a product's producttype, user needs read_and_write on BOTH old and new type

## Authentication
- **Token Authentication**: API tokens created in admin (User -> Tokens)
- **OpenID Connect**: Configurable OIDC provider with optional user auto-creation
- OIDC backend: `OIDCAuthenticationBackend` with custom claims handling

## Already in OpenRegister
- Basic authentication (user/password, API keys)
- Share-based access to registers

## Not yet in OpenRegister
- **Per-entity-type permission model** (read-only vs read-write per user per type)
- **Product list filtering by permission** (users only see their permitted types)
- **Cross-entity permission checking** (permissions checked on both source and target type during updates)
- **OIDC integration** for DRF API authentication
