# Delta for prometheus-metrics: health on the admin page

## ADDED Requirements

### Requirement: The admin page shows the app's health (REQ-PROM-011)

The Operations tab of the LaunchPad admin page MUST show a Health panel that reads the health check endpoint (REQ-PROM-007). The panel MUST show "Checking…" while it waits, then "Healthy" or "Degraded" with the result in words, not only a colour.

#### Scenario: A healthy app

- **GIVEN** Noor is a Nextcloud administrator and the health check answers `status: "ok"`
- **WHEN** she opens the Operations tab of the LaunchPad admin page
- **THEN** the Health panel says "Healthy"

#### Scenario: A degraded app

- **GIVEN** the health check reports that the database cannot be reached
- **WHEN** Noor opens the Operations tab
- **THEN** the Health panel says "Degraded"
