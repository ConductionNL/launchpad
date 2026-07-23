# Tile usage analytics — per-tile click counts, privacy-preserving and admin-reportable

LaunchPad already ships privacy-preserving **dashboard view analytics** (`dashboard-view-analytics`): daily-bucketed aggregate counts, salted-daily-hash unique-actor dedup, admin CSV export, a retention purge job, and a global + per-user opt-out. That capability answers "which dashboards get opened". It cannot answer the next question a teamleider asks in a KPI review: "which **tiles** do people actually click?".

Market research (Spectr `lp-tile-usage-analytics`, demand **8**, competitorCoverage **8**) shows the leading intranet suites — Happeo, Unily, Staffbase — report per-tile / per-widget usage, and that this metric is what justifies continued intranet spend to management. LaunchPad has the aggregation machinery already; this change **extends** it down to the tile/widget-placement grain.

This change adds aggregate-only, GDPR-clean per-tile click counting that reuses the existing analytics infrastructure wholesale: the same salted-daily-hash unique-actor dedup, the same `analytics_enabled` global setting, the same per-user `analytics_optout` preference, the same `SaltRotationJob`, and the same retention-purge job (extended to purge the new table). No per-event rows are ever persisted; admins get top-tiles and per-dashboard tile breakdowns plus CSV.

## Affected code units

- **Migration** — new aggregate table `oc_launchpad_tile_clicks` with columns `placementUuid`, `dashboardUuid`, `clickBucket` (DATE, UTC), `clickCount` (INT), `uniqueActorCount` (INT); composite unique index on `(placementUuid, clickBucket)` and an index on `(clickBucket)` for retention/date-range queries. Mirrors the `oc_launchpad_dashboard_views` shape.
- `lib/Service/TileAnalyticsService.php` — new. Records a click (increment-in-place per `(placementUuid, clickBucket)`), unique-actor dedup via the existing salted-daily-hash in `ICache`, honours `analytics_enabled` + per-user `analytics_optout` (no-op when off). Query methods: top tiles, per-dashboard tile breakdown, CSV rows.
- `lib/Controller/TileAnalyticsController.php` — new. `#[NoAdminRequired]` `POST /api/tile-click/{placementId}` to record a click (returns 204, always no-body); admin-guarded `GET` endpoints for top-tiles, per-dashboard tile breakdown, and CSV export.
- **Client hook** — a lightweight frontend hook that fires the record call once on tile activation (click / keyboard activation), async and non-blocking, suppressed when analytics is disabled.
- **Reuse of existing jobs** — the existing `SaltRotationJob` is reused unchanged for the daily salt; the existing retention-purge job is extended to also purge `oc_launchpad_tile_clicks` beyond the configured retention window.

## Why a new change

Tile usage is a strict, downward *extension* of an already-shipped capability, not a new analytics model. Filing it as its own change keeps the extension reviewable — the new table, the record endpoint, and the tile-grain reports — while making the reuse contract explicit and enforceable: it MUST reuse the existing `analytics_enabled` + `analytics_optout` settings (no new opt-out surface), the existing salt rotation, and the existing purge job. Bundling it into the dashboard-views capability would blur that "reuse, don't reinvent" boundary; a separate change lets the tests assert that the same privacy guarantees (aggregate-only, salted-daily dedup, honour opt-out) hold at the tile grain.

## Approach

- **Aggregate-only, same shape as views.** One row per `(placementUuid, clickBucket)`; additional clicks on the same day increment `clickCount` in place. Unique actors deduped via `sha256(userId || dailySalt)` held only in `ICache` with TTL = seconds-to-next-UTC-midnight — identical to REQ-ANLT-003. No per-event rows, ever.
- **Reuse the gates, not new ones.** `analytics_enabled = false` (global) and per-user `analytics_optout = true` both make `POST /api/tile-click/{placementId}` a no-op that still returns 204. No new settings keys are introduced.
- **Record endpoint is user-facing, reports are admin-only.** `#[NoAdminRequired]` on the record endpoint (any authed user's own clicks); admin-guarded GET endpoints for top-tiles, per-dashboard breakdown, and CSV.
- **Reuse SaltRotationJob + purge job.** The daily salt is shared with dashboard-view analytics; the retention purge job is extended to delete `oc_launchpad_tile_clicks` rows older than the configured window in the same run.
- **Reports serve the KPI flow.** Top tiles (instance-wide, date-ranged) and per-dashboard tile breakdown give a teamleider the tile-engagement numbers that justify intranet spend; CSV export mirrors the existing analytics export.

## Notes

- Out of scope: per-user or cohort-level tile analytics (aggregate-only by design).
- Out of scope: real-time tile dashboards / streaming (batch aggregate reads only).
- Out of scope: a separate tile-analytics opt-out — the existing `analytics_optout` governs both view and tile tracking.
- Reuses the `dashboard-view-analytics` retention setting (`analytics_retention_days`, clamped `[30, 3650]`) for the tile table purge; no new retention knob.
