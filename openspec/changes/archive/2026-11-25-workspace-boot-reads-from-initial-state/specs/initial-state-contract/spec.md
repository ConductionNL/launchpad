# Delta: initial-state-contract

## ADDED Requirements

### Requirement: The workspace carries its view's boot reads (REQ-INIT-008)

The Workspace page SHOULD carry the optional key `workspaceBootReads`: `{tiles, pendingAcknowledgements, defaultDashboard}`, where `tiles` is exactly what `GET /api/tiles` answers for the caller, `pendingAcknowledgements` exactly what `GET /api/acknowledgements/pending` answers, and `defaultDashboard` exactly what `GET /api/dashboards/default` answers. A part MUST be left out when the caller may not perform its action (`tile.index`, `dashboard.get-default-dashboard`) or when reading it fails. The tile store, the pending-acknowledgements read and the default-dashboard read MUST each take their part once, on the first read, instead of requesting the endpoint, and MUST request it on every later read and whenever the part is missing.

#### Scenario: The workspace opens without asking for its tiles, acknowledgements and default dashboard

- **GIVEN** Pieter may read his tiles and his default dashboard
- **WHEN** he opens the workspace
- **THEN** the page carries his tiles, his pending acknowledgements and his default dashboard in `workspaceBootReads`
- **AND** the view shows them without requesting `/api/tiles`, `/api/acknowledgements/pending` or `/api/dashboards/default`

#### Scenario: A refused part is asked for

- **GIVEN** the action matrix refuses Pieter `tile.index`
- **WHEN** he opens the workspace
- **THEN** the page carries no `tiles` part
- **AND** the tile store requests `/api/tiles` and meets the same refusal
