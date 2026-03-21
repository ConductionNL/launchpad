---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Access Control

## Purpose

NocoBase implements a comprehensive role-based access control (RBAC) system that controls access at three levels: system operations, plugin settings, and data source resources. It supports field-level permissions and record-level data scoping.

## Architecture Overview

The ACL system spans two packages:
- `@nocobase/acl` - Core ACL engine (role definitions, permission checks, strategies)
- `plugin-acl` - Server plugin that manages roles, syncs permissions, provides middleware

```
Request -> setCurrentRole middleware -> ACL middleware -> Action Handler
                                            |
                                     Check: role + resource + action
                                            |
                                     Apply: field filtering, data scope
```

## Data Model

### Roles Collection
- `name` - Role identifier (e.g., "admin", "member")
- `title` - Display name
- `description` - Role description
- `strategy` - Default permission strategy (allow-all or deny-all)
- `default` - Whether assigned to new users
- `hidden` - Whether visible in UI
- `snippets` - Array of permission snippet patterns

### Role Resources Collection
- `roleName` - Parent role
- `name` - Resource (collection) name
- `usingActionsConfig` - Whether custom action config is active

### Role Resource Actions Collection
- `roleName` - Parent role
- `resource` - Resource name
- `action` - Action name (list, get, create, update, destroy)
- `fields` - Allowed fields (whitelist)
- `scope` - Data scope filter (record-level restrictions)

## Business Logic

### Permission Levels

1. **System Permissions** - Checkbox-based:
   - Configure interface (UI editor)
   - Install/activate/disable plugins
   - Configure plugins
   - Clear cache, reboot application

2. **Plugin Settings** - Per-plugin access to settings pages:
   - Expandable tree with parent/child entries
   - E.g., "Notification manager" -> "Channels", "Logs"

3. **Data Source Permissions** - Per-collection CRUD:
   - Individual action toggles per collection
   - Field-level permissions (whitelist which fields are accessible)
   - Data scope (filter conditions limiting visible records)

4. **Route Permissions** - Control which menu items/pages each role can see:
   - Desktop routes
   - Mobile routes

### Strategies

A role's strategy defines the default permission level:
- **Allow all** - All actions permitted unless explicitly denied
- **Deny all** - All actions denied unless explicitly permitted

### Data Scoping

Record-level permissions via filter conditions:
- "All records"
- "Own records" (createdBy = currentUser)
- Custom filter (e.g., `department = currentUser.department`)

### ACL Snippets

Snippets are permission patterns for plugin features:
```typescript
acl.registerSnippet({
  name: 'pm.plugin-file-manager.configuration',
  actions: ['map-configuration:set'],
});
```

### Union Roles

Multiple roles can be combined. The ACL evaluates permissions across all assigned roles and uses the most permissive result.

## Requirements

### Functional
- Define roles with system, plugin, and data permissions
- Field-level access control per role per collection
- Record-level data scoping with filter conditions
- Default role assignment for new users
- Role-based route/menu visibility
- Union role evaluation (most permissive wins)

### Non-functional
- Permission checks cached for performance
- Sync across multi-instance deployments via PubSub
- ACL evaluation adds minimal latency to requests

## Comparison Notes

### vs Nextcloud/OpenRegister ACL
- NocoBase has built-in data-level scoping; Nextcloud relies on share/ACL per-item
- NocoBase has field-level permissions; OpenRegister does not have per-field restrictions
- Nextcloud has group-based sharing; NocoBase uses role-based only
- Nextcloud has external user backends (LDAP, SAML); NocoBase has basic auth + SMS
- Both support admin/member role distinction
- NocoBase's route-level permissions (menu visibility per role) has no direct equivalent in Nextcloud
