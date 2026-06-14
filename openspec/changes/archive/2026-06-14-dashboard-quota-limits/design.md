# Design — Dashboard Quota Limits

## Context

LaunchPad's governance knobs are booleans; large deployments need numeric ceilings.
The feature is small but has four sharp edges that must be decided up front: where
enforcement lives (every creation path, or it is theatre), who is exempt (admin
provisioning must never be blocked by user quotas), what happens when a limit drops
below existing usage (never destroy data), and how the limit reaches the UI before the
user hits it (disabled affordance beats a 409 toast).

## Goals / Non-Goals

**Goals:**
- Pin the enforcement point (service layer, single QuotaService) and the exemption model.
- Define the grandfathering rule for lowered limits.
- Choose the error contract (status code + structured body).
- Define how quota status reaches the frontend.

**Non-Goals:**
- Per-group or per-user quota overrides (single instance-wide value in v1; the
  structured settings make overrides an additive follow-up).
- Storage-size quotas (bytes) — LaunchPad content is grid config and small blobs;
  count-based limits address the actual sprawl problem.
- Retention/cleanup of over-quota data — explicitly never (D3).

## Decisions

### D1: Enforcement in the service layer via one QuotaService

**Decision**: A single `QuotaService` with `assertCanCreateDashboard(userId)` and
`assertCanAddPlacement(dashboardUuid)` is called from every user-initiated creation
path in `DashboardService` (create, duplicate, fork, import) and `PlacementService`
(add widget, add tile, import). Controllers never count; they only map
`QuotaExceededException` to the HTTP response.

**Alternatives considered:**
- *Controller-level checks*: rejected — duplicate/fork/import would each need their own
  copy and one missed path makes the quota advisory (the orphan-auth failure mode:
  a check that exists but is not on every path is no check).
- *DB constraint / trigger*: rejected — counting rows with scope and soft-delete
  semantics in a portable trigger across sqlite/mysql/postgres is not realistic.

**Rationale**: The service layer is the one choke point all creation surfaces (REST,
CLI commands per the `cli-commands` spec, import) already flow through. The count query
is `COUNT(*)` on indexed owner/dashboard columns — cheap enough to run inline on
creation, which is rare relative to reads. No caching: a stale cached count that blocks
a legitimate create is worse than one extra count query.

### D2: Exemption model — admin-initiated provisioning bypasses user quotas

**Decision**: Quotas bind only user-initiated actions on personal-scope dashboards.
Exempt: template rollout to groups, compulsory-widget pushes, and any action where the
acting context is an admin provisioning path. The bypass is an explicit
`QuotaService` provisioning flag set by the provisioning call sites — never inferred
from "the current user happens to be in the admin group", so an admin creating their
own personal dashboard is still subject to the quota.

**Rationale**: Quotas are a governance tool *for* admins; a quota that blocks the
admin's own rollout is self-defeating. Compulsory widgets may push a dashboard over
`max_widgets_per_dashboard` — that is the admin's deliberate choice, and the resulting
over-quota state is handled by D3. Tying exemption to the provisioning path rather than
the caller's group keeps the rule auditable and keeps admins honest about their own
personal sprawl.

### D3: Grandfathering — lowered limits never destroy or hide data

**Decision**: If usage exceeds a (newly lowered) limit, everything existing remains
fully visible, usable, editable, and deletable. Only *new* creations of the
over-quota kind are blocked until usage drops below the limit. The admin settings UI
SHOULD show the count of currently over-quota users next to the field, but no
enforcement action beyond blocking new creations ever occurs.

**Rationale**: An admin typing "5" into a settings field must never be a destructive
act. Auto-deleting or hiding "excess" dashboards (which ones? newest? least viewed?)
is unrecoverable and arbitrary. Block-new-only converges naturally and matches user
expectations from file-storage quotas.

### D4: Error contract — HTTP 409 with a machine-readable body

**Decision**: Quota rejections return HTTP 409 Conflict with body
`{"error": "quota_exceeded", "quota": "dashboards" | "widgets", "limit": N, "current": N}`.

**Alternatives considered:** 403 (rejected: implies an authorization failure — the user
*is* allowed to create dashboards, just not more of them; would also confuse the
no-admin-idor/semantic-auth lens), 422 (rejected: the payload is valid; the conflict is
with server state), 507 (rejected: WebDAV-specific storage semantics).

**Rationale**: 409 is "request conflicts with current state of the resource", which is
exactly what a count-based ceiling is. The structured body lets the frontend and API
clients render a precise message ("You have reached the limit of 5 dashboards") without
string-parsing, and gives Newman tests a stable contract.

### D5: Proactive UI surfacing via the dashboards list envelope

**Decision**: The dashboards list response gains an additive
`quota: {maxDashboards, dashboardsUsed, maxWidgetsPerDashboard}` envelope field
(`maxDashboards`/`maxWidgetsPerDashboard` are `0` when unlimited). The frontend
disables "New dashboard" at `dashboardsUsed >= maxDashboards` (when non-zero) and
"Add widget" at the per-dashboard placement count, each with an i18n'd tooltip. The
server-side 409 remains authoritative — the disabled button is UX, not enforcement.

**Alternatives considered:** dedicated `GET /api/quota` endpoint (rejected: one more
round-trip on every dashboard load for data the list call can carry for free);
initial-state only (rejected: goes stale after create/delete without a reload, whereas
the list is re-fetched on exactly those mutations).

### D6: Interaction with existing flags — most-restrictive-wins

**Decision**: `allow_multiple_dashboards = false` acts as an effective dashboard limit
of 1 regardless of `max_dashboards_per_user`; `allow_user_dashboards = false` keeps
blocking creation outright (quota never grants what the boolean denies). With the
in-flight `multi-scope-dashboards`, quotas count only personal-scope dashboards owned
by the user — group/admin-scope dashboards routed to a user are the admin's footprint,
not the user's.

**Rationale**: A numeric quota must never *loosen* an existing restriction on upgrade;
most-restrictive-wins is the only rule that keeps current instances' behaviour
invariant when this change ships with defaults.

## Spec changes implied

All requirements are net-new in the `dashboard-quota-limits` capability. The two
settings follow the existing `admin-settings` retrieval/update/validation contract
(REQ-ASET-001/002/007/014) without modifying its text; `dashboards`/`widgets` creation
requirements gain a precondition expressed here, not edited there.

## Open follow-ups

- Per-group quota overrides (e.g., power-user groups get more) once a deployment asks.
- Admin overview column "dashboards used / limit" per user in the admin user listing.
