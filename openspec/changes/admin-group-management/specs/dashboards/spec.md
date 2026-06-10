# Spec delta — dashboards capability

Additive change. Extends the existing `dashboards` capability with one
new requirement covering the admin-facing UI for group-shared
dashboards. No backend semantics change — this is a wrapper around the
existing endpoints from `multi-scope-dashboards`.

## ADDED Requirements

### REQ-DASH-015 — Admin group-management UI

The admin settings shall expose a **Group dashboards** tab under the
existing Beheer tab strip that lists every Nextcloud group + the
synthetic `default` sentinel, with per-row create / manage actions
that wrap the existing `multi-scope-dashboards` endpoints (no new
endpoints).

#### Scenario: Admin opens the Group dashboards tab

- **GIVEN** the user is an admin
- **WHEN** they navigate to Settings → LaunchPad → Beheer → Group
  dashboards
- **THEN** they see one row per Nextcloud group + a `default` row at
  the top
- **AND** each row shows the count of group-shared dashboards already
  configured for that group
- **AND** each row exposes a quick-action menu (View / Create /
  Manage)

#### Scenario: Admin creates a group-shared dashboard

- **GIVEN** the user is an admin on the Group dashboards tab
- **WHEN** they click "Create" on a group row
- **THEN** an `NcDialog` opens with fields (name, icon, layout
  template selector, default flag)
- **AND** submitting the form POSTs to
  `/api/dashboards/group/{groupId}` per
  `multi-scope-dashboards` REQ-DASH-014
- **AND** the new dashboard appears in the group row's count + the
  manage list

#### Scenario: Admin deletes the last dashboard in a group

- **GIVEN** the user is an admin
- **AND** a group has exactly one group-shared dashboard
- **WHEN** the admin clicks "Delete" on that dashboard
- **THEN** the backend returns HTTP 400 (last-in-group guard per
  `multi-scope-dashboards`)
- **AND** the UI surfaces a toast explaining the guard
- **AND** the dashboard remains visible to members

#### Scenario: Non-admin cannot open the tab

- **GIVEN** the user is NOT an admin
- **WHEN** they attempt to navigate to Settings → LaunchPad →
  Beheer → Group dashboards
- **THEN** the tab is not rendered (UI gate)
- **AND** any direct call to the underlying admin endpoints returns
  HTTP 403 (server gate, already enforced by
  `multi-scope-dashboards`)
