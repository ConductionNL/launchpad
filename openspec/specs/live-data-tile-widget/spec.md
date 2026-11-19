# live-data-tile-widget Specification

## Purpose
A tile that shows live data from an outside source: one value with a format and a badge, or a list, a table or key-value pairs, fetched and mapped on the server from an allowed host or an OpenConnector source, so personal fields and unsafe links never reach the page.

## Requirements

### Requirement: REQ-LIVETILE-001 Register live-data tile widget

The system MUST register a `launchpad_livetile` widget with the Nextcloud Dashboard Widget API (v2) so it appears in the widget picker.

#### Scenario: Widget appears in discovery
@e2e exclude never built as written: there is no `launchpad_livetile` Dashboard API widget, no `supportsV2`/`IReloadableWidget` (`git log --all -S launchpad_livetile -- src lib appinfo templates` finds only 5c110d12, which registered the frontend-only `livetile` type in src/constants/widgetRegistry.js instead and dropped the provider task); no test pins that registry entry.
- GIVEN the LaunchPad app is installed and enabled
- WHEN the user opens the "Add Widget" modal on a dashboard
- THEN the live-data tile MUST appear in the widget list with id `launchpad_livetile`
- AND the widget MUST have a title and an icon
- AND the widget MUST declare `supportsV2 = true` and implement `IReloadableWidget`

#### Scenario: Registration via IManager
@e2e exclude never built: no `LiveTileWidgetProvider` exists and nothing registers with `IManager` (`git log --all -S LiveTileWidgetProvider -- src lib appinfo templates` returns nothing; 5c110d12 notes the app has no lib/Widget provider layer); no test pins it.
- GIVEN `OCP\Dashboard\IManager` is available
- WHEN the LaunchPad app boots
- THEN the app MUST register `LiveTileWidgetProvider`

### Requirement: REQ-LIVETILE-002 Configure the tile source

The system MUST let a dashboard author configure how a live-data tile resolves its value, storing the config in the placement `widgetContent` JSON.

#### Scenario: OpenConnector source mode
- GIVEN OpenConnector is installed and reports the `dashboard-http-datasource` capability
- WHEN the author opens the tile config and selects source mode = `connector`
- THEN the config UI MUST let the author pick a configured OpenConnector source and a value expression
- AND the config MUST persist as `{ "sourceMode": "connector", "sourceId": "<id>", "valueExpr": "$.open", "refresh": 300 }`

#### Scenario: Direct-URL source mode
- GIVEN the author selects source mode = `url`
- WHEN the author enters a URL whose host is in the admin allow-list `livetile_allowed_hosts`
- THEN the config MUST persist as `{ "sourceMode": "url", "url": "https://…", "valueExpr": "$.data.count", "refresh": 300 }`
- AND a URL whose host is NOT in the allow-list MUST be rejected at save time with a validation error

#### Scenario: Refresh interval bounds
- GIVEN the author sets a refresh interval
- WHEN the value is below 30 seconds
- THEN the system MUST clamp it to 30 seconds (minimum) and default to 300 seconds when unset

### Requirement: REQ-LIVETILE-003 Resolve and cache the value server-side

The system MUST resolve a tile's value on the server and MUST NOT expose the source URL, headers or credentials to the browser.

#### Scenario: Browser fetches via the placement endpoint
- GIVEN a live-data tile placement the current user may view
- WHEN the widget calls `GET /api/livetile/{placementId}`
- THEN the response MUST be `{ value, formatted, badge, fetchedAt, stale }` and MUST NOT contain the source URL or credentials

#### Scenario: Caller authorization
- GIVEN a live-data tile placement on a dashboard the current user may NOT view
- WHEN the user calls `GET /api/livetile/{placementId}`
- THEN the system MUST return 403 and MUST NOT perform the fetch

#### Scenario: Cached within TTL
- GIVEN a tile with refresh = 300 that was resolved 100 seconds ago
- WHEN the endpoint is called again
- THEN the system MUST return the cached value with `stale = false` and MUST NOT perform a new upstream fetch

#### Scenario: Upstream failure degrades gracefully
- GIVEN the upstream source is unreachable or returns a non-2xx status
- WHEN resolution fails and a previously cached value exists
- THEN the endpoint MUST return the last-known value with `stale = true`
- AND WHEN no cached value exists THEN it MUST return `{ value: null, stale: true }` and the widget MUST render an error state, never crash

#### Scenario: Allow-list enforced at fetch time
- GIVEN a direct-URL tile whose host was removed from `livetile_allowed_hosts` after configuration
- WHEN the value is resolved
- THEN the fetch MUST be refused (fail-closed) and the tile MUST render an error state

### Requirement: REQ-LIVETILE-004 Render the tile with formatting and badge

The system MUST render the resolved value with author-configured formatting and an optional threshold badge, WCAG AA compliant.

#### Scenario: Number formatting
- GIVEN a tile configured with prefix `€`, thousands separator on, suffix ` open`
- WHEN the resolved value is 1234
- THEN the tile MUST render `€1,234 open`

#### Scenario: Threshold badge is not colour-only
- GIVEN badge thresholds mapping value ranges to states (ok / warn / alert)
- WHEN the value falls in the `alert` range
- THEN the badge MUST convey state with an icon and text label, not colour alone

#### Scenario: Click-through
- GIVEN a tile with a configured link target
- WHEN the user activates the tile
- THEN it MUST navigate to the link, honouring the placement's link-target (same-tab/new-tab) setting

### Requirement: REQ-LIVETILE-005 Leaf consumption of OpenConnector

The system MUST treat OpenConnector as an optional leaf and remain functional when it is absent.

#### Scenario: OpenConnector absent
- GIVEN OpenConnector is NOT installed
- WHEN a user opens the tile config
- THEN the `connector` source mode MUST be hidden or disabled and only `url` mode offered
- AND existing `connector`-mode tiles MUST render an informative "data source unavailable" state, not crash

#### Scenario: No direct class dependency
@e2e exclude a code-structure property with no browser surface; the capability probe and the container lookup are pinned by tests/Unit/Service/LiveTileServiceTest.php (testProbeAsksForTheConnectorsRealServiceClass, testResolvesViaConnectorWhenAvailable, testIsConnectorAvailableFalseWhenAppDisabled); no test checks for a static import.
- GIVEN the LaunchPad codebase
- WHEN resolving a `connector`-mode tile
- THEN LaunchPad MUST call OpenConnector through its documented runtime source-run API guarded by a capability probe, and MUST NOT statically import OpenConnector PHP classes

### Requirement: A live tile can show a list, a table or key-value pairs (REQ-LTC-001)

A live tile MUST accept a display of `value` (the default), `list`, `table` or `keyvalue`, a mapping of up to six fields each with a label, a path and a role (title, link, date or field), and a limit between 1 and 50. A tile saved before this change MUST keep rendering its single value.

#### Scenario: Ticket list from a connected system

@e2e exclude needs an Integriq "TOPdesk" source, which the CI fixture does not have and which Nextcloud resolves server-side; the connector node mapping is pinned by tests/Unit/Service/LiveTileListsTest.php::testAConnectorNodeIsMappedTheSameWay, the list rendering by live-tile-lists.spec.ts and src/components/Widgets/Renderers/__tests__/LiveTileWidget.lists.spec.js.

- **GIVEN** Integriq holds a source "TOPdesk" with stored credentials and Ella adds a live tile in connector mode with source "TOPdesk", expression `$.incidents`, display "List", limit 5, and mapping title `briefDescription`, link `url`, date `callDate`
- **WHEN** Pieter opens the Workspace page
- **THEN** the tile lists five incidents, each title linking to the incident

#### Scenario: Existing single-value tile

- **GIVEN** a live tile saved last month with `valueExpr: "$.open"` and no display
- **WHEN** it renders after this change
- **THEN** it shows the single value as before

### Requirement: Mapping happens on the server and drops unmapped data (REQ-LTC-002)

The server MUST evaluate the mapping, return only mapped fields, truncate to the limit, and cache the mapped result. The raw response MUST NOT be sent to the browser.

#### Scenario: Personal data in the payload stays on the server

- **GIVEN** a source whose items include `callerEmail`, and a mapping without it
- **WHEN** the tile's data is requested
- **THEN** the response items hold only the mapped fields and no `callerEmail`

### Requirement: Only safe links are clickable (REQ-LTC-003)

A mapped link MUST render as a link only when it is an `https` address, opening in a new tab; any other value MUST render as plain text.

#### Scenario: Script link shown as text

- **GIVEN** an item whose mapped link is `javascript:alert(1)`
- **WHEN** the list renders
- **THEN** the title shows as plain text without a link

### Requirement: A preset shows another intranet's latest documents (REQ-LTC-004)

The tile form MUST offer a "Latest documents" preset with SharePoint and Confluence variants that fill a list display with title, link, last modified and modified by, for the author to confirm.

#### Scenario: SharePoint library

- **GIVEN** Integriq holds a source "Intranet documenten" for a SharePoint document library through Microsoft Graph
- **WHEN** Ella picks the preset "Latest documents", variant SharePoint, and the source, and saves
- **THEN** the tile lists the ten latest documents with title, last modified date and editor, and each title opens the document in SharePoint
