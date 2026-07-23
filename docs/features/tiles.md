# Custom Tiles

Custom tiles are user-created shortcut cards that provide quick access to Nextcloud apps or external URLs.

## Features

- Create reusable tile definitions with icon, colors, and link
- Icon types: CSS class, URL, emoji, SVG path
- Link types: Nextcloud app route or external URL
- Tile placements store independent copies of tile data
- Changes to tile definitions do not propagate to existing placements

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tiles` | List user tiles |
| POST | `/api/tiles` | Create new tile |
| PUT | `/api/tiles/{id}` | Update tile |
| DELETE | `/api/tiles/{id}` | Delete tile |
| POST | `/api/dashboard/{id}/tile` | Place tile on dashboard |

## Usage analytics

Tile usage analytics is a strict, downward **extension** of the
[dashboard view-analytics](../../openspec/specs/dashboard-view-analytics/spec.md)
capability at the tile/widget-placement grain — it does not introduce
any new privacy machinery, only a finer-grained aggregate table.

- Aggregate-only counts stored in `oc_launchpad_tile_clicks`, one row
  per `(placementUuid, clickBucket)` per UTC day. No per-event rows
  are ever persisted.
- Unique-actor dedup reuses the SAME salted-daily-hash mechanism
  (`sha256(userId || dailySalt)`, cached in `ICache` only) and the
  SAME `SaltRotationJob` as dashboard views — no second salt or
  rotation job.
- Reuses the SAME `launchpad.analytics_enabled` (global) and
  `launchpad.analytics_optout` (per-user) settings. There is no
  separate tile-analytics opt-out.
- The existing analytics retention-purge job is extended to also
  purge `oc_launchpad_tile_clicks` rows older than
  `launchpad.analytics_retention_days` in the same run — no second
  purge job.
- The frontend fires a fire-and-forget `POST /api/tile-click/{id}` on
  tile activation (click or keyboard Enter), gated by
  `GET /api/tile-analytics/config` so tracking is suppressed
  client-side when analytics is disabled or the user opted out.

### API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/tile-click/{placementId}` | Any authed user | Record a click (always 204; no-op when disabled/opted out) |
| GET | `/api/tile-analytics/config` | Any authed user | Whether tracking is active for the caller |
| GET | `/api/admin/analytics/tiles/top` | Admin | Top-N tiles by click count for a period |
| GET | `/api/admin/analytics/tiles/by-dashboard/{uuid}` | Admin | Per-dashboard tile breakdown |
| GET | `/api/admin/analytics/tiles/export` | Admin | CSV export |

## Screenshot

![Dashboard with Tiles](/screenshots/launchpad-dashboard-overview.png)
