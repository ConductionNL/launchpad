## ADDED Requirements

### Requirement: REQ-TANLT-001 Tile Clicks Table Schema

The system MUST track per-tile (per-widget-placement) click events in a dedicated aggregate table with daily UTC buckets and privacy-preserving counters, mirroring the `oc_launchpad_dashboard_views` shape. No per-event rows are persisted.

#### Scenario: Create tile clicks table with correct schema

- GIVEN the system has no `oc_launchpad_tile_clicks` table
- WHEN the schema migration runs
- THEN the system MUST create `oc_launchpad_tile_clicks` with columns:
  - `id` (INT AUTO_INCREMENT, PRIMARY KEY)
  - `placementUuid` (VARCHAR 36, NOT NULL — the widget placement / tile clicked)
  - `dashboardUuid` (VARCHAR 36, NOT NULL — the dashboard the placement belongs to)
  - `clickBucket` (DATE, NOT NULL — calendar date in UTC)
  - `clickCount` (INT DEFAULT 0, NOT NULL)
  - `uniqueActorCount` (INT DEFAULT 0, NOT NULL)
- AND the system MUST create a composite unique index on `(placementUuid, clickBucket)`
- AND the system MUST create an index on `(clickBucket)` for retention and date-range queries

#### Scenario: One row per tile per day

- GIVEN a placement exists with UUID "plc-1" on dashboard "dsh-1"
- WHEN a click is recorded for "plc-1" on 2026-05-01
- AND another click is recorded for "plc-1" on 2026-05-01
- THEN the system MUST maintain only ONE row `(placementUuid = 'plc-1', clickBucket = '2026-05-01')`
- AND subsequent clicks on the same date MUST increment counters in that single row (no new rows created)
- AND the system MUST NOT persist any per-event row

#### Scenario: Schema supports multiple databases

- GIVEN the Nextcloud instance may use SQLite, MySQL, or PostgreSQL
- WHEN the migration runs on any of these databases
- THEN the table MUST be created with identical semantics
- AND the composite unique index MUST enforce uniqueness on all three backends

### Requirement: REQ-TANLT-002 Tile Click Record Endpoint

The system MUST expose an authenticated endpoint `POST /api/tile-click/{placementId}` (`#[NoAdminRequired]`) that records a click on a tile. It MUST return HTTP 204 with no body and MUST increment the click counter for today's bucket, deduplicating unique actors using the SAME salted-daily-hash mechanism as dashboard-view analytics (REQ-ANLT-003): `sha256(userId || dailySalt)` stored only in `ICache` with TTL = seconds until the next UTC midnight.

#### Scenario: Authenticated user records a tile click

- GIVEN a logged-in user "alice" activates a tile with placement UUID "plc-101"
- WHEN the frontend sends `POST /api/tile-click/plc-101` with body `{}`
- THEN the system MUST return HTTP 204 (No Content) with no body
- AND the system MUST increment `clickCount` in the `oc_launchpad_tile_clicks` row for today

#### Scenario: Same actor same day increments clickCount but uniqueActorCount once

- GIVEN user "alice" clicks tile "plc-102" twice on 2026-05-01
- WHEN both clicks are recorded
- THEN `clickCount = 2` and `uniqueActorCount = 1` for `(placementUuid='plc-102', clickBucket='2026-05-01')`

#### Scenario: Different actors same day increment uniqueActorCount separately

- GIVEN user "alice" and user "bob" each click tile "plc-103" on 2026-05-01
- WHEN both clicks are recorded
- THEN `uniqueActorCount = 2` for `(placementUuid='plc-103', clickBucket='2026-05-01')`

#### Scenario: Unauthenticated request is rejected

- GIVEN an unauthenticated request (no session or bearer token)
- WHEN it sends `POST /api/tile-click/plc-101`
- THEN the system MUST return HTTP 401 (Unauthorized)
- AND no click is recorded

### Requirement: REQ-TANLT-003 Reuse of Global and Per-User Opt-Out

Tile click recording MUST reuse the existing `launchpad.analytics_enabled` global setting and the existing per-user `launchpad.analytics_optout` preference. No new setting or opt-out surface is introduced. When either gate is off, `POST /api/tile-click/{placementId}` MUST be a no-op that still returns HTTP 204 — no counter change, no cache write, and no per-event row.

#### Scenario: Global disable makes recording a no-op

- GIVEN the admin has set `launchpad.analytics_enabled = false`
- WHEN user "alice" sends `POST /api/tile-click/plc-104`
- THEN the system MUST return HTTP 204
- AND the system MUST NOT increment any counter in `oc_launchpad_tile_clicks`
- AND the system MUST NOT add any hash to the cache
- AND the system MUST NOT persist any per-event row

#### Scenario: Per-user opt-out makes that user's clicks a no-op

- GIVEN user "eve" has set `launchpad.user_setting.analytics_optout = true`
- WHEN she sends `POST /api/tile-click/plc-105`
- THEN the system MUST return HTTP 204
- AND the system MUST NOT increment any counter and MUST NOT add her hash to the cache

#### Scenario: Opt-out is per-user, not global, and no new setting exists

- GIVEN user "eve" has opted out via `analytics_optout` and user "frank" has not
- WHEN both click tile "plc-106" on 2026-05-01
- THEN eve's click MUST be ignored entirely and frank's click MUST be recorded (`clickCount = 1`, `uniqueActorCount = 1`)
- AND the system MUST NOT read or require any tile-specific opt-out key (the same `analytics_optout` governs both view and tile tracking)

#### Scenario: Default enabled

- GIVEN a fresh installation with no explicit `analytics_enabled` setting
- WHEN a user clicks a tile
- THEN the system MUST treat `analytics_enabled` as `true` and record the click normally

### Requirement: REQ-TANLT-004 Tile Analytics Query Endpoints

The system MUST expose admin-only endpoints that report tile usage: a top-tiles ranking (instance-wide, date-ranged) and a per-dashboard tile breakdown. Non-admin callers MUST receive HTTP 403.

#### Scenario: Top tiles by click count

- GIVEN 4 tiles have cumulative 7-day click counts: 120, 80, 40, 15
- WHEN an admin sends `GET /api/admin/analytics/tiles/top?period=7d&limit=10`
- THEN the system MUST return HTTP 200 with tile objects sorted by `clickCount` descending: [120, 80, 40, 15]
- AND each object MUST include `placementUuid`, `dashboardUuid`, `clickCount` (period sum), and `uniqueActorCount` (period sum)

#### Scenario: Per-dashboard tile breakdown

- GIVEN dashboard "dsh-9" has 3 tiles with click activity in the last 30 days
- WHEN an admin sends `GET /api/admin/analytics/tiles/by-dashboard/dsh-9?period=30d`
- THEN the system MUST return HTTP 200 with a per-tile breakdown for that dashboard over the period
- AND only rows where `clickBucket >= CURRENT_DATE - 30 days` MUST be included

#### Scenario: Period parameter filters the date range

- GIVEN a tile has click rows for 2026-04-01, 2026-04-15, and 2026-05-01
- WHEN an admin queries top tiles with `period=7d` on 2026-05-01
- THEN the system MUST sum only rows where `clickBucket >= '2026-04-24'`
- AND rows from 2026-04-01 and 2026-04-15 MUST NOT be included

#### Scenario: Non-admin receives 403

- GIVEN a logged-in non-admin user
- WHEN they send `GET /api/admin/analytics/tiles/top?period=7d`
- THEN the system MUST return HTTP 403 (Forbidden)

### Requirement: REQ-TANLT-005 Tile Analytics CSV Export, Retention, and Salt Reuse

The system MUST expose an admin-only CSV export of tile analytics, MUST reuse the existing `SaltRotationJob` for the daily unique-actor salt (no separate salt), and MUST extend the existing analytics retention-purge job to also purge `oc_launchpad_tile_clicks` rows older than the configured retention window (`launchpad.analytics_retention_days`, clamped `[30, 3650]`).

#### Scenario: CSV export contains tile statistics

- GIVEN tile click data exists for multiple tiles
- WHEN an admin sends `GET /api/admin/analytics/tiles/export?period=30d`
- THEN the system MUST return HTTP 200 with `Content-Type: text/csv`
- AND a `Content-Disposition: attachment; filename=tile-analytics-2026-05-01.csv` header (today's UTC date)
- AND the CSV MUST contain columns: `placementUuid`, `dashboardUuid`, `clickBucket`, `clickCount`, `uniqueActorCount`
- AND rows MUST be sorted by `placementUuid`, then `clickBucket` ascending, with a header row

#### Scenario: Non-admin cannot export

- GIVEN a logged-in non-admin user
- WHEN they send `GET /api/admin/analytics/tiles/export?period=30d`
- THEN the system MUST return HTTP 403 and no CSV is generated

#### Scenario: Daily salt is shared, not duplicated

- GIVEN `SaltRotationJob` has stored `launchpad.analytics_dailysalt`
- WHEN the system computes a tile-click unique-actor hash
- THEN it MUST use the SAME `launchpad.analytics_dailysalt` value
- AND the system MUST NOT introduce a separate tile-analytics salt or a second rotation job

#### Scenario: Retention purge also deletes tile rows

- GIVEN the admin has set `launchpad.analytics_retention_days = 90`
- WHEN the existing analytics purge job runs
- THEN it MUST delete `oc_launchpad_tile_clicks` rows with `clickBucket < CURRENT_DATE - 90 days`
- AND rows within the last 90 days MUST be preserved
- AND the purge MUST be idempotent (re-running deletes nothing already deleted and raises no error)
