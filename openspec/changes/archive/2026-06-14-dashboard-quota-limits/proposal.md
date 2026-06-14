# Dashboard Quota Limits

## Why

LaunchPad's stated audience is organisation-wide rollout, but its admin governance
stops at booleans: `allow_user_dashboards` and `allow_multiple_dashboards`
(`admin-settings` REQ-ASET-003/004). Between "exactly one dashboard" and "unlimited
dashboards, each with unlimited widgets" there is nothing. At a 2,000-seat deployment
that is a real operational problem: unbounded per-user dashboard sprawl bloats the
switcher, the admin overview, export/import payloads, and (with `dashboard-rss-feeds`,
`background-job-feed-refresh`, and proxied NC widgets) the background refresh load —
every placed widget is recurring server work. Every comparable enterprise portal
product ships numeric quota knobs; large-org admins will ask for them in the first
procurement conversation.

This change adds two server-enforced, admin-configurable quotas — maximum dashboards
per user and maximum widgets per dashboard — with non-destructive grandfathering when
limits are lowered, structured API errors, and quota status surfaced to the frontend so
the UI can disable creation affordances instead of letting users hit a wall.

## What Changes

- Add two admin settings to the existing `oc_launchpad_admin_settings` key-value table
  (no new table): `max_dashboards_per_user` and `max_widgets_per_dashboard`, both
  integers, default `0` meaning unlimited, exposed as `maxDashboardsPerUser` /
  `maxWidgetsPerDashboard` on `GET`/`PUT /api/admin/settings` alongside the existing
  four settings
- Enforce the dashboard quota server-side at every personal-dashboard creation path
  (create, duplicate, fork, import, template instantiation initiated by the user):
  exceeding the limit returns HTTP 409 with structured body
  `{"error": "quota_exceeded", "quota": "dashboards", "limit": N, "current": N}`
- Enforce the widget quota server-side at every placement-creation path (add widget,
  add tile, duplicate, import): same structured 409 with `"quota": "widgets"`
- Admin-initiated provisioning is exempt: template rollout to groups, compulsory
  widgets, and admin actions on behalf of users MUST NOT be blocked by user quotas
- Quota interaction is most-restrictive-wins: `allow_multiple_dashboards = false`
  behaves as an effective dashboard limit of 1 regardless of the numeric setting
- Lowering a limit below current usage MUST NOT delete or hide anything: existing
  over-quota data stays fully usable; only *new* creations are blocked until the user
  is back under the limit
- Surface quota status in the dashboards list response envelope
  (`quota: {maxDashboards, dashboardsUsed, maxWidgetsPerDashboard}`) so the frontend
  disables "New dashboard" / "Add widget" affordances at the limit with an explanatory
  tooltip instead of a post-hoc error

## Capabilities

### New Capabilities

- `dashboard-quota-limits` — numeric admin governance quotas with server-side
  enforcement, grandfathering, structured errors, and UI surfacing

### Modified Capabilities

- `admin-settings` — gains two setting keys following the existing REQ-ASET-001/002
  retrieval/update/validation contract (clamping, camelCase API keys, persistence);
  enumerated here as ADDED requirements in the new capability rather than edits to the
  fifteen existing REQ-ASET requirements, which are unchanged
- `dashboards` / `widgets` — creation flows gain a quota precondition; all existing
  requirements (REQ-DASH-*, widget placement contracts) are otherwise unchanged

## Impact

**Affected code:**

- `lib/Service/AdminSettingsService.php` — add the two settings to defaults, retrieval,
  update validation (integer, clamp to `[0, 10000]`), and the camelCase response map
- `lib/Service/QuotaService.php` — new small service:
  `assertCanCreateDashboard(string $userId)`,
  `assertCanAddPlacement(string $dashboardUuid)`,
  `getQuotaStatus(string $userId)`; admin-context bypass flag for provisioning paths
- `lib/Exception/QuotaExceededException.php` — carries quota kind, limit, current count
- `lib/Service/DashboardService.php` — call `assertCanCreateDashboard()` in create,
  duplicate, fork, and import paths (user-initiated only; template rollout exempt)
- `lib/Service/PlacementService.php` — call `assertCanAddPlacement()` in `addWidget`,
  `addTileFromArray`, and import paths (compulsory-widget push exempt)
- `lib/Controller/*` — map `QuotaExceededException` to HTTP 409 with the structured body
- `src/views/AdminSettings*.vue` — two new numeric inputs with "0 = unlimited" helper
  text
- `src/stores/dashboards.js` + creation UI components — consume the `quota` envelope,
  disable create affordances at limit, show i18n'd tooltip
- No migration: settings ride the existing key-value table with code-level defaults

**Affected APIs:**

- `GET`/`PUT /api/admin/settings` — two additional keys (backward compatible; clients
  ignoring them are unaffected)
- Dashboard and placement creation endpoints — new 409 `quota_exceeded` error case
- Dashboards list response — additive `quota` envelope field

**Dependencies:**

- None beyond existing LaunchPad services. Interacts with (but does not modify) the
  in-flight `allow-personal-dashboards-flag` and `multi-scope-dashboards` changes:
  quotas count *personal-scope* dashboards owned by the user, never group/admin-scope
  dashboards routed to them.

**Migration:**

- Zero-impact: no schema change, defaults preserve current unlimited behaviour exactly.
  Instances notice nothing until an admin sets a non-zero limit.

## Standards & References

- `admin-settings` spec REQ-ASET-001/002/007/014 — settings retrieval, update,
  persistence, and validation contract the two new keys follow
- `admin-settings` REQ-ASET-004 — `allow_multiple_dashboards`; most-restrictive-wins
  interaction defined in this change
- HTTP 409 Conflict for state-based rejection with a machine-readable error body
  (consistent with structured error bodies elsewhere in LaunchPad's API)
- i18n: quota error messages and tooltips in `en` + `nl` (English source strings as
  keys)
