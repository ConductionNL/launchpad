---
capability: action-authorization
status: done
cross-links:
  - openspec/architecture/adr-023-action-authorization.md
  - openspec/specs/admin-roles/spec.md
  - openspec/specs/permissions/spec.md
---

# Action Authorization Specification

## Purpose

An administrator decides in one grid which Nextcloud groups may take each LaunchPad action: list dashboards, publish one, react, see analytics, edit the navigation, and so on. Every controller method names its action and asks one service whether the caller may take it. This is action RBAC as ADR-023 defines it. Which objects a user may touch stays with PermissionService and the admin roles spec; this spec only answers "may this user call this endpoint at all".

Written on 2026-10-07 after the fact, from the code on `development`. It describes what ships today and covers parity row `t-action-matrix`.

Code:
- `lib/Service/ActionAuthService.php`: `requireAction()` (line 117), `getAllowedGroups()` (171), `getMatrix()` (187), `setMatrix()` (234).
- `lib/Controller/ActionMatrixController.php`: `getMatrix()` (73) and `setMatrix()` (111), routed at `GET` and `PUT /api/admin/action-matrix` (`appinfo/routes.php:440-441`).
- `lib/actions.seed.json`: 82 actions and their default groups.
- `lib/Repair/InitializeActions.php` (run, line 71) and `lib/Repair/ApplyActionBaseline.php` (run, line 127), both registered as install and upgrade steps in `appinfo/info.xml`.
- `src/components/admin/ActionAuthMatrix.vue`, mounted by `src/components/admin/tabs/RolesPermissionsTab.vue:15` in the Roles and permissions tab of the admin settings (`src/components/admin/AdminSettings.vue:246`).

## Requirements

### Requirement: Every action endpoint asks the action service (REQ-ACT-001)

A controller method that performs a LaunchPad action MUST call `ActionAuthService::requireAction()` with the caller and a dot-separated action name (for example `dashboard-reaction.add-reaction`) before it does any work. The service SHALL refuse with an `OCSForbiddenException` when the caller may not take the action. Editing the matrix itself is not an action: it is guarded by `#[AuthorizedAdminSetting(LaunchPadAdmin::class)]` at the route.

@e2e exclude Backend authorization; asserted per controller in tests/Unit/Controller (for example DashboardReactionApiControllerTest.php). No page to drive.

#### Scenario: A member outside the allowed groups is refused
- GIVEN the matrix entry for `analytics.top-dashboards` is `["admin"]`
- AND Pieter is not an administrator
- WHEN Pieter's session calls the endpoint behind that action
- THEN the service MUST throw `OCSForbiddenException` with the message "Action 'analytics.top-dashboards' requires admin rights"
- AND the controller MUST NOT run the action

#### Scenario: A member of an allowed group passes
- GIVEN the matrix entry for `dashboard.publish` is `["admin", "redactie"]`
- AND Pieter is a member of the group `redactie`
- WHEN Pieter's session calls the endpoint behind that action
- THEN `requireAction()` MUST return without throwing

### Requirement: How an action resolves (REQ-ACT-002)

The service SHALL resolve an action in this order. An administrator always passes. An entry holding the sentinel `@all` passes every signed-in user. An entry that is empty or holds only `admin` refuses everyone else. Otherwise the caller passes when one of their groups, read through `AdminTemplateService::getUserGroupIdsFor()`, is in the entry. An action missing from the matrix MUST resolve to `["admin"]`, so a new action is closed until an administrator opens it.

@e2e exclude Pure service logic; covered by tests/Unit/Service/ActionAuthBaselineTest.php.

#### Scenario: The all-users sentinel wins over an admin-only reading
- GIVEN the entry for `dashboard.list` is `["admin", "@all"]`
- WHEN any signed-in user calls an endpoint behind `dashboard.list`
- THEN the call MUST pass

#### Scenario: An unknown action is admin-only
- GIVEN the matrix has no entry for `example.new-action`
- WHEN a user who is not an administrator calls the endpoint behind it
- THEN the service MUST refuse the call

#### Scenario: An unreadable matrix denies by default
- GIVEN the stored `actions` app config value is not valid JSON
- WHEN the service reads the matrix
- THEN it MUST treat the matrix as empty
- AND every action MUST resolve to `["admin"]`

### Requirement: The administrator edits the matrix in one grid (REQ-ACT-003)

The Roles and permissions tab of the LaunchPad admin settings MUST show an "Action authorization" table with one row per action and one column per group: first `admin`, then "All logged-in users" for `@all`, then every Nextcloud group. The action list SHALL be the union of the stored matrix and the seed file, so an action no one has configured yet still has a row. Each cell is a checkbox. The `admin` column MUST be ticked and disabled. "Save action matrix" MUST send the whole matrix with `PUT /api/admin/action-matrix`, always keeping `admin` in each entry, and confirm with "Action matrix saved." or report "Failed to save the action matrix.".

@e2e exclude Written after the fact; no Playwright spec drives this table yet. Adding one belongs to a build change.

#### Scenario: An administrator opens the publish action to a group
- GIVEN Fatima is an administrator on the Roles and permissions tab
- WHEN she ticks the `redactie` column on the `dashboard.publish` row and presses "Save action matrix"
- THEN the request MUST carry `dashboard.publish: ["admin", "redactie"]`
- AND the table MUST redraw from the matrix the server returns
- AND the message "Action matrix saved." MUST appear

#### Scenario: The admin column cannot be unticked
- GIVEN the action table is shown
- THEN every cell in the `admin` column MUST be ticked and disabled

#### Scenario: Only administrators reach the matrix endpoints
- GIVEN Pieter is not a LaunchPad administrator
- WHEN his session calls `GET` or `PUT /api/admin/action-matrix`
- THEN Nextcloud MUST refuse the request before the controller runs

### Requirement: A fresh install is usable and an upgrade keeps the administrator's choices (REQ-ACT-004)

On install, when the stored matrix is empty, the `InitializeActions` repair step MUST write the actions from `lib/actions.seed.json`, which grants the ordinary end-user actions to `@all` and keeps administrative actions at `["admin"]`. When the matrix already has entries it MUST leave them alone. The `ApplyActionBaseline` step SHALL, once per baseline version, widen only entries that still hold the untouched `["admin"]` default or are missing, and MUST leave every entry an administrator changed.

@e2e exclude Install and upgrade repair steps; covered by tests/Unit/Repair/ApplyActionBaselineTest.php and tests/Unit/Service/ActionSeedCoverageTest.php.

#### Scenario: Fresh install
- GIVEN LaunchPad is installed on an instance with no stored action matrix
- WHEN the install repair steps run
- THEN the matrix MUST equal the seed file's actions
- AND a non-admin user MUST be able to list their dashboards

#### Scenario: An administrator's narrowing survives the upgrade
- GIVEN an administrator set `dashboard.list` to `["admin", "medewerkers"]`
- WHEN a later version runs `ApplyActionBaseline`
- THEN the entry for `dashboard.list` MUST still be `["admin", "medewerkers"]`
