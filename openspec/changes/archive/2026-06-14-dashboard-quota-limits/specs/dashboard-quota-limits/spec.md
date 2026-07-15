---
capability: dashboard-quota-limits
delta: true
status: draft
---

# Dashboard Quota Limits — New Capability Specification

@e2e exclude no live-NC Playwright run available in this build context; quota enforcement is covered by PHPUnit (QuotaService + service-wiring + controller 409/envelope tests) and the UI affordance logic by vitest store tests. Re-annotate with real Playwright tests once a live instance is wired (tasks.md Task 10).

## ADDED Requirements

### Requirement: REQ-QUOTA-001 Quota Admin Settings

The system MUST provide two admin settings — `max_dashboards_per_user` and
`max_widgets_per_dashboard` — stored in the existing admin-settings key-value table,
both integers defaulting to `0` (unlimited), exposed on the admin settings API as
`maxDashboardsPerUser` and `maxWidgetsPerDashboard` following the existing
`admin-settings` retrieval, update, validation, and persistence contract
(REQ-ASET-001/002/007/014).

#### Scenario: Defaults preserve unlimited behaviour
- GIVEN a fresh installation (or an upgraded instance that has never set the new keys)
- WHEN the admin sends `GET /api/admin/settings`
- THEN the response MUST include `"maxDashboardsPerUser": 0` and `"maxWidgetsPerDashboard": 0`
- AND no quota MUST be enforced anywhere while a value is `0`

#### Scenario: Admin sets quota values
- GIVEN an admin
- WHEN they send `PUT /api/admin/settings` with `{"maxDashboardsPerUser": 5, "maxWidgetsPerDashboard": 40}`
- THEN the system MUST persist both values and return them in the camelCase response
- AND the values MUST survive restart per the settings persistence contract (REQ-ASET-007)

#### Scenario: Quota values are validated and clamped
- GIVEN an admin
- WHEN they send `PUT /api/admin/settings` with `{"maxDashboardsPerUser": -3}` or a non-integer value
- THEN the system MUST reject or clamp the value into the range `[0, 10000]` per the admin-settings validation contract (REQ-ASET-014)
- AND MUST NOT persist a negative or non-integer value

#### Scenario: Non-admin cannot change quotas
- GIVEN a non-admin user
- WHEN they attempt `PUT /api/admin/settings` with quota keys
- THEN the system MUST reject the request exactly as for the existing settings (admin guard, REQ-ASET-014)

### Requirement: REQ-QUOTA-002 Dashboard Count Enforcement

When `max_dashboards_per_user > 0`, the system MUST reject any user-initiated creation
of a personal dashboard — create, duplicate, fork, import, and user-initiated template
instantiation — once the user owns that many personal-scope dashboards. The rejection
MUST be HTTP 409 with body
`{"error": "quota_exceeded", "quota": "dashboards", "limit": N, "current": N}` and MUST
be enforced in the service layer so every creation surface (REST, CLI, import) passes
through the same check.

#### Scenario: Creation blocked at the limit
- GIVEN `max_dashboards_per_user = 5` and user "alice" owns 5 personal dashboards
- WHEN she sends `POST /api/dashboards` to create a sixth
- THEN the system MUST return HTTP 409 with body `{"error": "quota_exceeded", "quota": "dashboards", "limit": 5, "current": 5}`
- AND no dashboard MUST be created

#### Scenario: Creation allowed below the limit
- GIVEN `max_dashboards_per_user = 5` and user "alice" owns 4 personal dashboards
- WHEN she creates a dashboard
- THEN the creation MUST succeed normally

#### Scenario: Duplicate, fork, and import paths are equally bound
- GIVEN `max_dashboards_per_user = 5` and user "alice" owns 5 personal dashboards
- WHEN she attempts to duplicate an existing dashboard, fork a shared dashboard, or import a dashboard export file
- THEN each path MUST return the same HTTP 409 `quota_exceeded` response
- NOTE: Enforcement lives in the dashboard service creation choke point; controllers only map the exception. A creation path that skips the check is a defect.

#### Scenario: Deleting a dashboard frees quota immediately
- GIVEN user "alice" at her limit of 5
- WHEN she deletes one of her dashboards and then creates a new one
- THEN the creation MUST succeed (current count is computed live, not cached)

#### Scenario: Boolean flag interaction is most-restrictive-wins
- GIVEN `allow_multiple_dashboards = false` and `max_dashboards_per_user = 5`
- WHEN user "alice", who owns 1 dashboard, attempts to create a second
- THEN the system MUST reject it (effective limit 1)
- AND `max_dashboards_per_user` MUST NOT loosen any restriction imposed by `allow_multiple_dashboards` or `allow_user_dashboards`

#### Scenario: Group-scope dashboards do not count against the user
- GIVEN `max_dashboards_per_user = 5` and user "alice" owns 5 personal dashboards and is additionally routed 3 group-scope dashboards
- WHEN her quota usage is computed
- THEN only the 5 personal-scope dashboards MUST count
- AND the 3 group/admin-scope dashboards MUST be excluded from her count

### Requirement: REQ-QUOTA-003 Widget Count Enforcement

When `max_widgets_per_dashboard > 0`, the system MUST reject any user-initiated
placement creation — add widget, add tile, duplicate, import — on a dashboard that
already has that many placements. The rejection MUST be HTTP 409 with body
`{"error": "quota_exceeded", "quota": "widgets", "limit": N, "current": N}`.

#### Scenario: Adding a widget at the limit
- GIVEN `max_widgets_per_dashboard = 40` and a dashboard with 40 placements
- WHEN the owner sends a placement-creation request (widget or tile)
- THEN the system MUST return HTTP 409 with `{"error": "quota_exceeded", "quota": "widgets", "limit": 40, "current": 40}`
- AND no placement MUST be created

#### Scenario: Adding a widget below the limit
- GIVEN the same setting and a dashboard with 39 placements
- WHEN the owner adds a widget
- THEN the placement MUST be created normally

#### Scenario: Removing a placement frees quota immediately
- GIVEN a dashboard at its 40-placement limit
- WHEN the owner removes one placement and adds a new widget
- THEN the addition MUST succeed

#### Scenario: Import respects the widget quota
- GIVEN `max_widgets_per_dashboard = 40`
- WHEN a user imports a dashboard export containing 55 placements
- THEN the import MUST be rejected with the structured `quota_exceeded` error (not silently truncated to 40)

### Requirement: REQ-QUOTA-004 Admin Provisioning Exemption

The system MUST exempt admin-initiated provisioning — template rollout to groups,
compulsory-widget pushes, and admin provisioning actions on behalf of users — from user
quotas. The bypass MUST be tied to the provisioning code path (an explicit provisioning
context on the quota service), never inferred from the acting user's group membership.

#### Scenario: Template rollout exceeds a user's dashboard quota
- GIVEN `max_dashboards_per_user = 5` and user "alice" owns 5 personal dashboards
- WHEN an admin rolls out a template dashboard to alice's group
- THEN the rollout MUST succeed for alice
- AND her personal-creation quota MUST remain enforced for her own subsequent creations

#### Scenario: Compulsory widget push exceeds the widget quota
- GIVEN `max_widgets_per_dashboard = 40` and a user dashboard with 40 placements
- WHEN an admin pushes a compulsory widget to that dashboard
- THEN the push MUST succeed, putting the dashboard at 41 placements
- AND the user's own next placement creation MUST still be rejected (over-quota state blocks new user creations only)

#### Scenario: Admins are bound by quotas for their own personal dashboards
- GIVEN `max_dashboards_per_user = 5` and admin "carol" owns 5 personal dashboards
- WHEN carol creates a sixth personal dashboard through the normal user flow
- THEN the system MUST return HTTP 409 `quota_exceeded`
- NOTE: The exemption attaches to provisioning paths, not to admin group membership

### Requirement: REQ-QUOTA-005 Non-Destructive Grandfathering

Lowering a quota below current usage MUST NOT delete, hide, lock, or otherwise degrade
any existing dashboard or placement. Over-quota state blocks only new creations of the
over-quota kind until usage drops below the limit.

#### Scenario: Lowering the dashboard limit below usage
- GIVEN user "alice" owns 8 personal dashboards
- WHEN an admin sets `max_dashboards_per_user = 5`
- THEN all 8 dashboards MUST remain fully visible, usable, editable, and deletable
- AND alice's next creation attempt MUST return HTTP 409 with `{"limit": 5, "current": 8}`

#### Scenario: Converging back under the limit
- GIVEN alice over quota at 8 dashboards with a limit of 5
- WHEN she deletes 4 dashboards (now 4) and creates a new one
- THEN the creation MUST succeed

#### Scenario: Raising a limit back to zero restores unlimited
- GIVEN any over-quota state
- WHEN an admin sets the relevant setting back to `0`
- THEN all enforcement for that quota MUST stop immediately, with no residual state

### Requirement: REQ-QUOTA-006 Quota Status Surfacing in UI

The dashboards list response MUST include an additive
`quota: {maxDashboards, dashboardsUsed, maxWidgetsPerDashboard}` envelope field
(`0` meaning unlimited), and the frontend MUST disable creation affordances at the
limit with an explanatory tooltip. The server-side check remains authoritative; the
disabled affordance is UX, not enforcement.

#### Scenario: List response carries quota status
- GIVEN `max_dashboards_per_user = 5` and user "alice" owns 3 personal dashboards
- WHEN she fetches her dashboards list
- THEN the response envelope MUST include `"quota": {"maxDashboards": 5, "dashboardsUsed": 3, "maxWidgetsPerDashboard": 0}`

#### Scenario: Create button disabled at the limit
- GIVEN alice at 5 of 5 dashboards
- WHEN the dashboards UI renders
- THEN the "New dashboard" affordance MUST be disabled
- AND a tooltip MUST explain the limit (e.g., "You have reached the limit of 5 dashboards"), localised in `en` and `nl`

#### Scenario: Unlimited instances show no quota UI
- GIVEN both quota settings at `0`
- WHEN the dashboards UI renders
- THEN no quota counters, warnings, or disabled states MUST appear (current behaviour is pixel-identical)

#### Scenario: Race between UI state and server enforcement
- GIVEN alice's UI was loaded when she was below the limit, but she has since reached it in another tab
- WHEN she clicks the still-enabled "New dashboard" button
- THEN the server MUST return HTTP 409 `quota_exceeded`
- AND the frontend MUST render the structured error as a clear message and refresh the quota envelope

---

## Summary of `dashboard-quota-limits` Capability

Six requirements covering the two admin settings (riding the existing admin-settings
contract), service-layer enforcement of dashboard and widget counts across all
user-initiated creation paths, an explicit provisioning-path exemption for admin
rollout, non-destructive grandfathering when limits are lowered, and proactive quota
surfacing in the UI with the server remaining authoritative. Defaults (`0` = unlimited)
make the change behaviour-invariant on upgrade.

**Spec version**: draft (2026-06-11)
