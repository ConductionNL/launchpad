# Live-data tile

A tile that shows a **live value** — an open-case count, a queue length, a
budget figure — instead of being a static shortcut. It polls a source on a
schedule, formats the value, and can badge it against thresholds.

This closes LaunchPad's biggest functional gap against the wider dashboard
market: every serious competitor renders live data on tiles.

## Two ways to get the value

### 1. Via OpenConnector (preferred)

When the [OpenConnector](https://github.com/ConductionNL/openconnector) app is
installed and advertises the `dashboard-http-datasource` capability, pick a
pre-configured **source** and give a value expression. OpenConnector owns the
credentials, host allow-listing, rate-limiting and caching; LaunchPad only asks
for a value.

Use this whenever the upstream needs authentication.

### 2. Direct URL (fallback)

When OpenConnector is not installed, a tile can poll a URL directly — but only
if its host appears in the administrator's allow-list. This mode is intended
for unauthenticated internal endpoints.

If OpenConnector is absent the connector mode is hidden in the tile form, and
any tile already configured for it renders a clear "data source unavailable"
state rather than failing silently.

## Configuration

| Setting | Notes |
|---------|-------|
| Source mode | `connector` (OpenConnector source) or `url` (direct, allow-listed) |
| Value expression | JSONPath-lite: `$.data.open_count`, `$.items[0].total` |
| Refresh interval | Seconds; clamped to a 30 s minimum, defaults to 300 s |
| Formatting | Prefix, suffix, thousands separator |
| Badge thresholds | Value ranges mapped to ok / warn / alert |
| Link target | Where the tile navigates when activated |

## What the browser never sees

The widget calls LaunchPad's own endpoint, `GET /api/livetile/{placementId}`,
and receives only `{value, formatted, badge, fetchedAt, stale}`. The source
URL, request headers and any credential stay on the server. A caller who may
not view the placement gets `403` and **no fetch is performed**.

## The host allow-list

Direct-URL mode is governed by the `livetile_allowed_hosts` app config and is
**fail-closed** — enforced both when the tile is saved and again at every
fetch. Removing a host from the allow-list therefore immediately stops
existing tiles pointing at it, rather than leaving them running until someone
notices.

```bash
occ config:app:set launchpad livetile_allowed_hosts --value='intranet.example.nl,api.example.nl'
```

An empty allow-list denies everything.

## Stale values

If an upstream refresh fails, the last known value is served with `stale:
true` and the tile marks it as possibly out of date. On a service-desk or wall
display a slightly old number is more useful than an empty tile — but it must
be visibly flagged, so the staleness is never silent.

## Accessibility

The badge state is conveyed by an icon **and** a text label, never by colour
alone, and the value carries an accessible label. This matters here because a
threshold badge is exactly the kind of red/green signal that becomes invisible
to a colour-blind colleague.

## Related

- [Widgets](widgets.md) — how widgets are discovered and placed.
- OpenConnector `dashboard-http-datasource` — the governed resolve façade this
  tile consumes as a leaf.
