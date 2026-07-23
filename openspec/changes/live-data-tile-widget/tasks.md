# Tasks: Live-data tile widget

## Backend
- [ ] `lib/Widget/LiveTileWidgetProvider.php` — register `launchpad_livetile` (v2, IReloadableWidget) with `IManager`.
- [ ] `lib/Service/LiveTileService.php` — resolve config → value: OpenConnector source-run when capability present, else allow-listed server-side GET + JSONPath-lite extraction; `ICache` with per-config TTL, stale fallback.
- [ ] `lib/Controller/LiveTileController.php` — `#[NoAdminRequired]` `GET /api/livetile/{placementId}`; placement view-authorization guard; returns `{value, formatted, badge, fetchedAt, stale}`.
- [ ] `appinfo/routes.php` — register the route with auth attribute.
- [ ] Admin config `livetile_allowed_hosts`; validate direct-URL host against it on save AND on fetch (fail-closed).
- [ ] Capability probe for OpenConnector `dashboard-http-datasource`; no static OpenConnector imports.

## Frontend
- [ ] `src/components/widgets/LiveTileWidget.vue` — render value/format/badge/click-through with loading/stale/error states; WCAG AA (badge icon+text, not colour-only).
- [ ] `src/components/widgets/LiveTileWidgetConfig.vue` — source mode (connector picker / direct URL), value expression, refresh, formatting, badge thresholds, link target.
- [ ] Register `launchpad_livetile` in the widget catalogue/constants.

## Testing
- [ ] PHPUnit: `LiveTileService` value extraction, TTL cache hit, stale fallback, allow-list fail-closed, connector-absent path.
- [ ] PHPUnit: `LiveTileController` 403 on unauthorized placement; response shape excludes URL/credentials.
- [ ] Vitest: config validation (host allow-list, refresh clamp); render states.
- [ ] Playwright: drop tile, configure direct-URL against a stub, confirm value renders and click-through navigates.

## Docs
- [ ] Add "Live-data tile" section to dashboard-authoring docs; cross-reference OpenConnector `dashboard-http-datasource`.

## Out of scope (follow-ups)
- Sparklines on tiles — `livetile-sparkline`.
- Write actions — `livetile-actions`.
- WebSocket push — polling only in v1.
