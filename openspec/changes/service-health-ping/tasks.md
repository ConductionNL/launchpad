# Tasks: Service health ping

## Backend
- [ ] `lib/Service/HealthPingService.php` — allow-listed server-side HTTP ping via `IClientService`; classify online / degraded / offline against `expectedStatus` + latency threshold; read/write badge state in `ICache` (TTL = interval, default 60s, min 15s); fail-closed on non-allow-listed host.
- [ ] `lib/Controller/HealthPingController.php` — `#[NoAdminRequired]` `GET /api/health-ping/{placementId}`; placement view-authorization guard; return `{state, checkedAt, latencyMs, stale}`; never expose health URL or upstream body.
- [ ] `lib/BackgroundJob/HealthPingRefreshJob.php` — `TimedJob` refreshing due ping-enabled placements per configured interval, honouring the host allow-list.
- [ ] `appinfo/routes.php` — register the route with its auth attribute; register the background job in `appinfo/info.xml`.
- [ ] Admin config `healthping_allowed_hosts`; validate `healthUrl` host against it on save AND on ping (fail-closed).

## Frontend
- [ ] Tile health-badge overlay component — render state as icon + text (never colour-only); accessible checked-at / latency tooltip; poll `GET /api/health-ping/{placementId}` on the tile interval.
- [ ] Tile config fields `healthPingEnabled`, `healthUrl`, `expectedStatus`, `pingInterval`; persist into existing `widgetContent` JSON (no schema change).
- [ ] Clamp `pingInterval` to a 15s minimum / 60s default in the config UI.

## Testing
- [ ] PHPUnit: `HealthPingService` classification (online / degraded / offline), latency-threshold degraded case, allow-list fail-closed, cache hit within TTL, stale fallback on upstream failure.
- [ ] PHPUnit: `HealthPingController` 403 on unauthorized placement; response shape excludes health URL and body.
- [ ] PHPUnit: `HealthPingRefreshJob` only refreshes due, ping-enabled placements and skips non-allow-listed hosts.
- [ ] Vitest: badge renders icon+text per state (not colour-only); interval clamp; tooltip content.
- [ ] Playwright: enable ping on a tile against a stub, confirm the online badge renders and flips to offline when the stub is down.

## Docs
- [ ] Add a "Service health ping" section to dashboard-authoring docs; document the host allow-list and the interval bounds.

## Out of scope (follow-ups)
- Multi-step / content-assertion checks — `healthping-assertions`.
- Alerting on state change — `healthping-alerts`.
- Uptime history charts — `healthping-history`.
- Authenticated pings with stored credentials.
