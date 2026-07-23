# Tasks: Clock & weather widgets

## Backend
- [ ] `lib/Widget/ClockWidgetProvider.php` — register `launchpad_clock` (v2) with `IManager`; NO data fetch on discovery (fully client-side).
- [ ] `lib/Widget/WeatherWidgetProvider.php` — register `launchpad_weather` (v2) with `IManager`.
- [ ] `lib/Service/WeatherService.php` — resolve location + user locale → reading; reuse `weather_status` provider when present, else configurable provider URL + server-held API key; fetch via `OCP\Http\Client`; cache in `ICache` (TTL default 900s) keyed on location+units+language+config hash; stale fallback on upstream failure.
- [ ] `lib/Controller/WeatherController.php` — `#[NoAdminRequired]` `GET /api/weather/{placementId}`; placement view-authorization guard; response `{ location, tempValue, units, condition, conditionText, language, fetchedAt, stale }` with NO API key.
- [ ] `appinfo/routes.php` — register the weather route with its auth attribute.
- [ ] Admin config for the weather provider URL + API key (server-side only) when `weather_status` is not used.

## Frontend
- [ ] `src/components/widgets/ClockWidget.vue` — analog/digital render off the device clock; timezone conversion + locale-aware date/time via Intl; textual time for screen readers.
- [ ] `src/components/widgets/ClockWidgetConfig.vue` — style (analog/digital), 12/24h, timezone picker, date format/locale; config persisted to `widgetContent`.
- [ ] `src/components/widgets/WeatherWidget.vue` — render current conditions with loading/stale/error states; condition shown by icon AND text (WCAG AA).
- [ ] `src/components/widgets/WeatherWidgetConfig.vue` — location, units-follow-locale toggle (+ manual override), provider choice where applicable.
- [ ] Register `launchpad_clock` and `launchpad_weather` in the widget catalogue/constants.

## Testing
- [ ] Vitest: clock renders correct time for a configured timezone and 12/24h mode; locale-aware date string; no network call.
- [ ] PHPUnit: `WeatherService` picks `weather_status` when present, falls back to provider URL otherwise; ICache hit within TTL; stale fallback on upstream failure; response contains no API key.
- [ ] PHPUnit: `WeatherService` derives units + language from user locale (regression guard against hardcoded units / English-only strings).
- [ ] PHPUnit: `WeatherController` 403 on unauthorized placement; response shape excludes credentials.
- [ ] Playwright: drop clock tile, set timezone, confirm rendered time; drop weather tile against a stubbed provider, confirm conditions + units render and a stale badge appears on upstream failure.

## Docs
- [ ] Add "Clock" and "Weather" sections to dashboard-authoring docs; document locale-driven units/language and the server-side provider/API-key setup.

## Out of scope (follow-ups)
- Multi-day forecast strip — `weather-forecast-strip`.
- Viewer geolocation — author-configured location in v1.
- World-clock multi-timezone grid — one timezone per tile in v1.
