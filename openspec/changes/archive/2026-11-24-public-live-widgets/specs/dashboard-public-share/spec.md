# Delta: dashboard-public-share

## ADDED Requirements

### Requirement: Live widgets on a public link and a kiosk screen (REQ-PSHR-011)

On a public link and on a kiosk screen, the news widget SHALL list the published announcements targeted at everyone, the weather widget SHALL show its reading, and the live data widget SHALL show its value, each read through the link's or playlist's token and only for a placement that is on the shared dashboard. An announcement targeted at groups SHALL NOT be shown to an anonymous visitor.

#### Scenario: The canteen screen

- **GIVEN** the playlist Kantine shows Today in Zuiddrecht with News, Weather and Waiting time at the desk
- **WHEN** the screen shows it without a login
- **THEN** the cards show the news items, the weather and the waiting time

### Requirement: Text in Markdown on public screens (REQ-PSHR-012)

A text widget on a public link or kiosk screen SHALL render its Markdown (lists, emphasis, links), sanitised as on the dashboard.

#### Scenario: Opening hours as a list

- **GIVEN** the Opening hours widget holds a Markdown list
- **THEN** the public start page shows it as a list, not as raw text
