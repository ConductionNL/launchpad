# Admin group-management UI

## Why

The `multi-scope-dashboards` change shipped the backend + API for
group-shared dashboards (CRUD scoped to a Nextcloud group, plus the
`/api/dashboards/visible` union endpoint). Task 13 of that change
explicitly deferred the admin-facing UI so the API-first slice could
ship without UX dependencies. Today an admin who wants to create a
group-shared dashboard must call the REST endpoints directly —
acceptable for engineering, not acceptable for the operator persona
that owns "set up the marketing department's shared dashboard."

## What Changes

- Add a new admin settings tab **Group dashboards** under the existing
  Beheer tab strip (alongside Templates / Operations / Roles &
  Permissions / Versioning & Audit / Sharing / Org Navigation / Demo
  Data; the seven-tab grid grows to eight).
- The tab lists every Nextcloud group + the synthetic `default`
  sentinel, with each row showing the count of group-shared
  dashboards and a quick-action menu (View / Create / Manage).
- "Create" opens an `NcDialog` modal that wraps the existing
  `POST /api/dashboards/group/{groupId}` endpoint (name, icon, layout
  template selector, default flag).
- "Manage" opens a per-group page (or modal) listing the group's
  dashboards with rename / delete / set-default actions, all backed by
  the existing `GET|PUT|DELETE /api/dashboards/group/{groupId}/{uuid}`
  endpoints; respects the last-in-group delete guard documented in
  `multi-scope-dashboards`.
- Non-admin → 403 at the route guard (the controller endpoints already
  enforce admin-only on mutation).
- i18n: `nl_NL` + `en_US` for all new strings (`Group dashboards`,
  `Create group dashboard`, `Manage`, `Default group`, etc.).

## Capabilities

### New Capabilities

(none — the change is a UI wrapper around existing
multi-scope-dashboards endpoints)

### Modified Capabilities

- `dashboards`: adds REQ-DASH-015 (admin group-management UI exposes
  the group-shared CRUD surface) — no new backend semantics, only the
  admin-UX surface that closes the gap multi-scope-dashboards Task 13
  flagged.

## Impact

**Code.** New Vue components under `src/components/admin/tabs/`
(`GroupDashboardsTab.vue`) + `src/components/admin/group/` (per-group
detail modal + list rows). New Pinia store slice under
`src/stores/groupDashboards.js` that wraps the existing
`/api/dashboards/group/...` endpoints — no Vuex/store schema change.
No backend code edits beyond i18n strings.

**Tests.** Vitest for the new components (`GroupDashboardsTab` row
rendering, `Create` modal happy path, `Manage` delete guard). Newman
folder for the admin endpoints (already covered by
multi-scope-dashboards). Playwright e2e under the gate-19
honest-coverage program.

**Docs.** New section in `docs/architecture.md` and
`docs/tutorials/admin/` covering the group-dashboards workflow.

**Deferral.** Bulk operations (rename N dashboards, move dashboards
between groups) are explicitly out of scope for this change — file a
follow-up if/when that surface is requested.

## Trigger

This change is the direct follow-up to `multi-scope-dashboards`
Task 13 (filed 2026-06-10 per the lp-fin8 finisher run).
