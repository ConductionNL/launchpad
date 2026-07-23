# Tasks: Tile usage analytics

## Backend
- [ ] Migration — create `oc_launchpad_tile_clicks` (`id`, `placementUuid` VARCHAR 36, `dashboardUuid` VARCHAR 36, `clickBucket` DATE, `clickCount` INT DEFAULT 0, `uniqueActorCount` INT DEFAULT 0); composite unique index `(placementUuid, clickBucket)`; index `(clickBucket)`. Identical semantics across SQLite/MySQL/PostgreSQL.
- [ ] `lib/Service/TileAnalyticsService.php` — record a click (increment-in-place per `(placementUuid, clickBucket)`); unique-actor dedup via the existing salted-daily-hash in `ICache` (TTL = seconds to next UTC midnight); no-op when `analytics_enabled=false` or the actor's `analytics_optout=true`; never persist per-event rows.
- [ ] `TileAnalyticsService` query methods — top tiles (date-ranged, limit), per-dashboard tile breakdown, CSV rows.
- [ ] `lib/Controller/TileAnalyticsController.php` — `#[NoAdminRequired]` `POST /api/tile-click/{placementId}` (returns 204, no body); admin-guarded `GET` top-tiles, per-dashboard breakdown, and CSV export.
- [ ] `appinfo/routes.php` — register the record route (`#[NoAdminRequired]`) and the admin-guarded report/CSV routes with auth attributes.
- [ ] Reuse the existing `SaltRotationJob` unchanged for the daily salt (shared with dashboard-view analytics).
- [ ] Extend the existing analytics retention-purge job to also delete `oc_launchpad_tile_clicks` rows older than `analytics_retention_days` (clamped `[30, 3650]`) in the same run.

## Frontend
- [ ] Lightweight client hook — fire `POST /api/tile-click/{placementId}` once on tile activation (click / keyboard), async and non-blocking; suppress when `analytics_enabled=false` (seen via config).
- [ ] Wire the hook into tile/widget activation without altering existing navigation/click-through behaviour.

## Testing
- [ ] PHPUnit: recording increments `clickCount` in place; same actor same day increments `clickCount` but `uniqueActorCount` once; different actors increment `uniqueActorCount` separately.
- [ ] PHPUnit: recording is a no-op (no counter change, no cache write) when `analytics_enabled=false` and when the actor's `analytics_optout=true`; endpoint still returns 204.
- [ ] PHPUnit: no per-event rows are persisted — only aggregate `(placementUuid, clickBucket)` rows exist.
- [ ] PHPUnit: top-tiles and per-dashboard breakdown queries filter by date range and sort correctly; admin-only GET endpoints → 403 for non-admins; CSV shape + filename.
- [ ] PHPUnit: retention purge deletes tile rows older than the configured window and preserves in-window rows; idempotent.
- [ ] Vitest: client hook fires exactly once per activation and is suppressed when analytics disabled.

## Docs
- [ ] Document tile usage analytics as an extension of dashboard-view analytics: shared `analytics_enabled`/`analytics_optout`, shared salt + purge, admin top-tiles/per-dashboard/CSV reports.

## Out of scope (follow-ups)
- Per-user / cohort-level tile analytics (aggregate-only by design).
- Real-time / streaming tile dashboards.
- A separate tile-analytics opt-out (existing `analytics_optout` governs both).
