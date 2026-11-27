# Delta: dashboards

## MODIFIED Requirements

### Requirement: REQ-DASH-018 Active-dashboard resolution chain (multi-scope)

When the workspace page renders for a user at `/apps/launchpad/` without naming a dashboard, the system MUST resolve
which dashboard to open by walking the following precedence and stopping at the first match (board LpVandaag: launchpad
opens on the start page unless the person chose another start dashboard):

1. The person's chosen start dashboard: the dashboard whose UUID equals their `default_dashboard_uuid` preference (set
   with Set as default in the More menu), IF that dashboard is currently visible to the user (per REQ-DASH-013).
1b. For a user who owns no personal dashboard and has no start dashboard: the admin template that applies to them
   (admin-templates REQ-TMPL-019).
2. The `group_shared` dashboard with `isDefault = 1` in the user's primary group (per REQ-DASH-015).
3. The `group_shared` dashboard with `isDefault = 1` in the synthetic `'default'` group: the start page Vandaag
   (default-widget-bundle REQ-DWB-008).
4. The first `group_shared` dashboard (by `sortOrder` ascending, then `createdAt`) in the user's primary group.
5. The first `group_shared` dashboard in the `'default'` group.
6. The user's first personal `user`-type dashboard (by `sortOrder`, then `createdAt`).
7. `null`: the workspace page MUST then render an empty-state with a "Create your first dashboard" affordance.

The last-used dashboard (`active_dashboard_uuid`, REQ-DASH-019) MUST NOT decide which dashboard opens. A URL that names a
dashboard (`/apps/launchpad/<slug>`, `/apps/launchpad/dashboards/<id>`) MUST open that dashboard, as before.

The resolver MUST attach a `source` field to the returned dashboard descriptor with one of `'user'`, `'group'`,
`'default'`. REQ-DASH-018 supersedes REQ-DASH-009 for the workspace boot path; `GET /api/dashboard` MUST follow the same
order.

#### Scenario: The start page wins over the last-used dashboard

- **GIVEN** Pieter has no start dashboard of his own, and the `default` group's default is Vandaag
- **AND** he opened "Team Vergunningen" from the More menu yesterday
- **WHEN** he opens `/apps/launchpad/`
- **THEN** the dashboard shown MUST be Vandaag with `source = 'default'`

#### Scenario: Honoured user preference

- **GIVEN** Pieter chose Set as default on "Mijn werkplek"
- **WHEN** he opens `/apps/launchpad/`
- **THEN** the dashboard shown MUST be "Mijn werkplek" with `source = 'user'`

#### Scenario: Stale preference is silently cleared

- **GIVEN** Pieter's start dashboard was a group dashboard that has been deleted
- **WHEN** he opens `/apps/launchpad/`
- **THEN** his `default_dashboard_uuid` MUST be cleared and the page MUST open Vandaag without an error

#### Scenario: Group default wins over default-group default

- **GIVEN** Bob belongs to "engineering", which has a default dashboard `E`, and Bob has no start dashboard
- **WHEN** he opens `/apps/launchpad/`
- **THEN** the dashboard shown MUST be `E` with `source = 'group'`

#### Scenario: Falls through to default group when primary group has no dashboards

- **GIVEN** Carol belongs to "support", which has no group dashboards, and the `default` group's default is Vandaag
- **AND** Carol has no start dashboard
- **WHEN** she opens `/apps/launchpad/`
- **THEN** the dashboard shown MUST be Vandaag with `source = 'default'`

#### Scenario: A link still opens its dashboard

- **GIVEN** Pieter has no start dashboard
- **WHEN** he opens `/apps/launchpad/team-vergunningen`
- **THEN** "Team Vergunningen" MUST open, and a later visit to `/apps/launchpad/` MUST open Vandaag

#### Scenario: Empty state when no dashboards exist anywhere

- **GIVEN** a brand-new launchpad install with no dashboards of any type
- **WHEN** any user opens the workspace page
- **THEN** the resolver MUST return `null`, the initial state MUST carry `activeDashboardId: ''` and the page MUST render
  the empty state
@e2e exclude needs a brand-new install with no dashboard of any type, which would empty the shared instance; pinned by tests/Unit/Service/DashboardServiceActiveResolutionTest.php
