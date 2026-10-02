# Delta for dashboard-view-analytics: tile click report screens

## ADDED Requirements

### Requirement: An administrator sees the most clicked tiles (REQ-TILEUI-001)

The analytics page in administration MUST show the most clicked tiles for the selected period with dashboard name, tile title, click count and distinct people, and MUST offer the CSV export.

#### Scenario: Top tiles for thirty days

- **GIVEN** Pieter and Sanne clicked the "Zaaksysteem" tile on "Team" 12 times in the last month
- **WHEN** Ruben, an administrator, opens Administration, Analytics, Tiles for 30 days
- **THEN** a row reads "Team, Zaaksysteem, 12 clicks, 2 people"

#### Scenario: Export

- **GIVEN** the same view
- **WHEN** Ruben clicks "Export CSV"
- **THEN** the file from the existing export endpoint downloads

### Requirement: Tile numbers are not shown when tracking is off (REQ-TILEUI-002)

When tile tracking is inactive, the tiles section MUST state that clicks are not recorded and MUST NOT show a table or an export button.

#### Scenario: Tracking off

- **GIVEN** the administrator switched tile tracking off
- **WHEN** Ruben opens the Tiles section
- **THEN** a message says clicks are not being recorded and no table is rendered

