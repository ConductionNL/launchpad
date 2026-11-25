# Delta: service-health-ping

## MODIFIED Requirements

### Requirement: REQ-HPING-003 Cache the badge and refresh it in the background

The system MUST cache the badge state in `ICache` with a short TTL and MUST refresh due entries from a background job so viewers do not pay upstream latency.

#### Scenario: Endpoint serves the cached badge
- GIVEN a ping-enabled placement the current user may view
- WHEN the tile calls `GET /api/health-ping/{placementId}`
- THEN the response MUST be `{ state, checkedAt, latencyMs, stale, downSince }`, where `downSince` is the time of the first failed check of the current run of failures while `state` is `offline`, and null otherwise (icon-only-app-tiles REQ-ICT-003)
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
- GIVEN a previously cached badge exists and its interval has passed
- WHEN a refresh fails to reach the upstream, fewer times in a row than `healthping_offline_after_failures`
- THEN the endpoint MUST return the last-known badge with `stale = true` and the tile MUST NOT crash
- AND the cache MUST keep the last-known badge beyond the ping interval, so the interval decides when the next ping is due, not whether the badge survives

