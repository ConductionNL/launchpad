# Tasks: Clock & weather widgets

> **Implementation note (2026-07-23).** This repo has no `lib/Widget/`
> directory — dashboard widget types are registered from the **frontend**
> `src/constants/widgetRegistry.js`, not via PHP `IManager` providers. The two
> `*WidgetProvider.php` tasks below are therefore superseded by the registry
> entries (marked n/a). Component paths also live under
> `src/components/Widgets/Renderers/` rather than `src/components/widgets/`.

## Backend
- [n/a] `lib/Widget/ClockWidgetProvider.php` — superseded: registered in `src/constants/widgetRegistry.js` (`clock`); no PHP provider layer exists in this app.
- [n/a] `lib/Widget/WeatherWidgetProvider.php` — superseded: registered in `src/constants/widgetRegistry.js` (`weather`).
- [x] `lib/Service/WeatherService.php` — resolve location + user locale → reading; reuse `weather_status` provider when present, else configurable provider URL + server-held API key; fetch via `OCP\Http\Client`; cache in `ICache` (TTL default 900s) keyed on location+units+language+config hash; stale fallback on upstream failure.
- [x] `lib/Controller/WeatherController.php` — `#[NoAdminRequired]` `GET /api/weather/{placementId}`; placement view-authorization guard; response `{ location, tempValue, units, condition, conditionText, language, fetchedAt, stale }` with NO API key.
- [x] `appinfo/routes.php` — register the weather route with its auth attribute.
- [x] Admin config for the weather provider URL + API key (server-side only) when `weather_status` is not used (`weather_provider_url`, `weather_provider_api_key`).

## Frontend
- [x] `ClockWidget.vue` — analog/digital render off the device clock; timezone conversion + locale-aware date/time via Intl; textual time for screen readers.
- [x] `ClockWidgetForm.vue` — style (analog/digital), 12/24h, timezone picker, date format/locale; config persisted to `widgetContent`.
- [x] `WeatherWidget.vue` — render current conditions with loading/stale/error states; condition shown by icon AND text (WCAG AA).
- [x] `WeatherWidgetForm.vue` — location, units-follow-locale toggle (+ manual override), provider choice where applicable.
- [x] Register `clock` and `weather` in the widget catalogue/constants (+ completeness spec).

## Testing
- [x] Vitest: clock renders correct time for a configured timezone and 12/24h mode; locale-aware date string; no network call. (44 assertions pass across 6 files.)
- [x] PHPUnit: `WeatherService` picks `weather_status` when present, falls back to provider URL otherwise; ICache hit within TTL; stale fallback on upstream failure; response contains no API key. (`tests/Unit/Service/WeatherServiceTest.php`)
- [x] PHPUnit: `WeatherService` derives units + language from user locale (regression guard against hardcoded units / English-only strings).
- [x] PHPUnit: `WeatherController` 403 on unauthorized placement; response shape excludes credentials. (`tests/Unit/Controller/WeatherControllerTest.php`)
- [ ] Playwright: drop clock tile, set timezone, confirm rendered time; drop weather tile against a stubbed provider, confirm conditions + units render and a stale badge appears on upstream failure. — deferred, tracked as a follow-up; unit coverage stands in for now.

## Docs
- [x] Add "Clock" and "Weather" sections to dashboard-authoring docs; document locale-driven units/language and the server-side provider/API-key setup. (`docs/features/clock-weather-widgets.md` + features README row)

## Out of scope (follow-ups)
- Multi-day forecast strip — `weather-forecast-strip`.
- Viewer geolocation — author-configured location in v1.
- World-clock multi-timezone grid — one timezone per tile in v1.
