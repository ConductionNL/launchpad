# clock-weather-widgets Specification

## Purpose
TBD - created by archiving change clock-weather-widgets. Update Purpose after archive.
## Requirements
### Requirement: REQ-CLOCK-001 Register client-side clock widget

The system MUST register a `launchpad_clock` widget with the Nextcloud Dashboard Widget API (v2) that is rendered entirely client-side, with no backend endpoint and no data fetch on discovery.

#### Scenario: Widget appears in discovery
- GIVEN the LaunchPad app is installed and enabled
- WHEN the user opens the "Add Widget" modal on a dashboard
- THEN the clock widget MUST appear in the widget list with id `launchpad_clock`
- AND the widget MUST have a title and an icon
- AND the widget MUST NOT fetch any data on discovery — it is fully client-side

#### Scenario: Registration via IManager
- GIVEN `OCP\Dashboard\IManager` is available
- WHEN the LaunchPad app boots
- THEN the app MUST register `ClockWidgetProvider` by calling `$manager->registerWidget(...)`

#### Scenario: No backend endpoint or migration
- GIVEN the clock widget is implemented
- WHEN the LaunchPad app is upgraded
- THEN NO custom API endpoint (e.g. `/api/clock/...`) MUST exist for the clock
- AND NO database migration MUST be created — the clock reads only the device clock and its `widgetContent` config

### Requirement: REQ-CLOCK-002 Configure clock style, format, and timezone

The system MUST store clock configuration in the placement `widgetContent` JSON so a dashboard author can choose analog or digital style, 12- or 24-hour format, a timezone, and a locale-aware date.

#### Scenario: Digital style configuration
- GIVEN a clock widget is placed on a dashboard
- WHEN the author selects style = `digital`, hourFormat = `24h`, timezone = `Europe/Amsterdam`, showDate = true
- THEN the config MUST persist as `{ "style": "digital", "hourFormat": "24h", "timezone": "Europe/Amsterdam", "showDate": true }`

#### Scenario: Analog style configuration
- GIVEN a clock widget is placed on a dashboard
- WHEN the author selects style = `analog` and timezone = `America/New_York`
- THEN the config MUST persist as `{ "style": "analog", "timezone": "America/New_York" }`
- AND the config UI MUST offer a timezone picker listing IANA timezone identifiers

#### Scenario: Defaults when unset
- GIVEN a newly placed clock widget with no explicit config
- WHEN it first renders
- THEN it MUST default to style = `digital`, hourFormat following the user locale, timezone = the user's Nextcloud timezone, showDate = true

### Requirement: REQ-CLOCK-003 Render locale-aware clock, WCAG AA

The system MUST render the clock in the browser using the configured timezone and format, with a locale-aware date, accessible to screen readers.

#### Scenario: Digital time honours timezone and format
- GIVEN a clock with style = `digital`, hourFormat = `24h`, timezone = `Europe/Amsterdam`
- WHEN the widget renders
- THEN it MUST display the current time in that timezone in 24-hour form, updating at least once per second (or per minute if seconds are hidden)
- AND a `12h` configuration MUST render an AM/PM suffix instead

#### Scenario: Locale-aware date
- GIVEN a clock with showDate = true and the user's Nextcloud locale is Dutch
- WHEN the widget renders the date
- THEN the date MUST be formatted per the Dutch locale via `Intl` (e.g. weekday and month names in Dutch), not hardcoded English

#### Scenario: Analog clock is accessible
- GIVEN a clock with style = `analog`
- WHEN a screen reader accesses the widget
- THEN the widget MUST expose the current time as text (e.g. via `aria-label` or a visually-hidden element), so the time is not conveyed by the analog face alone

### Requirement: REQ-WEATHER-001 Register weather widget with server-side fetch

The system MUST register a `launchpad_weather` widget (v2) whose reading is fetched server-side, so any provider API key stays on the server and the browser calls only a LaunchPad endpoint.

#### Scenario: Widget appears in discovery
- GIVEN the LaunchPad app is installed and enabled
- WHEN the user opens the "Add Widget" modal on a dashboard
- THEN the weather widget MUST appear in the widget list with id `launchpad_weather`
- AND the widget MUST have a title and an icon

#### Scenario: Registration via IManager
- GIVEN `OCP\Dashboard\IManager` is available
- WHEN the LaunchPad app boots
- THEN the app MUST register `WeatherWidgetProvider` by calling `$manager->registerWidget(...)`

#### Scenario: Browser fetches via the placement endpoint, key never exposed
- GIVEN a weather placement the current user may view
- WHEN the widget calls `GET /api/weather/{placementId}`
- THEN the response MUST be `{ location, tempValue, units, condition, conditionText, language, fetchedAt, stale }`
- AND the response MUST NOT contain the provider API key or the raw provider URL

#### Scenario: Caller authorization
- GIVEN a weather placement on a dashboard the current user may NOT view
- WHEN the user calls `GET /api/weather/{placementId}`
- THEN the system MUST return 403 and MUST NOT perform the fetch

### Requirement: REQ-WEATHER-002 Resolve provider, fetch, and cache

The system MUST resolve the weather reading via the Nextcloud `weather_status` provider when available, otherwise via a configurable provider URL with a server-held API key, fetching via `OCP\Http\Client` and caching in `ICache`.

#### Scenario: Reuse weather_status when present
- GIVEN the Nextcloud `weather_status` provider is available on the instance
- WHEN `WeatherService` resolves a placement's location
- THEN it MUST obtain the reading through the `weather_status` provider pattern rather than a bespoke external call

#### Scenario: Fallback to configurable provider URL
- GIVEN `weather_status` is NOT available
- WHEN `WeatherService` resolves a placement's location
- THEN it MUST fetch from the admin-configured provider URL using `OCP\Http\Client`, sending the admin-held API key server-side only

#### Scenario: Cached within TTL
- GIVEN a weather reading fetched 200 seconds ago with a 900-second TTL
- WHEN the endpoint is called again for the same location + units + language
- THEN the system MUST return the cached reading with `stale = false` and MUST NOT perform a new upstream fetch

#### Scenario: Upstream failure degrades gracefully
- GIVEN the weather provider is unreachable or returns a non-2xx status
- WHEN resolution fails and a previously cached reading exists
- THEN the endpoint MUST return the last-known reading with `stale = true`
- AND WHEN no cached reading exists THEN it MUST return an error shape and the widget MUST render an error state, never crash

### Requirement: REQ-WEATHER-003 Locale-aware units and language, WCAG AA

The system MUST derive units and forecast language from the requesting user's Nextcloud locale (with an author override for units), and MUST render the condition accessibly. This guards against the historical Nextcloud weather bug of hardcoded units and English-only strings.

#### Scenario: Units follow the user locale
- GIVEN a user whose Nextcloud locale implies metric units
- WHEN the weather reading is resolved with units-follow-locale enabled
- THEN `units` MUST be metric (°C, km/h) and the response MUST state `units`
- AND a user whose locale implies imperial units MUST receive °F / mph without any code change

#### Scenario: Author override of units
- GIVEN an author has overridden units to `imperial` for a specific weather tile
- WHEN the reading is resolved
- THEN the response `units` MUST be `imperial` regardless of the viewer's locale

#### Scenario: Forecast language follows the locale
- GIVEN the user's Nextcloud language is Dutch
- WHEN the weather reading is resolved
- THEN `conditionText` MUST be requested/rendered in Dutch where the provider supports it, and `language` MUST report `nl`
- AND English MUST be the fallback when the provider has no localisation, never a silent wrong-language string

#### Scenario: Condition is not conveyed by icon or colour alone
- GIVEN a weather tile rendering a condition (e.g. "Light rain")
- WHEN a screen-reader or colour-blind user views it
- THEN the condition MUST be conveyed by an icon AND a text label
- AND the temperature MUST carry an accessible label including its units

