# Tasks: Live-data tile widget

> Implementation note: this codebase has **no `lib/Widget/` PHP-provider
> layer** — dashboard widget types are registered from the FRONTEND registry
> `src/constants/widgetRegistry.js` (see the `clock`/`weather` precedent),
> not via `OCP\Dashboard\IManager`/`IReloadableWidget`. The widget id used
> is `livetile` (not `launchpad_livetile`), matching the unprefixed
> `clock`/`weather` convention. Tasks below are annotated accordingly.

## Backend
- [x] ~~`lib/Widget/LiveTileWidgetProvider.php`~~ — N/A, no such layer exists in this app; the widget is registered in `src/constants/widgetRegistry.js` instead (see Frontend section).
- [x] `lib/Service/LiveTileService.php` — resolve config → value: OpenConnector source-run when capability present, else allow-listed server-side GET + JSONPath-lite extraction; `ICache` with per-config TTL, stale fallback.
- [x] `lib/Controller/LiveTileController.php` — `#[NoAdminRequired]` `GET /api/livetile/{placementId}`; placement view-authorization guard; returns `{value, formatted, badge, fetchedAt, stale}`. Also added `GET /api/livetile/connector/status` (drives the form's connector-mode hide/disable) and `POST /api/livetile/validate-source` (save-time allow-list check).
- [x] `appinfo/routes.php` — registered all three routes, before the catch-all `page#deepLink` route.
- [x] Admin config `livetile_allowed_hosts`; validated fail-closed at BOTH save time (`validateSourceConfig()` / `POST /api/livetile/validate-source`) and fetch time (`resolveForPlacement()`). Unlike `UrlSafetyValidator::checkAllowList()` (fail-open when the list is empty, used elsewhere for lower-sensitivity feeds), this feature's own `isHostAllowed()` fails closed: an empty/missing list permits NO host.
- [x] Capability probe for OpenConnector `dashboard-http-datasource` (`LiveTileService::isConnectorAvailable()`); no static OpenConnector imports — FQCN referenced only as a string, mirroring `WeatherService`'s `weather_status` reuse pattern.

## Frontend
- [x] `src/components/Widgets/Renderers/LiveTileWidget.vue` — render value/format/badge/click-through with loading/stale/error/"data source unavailable" states; WCAG AA (badge icon+text, not colour-only).
- [x] `src/components/Widgets/Renderers/LiveTileWidgetForm.vue` — source mode (connector picker / direct URL, connector option hidden when OpenConnector absent), value expression, refresh (clamped 30–, default 300), formatting (prefix/suffix/thousands), badge thresholds, link target.
- [x] `src/services/liveTileClient.js` — browser client for the three endpoints (fetch value, connector availability, save-time validation).
- [x] Register `livetile` in `src/constants/widgetRegistry.js` and `src/constants/__tests__/widgetRegistry.completeness.spec.js`.

## Testing
- [x] PHPUnit: `LiveTileService` value extraction (property + array-index JSONPath-lite), TTL cache hit, stale fallback, allow-list fail-closed (unset list AND host removed after configuration), connector-absent path. `tests/Unit/Service/LiveTileServiceTest.php`, 25 tests, all mocking the HTTP client — no real network call.
- [x] PHPUnit: `LiveTileController` 401/403 on unauthorized/anonymous, 403 never triggers a fetch, response shape excludes URL/credentials, `connectorStatus`/`validateSource` auxiliary endpoints. `tests/Unit/Controller/LiveTileControllerTest.php`, 11 tests.
- [x] Vitest: form validation (host allow-list via `checkUrlAllowed()`/`validate()`, refresh clamp, connector-mode gating), render states (loading/error/stale/unavailable/badge/click-through). `LiveTileWidget.spec.js` (12 tests) + `LiveTileWidgetForm.spec.js` (16 tests).
- [ ] Playwright: drop tile, configure direct-URL against a stub, confirm value renders and click-through navigates. **NOT DONE** — explicitly out of scope for this build pass (no e2e/playwright against the shared instance; local unit tests only per task instructions).

## Docs
- [ ] Add "Live-data tile" section to dashboard-authoring docs; cross-reference OpenConnector `dashboard-http-datasource`. **NOT DONE.**

## Out of scope (follow-ups)
- Sparklines on tiles — `livetile-sparkline`.
- Write actions — `livetile-actions`.
- WebSocket push — polling only in v1.
