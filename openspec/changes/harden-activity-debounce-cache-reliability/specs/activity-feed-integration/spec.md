---
capability: activity-feed-integration
delta: true
status: draft
---

# Activity Feed Integration — Debounce Backend Reliability

## MODIFIED Requirements

### Requirement: REQ-ACT-007 Reaction debounce holds regardless of deployment topology

The per-`(actorUserId, dashboardUuid)` reaction debounce (900-second window) MUST hold across HTTP requests and PHP-FPM worker processes on every supported deployment topology, including installations where the
APCu PHP extension is not installed or not enabled for the running SAPI.
The debounce MUST NOT silently degrade to "always allow" when APCu is
unavailable.

#### Scenario: Debounce holds without APCu across two separate requests

- **GIVEN** a Nextcloud instance with APCu not installed
- AND user "bob" reacted to dashboard `abc` in request 1, one second ago
- **WHEN** bob reacts to dashboard `abc` again in a separate HTTP request
  (request 2) within the same 900-second window
- **THEN** `allowReaction('bob', 'abc')` MUST return `false` in request 2
- **AND** no second reaction activity row MUST be written

#### Scenario: Debounce still uses APCu when available

- **GIVEN** a Nextcloud instance with APCu installed and enabled
- **WHEN** `allowReaction()` is called
- **THEN** the claim MUST be served via `apcu_add()` exactly as before this
  change (no regression to the fast path)

### Requirement: REQ-ACT-008 Global fan-out debounce holds regardless of deployment topology

The per-`(dashboardUuid, eventType)` fan-out debounce (900-second window) MUST hold across HTTP requests and PHP-FPM worker processes, including on installations without APCu.

#### Scenario: Fan-out debounce holds without APCu across two separate requests

- **GIVEN** a Nextcloud instance with APCu not installed
- AND a `dashboard_updated` fan-out for dashboard `abc` was performed in
  request 1
- **WHEN** a second `dashboard_updated` event for dashboard `abc` arrives in
  a separate HTTP request within the same 900-second window
- **THEN** `allowGlobalFanout('abc', 'dashboard_updated')` MUST return
  `false` in request 2
- **AND** the fan-out MUST be skipped silently, exactly as the existing
  single-request scenario requires

#### Scenario: Deterministic unit tests remain unaffected

- **GIVEN** a test injects a fake clock into `DebounceHelper`
- **WHEN** the test advances the fake clock past the 900-second window
- **THEN** the helper MUST continue to use the in-memory fallback for
  deterministic behaviour, unaffected by this change
