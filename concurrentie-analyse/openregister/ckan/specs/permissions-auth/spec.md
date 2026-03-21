---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# Permissions and Authentication

## What It Does

CKAN provides a layered authorization system combining user roles within organizations, action-level auth functions, and dataset-level visibility controls. Authentication supports local accounts with API tokens, with LDAP, SAML, and OAuth2 available via extensions.

## How It Works

**Authorization architecture:**

Every action in CKAN has a corresponding auth function in `ckan/logic/auth/`. When an action is called, `_check_access(action_name, context, data_dict)` runs the auth function before any data operations.

Auth functions return `{"success": True/False}` and can inspect the context (user, model) and data to make decisions:

```python
def package_create(context, data_dict):
    user = context['user']
    if authz.auth_is_anon_user(context):
        return {'success': False}
    if data_dict.get('owner_org'):
        return authz.has_user_permission_for_group_or_org(
            data_dict['owner_org'], user, 'create_dataset')
    return {'success': True}
```

**Organization roles:**
- `admin` - Full control over the organization and its datasets
- `editor` - Create and edit datasets within the organization
- `member` - View private datasets only

**Sysadmin:** A special flag on user accounts that bypasses all authorization checks.

**Dataset visibility:**
- `private=False` - Visible to everyone
- `private=True` - Only visible to members of the owning organization

**API tokens:**
CKAN 2.10+ uses API tokens instead of legacy API keys. Tokens are generated per user and passed via `Authorization` header. The `IApiToken` plugin interface allows custom token encoding/decoding.

**Plugin extensibility:**
- `IAuthFunctions` - Override or add auth functions for any action
- `IAuthenticator` - Custom authentication methods (LDAP, SAML, etc.)
- `IPermissionLabels` - Fine-grained permission labels indexed into Solr for search-level authorization

## Key Source Files
- `ckan/logic/auth/` - Auth functions for every action (get.py, create.py, update.py, delete.py)
- `ckan/authz.py` - Authorization utilities, role checking
- `ckan/lib/api_token.py` - API token generation and validation
- `ckan/plugins/interfaces.py` - `IAuthFunctions`, `IAuthenticator`, `IPermissionLabels`

## Relevance to OpenRegister

OpenRegister leverages Nextcloud's authentication and RBAC system, which provides user/group management, LDAP, SAML, and OAuth2 out of the box. CKAN's per-action auth functions provide more granular control -- each API action can have custom authorization logic. The `IPermissionLabels` pattern (indexing permissions into Solr for search-time filtering) is an advanced technique OpenRegister could adopt.
