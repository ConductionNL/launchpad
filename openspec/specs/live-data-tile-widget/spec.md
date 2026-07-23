# live-data-tile-widget Specification

## Purpose
TBD - created by archiving change live-data-tile-widget. Update Purpose after archive.
## Requirements
### Requirement: REQ-LIVETILE-001 Register live-data tile widget

The system MUST register a `launchpad_livetile` widget with the Nextcloud Dashboard Widget API (v2) so it appears in the widget picker.

#### Scenario: Widget appears in discovery
- GIVEN the LaunchPad app is installed and enabled
- WHEN the user opens the "Add Widget" modal on a dashboard
- THEN the live-data tile MUST appear in the widget list with id `launchpad_livetile`
- AND the widget MUST have a title and an icon
- AND the widget MUST declare `supportsV2 = true` and implement `IReloadableWidget`

#### Scenario: Registration via IManager
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
- GIVEN the LaunchPad codebase
- WHEN resolving a `connector`-mode tile
- THEN LaunchPad MUST call OpenConnector through its documented runtime source-run API guarded by a capability probe, and MUST NOT statically import OpenConnector PHP classes

