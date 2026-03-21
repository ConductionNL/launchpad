---
competitor: twenty
analyzed_date: 2026-03-14
feature: Permissions & RBAC
category: security
maturity: stable
---

# Permissions & Role-Based Access Control

## Summary

Twenty provides a comprehensive RBAC system with cascading permissions across objects, fields, settings, and actions. Row-level permissions and SSO require Organization plan.

## Permission Architecture

### Cascade Hierarchy
1. **All Objects baseline** -- Universal defaults
2. **Object-level overrides** -- Per-object rules
3. **Field-level overrides** -- Per-field rules (most specific wins)

### Object Permissions
- See Records
- Edit Records
- Delete Records
- Destroy Records (permanent deletion)

### Field Permissions
- See Field
- Edit Field
- No Access

### Settings Permissions
- API keys
- Workspace preferences
- Role assignment
- Data model management
- Security settings
- Workflow management

### Action Permissions
- Send Email
- Import/Export CSV

## Role Management
- Custom roles with configurable permissions
- Admin role cannot be deleted; must always have one Admin
- Default role auto-assigned to new workspace members
- Roles apply to: workspace members, API keys, AI agents
- Deleted roles revert members to default

## SSO (Organization Plan)

### Supported Protocols
- SAML 2.0 (most enterprise IdPs)
- Google Workspace (OAuth)
- Microsoft Entra ID (formerly Azure AD)

### Features
- Just-in-Time (JIT) user provisioning
- Manual pre-invitation option
- SSO-only enforcement (disable password login)
- Offboarding integration
- Test configuration before activation

## Relevance to Pipelinq

**Twenty's RBAC strengths:**
- Granular three-level cascade (all > object > field)
- API key and AI agent role inheritance
- SSO with major enterprise IdPs
- Destroy vs Delete distinction

**Pipelinq/Nextcloud advantages:**
- Nextcloud provides SSO, LDAP, SAML out of the box
- Nextcloud Groups and Circles for team-based access
- Nextcloud shares and permissions are battle-tested at enterprise scale
- OpenRegister has register-level isolation (data partitioning)
- No premium plan required for SSO in Nextcloud (self-hosted always has full features)
