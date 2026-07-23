# Tasks: Service health ping

## Backend
- [x] `lib/Service/HealthPingService.php` — allow-listed server-side HTTP ping via `IClientService`; classify online / degraded / offline against `expectedStatus` + latency threshold; read/write badge state in `ICache` (TTL = interval, default 60s, min 15s); fail-closed on non-allow-listed host.
- [x] `lib/Controller/HealthPingController.php` — `#[NoAdminRequired]` `GET /api/health-ping/{placementId}`; placement view-authorization guard; return `{state, checkedAt, latencyMs, stale}`; never expose health URL or upstream body.
- [x] `lib/BackgroundJob/HealthPingRefreshJob.php` — `TimedJob` refreshing due ping-enabled placements per configured interval, honouring the host allow-list.
- [x] `appinfo/routes.php` — register the route with its auth attribute; register the background job in `appinfo/info.xml`.
- [x] Admin config `healthping_allowed_hosts`; validate `healthUrl` host against it on save AND on ping (fail-closed). (Save-time check is exposed via `POST /api/health-ping/validate`, mirroring `livetile#validateSource`; no dedicated admin-settings UI field was added for the allow-list itself — set via `occ config:app:set launchpad healthping_allowed_hosts`, same as `livetile_allowed_hosts`.)

## Frontend
- [x] Tile health-badge overlay component (`HealthPingBadge.vue`) — render state as icon + text (never colour-only); accessible checked-at / latency tooltip; poll `GET /api/health-ping/{placementId}` on the tile interval. Wired into both live tile-render paths (`Views.vue`'s main grid and `WidgetRenderer.vue`).
- [x] Tile config fields `healthPingEnabled`, `healthUrl`, `expectedStatus`, `pingInterval` in `TileEditor.vue`; persist into existing placement `content` JSON (no schema change) via a create-then-patch follow-up (mirrors the existing `addWidget` pattern) or in the same PUT on edit.
- [x] Clamp `pingInterval` to a 15s minimum / 60s default in the config UI (`TileEditor.clampPingInterval`, `HealthPingBadge.clampedInterval`).

## Testing
- [x] PHPUnit: `HealthPingService` classification (online / degraded / offline), latency-threshold degraded case, allow-list fail-closed, cache hit within TTL, stale fallback on upstream failure. 17 tests, `tests/Unit/Service/HealthPingServiceTest.php`.
- [x] PHPUnit: `HealthPingController` 403 on unauthorized placement; response shape excludes health URL and body. 8 tests, `tests/Unit/Controller/HealthPingControllerTest.php`.
- [x] PHPUnit: `HealthPingRefreshJob` only refreshes due, ping-enabled placements and skips non-allow-listed hosts (covered inside `HealthPingServiceTest`, since the job itself is a thin delegating shell); 3 job-level tests in `tests/Unit/BackgroundJob/HealthPingRefreshJobTest.php` cover interval + delegation + exception-swallowing.
- [x] Vitest: badge renders icon+text per state (not colour-only); interval clamp; tooltip content — `HealthPingBadge.spec.js` (12 tests). Config validation (interval clamp, save-time host check, toggle) — `TileEditor.spec.js` health-ping block (10 tests).
- [ ] Playwright: enable ping on a tile against a stub, confirm the online badge renders and flips to offline when the stub is down. NOT DONE — out of scope for this pass (unit tests only per task instructions); left for a follow-up e2e pass.

## Docs
- [x] Add a "Service health ping" section to dashboard-authoring docs; document the host allow-list and the interval bounds. `docs/features/service-health-ping.md`, cross-linked from `tiles.md`'s sibling `live-data-tile.md`.

## Out of scope (follow-ups)
- Multi-step / content-assertion checks — `healthping-assertions`.
- Alerting on state change — `healthping-alerts`.
- Uptime history charts — `healthping-history`.
- Authenticated pings with stored credentials.
