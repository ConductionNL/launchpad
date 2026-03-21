---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Access Control & Authentication

## Overview

Strapi has two separate access control systems: the **Admin Panel** role-based access control (RBAC) for CMS administrators, and the **Users & Permissions** plugin for public-facing API authentication. Both support role-based permissions, but the admin system uses a more sophisticated ABAC (Attribute-Based Access Control) engine with conditions.

## Admin Panel RBAC

### Role System
- **Super Admin** - Full access to everything
- **Editor** - Can manage and publish content
- **Author** - Can create and manage own content
- Custom roles (EE feature)

### Permission Structure
```
Permission = {
  action: "plugin::content-manager.explorer.read",
  subject: "api::article.article",  // content type UID
  conditions: ["admin::is-creator"], // optional conditions
  properties: {
    fields: ["title", "content"],    // allowed fields
    locales: ["en", "fr"]            // allowed locales (i18n)
  }
}
```

### ABAC Conditions (EE)
Conditions are functions that evaluate at runtime:
- `admin::is-creator` - User created the entry
- `admin::has-same-role-as-creator` - Same role as creator
- Custom conditions via plugins

### Permission Engine (`@strapi/permissions`)
The permission engine provides:
- **Domain layer** - Permission data structures and validation
- **Engine** - Runtime permission evaluation with condition resolution
- Supports `ability.can(action, subject)` pattern (similar to CASL)

## Users & Permissions Plugin (Public API)

### Authentication Methods
- **Local** - Email/password registration and login
- **JWT** - JSON Web Token for API access (issued on login)
- **OAuth Providers** - Third-party authentication

### Supported OAuth Providers
| Provider | Protocol |
|----------|----------|
| Google | OAuth 2.0 |
| Facebook | OAuth 2.0 |
| GitHub | OAuth 2.0 |
| Discord | OAuth 2.0 |
| Twitter | OAuth 1.0a |
| Apple | OAuth 2.0 / OIDC |
| Microsoft | OAuth 2.0 |
| Auth0 | OAuth 2.0 |
| CAS | CAS Protocol |
| Cognito | OIDC (JWK verification) |
| Instagram | OAuth 2.0 |
| LinkedIn | OAuth 2.0 |
| Reddit | OAuth 2.0 |
| Twitch | OAuth 2.0 |
| VK | OAuth 2.0 |
| Patreon | OAuth 2.0 |
| Keycloak | OAuth 2.0 |

Each provider has a `grantConfig` (OAuth settings) and `authCallback` (profile extraction).

### Public Roles
- **Authenticated** - Default role for logged-in users
- **Public** - Permissions for unauthenticated requests
- Custom roles can be created

### Permission Model
Permissions are stored per role with action strings:
```
action = "api::article.article.find"
         (type).(controller).(action)
```

Each action can be enabled/disabled per role. No field-level or condition-based restrictions in the public API (unlike admin RBAC).

### JWT Configuration
```typescript
// plugins.ts
export default {
  'users-permissions': {
    config: {
      jwt: {
        expiresIn: '7d',
      },
    },
  },
};
```

### Authentication Flow
1. `POST /api/auth/local` - Login with identifier + password
2. Returns `{ jwt, user }` response
3. Subsequent requests: `Authorization: Bearer <jwt>`
4. `POST /api/auth/local/register` - User registration
5. `POST /api/auth/forgot-password` - Password reset flow

### SSO (EE)
Enterprise edition adds:
- SAML 2.0 authentication for admin panel
- Custom OAuth/OIDC providers for admin panel
- Configurable auto-registration

## Route-Level Security

### Policies
Policies are functions that gate access to routes:
```typescript
// Global policy
export default (ctx, config, { strapi }) => {
  if (ctx.state.user.role.name === 'Editor') {
    return true;
  }
  return false;
};
```

### Route Scopes
Every route automatically gets an auth scope based on its handler:
```
scope = "api::article.article.find"
```
The permission system checks if the user's role has this action enabled.

### Middleware Auth
The `@strapi/core` authentication middleware:
1. Extracts credentials from request (Bearer token, session, API token)
2. Resolves the authentication strategy
3. Sets `ctx.state.auth` and `ctx.state.user`
4. Applies route-level policies

## API Tokens
Strapi also supports API tokens as an alternative to JWT:
- **Read-only** - Only GET requests
- **Full access** - All operations
- **Custom** - Per-route permissions
- Tokens are hashed and stored in the database

## Relevance to OpenRegister

**Key differences:**
- Strapi manages its own user database; OpenRegister uses Nextcloud users
- Strapi has two auth systems (admin + public); OpenRegister uses Nextcloud's unified auth
- Strapi's admin RBAC supports field-level permissions; OpenRegister uses Nextcloud groups

**Features OpenRegister could adopt:**
- Field-level permission control (restrict which fields a role can read/write)
- ABAC conditions (e.g., "is-creator" - restrict to entries created by the user)
- API token system for service-to-service access
- Per-content-type permission granularity (separate read/write/delete per schema)
- OAuth provider registry pattern for extensible authentication
