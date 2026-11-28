# Delta: app-connections

## ADDED Requirements

### Requirement: REQ-LP-CONN-005 A dashboard shows the state of its own connections

LaunchPad SHALL offer a `connections` widget type ("Koppelingen van dit dashboard", board LpManagement). Each
connection declared in `lib/Settings/connections.json` SHALL name the widget types that use it (`uses`, for example
`live-tiles` for `livetile`, `news-feeds` for `news`, `ics-calendars` for `calendar`, `health-ping` for tiles with a
health ping). The widget SHALL list, once each, the connections that the placements on its own dashboard use, with the
connection's name, its status as the Integrations page names it (REQ-LP-CONN-004), the time of its last success and
the time and message of its last error, as integriq holds them in `integriq/app_connection`. It SHALL read those rows
with the viewer's own session and SHALL NOT call any connection itself. A message SHALL show at most a host, as
REQ-LP-CONN-003 reports it. A connection no placement uses SHALL NOT be listed. Without integriq the widget SHALL say
"Connection states need the Integrations app" and the picker SHALL not offer it.

#### Scenario: The management dashboard's connections

- **GIVEN** Managementinformatie holds the live tile "Wachttijd KCC" and a news widget
- **AND** integriq holds `live-tiles` as working, last success 10:44, and `news-feeds` as error since 09:30 with "feeds.example.nl answered 503"
- **WHEN** Pieter opens the dashboard
- **THEN** "Koppelingen van dit dashboard" lists Live tiles with "werkt" and last success 10:44, and News feeds with the error, its time and the host
- **AND** it does not list the weather provider, which no widget on this dashboard uses

#### Scenario: Without integriq

- **GIVEN** integriq is not installed
- **WHEN** a dashboard with the connections widget renders
- **THEN** the widget says "Connection states need the Integrations app"
- **AND** the widget picker does not offer "Koppelingen van dit dashboard"
