## ADDED Requirements

### Requirement: REQ-HPING-001 Configure a health ping on a tile

The system MUST let a dashboard author enable a health ping on a tile and store the config in the placement `widgetContent` JSON without any schema change.

#### Scenario: Enable ping with a health URL
- GIVEN a tile whose linked service host is in the admin allow-list `healthping_allowed_hosts`
- WHEN the author enables the health ping and sets a health URL, expected status and interval
- THEN the config MUST persist to `widgetContent` as `{ "healthPingEnabled": true, "healthUrl": "https://…", "expectedStatus": 200, "pingInterval": 60 }`
- AND no database migration or new column MUST be introduced

#### Scenario: Host not on the allow-list is rejected at save
- GIVEN the author enters a `healthUrl` whose host is NOT in `healthping_allowed_hosts`
- WHEN the config is saved
- THEN the system MUST reject it with a validation error and MUST NOT persist the ping config

#### Scenario: Interval bounds
- GIVEN the author sets a ping interval
- WHEN the value is below 15 seconds
- THEN the system MUST clamp it to 15 seconds (minimum) and default to 60 seconds when unset

### Requirement: REQ-HPING-002 Ping the service server-side and classify the result

The system MUST perform the health request on the server via `IClientService` and classify the outcome as online, degraded or offline. It MUST NOT perform the request from the browser.

#### Scenario: Online when status matches within latency threshold
- GIVEN a ping-enabled tile with `expectedStatus` 200 and a latency threshold
- WHEN the server request returns 200 within the threshold
- THEN the badge state MUST be classified `online`

#### Scenario: Degraded when slow
- GIVEN a ping-enabled tile whose expected status is returned
- WHEN the response latency exceeds the configured threshold
- THEN the badge state MUST be classified `degraded`

#### Scenario: Offline on failure or unexpected status
- GIVEN a ping-enabled tile
- WHEN the request times out, the connection fails, or the status does not match `expectedStatus`
- THEN the badge state MUST be classified `offline`

#### Scenario: Allow-list enforced at ping time (fail closed)
- GIVEN a tile whose `healthUrl` host was removed from `healthping_allowed_hosts` after configuration
- WHEN the ping is attempted
- THEN the request MUST be refused (fail-closed) and MUST NOT be classified `online`
- AND the badge MUST reflect that no ping was performed rather than a false "up" state

### Requirement: REQ-HPING-003 Cache the badge and refresh it in the background

The system MUST cache the badge state in `ICache` with a short TTL and MUST refresh due entries from a background job so viewers do not pay upstream latency.

#### Scenario: Endpoint serves the cached badge
- GIVEN a ping-enabled placement the current user may view
- WHEN the tile calls `GET /api/health-ping/{placementId}`
- THEN the response MUST be `{ state, checkedAt, latencyMs, stale }`
- AND the response MUST NOT contain the health URL, request headers, or upstream response body

#### Scenario: Caller authorization
- GIVEN a ping-enabled placement on a dashboard the current user may NOT view
- WHEN the user calls `GET /api/health-ping/{placementId}`
- THEN the system MUST return 403 and MUST NOT perform or reveal the ping

#### Scenario: Cached within TTL
- GIVEN a tile with interval 60 whose badge was refreshed 20 seconds ago
- WHEN the endpoint is called again
- THEN the system MUST return the cached badge with `stale = false` and MUST NOT perform a new upstream request

#### Scenario: Background refresh of due entries
- GIVEN ping-enabled placements whose cached badge is older than their interval
- WHEN the `HealthPingRefreshJob` `TimedJob` runs
- THEN it MUST refresh only the due, ping-enabled placements and skip any whose host is not allow-listed

#### Scenario: Stale fallback on refresh failure
- GIVEN a previously cached badge exists
- WHEN a refresh fails to reach the upstream
- THEN the endpoint MUST return the last-known badge with `stale = true` and the tile MUST NOT crash

### Requirement: REQ-HPING-004 Render an accessible health badge on the tile

The system MUST render the badge on the tile conveying state with an icon and text, never colour alone, WCAG AA compliant.

#### Scenario: Badge is not colour-only
- GIVEN a tile with badge state `offline`
- WHEN the tile renders
- THEN the badge MUST show a distinct icon AND a text label (e.g. "Offline"), not colour alone
- AND the same MUST hold for `online` and `degraded` states

#### Scenario: Accessible detail on demand
- GIVEN a rendered health badge
- WHEN the user focuses or hovers the badge
- THEN an accessible tooltip MUST expose the checked-at time and latency
- AND the tooltip MUST be reachable by keyboard and announced to screen readers

#### Scenario: Ping disabled shows no badge
- GIVEN a tile with `healthPingEnabled` false
- WHEN the tile renders
- THEN no health badge MUST appear and no `GET /api/health-ping/{placementId}` request MUST be made
