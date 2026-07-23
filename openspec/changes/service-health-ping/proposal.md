# Service health ping — online/offline/degraded badge on a tile

Municipal IT landing pages built on LaunchPad answer a recurring question: *"is de zaakapplicatie bereikbaar?"* Today a tile is a static link with no signal about whether the service behind it is up. Every serious dashboard competitor ships this: Homarr's app-status pings, Dashy's status-checks, gethomepage's site-monitor widget, and Glance's monitor widget. Market research (Spectr `lp-service-health-ping`, demand 7, competitorCoverage 6) marks it a strong, well-covered gap.

This change adds an optional **server-side** HTTP health ping to a tile. A background job periodically pings the tile's linked service, the result is cached with a short TTL, and the tile renders an online / offline / degraded badge. The ping is server-side to avoid CORS and mixed-content problems and to keep internal service hosts off the browser; target hosts are allow-listed and fail closed. Config lives in the existing `widgetContent` JSON — no schema change.

## Affected code units

- `lib/Service/HealthPingService.php` — new: performs an allow-listed server-side HTTP request to a tile's health URL via `OCP\Http\Client\IClientService`, classifies the result (online / degraded / offline) against the tile's expected status and a latency threshold, and reads/writes the cached badge state in `OCP\ICache` (short TTL, default 60s, min 15s). Fail-closed when the host is not allow-listed.
- `lib/Controller/HealthPingController.php` — new: `#[NoAdminRequired]` `GET /api/health-ping/{placementId}` returning the cached badge `{ state, checkedAt, latencyMs, stale }` for one placement; guards that the caller may view the placement; never returns the health URL or response body.
- `lib/BackgroundJob/HealthPingRefreshJob.php` — new `OCP\BackgroundJob\TimedJob`: iterates ping-enabled placements whose cache is due and refreshes their badge state, honouring each tile's configured interval and the host allow-list.
- `src/components/widgets/` tile — health-badge overlay component rendered on the tile corner; shows the state as icon + text (never colour-only), plus checked-at / latency in an accessible tooltip; polls `GET /api/health-ping/{placementId}` on the tile's interval.
- Tile config fields (in the existing tile config UI): `healthPingEnabled`, `healthUrl`, `expectedStatus`, `pingInterval` — persisted into the existing `widgetContent` JSON; no new DB column or migration.

## Why a new change

Health pinging introduces server-side egress, an allow-list, a cache and a background job — all security-sensitive and orthogonal to how a tile is authored or rendered. Isolating it keeps the ping/egress surface auditable and lets the badge be added to existing tiles without touching the tile data model. It reuses stock Nextcloud abstractions (`IClientService`, `ICache`, `TimedJob`) rather than introducing new infrastructure.

## Approach

- Server-side only. The browser calls `GET /api/health-ping/{placementId}` and receives a badge state; it never sees the health URL, request headers, or upstream response body.
- Allow-list, fail-closed. A tile's `healthUrl` host MUST appear in the admin allow-list (`launchpad` app config `healthping_allowed_hosts`); a non-listed host is refused at save time AND at ping time, yielding no ping (not a silent "online").
- Classification: `online` when the response status matches `expectedStatus` (default 200–399) within the latency threshold; `degraded` when it matches but latency exceeds the threshold; `offline` on connection failure, timeout, or unexpected status.
- Caching: badge state cached in `ICache` keyed on placement id, TTL = the tile's interval (default 60s, min 15s). The endpoint serves the cached value; the `TimedJob` refreshes due entries so the first viewer does not pay the upstream latency.
- WCAG AA: badge conveys state with an icon AND a text label (e.g. "Online"), not colour alone; the tooltip is keyboard-reachable and announced to screen readers.

## Notes

- Out of scope: multi-step / content-assertion health checks (follow-up `healthping-assertions`).
- Out of scope: alerting/notifications on state change (follow-up `healthping-alerts`).
- Out of scope: historical uptime charts (follow-up `healthping-history`).
- Out of scope: authenticated pings with stored credentials — v1 pings unauthenticated public/internal health endpoints only.
