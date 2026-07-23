# Clock & weather widgets — ambient dashboard tiles

Ambient tiles — a clock and a weather panel — are table-stakes on every consumer and workspace dashboard (Google, Workspace 365, Homarr, gethomepage), yet LaunchPad has neither. Market research (Spectr `lp-clock-weather-widget`, demand 8, competitorCoverage 8) flags both as high-demand, well-covered gaps that make a dashboard feel "alive" and personal.

This change adds two widgets:

- **`launchpad_clock`** — a fully client-side clock/date tile (analog or digital style, 12/24-hour, configurable timezone, locale-aware date formatting). It mirrors the divider widget exactly: **no backend at all**, config stored in the placement `widgetContent` JSON, rendered entirely in-browser off the device clock.
- **`launchpad_weather`** — a locale- and units-aware weather tile for a configured location. Because a weather fetch needs an outbound call and (usually) an API key, the fetch is performed **server-side** via `OCP\Http\Client` and cached in `ICache`; the browser only ever calls a LaunchPad endpoint. Where the Nextcloud `weather_status` provider is available it is reused; otherwise a configurable provider URL with an admin-held API key is used, and the key never reaches the browser. Units and language MUST follow the user locale — Nextcloud has a history of weather-localisation bugs (hardcoded units / English strings), so this is called out explicitly.

## Affected code units

- `lib/Widget/ClockWidgetProvider.php` — new v2 widget provider registering `launchpad_clock`; zero backend data.
- `lib/Widget/WeatherWidgetProvider.php` — new v2 widget provider registering `launchpad_weather`.
- `lib/Service/WeatherService.php` — resolves a placement's location + user locale to a weather reading: reuse the `weather_status` provider when present, else a configurable provider URL with a server-held API key; fetch via `OCP\Http\Client`, cache in `ICache` with a TTL; unit/language selection from the user locale.
- `lib/Controller/WeatherController.php` — new `#[NoAdminRequired]` endpoint `GET /api/weather/{placementId}` returning the cached reading for one placement; validates the caller may view the placement.
- `src/components/widgets/ClockWidget.vue` + `src/components/widgets/ClockWidgetConfig.vue` — render the clock and its author UI; entirely client-side.
- `src/components/widgets/WeatherWidget.vue` + `src/components/widgets/WeatherWidgetConfig.vue` — render the weather reading (states: loading / stale / error) and its author UI (location, units-follow-locale toggle, provider choice when applicable).
- `lib/Db/WidgetPlacement.php` — no schema change; both widgets store config in the existing `widgetContent` JSON blob.

## Why a new change

The two widgets ship together because they are the "ambient tiles" pairing users expect, but they sit at opposite ends of the backend spectrum: the clock is pure client-side (divider-class, no endpoints), while weather needs a governed server-side fetch (credentials, caching, locale-correct units). Keeping them in one change lets the review contrast the two patterns; keeping the clock backend-free avoids inventing needless plumbing.

## Approach

- **Clock: zero backend.** Rendered in-browser from the device clock; timezone conversion and locale-aware date/time formatting done client-side (Intl). No endpoint, no data fetch on discovery, no migration — mirrors the divider widget.
- **Weather: server-side, cached.** The browser calls `GET /api/weather/{placementId}`; the server resolves the location, fetches via `OCP\Http\Client`, and caches in `ICache` keyed on location + units + language + config hash, with a TTL (default 900s). On upstream failure a previously cached reading is returned marked `stale`; with no cache the widget renders an error state, never crashes.
- **Provider.** Prefer the existing `weather_status` provider pattern when it is available on the instance. Otherwise use a configurable provider URL with an admin-held API key kept server-side; the key MUST NOT appear in any response or in the widget config.
- **Locale correctness (explicit).** Units (°C/°F, km/h vs mph) and forecast text language MUST be derived from the requesting user's Nextcloud locale, with an author override for units. The response MUST state which units and language it used so the frontend never re-guesses.
- **WCAG AA.** Clock and weather values carry accessible labels; weather condition is conveyed by icon **and** text (not colour/icon alone); the analog clock exposes a textual time for screen readers.

## Notes

- Out of scope: multi-day forecast strip (v1 shows current conditions only) — follow-up `weather-forecast-strip`.
- Out of scope: automatic geolocation of the viewer — location is author-configured in v1.
- Out of scope: world-clock multi-timezone grid — one timezone per clock tile in v1.
