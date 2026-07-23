# Clock & Weather widgets

Two lightweight "ambient tile" widgets that give a dashboard a sense of time
and place. Both are placed like any other widget and configured from the
widget settings panel; their configuration lives in the placement's
`widgetContent` JSON.

## Clock

A fully **client-side** widget — it reads the device clock and makes no
network request and no backend call at all.

| Setting | Values | Notes |
|---------|--------|-------|
| Style | `digital`, `analog` | Digital shows a formatted time string; analog draws a clock face. |
| Hour format | 12-hour, 24-hour | Applies to the digital style and the accessible label. |
| Timezone | any IANA zone (e.g. `Europe/Amsterdam`) | Converted with `Intl`; defaults to the browser's zone. |
| Show date | on / off | Date is rendered in the viewer's locale. |

**Accessibility.** The rendered time is always available to screen readers as
a text string, including for the analog style, so the widget is never a
purely visual element.

**Typical use.** A kiosk or narrowcasting screen in a public hall, or a
service-desk dashboard where a shared, unambiguous clock (and, for
distributed teams, a second tile pinned to another timezone) matters.

## Weather

Shows current conditions for a location. Unlike the clock, this widget needs
data, so the fetch happens **server-side** and the result is cached — the
browser never sees a provider URL or API key.

| Setting | Values | Notes |
|---------|--------|-------|
| Location | free text | Leave empty to use the viewer's own Nextcloud `weather_status` location. |
| Units | follow locale (default), metric, imperial | An explicit choice overrides the locale default. |

### How the reading is resolved

1. The widget calls LaunchPad's own endpoint, `GET /api/weather/{placementId}`.
2. The endpoint checks that the caller may view that placement — an
   unauthorised caller gets `403` and **no fetch is performed**.
3. `WeatherService` returns a cached reading when one exists inside the TTL
   (default 900 s, configurable).
4. Otherwise it fetches: the viewer's `weather_status` provider when no
   location is configured, else the configured provider URL.
5. If the upstream fails but an older reading exists, that reading is served
   with `stale: true` rather than an error. With no cached reading at all the
   endpoint returns `502` and the widget renders an error state.

The response contains exactly `location`, `tempValue`, `units`, `condition`,
`conditionText`, `language`, `fetchedAt`, `stale` — never a credential.

### Locale-driven units and language

Units and language follow the **viewer's** Nextcloud locale by default, so a
`nl_NL` colleague sees °C and Dutch condition text while an `en_US` colleague
on the same shared dashboard sees °F. An author-set units override wins over
the locale default. This is deliberate: hardcoding units or English-only
condition strings is a long-standing source of complaints about weather
widgets.

**Accessibility.** The condition is conveyed by an icon **and** a text label,
never by icon or colour alone.

### Admin setup

Only needed when you are not relying on the viewer's `weather_status`
location. Both values are stored server-side and never sent to the browser:

| App config key | Meaning |
|----------------|---------|
| `weather_provider_url` | Provider endpoint template; supports the placeholders `{location}`, `{apiKey}`, `{units}`, `{lang}`. |
| `weather_provider_api_key` | Provider API key, substituted into `{apiKey}`. |
| `weather_cache_ttl_seconds` | Cache TTL; defaults to 900. |

```bash
occ config:app:set launchpad weather_provider_url --value='https://api.example/weather?q={location}&units={units}&lang={lang}&appid={apiKey}'
occ config:app:set launchpad weather_provider_api_key --value='…'
```

## Related

- [Widgets](widgets.md) — how widgets are discovered and placed.
- [Conditional visibility](conditional-visibility.md) — show an ambient tile
  only during opening hours, or only to one group.
