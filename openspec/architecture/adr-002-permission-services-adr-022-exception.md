# ADR-002: The dashboard permission services are an ADR-022 documented exception

- **Status:** Proposed (held for review)
- **Date:** 2026-11-03
- **Sunset:** 2027-06-30
- **Gate 23 rules:** 6
- **Deciders:** Ruben van der Linde
- **Scope:** launchpad, permission checks on its own dashboards, placements and role features
- **References:** hydra ADR-022 (apps consume OpenRegister abstractions), exception clause. Hydra gate 23, `or-abstraction-anti-patterns`, rule 6 (`consume-or-rbac-fleet-wide`). `docs/architecture.md`, "Runtime-only OR consumption policy". `appinfo/info.xml` dependencies comment.

## Context

ADR-022 gives role-based access to OpenRegister objects to OpenRegister's rbac
scopes. Gate 23 rule 6 enforces that on file name: any `*Permission*Service.php`
or `*Authorization*Service.php` outside OpenRegister. Two launchpad files match:

- `lib/Service/PermissionService.php` decides who may view, edit, add to and
  share a dashboard, from the dashboard's owner, type, group and permission
  level, and from the admin settings.
- `lib/Service/RoleFeaturePermissionService.php` decides which widgets a user's
  groups may place, and seeds a new dashboard from the role layout defaults.

Neither guards an OpenRegister object. Both read launchpad's own tables only
(`Db\Dashboard`, `Db\WidgetPlacement`, `Db\AdminSetting`,
`Db\RoleFeaturePermission`, `Db\RoleLayoutDefault`) and Nextcloud's group and
user managers. Launchpad is an OpenRegister-free app: it must boot and work on
an instance without OpenRegister, and `appinfo/info.xml` deliberately declares
no OpenRegister dependency. There are no OpenRegister objects here for rbac
scopes to cover.

## Decision

Per the ADR-022 exception clause, gate 23 suppresses these two paths under rule
6, the permission and authorization service name rule, and under no other rule.
The `Gate 23 rules: 6` line in the header tells the gate so. Every suppression
is printed on every gate run with this ADR named beside it.

- `lib/Service/PermissionService.php`
- `lib/Service/RoleFeaturePermissionService.php`

## When this ends

The exception holds while dashboards and placements live in launchpad's own
tables. If they move into OpenRegister registers, these checks move to rbac
scopes in the same change and this ADR is withdrawn. The sunset date forces the
question to be asked again by then.
