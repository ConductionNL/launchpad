# Service health ping

An optional **online / offline / degraded** status badge on a tile, so a
municipal IT landing page can answer *"is de zaakapplicatie bereikbaar?"* at a
glance instead of a static link that gives no signal about whether the
service behind it is actually up.

A background job periodically pings the tile's configured health URL
server-side, the result is cached with a short TTL, and the tile renders the
badge from that cache — viewers never pay the upstream ping latency on page
load.

## Configuration

Health ping is configured per tile, in the same editor used for the tile's
title, icon and colours:

| Setting | Notes |
|---------|-------|
| Enable health ping | Off by default — no badge, no request, until turned on |
| Health check URL | Must resolve to a host on the administrator's allow-list |
| Expected HTTP status | Defaults to any 2xx/3xx when left unset |
| Check interval | Seconds; clamped to a 15 s minimum, defaults to 60 s |

The config is stored in the placement's existing content JSON — no database
schema change.

## Classification

- **Online** — the response status matches the expected status within the
  latency threshold.
- **Degraded** — the status matches, but the response was slow.
- **Offline** — the request timed out, the connection failed, or the status
  did not match. This is a *completed* reading, not a missed one: it is
  cached and served immediately, exactly like online/degraded.

## What the browser never sees

The badge calls LaunchPad's own endpoint, `GET
/api/health-ping/{placementId}`, and receives only `{state, checkedAt,
latencyMs, stale}`. The health URL, request headers and any upstream response
body stay on the server. A caller who may not view the placement gets `403`
and **no ping is performed**.

## The host allow-list

Health ping is governed by the `healthping_allowed_hosts` app config and is
**fail-closed** — enforced both when the tile is saved and again at every
ping. When a host is refused, no request is ever attempted: the badge falls
back to the last-known reading (marked stale) rather than showing a false
"up" state.

```bash
occ config:app:set launchpad healthping_allowed_hosts --value='intranet.example.nl,api.example.nl'
```

An empty allow-list denies everything.

## Background refresh

`HealthPingRefreshJob` runs every 15 seconds and refreshes any ping-enabled
tile whose cached badge is older than its own configured interval, so the
badge a viewer sees on page load is almost always already warm.

## Accessibility

The badge state is conveyed by an icon **and** a text label — "Online",
"Degraded", "Offline" — never by colour alone, and the checked-at time plus
latency are exposed via a keyboard-reachable, screen-reader announced
tooltip.

## Related

- [Custom Tiles](tiles.md) — where the health-ping toggle lives in the tile
  editor.
- [Live-data tile](live-data-tile.md) — the sibling capability this ping
  reuses the shape of (allow-listed server-side fetch, `ICache`, stale
  fallback).
