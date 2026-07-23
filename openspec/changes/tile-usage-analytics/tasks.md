# Tasks: Tile usage analytics

## Backend
- [x] Migration — create `oc_launchpad_tile_clicks` (`id`, `placementUuid` VARCHAR 36, `dashboardUuid` VARCHAR 36, `clickBucket` DATE, `clickCount` INT DEFAULT 0, `uniqueActorCount` INT DEFAULT 0); composite unique index `(placementUuid, clickBucket)`; index `(clickBucket)`. Identical semantics across SQLite/MySQL/PostgreSQL. (`Version002006Date20260723000000` + `TileClicksTableBuilder`; not exercised against a live DB in this worktree — schema reviewed by hand against `DashboardViewsTableBuilder`, `lib/Migration/` is excluded from the PHPUnit coverage source list in this repo so no dedicated migration test exists for the sibling table either.)
- [x] `lib/Service/TileAnalyticsService.php` — record a click (increment-in-place per `(placementUuid, clickBucket)`); unique-actor dedup via the existing salted-daily-hash in `ICache` (TTL = seconds to next UTC midnight), reusing `UniqueViewerDedup` unchanged; no-op when `analytics_enabled=false` or the actor's `analytics_optout=true` (delegates to `AnalyticsService::isGloballyEnabled()`/`isUserOptedOut()` — no local config read); never persist per-event rows.
- [x] `TileAnalyticsService` query methods — top tiles (date-ranged, limit), per-dashboard tile breakdown, CSV rows.
- [x] `lib/Controller/TileAnalyticsController.php` — `#[NoAdminRequired]` `POST /api/tile-click/{placementId}` (returns 204, no body); admin-guarded `GET` top-tiles, per-dashboard breakdown, and CSV export. Also added `GET /api/tile-analytics/config` (not in the original task list) so the frontend hook can suppress the record call without duplicating the enable/opt-out gate logic client-side.
- [x] `appinfo/routes.php` — register the record route (`#[NoAdminRequired]`) and the admin-guarded report/CSV routes with auth attributes. Verified with `grep -n tile-click appinfo/routes.php` — all new routes registered before the catch-all `page#deepLink` route.
- [x] Reuse the existing `SaltRotationJob` unchanged for the daily salt (shared with dashboard-view analytics) — file untouched.
- [x] Extend the existing analytics retention-purge job to also delete `oc_launchpad_tile_clicks` rows older than `analytics_retention_days` (clamped `[30, 3650]`) in the same run.

## Frontend
- [x] Lightweight client hook (`src/composables/useTileClickTracking.js`) — fire `POST /api/tile-click/{placementId}` once on tile activation (click / keyboard), async and non-blocking; suppress when tracking is not active, checked via the new `GET /api/tile-analytics/config` endpoint (module-level cached; fails open on a config-fetch error so a network blip never silently disables analytics — the server still enforces the gates authoritatively).
- [x] Wire the hook into tile/widget activation (`TileWidget.vue`'s `@click` on the tile `<a>`, covering both mouse and keyboard-Enter activation on the native anchor) without altering existing navigation/click-through behaviour. NOTE: only the "Custom Tile Widget" legacy render path (`isTileWidget` in `WidgetRenderer.vue`) was wired — this is the sole tile-click surface; the registry-driven custom-widget renderers (`widgetRegistry.js`) are a distinct capability (widgets, not tiles — see `docs/widgets-vs-tiles.md`) and are out of scope for this change.

## Testing
- [x] PHPUnit: recording increments `clickCount` in place; same actor same day increments `clickCount` but `uniqueActorCount` once; different actors increment `uniqueActorCount` separately. (`TileAnalyticsServiceTest`)
- [x] PHPUnit: recording is a no-op (no counter change, no cache write) when `analytics_enabled=false` and when the actor's `analytics_optout=true`; endpoint still returns 204. (`TileAnalyticsServiceTest` for the no-op; `TileAnalyticsControllerTest::testRecordClickReturns204EvenWhenServiceNoOps` for the 204 contract)
- [x] PHPUnit: no per-event rows are persisted — only aggregate `(placementUuid, clickBucket)` rows exist. (`TileAnalyticsServiceTest::testRecordClickNeverCallsAnythingOtherThanUpsert` — asserts `upsertClick()` is the only mapper write path; the mapper itself has no other insert/write method, mirroring `DashboardViewMapper`)
- [x] PHPUnit: top-tiles and per-dashboard breakdown queries filter by date range and sort correctly; admin-only GET endpoints → 403 for non-admins; CSV shape + filename. (`TileAnalyticsServiceTest` for date-range delegation + CSV shape/filename; `TileAnalyticsControllerTest` for the 403 path on all three admin endpoints. Actual SQL `ORDER BY`/date-range behaviour is exercised via mocked mapper calls only, consistent with the existing `AnalyticsServiceTest` precedent in this repo — no live-DB integration test exists for either the dashboard or tile mapper in this suite.)
- [x] PHPUnit: retention purge deletes tile rows older than the configured window and preserves in-window rows; idempotent. (`PurgeViewsJobTest` — new file, none existed for the sibling job either; asserts both mappers are called with the same cutoff, and that a second run raises no error)
- [x] Vitest: client hook fires exactly once per activation and is suppressed when analytics disabled. (`useTileClickTracking.spec.js` — 6 tests, also covers fail-open on config error, never-throws on record-POST rejection, and shared single config fetch across concurrent activations)

## Docs
- [x] Document tile usage analytics as an extension of dashboard-view analytics: shared `analytics_enabled`/`analytics_optout`, shared salt + purge, admin top-tiles/per-dashboard/CSV reports. (`docs/features/tiles.md` — new "Usage analytics" section + endpoint table)

## Out of scope (follow-ups)
- Per-user / cohort-level tile analytics (aggregate-only by design).
- Real-time / streaming tile dashboards.
- A separate tile-analytics opt-out (existing `analytics_optout` governs both).
