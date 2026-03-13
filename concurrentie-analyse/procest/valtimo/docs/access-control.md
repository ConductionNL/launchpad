# Valtimo Access Control

Source: https://docs.valtimo.nl/features/access-control

## Overview

Valtimo implements Policy-Based Access Control (PBAC), differing from traditional role-based systems. Access rules are defined based on user identity, resource type, action being performed, and contextual information.

## Authentication

- Uses Keycloak as identity provider
- Supports external IdP integration (Microsoft Entra, LDAP)
- JWT tokens contain embedded Keycloak roles
- Roles must be mirrored in Valtimo's Access Control configuration

## Security Default

**By default, a Valtimo user has NO access.** Access is only granted by explicitly configuring permissions for a Role in Access Control.

## Permission Model

### Permission Evaluation
- Multiple permissions evaluated using **OR logic** (any single permission passing = authorized)
- Each permission checks: role assignment, resource matching, action compatibility, contextual requirements

### Conditions
- Optional conditions use **AND logic** (all must be true)
- Can be nested using containers to join related resources
- Support field-based matching (e.g., restrict to specific dashboard IDs)

### Scope
- Access control applies universally by default
- Exception: Automated BPMN tasks and listeners bypass authorization (no user context)

## Resource Types

Various modules register resource types:
- Dashboard: `view`, `view_list`
- Process: `OperatonExecution` (create), `OperatonProcessDefinition`
- Case/Document: various CRUD actions
- Search fields, forms, notes, etc.
