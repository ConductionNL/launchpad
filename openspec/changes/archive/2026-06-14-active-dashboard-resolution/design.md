# Design — active-dashboard-resolution

## Status

`pr-created`

## Summary

Implements the deterministic 7-step precedence chain that LaunchPad uses to
resolve the "active" dashboard when a user opens the workspace, and exposes a
`POST /api/dashboards/active` endpoint so the frontend can persist the user's
last-used dashboard across page loads.

## Changes

### Backend

**`lib/Service/DashboardService.php`**
- `ACTIVE_DASHBOARD_UUID_PREF_KEY = 'active_dashboard_uuid'` constant
- `resolveActiveDashboard(string $userId, ?string $primaryGroupId): ?array`
  — walks the 7-step REQ-DASH-018 chain; side-effect-free on read except for
  the stale-pref auto-clear (step 1 miss → `IConfig::deleteUserValue` + WARNING log)
- `setActivePreference(string $userId, string $uuid): void`
  — writes via `IConfig::setUserValue`; empty string clears the preference

**`lib/Controller/DashboardApiController.php`**
- `setActiveDashboard(?string $uuid): JSONResponse` mapped to `POST /api/dashboards/active`
  — accepts any UUID, no existence check, returns `{status: 'success'}`

**`lib/Controller/PageController.php`**
- Calls `resolveActiveDashboard($userId, $primaryGroupId)` on first render and
  pushes `activeDashboardId` ('' when null) + `dashboardSource` into initial-state
  JSON via `IInitialState`

**`appinfo/routes.php`**
- `POST /api/dashboards/active` registered before the `{groupId}` wildcard routes

### Frontend

**`src/stores/dashboard.js`**
- `resolveActive` getter mirrors the 7-step precedence for client-side resolution
  after store mutations (dashboard delete, group dashboards refreshed)
- `switchDashboard(dashboardId)` action — updates store state and calls
  `persistActivePreference()` fire-and-forget on switch
- `persistActivePreference(uuid)` — POSTs to `/api/dashboards/active`; failure
  logged but does not block the UI

**`src/views/Views.vue`**
- Empty-state UI rendered when `activeDashboard` is null, gated by
  `allowUserDashboards` for the "Create dashboard" affordance

## Declarative-vs-imperative decision

The `resolveActiveDashboard` method reads from `oc_preferences` via `IConfig`,
which is a standard Nextcloud user-preference store — not an OpenRegister schema.
Per the project's ADR-031 guidance this preference storage is appropriate here
because:
1. It is per-user, not per-object — OpenRegister objects have no per-user row to flip
2. The key (`active_dashboard_uuid`) is a simple scalar, not structured domain data
3. The resolver needs to walk across all three dashboard scopes (personal, group, default)
   in a single pass — schema-declarative aggregations don't compose across scopes this way

## Stale-pref cleanup rationale

Stale preferences are cleaned per-request (write-on-read in step 1 of the resolver)
rather than via a background cron job because:

- **Load is bounded by login frequency** — `IConfig::deleteUserValue` is a single
  indexed DELETE against `oc_preferences`. A user who logs in 20 times a day with a
  stale pref triggers at most 20 deletes; that number is negligible compared to
  the number of dashboard reads the same user triggers.
- **Correctness is immediate** — a cron-based cleanup would leave a window
  (cron interval) where the resolver could incorrectly return `null` (step 7)
  instead of falling through to the correct dashboard (step 2–6). Per-request
  cleanup ensures correctness on the very first load after the dashboard disappears.
- **No extra infrastructure** — cron jobs require scheduler registration, job
  locks, and failure handling. A simple write-on-read sidesteps all of that for
  a problem whose scale does not justify the complexity.

If `IConfig::deleteUserValue` ever becomes a hotspot (e.g. in a deployment with
millions of users switching groups daily), a background job can be added as a
follow-up change without breaking the per-request path.

## Reuse Analysis

- `IConfig::setUserValue` / `getUserValue` / `deleteUserValue` — already injected
  into `DashboardService`; no new dependency
- `DashboardService::getVisibleToUser` — re-used to build the UUID index for
  O(1) pref lookup in `resolveActiveDashboard`
- `AdminTemplateService::resolvePrimaryGroup` — re-used in `PageController` to
  determine the primary group before calling the resolver
- `InitialStateBuilder` — re-used to push `activeDashboardId` and `dashboardSource`
  into the initial state; no direct `IInitialState::provideInitialState` calls

## Seed Data

No schema changes — the preference lives in `oc_preferences` via `IConfig`.
No seed data required for this change.

## MCP Coverage

No MCP surface — the active-dashboard resolver is an internal workspace
initialisation concern; there is no user-callable action that maps to a
tool in the Hydra MCP vocabulary.
