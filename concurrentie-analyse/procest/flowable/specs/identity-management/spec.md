---
competitor: flowable
analyzed_date: 2026-03-14
feature: identity-management
module_path: modules/flowable-idm-api, modules/flowable-idm-engine, modules/flowable-ldap
---

# Identity Management (IDM)

## Overview

Flowable has a built-in identity management engine supporting users, groups, privileges, and tokens. It can also integrate with external identity providers via LDAP.

## Core Entities

### User
- `id`, `firstName`, `lastName`, `displayName`
- `email`, `password`
- `tenantId`
- Picture/avatar support

### Group
- `id`, `name`, `type`
- Users can be members of multiple groups
- Groups used for candidate assignment in tasks

### Privilege
- Named permissions
- Can be assigned to users or groups
- `PrivilegeMapping` tracks user/group-to-privilege assignments

### Token
- Authentication tokens
- `id`, `tokenValue`, `tokenDate`
- `userId`, `tokenData`, `ipAddress`

## IdmIdentityService

Core identity operations:
- CRUD for users, groups, privileges, tokens
- User-group membership management
- User-privilege and group-privilege management
- User queries with flexible filtering
- Group queries with flexible filtering
- Privilege queries
- Password encoding support (`PasswordEncoder`, `PasswordSalt`)

## LDAP Integration

`flowable-ldap` module provides:
- LDAP/Active Directory authentication
- User/group sync from directory
- Configurable attribute mapping
- Search filters for users and groups

## Identity in Case/Process Context

### Task Assignment
- `assignee` -- direct user assignment
- `owner` -- task owner (for delegation scenarios)
- `candidateUsers` -- list of users who can claim
- `candidateGroups` -- list of groups who can claim

### Identity Links
Cross-engine identity tracking:
- Case instances: owner, assignee, participant, starter
- Plan item instances: assignee, participant
- Tasks: assignee, owner, candidate user/group, participant
- Historic identity links preserved after completion

### IdentityLink Types
- `assignee` -- currently responsible
- `owner` -- original owner (before delegation)
- `candidate` -- can claim
- `participant` -- involved user
- `starter` -- who started the case/process

## Procest Comparison

| Feature | Flowable IDM | Procest |
|---------|-------------|---------|
| User source | Built-in DB + LDAP | Nextcloud users |
| Groups | Flowable groups | Nextcloud groups |
| Privileges | Named privileges | Nextcloud capabilities |
| Assignment | Direct + candidate users/groups | Nextcloud user assignment |
| Delegation | Full delegation workflow | Not available |
| Claiming | Task claim/unclaim | Not available |
| Identity history | Full historic identity links | OpenRegister audit |
| Multi-tenancy | Tenant-scoped identity | Nextcloud instance scope |
