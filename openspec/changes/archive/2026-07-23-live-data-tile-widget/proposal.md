# Live-data tile widget — turn static tiles into data-bound tiles

LaunchPad tiles are today static shortcut cards: an icon, a label, a link. Every serious competitor — Workspace 365 live tiles, Microsoft Viva data-bound adaptive cards, Homarr (40+ integrations), gethomepage (100+ API widgets), Glance (custom JSON-API widget), Basaas data widgets — renders **live data** on tiles. Market research (Spectr `lp-live-data-json-tile`, demand 12, competitorCoverage 12) identifies this as LaunchPad's single biggest functional gap.

This change adds a `launchpad_livetile` widget that periodically fetches a JSON value from a source and templates it onto a tile: a count, a status, a KPI number, with an optional badge and click-through. The fetch is server-side (so credentials never reach the browser and CORS is avoided) and cached.

## Affected code units

- `lib/Widget/LiveTileWidgetProvider.php` — new v2 widget provider registering `launchpad_livetile` with the Nextcloud Dashboard `IManager`.
- `lib/Controller/LiveTileController.php` — new `#[NoAdminRequired]` endpoint `GET /api/livetile/{placementId}` returning the resolved, cached value for one placement; validates the caller owns/can-view the placement.
- `lib/Service/LiveTileService.php` — resolves a placement's live-tile config to a value: when OpenConnector is present it calls the OpenConnector `dashboard-http-datasource` source (leaf, see hand-off); otherwise it performs a server-side allow-listed HTTP GET and extracts a value via a JSONPath-lite expression. Results cached in `ICache` with per-config TTL (default 300s, min 30s).
- `src/components/widgets/LiveTileWidget.vue` — renders the tile: label, icon, resolved value, optional trend/badge, loading/stale/error states, click-through to the configured link.
- `src/components/widgets/LiveTileWidgetConfig.vue` — author UI: source mode (OpenConnector source picker OR direct URL), value expression, refresh interval, formatting (number/thousands separator/prefix/suffix), badge thresholds, link target.
- `lib/Db/WidgetPlacement.php` — no schema change; live-tile config stored in the existing `widgetContent` JSON blob.

## Why a new change

Live data is a contained widget surface but the fetching, credential handling and rate-limiting are security-sensitive and belong to a reusable data-source capability. Splitting the *tile rendering + config* (this change, LaunchPad) from the *HTTP/credential data-source* (OpenConnector `dashboard-http-datasource`, sibling change) keeps LaunchPad free of connector plumbing and lets the data-source be reused by other Conduction apps. LaunchPad consumes OpenConnector as a **leaf** when present and degrades to a minimal allow-listed direct GET when it is not, honouring the runtime-OR-consumption policy (`runtime-or-consumption` spec).

## Approach

- Server-side fetch only. The browser never sees the source URL, headers or credentials — it calls `GET /api/livetile/{placementId}` and receives `{ value, formatted, badge, fetchedAt, stale }`.
- Value extraction via a JSONPath-lite expression (`$.data.open_count`) evaluated in PHP; no arbitrary code.
- Allow-list: direct-URL mode requires the target host to appear in an admin allow-list (`launchpad` app config `livetile_allowed_hosts`), fail-closed. OpenConnector-source mode has no allow-list (the connector governs egress).
- Caching keyed on placement id + config hash; `IReloadableWidget` reload interval mirrors the configured refresh.
- WCAG AA: value has an accessible label; badge state is not colour-only (icon + text).

## Hand-off context

Sibling leaf change: **OpenConnector `dashboard-http-datasource`** — a governed HTTP/JSON data-source (allow-list, auth vault, rate-limit, response cache) that this widget consumes when OpenConnector is installed. Filed at `openconnector/openspec/changes/dashboard-http-datasource`. LaunchPad reintegrates it as a leaf: no LaunchPad code imports OpenConnector classes directly; resolution is via the documented OpenConnector source-run API and guarded by a capability probe.

## Notes

- Out of scope: charts/sparklines on tiles (follow-up `livetile-sparkline`).
- Out of scope: write actions from a tile (follow-up `livetile-actions`).
- Out of scope: websocket push updates (polling only in v1).
