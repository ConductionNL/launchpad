# Design: adopt-connection-registry

The contract is hydra `openspec/changes/connection-registry/design.md` (hydra#667, amended in hydra#673, hydra#674 and hydra#676). This file records how LaunchPad meets it and where it fits loosely.

## D1. Which connections are declared

Each candidate was checked against the code on `development`, not against its name.

| Key | Declared as | Why |
|---|---|---|
| `dashboard-registry` | `requiredConfig: ["registry_url"]`, `settingsUrl` to the Sharing tab | OpenRegister's `GenericStoreService` reads `registry_url` and `registry_token` from LaunchPad's app config. `StoreService::search()` is the one caller. The token is optional, so only the URL is required. |
| `weather` | `reportedOnly: true` | `WeatherService` picks its source per placement. A placement with a location calls `weather_provider_url`. A placement without one reads Nextcloud's weather status app for the viewing user. So an empty URL is a working default for some widgets and a broken one for others, and only a fetch can tell. |
| `news-feeds` | `reportedOnly: true` | `NewsWidgetService` fetches the feed URLs each widget names. `news_widget_allowed_feed_hosts` is fail-open: an empty list allows every HTTPS host, so there is nothing to require. |
| `ics-calendars` | `reportedOnly: true` | `CalendarWidgetService` fetches the ICS URLs each widget names. `calendar_widget_allowed_ics_hosts` is fail-open like the news list. |
| `live-tiles` | `reportedOnly: true` | `LiveTileService` fetches the URL each tile names. `livetile_allowed_hosts` is fail-closed: an empty list refuses every host. |
| `health-ping` | `reportedOnly: true` | `HealthPingService` pings the URL each tile names, on a page load and from `HealthPingRefreshJob`. `healthping_allowed_hosts` is fail-closed like the live tile list. |

**Why only the registry links to settings.** The weather provider and the four allow-lists have no admin screen; `docs/features` tells an admin to set them with `occ`. A link would open a page that cannot change them. The registry form on the Sharing tab now carries `id="section-dashboard-registry"`, and the link adds `?tab=sharing` because `BeheerTabs` renders only the active tab.

**Why a fail-closed allow-list is not `requiredConfig`.** The value is a JSON list. An admin who has not set it holds `""`, which D4 reads as empty. An admin who cleared it holds `[]`, which D4 reads as filled. So rule 5 would call a list that allows nothing Configured. A report after a refused fetch says it instead.

**Why each family is one row.** Every widget names its own address, which is the case D12 leaves out. Each row stands for the family and shows the last fetch.

## D2. What the reports say

`ConnectionReporter` sends the events. `ConnectionObservations` turns an outcome into a status and a message, and holds no state. Each caller hands over the outcome it already had; no call is added.

| Row | Outcome | Status | Message |
|---|---|---|---|
| `dashboard-registry` | OpenRegister absent | `unavailable` | "OpenRegister is not enabled, so LaunchPad cannot reach a dashboard registry." |
| | `ok` | `configured` | "The dashboard registry at {host} answered the last search." |
| | `not_configured` | `unconfigured` | "No registry address is set. Set the registry URL on the Sharing tab of the LaunchPad settings." |
| | `store_unreachable` | `error` | "The last search could not reach the dashboard registry at {host}." |
| | `store_invalid_response` | `error` | "The dashboard registry at {host} answered, but not with a list of templates." |
| | `rate_limited` | `limited` | "The dashboard registry at {host} limited the last search." |
| `weather` | no provider URL | `unconfigured` | "A weather widget with a location found no provider URL. Set weather_provider_url with occ." |
| | URL not http or https | `error` | "The weather provider URL does not start with http or https. Set weather_provider_url with occ." |
| | 2xx without a temperature | `error` | "The weather provider at {host} answered, but not with a temperature LaunchPad can read." |
| any HTTP row | no answer | `error` | "The last call to the {name} at {host} got no answer." |
| | HTTP 401 or 403 | `error` | "The {name} at {host} refused the request (HTTP 401)." |
| | HTTP 429 | `limited` | "The {name} at {host} limited the last call (HTTP 429)." |
| | HTTP 502, 503 or 504 | `error` | "The {name} at {host} answered HTTP 503 on the last call." |
| | HTTP 2xx | `configured` | "The {name} at {host} answered the last call." |
| `live-tiles`, `health-ping` | host refused, and the list holds no host | `unconfigured` | "{configKey} holds no JSON list of hosts, so LaunchPad refused the last call. Set it with occ." |

The HTTP rows are `weather` (weather provider), `news-feeds` (news feed), `ics-calendars` (calendar feed), `live-tiles` (live tile source) and `health-ping` (health ping target).

Anything else sends nothing. A 404, a 400, a 500 or a 3xx is about one address. A host that a non-empty allow-list refuses is about one widget. A weather status reading depends on one user. Those would make a working connection read Error.

A message names the host of the address, and nothing else from it. No path, query, user info or API key reaches the row.

## D3. When it reports

The report memory is one app-config value per connection, `connection_report_{key}`, holding the last status and its time.

- A different status reports once five minutes have passed since the last report, so two widgets that disagree cannot write on every page load.
- The same status reports again after an hour, so `lastReport` stays newer than a stale probe.
- A registry save clears the registry's memory, so the next search reports at once.

Without integriq the class check fails first: nothing is read, stored, sent or logged. Every report method catches everything, because it runs beside a widget response whose own answer is what the caller returns.

**Why this is cheap enough.** Dashboard widgets fetch on page load. Every service already caches its fetch (weather 900 s, feeds 3600 s, calendars 1800 s, live tiles and pings per tile), so a report can only follow a real outbound call. A report costs a `class_exists` and one app-config read, which Nextcloud has already loaded for the request. A write and an event happen at most once per window per connection. The report never changes what the widget answers.

## D4. The refresh

`StoreService::updateRegistryConfig()` is the one writer of the registry keys; `StoreController::updateConfig()` calls it for the Sharing tab. After the write it hands the keys it wrote to `ConnectionReporter::refreshFromSave()`. When `registry_url`, `registry_register` or `registry_token` is among them, the reporter clears the registry's report memory and sends `ConnectionRefreshRequestedEvent('launchpad', 'dashboard-registry')`.

The refresh goes first and any report after it. A save sends no report of its own; the next search does, and because the memory was cleared, that report lands at once instead of waiting out the window. Under hydra#674 the refresh retires older observations, so a save brings back rule 5 until that search reports.

The other five rows have no save to hook. A key set with `occ` sends no event, and integriq's hourly resolver pass covers that (D12 amendment 3).

## D5. The page

- `src/manifest.d/connection-registry.json`: an `index` page `Integrations` at `/settings/integrations`, `requiresApp` integriq, `permission: admin`, `showAdd: false`, and the contract columns: connection, status, status message, last checked, settings.
- Its menu entry `IntegrationsMenu` sits in the settings gear with `query: {app: launchpad}`, `permission: admin` and `visibleIf.appInstalled: integriq`.
- `src/services/connectionRegistry.js` holds `connectionStatus`, `connectionSettingsLabel` and `openIntegriqConnections`.
- `src/customComponents.js` exposes the handler, and `App.vue` passes it with the formatters to `CnAppRoot`. CnIndexPage resolves a header action's handler against `customComponents`, not against the v2 `registry`.

**Formatters.** The installed `@conduction/nextcloud-vue` 2.46.0 ships no `connectionStatus` built-in, so LaunchPad carries a local copy with all six labels, `limited` included.

## D6. Contract misfits

- **An allow-list is a JSON list.** D4 counts `""`, `false`, `0` and `null` as empty, not `[]`. A fail-closed list therefore cannot be `requiredConfig` (D1).
- **No settings surface.** Five of six rows are configured with `occ` only, so they carry no `settingsUrl`, and their unconfigured messages name the key.
- **A source chosen per widget.** The weather source depends on whether a placement has a location. `reportedOnly` plus outcome reports is the closest fit.
- **A family row shows one address.** The last fetch stands for every widget. The message names that host, so the reader can tell which one.

## Risks

- **A mixed dashboard flips a family row.** One dead feed among working ones moves the row between Configured and Error, at most every five minutes.
- **A busy instance writes app config at most every five minutes per connection.** That is six keys.
