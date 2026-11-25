# Delta: initial-state-contract

## ADDED Requirements

### Requirement: The workspace carries the first dashboard load (REQ-INIT-006)

The Workspace page SHOULD carry the optional key `dashboardFirstLoad`: `{visible, active}`, where `visible` is exactly the payload `GET /api/dashboards/visible` answers for the caller and `active` is exactly the payload of `GET /api/dashboard`, or `null` when the caller has no dashboard. Both MUST be built by the same code as those endpoints. The key MUST be left out when the caller may not perform `dashboard.visible` or `dashboard.get-active`. The dashboard store MUST take the key once, on its first load, instead of requesting the two endpoints, and MUST request them on every later load and whenever the key is missing (default `null`).

#### Scenario: The workspace opens without asking for its dashboards again

- **GIVEN** Pieter may read his dashboards
- **WHEN** he opens the workspace
- **THEN** the page carries his visible dashboards and his active dashboard in `dashboardFirstLoad`
- **AND** the store shows them without requesting `/api/dashboards/visible` or `/api/dashboard`

#### Scenario: A later load asks the API

- **GIVEN** the workspace has opened from `dashboardFirstLoad`
- **WHEN** the store loads the dashboards again, for example after a dashboard limit refusal
- **THEN** it requests both endpoints

#### Scenario: A refused caller gets no first load

- **GIVEN** the action matrix refuses Pieter `dashboard.visible`
- **WHEN** he opens the workspace
- **THEN** the page carries no `dashboardFirstLoad`
- **AND** the store requests the endpoints and meets the same refusal
