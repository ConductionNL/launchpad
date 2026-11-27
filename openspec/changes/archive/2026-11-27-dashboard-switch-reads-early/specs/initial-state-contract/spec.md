# Delta: initial-state-contract

## ADDED Requirements

### Requirement: A dashboard switch starts its follow-up reads at once (REQ-INIT-012)

When the workspace switches to another dashboard, the store SHOULD start `GET /api/dashboards/{uuid}/reactions` of the target and, when the target's switcher row says the caller does not own it (`isOwner` false), `GET /api/dashboards/{id}/personal-layer`, together with the dashboard read. The app's HTTP client MUST answer the first GET that matches one of these early reads (same path and query) from it, once, within 5 seconds of its start, and MUST send every other request. A failing early read MUST answer its matching GET with the same failure. The next switch MUST drop the early reads of the previous one.

#### Scenario: The reactions bar does not ask again after a switch

- **GIVEN** Pieter views the workspace on his own dashboard
- **WHEN** he switches to the group dashboard "Team"
- **THEN** the reactions of "Team" and his personal layer on it are requested once, while the dashboard is read
- **AND** the reactions bar and the "Hidden (n)" control show them without another request

#### Scenario: An early read nobody asks for expires

- **GIVEN** Pieter switched to "Team" in edit mode, so the reactions bar is not shown
- **WHEN** he leaves edit mode more than 5 seconds later
- **THEN** the reactions bar requests the reactions from the server
