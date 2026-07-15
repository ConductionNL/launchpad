# Spec delta — widgets

## ADDED Requirements

### Requirement: REQ-WDG-027 Single workspace canvas; no separate catalog region

The workspace MUST render the dashboard canvas as its only content region. There MUST NOT be a separate "catalog"/browse region, nor a sidebar mode switch that toggles the workspace away from the canvas. Widget discovery and placement are owned exclusively by the Add-widget flow (`WidgetPickerModal`, REQ-WDG-010), which lists every available widget AND places the chosen one — so a read-only catalog adds navigation cost without unique value.

#### Scenario: Workspace shows only the dashboard canvas

- **GIVEN** an authenticated user on the workspace
- **WHEN** the page loads
- **THEN** the dashboard canvas MUST render as the content region
- **AND** no catalog/browse region MUST be present
- **AND** the sidebar MUST NOT render a Dashboards/Catalog mode switch

#### Scenario: Widget discovery happens through the picker

- **GIVEN** the user is in edit mode
- **WHEN** they open the Add-widget flow (`WidgetPickerModal`)
- **THEN** every available widget MUST be listed there
- **AND** selecting one MUST place it on the active dashboard
